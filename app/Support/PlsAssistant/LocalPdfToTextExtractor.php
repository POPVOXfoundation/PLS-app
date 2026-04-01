<?php

namespace App\Support\PlsAssistant;

use App\Domain\Documents\AssistantSourceDocument;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class LocalPdfToTextExtractor implements AssistantSourceTextExtractor
{
    public function __construct(
        private readonly string $binary = 'pdftotext',
    ) {}

    public function extract(AssistantSourceDocument $document): AssistantSourceExtractionResult
    {
        $diskName = $this->documentDisk($document);
        $disk = Storage::disk($diskName);
        $temporaryPath = $this->temporaryPath();

        try {
            $contents = $disk->get($document->storage_path);

            if (! is_string($contents) || $contents === '') {
                return AssistantSourceExtractionResult::failed(
                    driver: 'local',
                    method: $this->method(),
                    error: sprintf('Stored source [%s] is missing or empty.', $document->storage_path),
                );
            }

            file_put_contents($temporaryPath, $contents);

            $command = sprintf(
                '%s -layout -nopgbrk -enc UTF-8 %s -',
                escapeshellarg($this->binary),
                escapeshellarg($temporaryPath),
            );

            $result = Process::timeout(120)->run($command);

            if (! $result->successful()) {
                return AssistantSourceExtractionResult::failed(
                    driver: 'local',
                    method: $this->method(),
                    error: $this->errorMessage($result->errorOutput()),
                );
            }

            $content = $this->cleanText($result->output());

            if ($content === '') {
                return AssistantSourceExtractionResult::failed(
                    driver: 'local',
                    method: $this->method(),
                    error: 'No usable text was extracted from the stored PDF.',
                );
            }

            return AssistantSourceExtractionResult::completed(
                driver: 'local',
                method: $this->method(),
                content: $content,
            );
        } catch (Throwable $exception) {
            return AssistantSourceExtractionResult::failed(
                driver: 'local',
                method: $this->method(),
                error: $exception->getMessage(),
            );
        } finally {
            if (is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }
    }

    private function documentDisk(AssistantSourceDocument $document): string
    {
        $diskName = trim((string) data_get($document->metadata, 'disk', ''));

        if ($diskName === '') {
            throw new RuntimeException(sprintf('Assistant source [%s] does not define a storage disk.', $document->title));
        }

        return $diskName;
    }

    private function temporaryPath(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'assistant-source-');

        if ($path === false) {
            throw new RuntimeException('Unable to allocate a temporary file for PDF extraction.');
        }

        return $path;
    }

    private function cleanText(string $text): string
    {
        $text = str_replace("\f", "\n", $text);
        $text = preg_replace("/\r\n?/", "\n", $text) ?? $text;
        $text = collect(explode("\n", $text))
            ->map(function (string $line): string {
                $line = rtrim($line);

                if (preg_match('/^\s*\d+\s*$/', $line) === 1) {
                    return '';
                }

                return $line;
            })
            ->implode("\n");

        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }

    private function method(): string
    {
        return 'pdftotext -layout -nopgbrk -enc UTF-8';
    }

    private function errorMessage(string $errorOutput): string
    {
        $message = trim($errorOutput);

        return $message === '' ? 'Unknown extraction error.' : $message;
    }
}
