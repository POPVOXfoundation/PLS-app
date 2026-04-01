<?php

namespace App\Support\PlsAssistant;

use App\Domain\Documents\AssistantSourceDocument;
use App\Domain\Documents\Enums\AssistantSourceScope;
use App\Jobs\ExtractAssistantSourceText;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class WfdAssistantSourceImporter
{
    public function __construct(
        private readonly AssistantSourceTextExtractorFactory $extractorFactory,
    ) {}

    /**
     * @return Collection<int, array{
     *     content_length: int,
     *     disk: string,
     *     extraction_method: string,
     *     extraction_status: string,
     *     status: string,
     *     storage_path: string,
     *     title: string
     * }>
     */
    public function importConfiguredSources(array $sourceOverrides = []): Collection
    {
        /** @var array<string, array<string, mixed>> $sources */
        $sources = config('pls_assistant.assistant_sources.wfd_documents', []);

        if ($sources === []) {
            throw new RuntimeException('No WFD assistant source documents are configured for import.');
        }

        return collect($sources)
            ->map(function (array $source, string $alias) use ($sourceOverrides): array {
                $diskName = $this->configuredDisk();
                $stagedSource = $this->stageSourceFile(
                    source: $source,
                    diskName: $diskName,
                    overridePath: $sourceOverrides[$alias] ?? null,
                );

                $document = AssistantSourceDocument::query()->updateOrCreate(
                    [
                        'scope' => AssistantSourceScope::Global,
                        'title' => (string) $source['title'],
                    ],
                    [
                        'summary' => (string) $source['summary'],
                        'storage_path' => $stagedSource['storage_path'],
                        'mime_type' => 'application/pdf',
                        'file_size' => $stagedSource['file_size'],
                        'metadata' => [
                            'disk' => $diskName,
                            'document_key' => $source['key'] ?? null,
                            'original_filename' => $stagedSource['original_filename'],
                            'published_at' => $source['published_at'] ?? null,
                            'source_path' => $stagedSource['source_path'],
                            'source_type' => 'wfd_pdf',
                            'import_command' => 'pls:assistant-sources:import-wfd',
                            'import_version' => 2,
                        ],
                    ],
                );

                $status = $document->wasRecentlyCreated ? 'created' : 'updated';

                $document->forceFill([
                    'metadata' => $this->markExtractionPending($document, $source, $diskName),
                ])->save();

                ExtractAssistantSourceText::dispatch($document->getKey());

                $document->refresh();

                return [
                    'title' => $document->title,
                    'status' => $status,
                    'disk' => $diskName,
                    'storage_path' => $document->storage_path,
                    'content_length' => mb_strlen((string) $document->content),
                    'extraction_method' => $this->extractorFactory->configuredDriver(),
                    'extraction_status' => $this->reportedExtractionStatus($document),
                ];
            });
    }

    private function configuredDisk(): string
    {
        $diskName = trim((string) config('pls_assistant.assistant_sources.source_disk', ''));

        if ($diskName === '') {
            throw new RuntimeException('PLS assistant source disk is not configured.');
        }

        return $diskName;
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array{file_size: int, original_filename: string, source_path: string|null, storage_path: string}
     */
    private function stageSourceFile(array $source, string $diskName, ?string $overridePath = null): array
    {
        $storagePath = $this->canonicalStoragePath($source);
        $disk = Storage::disk($diskName);
        $sourcePath = $this->firstValidPath([
            $overridePath,
            $disk->exists($storagePath) ? null : ($source['bootstrap_path'] ?? null),
        ]);

        if ($sourcePath !== null) {
            $stream = fopen($sourcePath, 'r');

            if ($stream === false) {
                throw new RuntimeException(sprintf('Unable to open WFD assistant source [%s].', $sourcePath));
            }

            try {
                $disk->put($storagePath, $stream);
            } finally {
                fclose($stream);
            }
        }

        if (! $disk->exists($storagePath)) {
            throw new RuntimeException(sprintf(
                'WFD assistant source file not found for [%s]. Expected either a provided local path or stored file at [%s:%s].',
                $source['title'] ?? 'unknown',
                $diskName,
                $storagePath,
            ));
        }

        return [
            'storage_path' => $storagePath,
            'original_filename' => basename($sourcePath ?? $storagePath),
            'source_path' => $sourcePath,
            'file_size' => (int) $disk->size($storagePath),
        ];
    }

    /**
     * @param  array<string, mixed>  $source
     */
    private function canonicalStoragePath(array $source): string
    {
        $configured = trim((string) ($source['storage_path'] ?? ''));

        if ($configured !== '') {
            return trim($configured, '/');
        }

        $prefix = trim((string) config('pls_assistant.assistant_sources.source_prefix', ''), '/');
        $filename = Str::slug((string) ($source['key'] ?? $source['title'] ?? 'assistant-source')).'.pdf';

        return trim($prefix.'/'.$filename, '/');
    }

    /**
     * @param  array<int, mixed>  $paths
     */
    private function firstValidPath(array $paths): ?string
    {
        foreach ($paths as $path) {
            $normalized = trim((string) $path);

            if ($normalized !== '' && File::exists($normalized)) {
                return $normalized;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array<string, mixed>
     */
    private function markExtractionPending(
        AssistantSourceDocument $document,
        array $source,
        string $diskName,
    ): array {
        return [
            ...($document->metadata ?? []),
            'disk' => $diskName,
            'document_key' => $source['key'] ?? null,
            'original_filename' => data_get($document->metadata, 'original_filename', basename($document->storage_path)),
            'published_at' => $source['published_at'] ?? null,
            'source_type' => 'wfd_pdf',
            'import_command' => 'pls:assistant-sources:import-wfd',
            'import_version' => 2,
            'extraction_method' => $this->extractorFactory->configuredDriver(),
            'extraction' => [
                'status' => 'pending',
                'driver' => $this->extractorFactory->configuredDriver(),
                'job_id' => (string) Str::uuid(),
                'error' => null,
                'queued_at' => now()->toIso8601String(),
            ],
        ];
    }

    private function reportedExtractionStatus(AssistantSourceDocument $document): string
    {
        if ((string) config('queue.default') !== 'sync') {
            return 'queued';
        }

        return (string) data_get($document->metadata, 'extraction.status', 'pending');
    }
}
