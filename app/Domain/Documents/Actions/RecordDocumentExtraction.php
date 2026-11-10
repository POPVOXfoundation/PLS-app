<?php

namespace App\Domain\Documents\Actions;

use App\Domain\Documents\Document;
use Closure;
use Illuminate\Support\Facades\DB;

class RecordDocumentExtraction
{
    public function forRun(int $documentId, ?string $runId, Closure $operation): mixed
    {
        return DB::transaction(function () use ($documentId, $runId, $operation): mixed {
            $document = Document::query()->lockForUpdate()->find($documentId);
            if ($document === null || data_get($document->metadata, 'extraction.run_id') !== $runId) {
                return null;
            }

            return $operation($document);
        });
    }

    public function fail(int $documentId, ?string $runId, string $analysisKey): void
    {
        DB::transaction(function () use ($documentId, $runId, $analysisKey): void {
            $document = Document::query()->lockForUpdate()->find($documentId);
            if ($document === null || data_get($document->metadata, 'extraction.run_id') !== $runId
                || ! in_array(data_get($document->metadata, 'extraction.status'), ['queued', 'processing'], true)) {
                return;
            }
            $metadata = $document->metadata ?? [];
            $message = __('Processing stopped before completion. Your source and available text are safe. Retry processing.');
            data_set($metadata, 'extraction.status', 'failed');
            data_set($metadata, 'extraction.error', $message);
            data_set($metadata, 'extraction.failed_at', now()->toIso8601String());
            data_set($metadata, $analysisKey.'.status', $analysisKey === 'legislation_analysis' ? 'failed' : 'needs_attention');
            data_set($metadata, $analysisKey.'.progress_stage', null);
            data_set($metadata, $analysisKey.'.warnings', array_values(array_unique([...(array) data_get($metadata, $analysisKey.'.warnings', []), $message])));
            $document->update(['metadata' => $metadata]);
        });
    }

    /** @param array<string, mixed> $result */
    public function record(Document $document, array $result): void
    {
        DB::transaction(function () use ($document, $result): void {
            $current = Document::query()->lockForUpdate()->findOrFail($document->id);
            if (data_get($current->metadata, 'extraction.run_id') !== data_get($document->metadata, 'extraction.run_id')) {
                return;
            }
            $metadata = $current->metadata ?? [];
            foreach ($result['extraction_metadata'] ?? [] as $key => $value) {
                data_set($metadata, 'extraction.'.$key, $value);
            }
            if (filled($result['extraction_driver'] ?? null)) {
                data_set($metadata, 'extraction.driver', $result['extraction_driver']);
            }
            if (filled($result['extraction_method'] ?? null)) {
                data_set($metadata, 'extraction_method', $result['extraction_method']);
            }
            data_set($metadata, 'extraction.text_ready_at', now()->toIso8601String());
            $current->update(['metadata' => $metadata]);
        });
    }
}
