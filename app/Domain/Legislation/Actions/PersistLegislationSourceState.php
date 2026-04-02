<?php

namespace App\Domain\Legislation\Actions;

use App\Domain\Documents\Document;
use App\Domain\Legislation\Enums\LegislationType;
use App\Domain\Legislation\Enums\ReviewLegislationRelationshipType;

class PersistLegislationSourceState
{
    public function markQueued(Document $document): Document
    {
        $metadata = $document->metadata ?? [];

        data_set($metadata, 'extraction.status', 'queued');
        data_set($metadata, 'extraction.error', null);
        data_set($metadata, 'extraction.queued_at', now()->toIso8601String());
        data_set($metadata, 'extraction.poll_attempts', 0);

        data_set($metadata, 'legislation_analysis', array_filter([
            'analysis_driver' => 'ai',
            'status' => 'processing',
            'progress_stage' => 'queued',
            'source_document_id' => $document->id,
            'source_label' => $document->title,
            'title' => $document->title,
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
     *     source_document_id?: int,
     *     source_label?: string,
     *     title?: string,
     *     short_title?: string,
     *     legislation_type?: string,
     *     date_enacted?: string,
     *     summary?: string,
     *     relationship_type?: string,
     *     warnings?: list<string>,
     *     duplicate_candidates?: list<array{id: int, title: string, short_title: string, legislation_type: string, date_enacted: string}>
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
            'failed' => 'failed',
            default => 'needs_review',
        };

        data_set($metadata, 'legislation_analysis', array_filter([
            'analysis_driver' => 'ai',
            'status' => $status,
            'progress_stage' => $status === 'processing'
                ? ($result['progress_stage'] ?? data_get($metadata, 'legislation_analysis.progress_stage', 'extracting_text'))
                : null,
            'source_document_id' => $result['source_document_id'] ?? $document->id,
            'source_label' => $result['source_label'] ?? $document->title,
            'title' => $result['title'] ?? null,
            'short_title' => $result['short_title'] ?? null,
            'legislation_type' => $result['legislation_type'] ?? null,
            'date_enacted' => $result['date_enacted'] ?? null,
            'summary' => $result['summary'] ?? null,
            'relationship_type' => $result['relationship_type'] ?? null,
            'warnings' => $result['warnings'] ?? [],
            'duplicate_candidates' => $result['duplicate_candidates'] ?? [],
            'updated_at' => now()->toIso8601String(),
        ], fn (mixed $value): bool => $value !== null));

        $document->forceFill([
            'metadata' => $metadata,
        ])->save();

        return $status;
    }

    public function markSaved(Document $document, int $legislationId): void
    {
        if ($legislationId <= 0) {
            return;
        }

        $metadata = $document->metadata ?? [];

        data_set($metadata, 'legislation_analysis.status', 'saved');
        data_set($metadata, 'legislation_analysis.progress_stage', null);
        data_set($metadata, 'legislation_analysis.legislation_id', $legislationId);
        data_set($metadata, 'legislation_analysis.saved_at', now()->toIso8601String());

        $document->forceFill([
            'metadata' => $metadata,
        ])->save();
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

        data_set($metadata, 'legislation_analysis', array_filter([
            'analysis_driver' => 'ai',
            'status' => 'processing',
            'progress_stage' => 'queued',
            'source_document_id' => $document->id,
            'source_label' => data_get($metadata, 'legislation_analysis.source_label', $document->title),
            'title' => data_get($metadata, 'legislation_analysis.title', $document->title),
            'warnings' => [],
            'duplicate_candidates' => [],
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
        $analysis = data_get($document->metadata, 'legislation_analysis', []);

        return is_array($analysis) ? $analysis : [];
    }

    public function storedAnalysisNeedsRefresh(Document $document): bool
    {
        $storedAnalysis = $this->storedAnalysis($document);

        if (($storedAnalysis['status'] ?? null) !== 'needs_review') {
            return false;
        }

        return ($storedAnalysis['analysis_driver'] ?? null) !== 'ai'
            || blank((string) ($storedAnalysis['title'] ?? ''))
            || blank((string) ($storedAnalysis['legislation_type'] ?? ''))
            || blank((string) ($storedAnalysis['relationship_type'] ?? ''));
    }

    public function defaultAnalysisType(): string
    {
        return LegislationType::Act->value;
    }

    public function defaultRelationshipType(): string
    {
        return ReviewLegislationRelationshipType::Primary->value;
    }

    private function markProcessingStage(Document $document, string $progressStage): Document
    {
        $metadata = $document->metadata ?? [];

        data_set($metadata, 'extraction.status', 'processing');
        data_set($metadata, 'extraction.error', null);
        data_set($metadata, 'extraction.processing_at', now()->toIso8601String());
        data_set($metadata, 'legislation_analysis.status', 'processing');
        data_set($metadata, 'legislation_analysis.progress_stage', $progressStage);
        data_set($metadata, 'legislation_analysis.updated_at', now()->toIso8601String());

        $document->forceFill([
            'metadata' => $metadata,
        ])->save();

        return $document->fresh();
    }
}
