<?php

namespace App\Domain\Documents\Actions;

use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentType;

class PersistReviewDocumentState
{
    public function markQueued(Document $document): Document
    {
        $metadata = $document->metadata ?? [];

        data_set($metadata, 'extraction.status', 'queued');
        data_set($metadata, 'extraction.error', null);
        data_set($metadata, 'extraction.queued_at', now()->toIso8601String());
        data_set($metadata, 'extraction.poll_attempts', 0);

        data_set($metadata, 'document_analysis', array_filter([
            'analysis_driver' => 'review_document_extractor_v1',
            'status' => 'processing',
            'progress_stage' => 'queued',
            'title' => $document->title,
            'document_type' => $document->document_type->value,
            'updated_at' => now()->toIso8601String(),
        ], fn (mixed $value): bool => $value !== null));

        $document->forceFill([
            'metadata' => $metadata,
        ])->save();

        return $document->fresh();
    }

    public function markExtractingText(Document $document): Document
    {
        return $this->markProcessingStage($document, 'extracting_text');
    }

    public function markFillingRecord(Document $document): Document
    {
        return $this->markProcessingStage($document, 'filling_record');
    }

    /**
     * @param  array{
     *     status?: string,
     *     extraction_driver?: string|null,
     *     extraction_method?: string|null,
     *     extraction_metadata?: array<string, mixed>,
     *     progress_stage?: string|null,
     *     title?: string,
     *     document_type?: string,
     *     summary?: string,
     *     key_themes?: list<string>,
     *     notable_excerpts?: list<string>,
     *     important_dates?: list<string>,
     *     warnings?: list<string>
     * }  $result
     */
    public function persistResult(Document $document, array $result): string
    {
        $metadata = $document->metadata ?? [];

        foreach (($result['extraction_metadata'] ?? []) as $key => $value) {
            data_set($metadata, "extraction.{$key}", $value);
        }

        data_set($metadata, 'extraction.status', $result['status'] ?? 'completed');
        data_set($metadata, 'extraction.driver', $result['extraction_driver'] ?? data_get($metadata, 'extraction.driver'));
        data_set($metadata, 'extraction_method', $result['extraction_method'] ?? data_get($metadata, 'extraction_method'));
        data_set($metadata, 'extraction.error', ($result['status'] ?? null) === 'failed' ? ($result['warnings'][0] ?? null) : null);

        if (($result['status'] ?? null) === 'processing') {
            data_set($metadata, 'extraction.processing_at', now()->toIso8601String());
        }

        if (($result['status'] ?? null) === 'completed') {
            data_set($metadata, 'extraction.completed_at', now()->toIso8601String());
            data_set($metadata, 'extraction.error', null);
        }

        if (($result['status'] ?? null) === 'failed') {
            data_set($metadata, 'extraction.failed_at', now()->toIso8601String());
        }

        $status = match ($result['status'] ?? 'completed') {
            'processing' => 'processing',
            'failed' => 'needs_attention',
            default => 'saved',
        };

        data_set($metadata, 'document_analysis', array_filter([
            'analysis_driver' => 'review_document_extractor_v1',
            'status' => $status,
            'progress_stage' => $status === 'processing'
                ? ($result['progress_stage'] ?? data_get($metadata, 'document_analysis.progress_stage', 'extracting_text'))
                : null,
            'title' => $result['title'] ?? null,
            'document_type' => $result['document_type'] ?? null,
            'summary' => $result['summary'] ?? null,
            'key_themes' => $result['key_themes'] ?? [],
            'notable_excerpts' => $result['notable_excerpts'] ?? [],
            'important_dates' => $result['important_dates'] ?? [],
            'warnings' => $result['warnings'] ?? [],
            'updated_at' => now()->toIso8601String(),
        ], fn (mixed $value): bool => $value !== null));

        $attributes = [
            'metadata' => $metadata,
        ];

        if ($status === 'saved') {
            $attributes['title'] = (string) ($result['title'] ?? $document->title);
            $attributes['document_type'] = (string) ($result['document_type'] ?? $document->document_type->value);
            $attributes['summary'] = $this->blankToNull((string) ($result['summary'] ?? ''));
        }

        $document->forceFill($attributes)->save();

        return $status;
    }

    public function incrementPollAttempts(Document $document): Document
    {
        $metadata = $document->metadata ?? [];
        $pollAttempts = (int) data_get($metadata, 'extraction.poll_attempts', 0) + 1;

        data_set($metadata, 'extraction.poll_attempts', $pollAttempts);

        $document->forceFill([
            'metadata' => $metadata,
        ])->save();

        return $document->fresh();
    }

    public function resetForRetry(Document $document): Document
    {
        $metadata = $document->metadata ?? [];

        data_set($metadata, 'extraction.status', 'queued');
        data_set($metadata, 'extraction.poll_attempts', 0);
        data_set($metadata, 'extraction.error', null);
        data_set($metadata, 'extraction.failed_at', null);
        data_set($metadata, 'extraction.processing_at', null);
        data_set($metadata, 'extraction.completed_at', null);
        data_set($metadata, 'extraction.queued_at', now()->toIso8601String());

        data_set($metadata, 'document_analysis', array_filter([
            'analysis_driver' => 'review_document_extractor_v1',
            'status' => 'processing',
            'progress_stage' => 'queued',
            'title' => data_get($metadata, 'document_analysis.title', $document->title),
            'document_type' => data_get($metadata, 'document_analysis.document_type', $document->document_type->value),
            'summary' => null,
            'key_themes' => [],
            'notable_excerpts' => [],
            'important_dates' => [],
            'warnings' => [],
            'updated_at' => now()->toIso8601String(),
        ], fn (mixed $value): bool => $value !== null));

        $document->forceFill([
            'metadata' => $metadata,
        ])->save();

        return $document->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function storedAnalysis(Document $document): array
    {
        $analysis = data_get($document->metadata, 'document_analysis', []);

        return is_array($analysis) ? $analysis : [];
    }

    public function defaultAnalysisType(): string
    {
        return DocumentType::GroupReport->value;
    }

    private function markProcessingStage(Document $document, string $progressStage): Document
    {
        $metadata = $document->metadata ?? [];

        data_set($metadata, 'extraction.status', 'processing');
        data_set($metadata, 'extraction.error', null);
        data_set($metadata, 'extraction.processing_at', now()->toIso8601String());
        data_set($metadata, 'document_analysis.status', 'processing');
        data_set($metadata, 'document_analysis.progress_stage', $progressStage);
        data_set($metadata, 'document_analysis.updated_at', now()->toIso8601String());

        $document->forceFill([
            'metadata' => $metadata,
        ])->save();

        return $document->fresh();
    }

    private function blankToNull(string $value): ?string
    {
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
