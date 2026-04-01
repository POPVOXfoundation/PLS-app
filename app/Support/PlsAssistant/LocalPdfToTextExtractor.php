<?php

namespace App\Support\PlsAssistant;

use App\Domain\Documents\AssistantSourceDocument;
use App\Domain\Documents\Document;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;
use ZipArchive;

class LocalPdfToTextExtractor implements AssistantSourceTextExtractor
{
    public function __construct(
        private readonly string $binary = 'pdftotext',
    ) {}

    public function extract(AssistantSourceDocument|Document $document): AssistantSourceExtractionResult
    {
        $extension = $this->documentExtension($document);
        $method = $this->method($extension);
        $diskName = $this->documentDisk($document);
        $disk = Storage::disk($diskName);

        try {
            $storagePath = trim((string) $document->storage_path);

            if ($storagePath === '') {
                return AssistantSourceExtractionResult::failed(
                    driver: 'local',
                    method: $method,
                    error: 'Stored source file path is missing.',
                );
            }

            $contents = $disk->get($document->storage_path);

            if (! is_string($contents) || $contents === '') {
                return AssistantSourceExtractionResult::failed(
                    driver: 'local',
                    method: $method,
                    error: sprintf('Stored source [%s] is missing or empty.', $document->storage_path),
                );
            }

            return match ($extension) {
                'pdf' => $this->extractPdfText($contents, $method),
                'docx' => $this->extractDocxText($contents, $method),
                'txt', 'text', 'md' => $this->extractPlainText($contents, $method),
                default => AssistantSourceExtractionResult::failed(
                    driver: 'local',
                    method: $method,
                    error: sprintf('Stored source [%s] uses unsupported extension [%s].', $document->storage_path, $extension),
                ),
            };
        } catch (Throwable $exception) {
            return AssistantSourceExtractionResult::failed(
                driver: 'local',
                method: $method,
                error: $exception->getMessage(),
            );
        }
    }

    private function documentDisk(AssistantSourceDocument|Document $document): string
    {
        $diskName = trim((string) data_get($document->metadata, 'disk', ''));

        if ($diskName !== '') {
            return $diskName;
        }

        if ($document instanceof AssistantSourceDocument) {
            $assistantSourceDisk = trim((string) config('pls_assistant.assistant_sources.source_disk', ''));

            if ($assistantSourceDisk !== '') {
                return $assistantSourceDisk;
            }
        }

        return (string) config('filesystems.default');
    }

    private function temporaryPath(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'assistant-source-');

        if ($path === false) {
            throw new RuntimeException('Unable to allocate a temporary file for PDF extraction.');
        }

        return $path;
    }

    private function documentExtension(AssistantSourceDocument|Document $document): string
    {
        $originalName = trim((string) data_get($document->metadata, 'original_name', ''));
        $path = $originalName !== '' ? $originalName : (string) $document->storage_path;

        return strtolower(pathinfo($path, PATHINFO_EXTENSION));
    }

    private function extractPdfText(string $contents, string $method): AssistantSourceExtractionResult
    {
        $temporaryPath = $this->temporaryPath();

        try {
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
                    method: $method,
                    error: $this->errorMessage($result->errorOutput()),
                );
            }

            $content = $this->cleanText($result->output());

            if ($content === '') {
                return AssistantSourceExtractionResult::failed(
                    driver: 'local',
                    method: $method,
                    error: 'No usable text was extracted from the stored PDF.',
                );
            }

            return AssistantSourceExtractionResult::completed(
                driver: 'local',
                method: $method,
                content: $content,
            );
        } finally {
            if (is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }
    }

    private function extractDocxText(string $contents, string $method): AssistantSourceExtractionResult
    {
        $temporaryPath = $this->temporaryPath();
        $archive = new ZipArchive;

        try {
            file_put_contents($temporaryPath, $contents);

            if ($archive->open($temporaryPath) !== true) {
                return AssistantSourceExtractionResult::failed(
                    driver: 'local',
                    method: $method,
                    error: 'The stored DOCX could not be opened for text extraction.',
                );
            }

            $documentXml = $archive->getFromName('word/document.xml');

            if (! is_string($documentXml) || trim($documentXml) === '') {
                return AssistantSourceExtractionResult::failed(
                    driver: 'local',
                    method: $method,
                    error: 'No readable document text was found in the stored DOCX.',
                );
            }

            $content = $this->cleanText(html_entity_decode(strip_tags(str_replace('</w:p>', "</w:p>\n", $documentXml))));

            if ($content === '') {
                return AssistantSourceExtractionResult::failed(
                    driver: 'local',
                    method: $method,
                    error: 'No usable text was extracted from the stored DOCX.',
                );
            }

            return AssistantSourceExtractionResult::completed(
                driver: 'local',
                method: $method,
                content: $content,
            );
        } finally {
            $archive->close();

            if (is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }
    }

    private function extractPlainText(string $contents, string $method): AssistantSourceExtractionResult
    {
        $content = $this->cleanText($contents);

        if ($content === '') {
            return AssistantSourceExtractionResult::failed(
                driver: 'local',
                method: $method,
                error: 'No usable text was found in the stored text file.',
            );
        }

        return AssistantSourceExtractionResult::completed(
            driver: 'local',
            method: $method,
            content: $content,
        );
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

    private function method(string $extension): string
    {
        return match ($extension) {
            'pdf' => 'pdftotext -layout -nopgbrk -enc UTF-8',
            'docx' => 'docx xml text extraction',
            'txt', 'text', 'md' => 'direct text read',
            default => 'local document text extraction',
        };
    }

    private function errorMessage(string $errorOutput): string
    {
        $message = trim($errorOutput);

        return $message === '' ? 'Unknown extraction error.' : $message;
    }
}
