<?php

namespace App\Support\Reports;

use App\Domain\Documents\Document;
use App\Domain\Reporting\Report;
use App\Support\PlsAssistant\ReviewAssistantContextBuilder;
use LengthException;

class FullReviewReportContext
{
    public function __construct(private ReviewAssistantContextBuilder $builder) {}

    public function build(Report $report, ?int $templateId): string
    {
        $review = $this->builder->hydrateReview($report->review);
        $documents = $review->documents->reject(fn (Document $document): bool => (bool) data_get($document->metadata, 'source_review.excluded', false));
        if ($templateId !== null && ! $documents->contains(fn (Document $document): bool => $document->id === $templateId && $document->extractedText() !== '' && $document->isReportTemplate())) {
            throw new \DomainException('The selected template has no available text. Upload and process a template before retrying.');
        }
        $review->loadMissing('findings.citations.document.chunks', 'findings.citations.finding', 'consultations.materials.document');
        $record = [
            'review' => $review->only(['id', 'title', 'description']),
            'findings' => $review->findings->map(fn ($finding): array => [
                ...$finding->only(['id', 'title', 'finding_type', 'summary', 'detail']),
                'passages' => $finding->citations->filter(fn ($citation): bool => $documents->contains('id', $citation->document_id))->map(fn ($citation): array => [
                    ...$citation->only(['document_id', 'passage', 'relationship', 'note']), 'check_is_current' => $citation->isCurrent(),
                ])->values()->all(),
            ])->all(),
            'recommendations' => $review->recommendations->map->only(['id', 'finding_id', 'title', 'description', 'recommendation_type'])->all(),
            'consultations' => $review->consultations->map(fn ($consultation): array => [
                ...$consultation->only(['id', 'title', 'held_at', 'summary']),
                'results' => $consultation->materials->filter(fn ($material): bool => $documents->contains('id', $material->document_id))->map->only(['document_id', 'summary', 'material_type'])->values()->all(),
            ])->all(),
            'submissions' => $review->submissions->filter(fn ($submission): bool => ! $submission->document_id || $documents->contains('id', $submission->document_id))->map->only(['id', 'document_id', 'stakeholder_id', 'summary', 'submitted_at'])->values()->all(),
            'government_responses' => $review->governmentResponses->filter(fn ($response): bool => ! $response->document_id || $documents->contains('id', $response->document_id))->values()->map->only(['report_id', 'document_id', 'received_at', 'response_status', 'summary'])->all(),
            'selected_template_id' => $templateId,
            'sources' => $documents->sortBy('id')->map(fn (Document $document): array => [
                'id' => $document->id, 'title' => $document->title,
                'role' => $document->isReportTemplate() ? 'structure only, not evidence of results' : $document->document_type->value,
                'classification' => data_get($document->metadata, 'source_review.classification', data_get($document->metadata, 'legislation_analysis.legislation_type')),
                'coverage' => $document->pageCoverageLabel(),
                'source_url' => route('pls.reviews.documents.show', [$review, $document]),
                'text' => $document->extractedText() ?: '[Text unavailable. Do not infer its contents.]',
            ])->values()->all(),
        ];
        $content = json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (mb_strlen($content) > (int) config('pls_assistant.max_context_characters', 300000)) {
            throw new LengthException('The complete record exceeds the report request limit. No source text was silently shortened. Split the material into smaller reviews.');
        }

        return $content;
    }
}
