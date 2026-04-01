<?php

namespace App\Support\PlsAssistant;

use App\Domain\Documents\AssistantSourceDocument;
use App\Domain\Documents\Enums\AssistantSourceScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class WfdAssistantSourceImporter
{
    /**
     * @return Collection<int, array{
     *     content_length: int,
     *     extraction_method: string,
     *     status: string,
     *     storage_path: string,
     *     title: string
     * }>
     */
    public function importConfiguredSources(): Collection
    {
        /** @var array<int, array<string, mixed>> $sources */
        $sources = config('pls_assistant.wfd_import.documents', []);

        if ($sources === []) {
            throw new RuntimeException('No WFD assistant source documents are configured for import.');
        }

        return collect($sources)
            ->map(function (array $source): array {
                $path = $this->validatedPath($source);
                $content = $this->extractText($path);

                $document = AssistantSourceDocument::query()->updateOrCreate(
                    [
                        'scope' => AssistantSourceScope::Global,
                        'title' => (string) $source['title'],
                    ],
                    [
                        'summary' => (string) $source['summary'],
                        'storage_path' => $path,
                        'mime_type' => File::mimeType($path) ?: 'application/pdf',
                        'file_size' => File::size($path),
                        'content' => $content,
                        'metadata' => [
                            'document_key' => $source['key'] ?? null,
                            'original_filename' => basename($path),
                            'published_at' => $source['published_at'] ?? null,
                            'source_path' => $path,
                            'source_type' => 'wfd_pdf',
                            'import_command' => 'pls:assistant-sources:import-wfd',
                            'import_version' => 1,
                            'extraction_method' => $this->extractionMethod(),
                        ],
                    ],
                );

                return [
                    'title' => $document->title,
                    'status' => $document->wasRecentlyCreated ? 'created' : 'updated',
                    'storage_path' => $path,
                    'content_length' => mb_strlen($content),
                    'extraction_method' => $this->extractionMethod(),
                ];
            });
    }

    /**
     * @param  array<string, mixed>  $source
     */
    private function validatedPath(array $source): string
    {
        $path = trim((string) ($source['path'] ?? ''));

        if ($path === '') {
            throw new RuntimeException(sprintf('Missing path for WFD assistant source [%s].', $source['title'] ?? 'unknown'));
        }

        if (! File::exists($path)) {
            throw new RuntimeException(sprintf('WFD assistant source file not found: %s', $path));
        }

        return $path;
    }

    private function extractText(string $path): string
    {
        $binary = (string) config('pls_assistant.wfd_import.pdftotext_binary', 'pdftotext');
        $command = sprintf(
            '%s -layout -nopgbrk -enc UTF-8 %s -',
            escapeshellarg($binary),
            escapeshellarg($path),
        );

        $result = Process::timeout(120)->run($command);

        if (! $result->successful()) {
            throw new RuntimeException(sprintf(
                'Failed to extract PDF text from [%s]: %s',
                $path,
                $this->errorMessage($result->errorOutput()),
            ));
        }

        $content = $this->cleanText($result->output());

        if ($content === '') {
            throw new RuntimeException(sprintf('No usable text was extracted from [%s].', $path));
        }

        return $content;
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

    private function extractionMethod(): string
    {
        return 'pdftotext -layout -nopgbrk -enc UTF-8';
    }

    private function errorMessage(string $errorOutput): string
    {
        $message = trim($errorOutput);

        return $message === '' ? 'Unknown extraction error.' : $message;
    }
}
