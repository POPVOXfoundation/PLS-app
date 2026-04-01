<?php

namespace App\Livewire\Pls\Reviews;

use App\Domain\Documents\Actions\ChunkDocumentText;
use App\Domain\Documents\Actions\StoreReviewDocumentMetadata;
use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Legislation\Actions\AnalyzeLegislationSource;
use App\Domain\Legislation\Actions\SaveAnalyzedLegislationForReview;
use App\Domain\Legislation\Enums\LegislationType;
use App\Domain\Legislation\Enums\ReviewLegislationRelationshipType;
use App\Domain\Legislation\Legislation;
use App\Domain\Reviews\PlsReview;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class LegislationPage extends Workspace
{
    use AuthorizesRequests;
    use WithFileUploads;

    private const MAX_SOURCE_UPLOAD_KB = 51200;

    protected string $workspace = 'legislation';

    public ?TemporaryUploadedFile $sourceUpload = null;

    public string $analysisSourceDocumentId = '';

    public string $analysisSourceLabel = '';

    public string $analysisStatus = 'idle';

    public string $analysisTitle = '';

    public string $analysisShortTitle = '';

    public string $analysisType = LegislationType::Act->value;

    public string $analysisDateEnacted = '';

    public string $analysisSummary = '';

    public string $analysisRelationshipType = ReviewLegislationRelationshipType::Primary->value;

    public string $analysisSaveMode = 'create';

    public string $analysisExistingLegislationId = '';

    /**
     * @var list<string>
     */
    public array $analysisWarnings = [];

    /**
     * @var list<array{id: int, title: string, short_title: string, legislation_type: string, date_enacted: string}>
     */
    public array $analysisDuplicateCandidates = [];

    public function mount(PlsReview $review): void
    {
        parent::mount($review);
    }

    public function render(): View
    {
        $review = $this->loadReview();

        return $this->renderWorkspaceView('livewire.pls.reviews.legislation-page', [
            'review' => $review,
            'legislationTypes' => LegislationType::cases(),
            'legislationRelationshipTypes' => ReviewLegislationRelationshipType::cases(),
            'hasProcessingRecords' => $this->hasProcessingRecords($review),
            'recordRows' => $this->recordRows($review),
        ], $review);
    }

    public function updatedSourceUpload(): void
    {
        $this->authorizeReviewMutation();

        if ($this->sourceUpload === null) {
            return;
        }

        $this->validate([
            'sourceUpload' => ['file', 'mimes:pdf,docx', 'max:'.self::MAX_SOURCE_UPLOAD_KB],
        ], [
            'sourceUpload.max' => __('Choose a file that is 50 MB or smaller.'),
            'sourceUpload.mimes' => __('Choose a PDF or DOCX file.'),
        ]);

        $storedReview = app(StoreReviewDocumentMetadata::class)->store([
            'pls_review_id' => $this->review->id,
            'title' => $this->sourceTitleFromUpload($this->sourceUpload),
            'document_type' => DocumentType::LegislationText->value,
            'storage_path' => null,
            'file' => $this->sourceUpload,
            'mime_type' => null,
            'file_size' => null,
            'summary' => null,
            'metadata' => [
                'disk' => $this->sourceStorageDisk(),
                'original_name' => $this->sourceUpload->getClientOriginalName(),
            ],
        ]);

        $this->review = $storedReview->fresh(['legislation', 'documents']);
        $document = $this->review->documents->sortByDesc('id')->first();

        if (! $document instanceof Document) {
            $this->addError('sourceUpload', __('The uploaded legislation source could not be stored.'));

            return;
        }

        $status = $this->processSourceDocument($document);

        $this->sourceUpload = null;
        $this->resetValidation(['sourceUpload']);
        $this->review = $this->loadReview();

        $this->dispatch('review-workspace-updated', status: match ($status) {
            'processing' => __('Source added. Processing has started.'),
            'needs_review' => __('Source added. It is ready for review.'),
            'failed' => __('Source added, but it needs attention before it can be reviewed.'),
            default => __('Source added.'),
        });
    }

    public function saveAnalyzedLegislation(SaveAnalyzedLegislationForReview $action): void
    {
        $this->authorizeReviewMutation();

        $sourceDocumentId = $this->blankToNull($this->analysisSourceDocumentId) === null
            ? null
            : (int) $this->analysisSourceDocumentId;

        try {
            $this->review = $action->save([
                'pls_review_id' => $this->review->id,
                'source_document_id' => $sourceDocumentId,
                'save_mode' => $this->analysisSaveMode,
                'legislation_id' => $this->blankToNull($this->analysisExistingLegislationId) === null ? null : (int) $this->analysisExistingLegislationId,
                'title' => $this->analysisTitle,
                'short_title' => $this->blankToNull($this->analysisShortTitle),
                'legislation_type' => $this->analysisType,
                'date_enacted' => $this->blankToNull($this->analysisDateEnacted),
                'summary' => $this->blankToNull($this->analysisSummary),
                'relationship_type' => $this->analysisRelationshipType,
            ])->fresh();
        } catch (ValidationException $exception) {
            $this->mapValidationErrors($exception, [
                'source_document_id' => 'analysisSourceDocumentId',
                'save_mode' => 'analysisSaveMode',
                'legislation_id' => 'analysisExistingLegislationId',
                'title' => 'analysisTitle',
                'short_title' => 'analysisShortTitle',
                'legislation_type' => 'analysisType',
                'date_enacted' => 'analysisDateEnacted',
                'summary' => 'analysisSummary',
                'relationship_type' => 'analysisRelationshipType',
            ]);

            return;
        }

        if ($sourceDocumentId !== null) {
            $this->markSourceDocumentSaved(
                $sourceDocumentId,
                $this->analysisSaveMode === 'update'
                    ? (int) $this->analysisExistingLegislationId
                    : (int) $this->review->legislation()
                        ->where('source_document_id', $sourceDocumentId)
                        ->latest('legislation.id')
                        ->value('legislation.id'),
            );
        }

        $this->resetSourceFlow();

        $this->dispatch('review-workspace-updated', status: __('Legislation saved and linked to the review.'));
    }

    public function resetSourceFlow(): void
    {
        $this->sourceUpload = null;

        $this->resetAnalysisState();
        $this->dispatch('modal-close', name: 'review-record');
    }

    public function confirmDeletion(int $documentId): void
    {
        $this->authorizeReviewMutation();

        $this->performSourceDeletion($documentId);
    }

    public function startReviewDocument(int $documentId): void
    {
        $this->authorizeReviewMutation();

        $document = $this->review->documents()
            ->where('document_type', DocumentType::LegislationText->value)
            ->findOrFail($documentId);

        $storedAnalysis = $this->storedAnalysisForDocument($document);
        $status = (string) ($storedAnalysis['status'] ?? '');

        if ($status === 'processing') {
            return;
        }

        if ($status === '' || $status === 'failed' || $this->storedAnalysisNeedsRefresh($storedAnalysis)) {
            $status = $this->processSourceDocument($this->incrementExtractionPollAttempts($document));

            $document = $document->fresh();
            $storedAnalysis = $this->storedAnalysisForDocument($document);
        }

        if (($storedAnalysis['status'] ?? null) !== 'needs_review') {
            return;
        }

        $this->fillAnalysisStateFromStored($document, $storedAnalysis);
        $this->dispatch('modal-show', name: 'review-record');
    }

    public function hasAnalysisState(): bool
    {
        return $this->analysisSourceDocumentId !== '';
    }

    public function refreshPendingAnalyses(): void
    {
        $documents = $this->review->documents()
            ->where('document_type', DocumentType::LegislationText->value)
            ->get()
            ->filter(fn (Document $document): bool => $this->sourceRecordStatus($document) === 'processing');

        if ($documents->isEmpty()) {
            return;
        }

        foreach ($documents as $document) {
            $this->processSourceDocument($this->incrementExtractionPollAttempts($document));
        }

        $this->review = $this->loadReview();
    }

    public function sourceUploadLimitLabel(): string
    {
        return __('50 MB max');
    }

    /**
     * @param  array<string, string>  $mapping
     */
    private function mapValidationErrors(ValidationException $exception, array $mapping): void
    {
        $this->resetValidation(array_values($mapping));

        foreach ($exception->errors() as $key => $messages) {
            $mappedKey = $mapping[$key] ?? $key;

            foreach ($messages as $message) {
                $this->addError($mappedKey, $message);
            }
        }
    }

    private function authorizeReviewMutation(): void
    {
        $this->authorize('update', $this->review);
    }

    private function blankToNull(string $value): ?string
    {
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function processSourceDocument(Document $document): string
    {
        $result = app(AnalyzeLegislationSource::class)->analyze($document, $this->review->jurisdiction_id);
        $this->persistExtractionState($document, $result);

        $rawText = trim((string) ($result['raw_text'] ?? ''));

        if ($rawText !== '') {
            app(ChunkDocumentText::class)->chunk($document, $rawText);
        }

        return $this->persistLegislationAnalysisState($document->fresh(), $result);
    }

    /**
     * @param  array{
     *     status?: string,
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
    private function persistLegislationAnalysisState(Document $document, array $result): string
    {
        $metadata = $document->metadata ?? [];

        $status = match ($result['status'] ?? 'completed') {
            'processing' => 'processing',
            'failed' => 'failed',
            default => 'needs_review',
        };

        data_set($metadata, 'legislation_analysis', array_filter([
            'analysis_driver' => 'ai',
            'status' => $status,
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

    /**
     * @return array<string, mixed>
     */
    private function storedAnalysisForDocument(Document $document): array
    {
        $analysis = data_get($document->metadata, 'legislation_analysis', []);

        return is_array($analysis) ? $analysis : [];
    }

    /**
     * @param  array<string, mixed>  $storedAnalysis
     */
    private function storedAnalysisNeedsRefresh(array $storedAnalysis): bool
    {
        if (($storedAnalysis['status'] ?? null) !== 'needs_review') {
            return false;
        }

        return ($storedAnalysis['analysis_driver'] ?? null) !== 'ai'
            || blank((string) ($storedAnalysis['title'] ?? ''))
            || blank((string) ($storedAnalysis['legislation_type'] ?? ''))
            || blank((string) ($storedAnalysis['relationship_type'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $storedAnalysis
     */
    private function fillAnalysisStateFromStored(Document $document, array $storedAnalysis): void
    {
        $this->analysisSourceDocumentId = (string) ($storedAnalysis['source_document_id'] ?? $document->id);
        $this->analysisSourceLabel = (string) ($storedAnalysis['source_label'] ?? $document->title);
        $this->analysisStatus = (string) ($storedAnalysis['status'] ?? 'needs_review');
        $this->analysisWarnings = collect($storedAnalysis['warnings'] ?? [])
            ->filter(fn (mixed $warning): bool => is_string($warning) && trim($warning) !== '')
            ->map(fn (mixed $warning): string => trim((string) $warning))
            ->values()
            ->all();
        $this->analysisTitle = (string) ($storedAnalysis['title'] ?? '');
        $this->analysisShortTitle = (string) ($storedAnalysis['short_title'] ?? '');
        $this->analysisType = (string) ($storedAnalysis['legislation_type'] ?? LegislationType::Act->value);
        $this->analysisDateEnacted = (string) ($storedAnalysis['date_enacted'] ?? '');
        $this->analysisSummary = (string) ($storedAnalysis['summary'] ?? '');
        $this->analysisRelationshipType = (string) ($storedAnalysis['relationship_type'] ?? ReviewLegislationRelationshipType::Primary->value);
        $this->analysisDuplicateCandidates = collect($storedAnalysis['duplicate_candidates'] ?? [])
            ->filter(fn (mixed $candidate): bool => is_array($candidate))
            ->values()
            ->all();
        $this->analysisExistingLegislationId = $this->analysisDuplicateCandidates === []
            ? ''
            : (string) $this->analysisDuplicateCandidates[0]['id'];
        $this->analysisSaveMode = $this->analysisDuplicateCandidates === [] ? 'create' : 'update';

        $this->resetValidation([
            'analysisSourceDocumentId',
            'analysisSaveMode',
            'analysisExistingLegislationId',
            'analysisTitle',
            'analysisShortTitle',
            'analysisType',
            'analysisDateEnacted',
            'analysisSummary',
            'analysisRelationshipType',
        ]);
    }

    private function markSourceDocumentSaved(int $documentId, int $legislationId): void
    {
        if ($legislationId <= 0) {
            return;
        }

        $document = $this->review->documents()->find($documentId);

        if (! $document instanceof Document) {
            return;
        }

        $metadata = $document->metadata ?? [];
        data_set($metadata, 'legislation_analysis.status', 'saved');
        data_set($metadata, 'legislation_analysis.legislation_id', $legislationId);
        data_set($metadata, 'legislation_analysis.saved_at', now()->toIso8601String());

        $document->forceFill([
            'metadata' => $metadata,
        ])->save();
    }

    private function incrementExtractionPollAttempts(Document $document): Document
    {
        $metadata = $document->metadata ?? [];
        $pollAttempts = (int) data_get($metadata, 'extraction.poll_attempts', 0) + 1;

        data_set($metadata, 'extraction.poll_attempts', $pollAttempts);

        $document->forceFill([
            'metadata' => $metadata,
        ])->save();

        return $document->fresh();
    }

    /**
     * @param  array{
     *     status?: string,
     *     extraction_driver?: string|null,
     *     extraction_method?: string|null,
     *     extraction_metadata?: array<string, mixed>
     * }  $result
     */
    private function persistExtractionState(Document $document, array $result): void
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

        $document->forceFill([
            'metadata' => $metadata,
        ])->save();
    }

    private function loadReview(): PlsReview
    {
        return PlsReview::query()
            ->with([
                'legislation',
                'documents.sourcedLegislation',
            ])
            ->findOrFail($this->review->getKey());
    }

    private function resetAnalysisState(): void
    {
        $this->reset([
            'analysisSourceDocumentId',
            'analysisSourceLabel',
            'analysisStatus',
            'analysisTitle',
            'analysisShortTitle',
            'analysisDateEnacted',
            'analysisSummary',
            'analysisExistingLegislationId',
        ]);

        $this->analysisType = LegislationType::Act->value;
        $this->analysisRelationshipType = ReviewLegislationRelationshipType::Primary->value;
        $this->analysisSaveMode = 'create';
        $this->analysisWarnings = [];
        $this->analysisDuplicateCandidates = [];

        $this->resetValidation([
            'sourceUpload',
            'analysisSourceDocumentId',
            'analysisSaveMode',
            'analysisExistingLegislationId',
            'analysisTitle',
            'analysisShortTitle',
            'analysisType',
            'analysisDateEnacted',
            'analysisSummary',
            'analysisRelationshipType',
        ]);
    }

    private function sourceTitleFromUpload(TemporaryUploadedFile $upload): string
    {
        $baseName = pathinfo($upload->getClientOriginalName(), PATHINFO_FILENAME);
        $baseName = preg_replace('/[_-][a-f0-9]{8,}$/i', '', $baseName) ?? $baseName;

        return Str::of($baseName)
            ->replace(['_', '-'], ' ')
            ->headline()
            ->trim()
            ->toString();
    }

    private function sourceStorageDisk(): string
    {
        $configuredSourceDisk = trim((string) config('pls_assistant.assistant_sources.source_disk', ''));
        $configuredExtractor = (string) config('pls_assistant.assistant_sources.extractor', 'local');

        if (
            $configuredExtractor === 'textract'
            && (string) config('filesystems.disks.s3.driver', '') === 's3'
        ) {
            return 's3';
        }

        if ($configuredSourceDisk !== '') {
            return $configuredSourceDisk;
        }

        return (string) config('filesystems.default');
    }

    private function hasProcessingRecords(PlsReview $review): bool
    {
        return $review->documents
            ->filter(fn (Document $document): bool => $document->document_type === DocumentType::LegislationText)
            ->contains(fn (Document $document): bool => $this->sourceRecordStatus($document) === 'processing');
    }

    /**
     * @return list<array{
     *     id: string,
     *     kind: string,
     *     source_document_id: int|null,
     *     title: string,
     *     subtitle: string|null,
     *     relationship: string,
     *     legislation_type: string,
     *     date_enacted: string,
     *     status: string,
     *     status_label: string,
     *     status_color: string,
     *     action: string|null
     * }>
     */
    private function recordRows(PlsReview $review): array
    {
        $rows = [];
        $sourcedLegislationIds = [];

        foreach ($review->documents->filter(fn (Document $document): bool => $document->document_type === DocumentType::LegislationText)->sortByDesc('id') as $document) {
            $savedLegislation = $review->legislation->firstWhere('source_document_id', $document->id);

            if ($savedLegislation instanceof Legislation) {
                $sourcedLegislationIds[] = $savedLegislation->id;
            }

            $storedAnalysis = $this->storedAnalysisForDocument($document);
            $status = $savedLegislation instanceof Legislation
                ? 'saved'
                : $this->sourceRecordStatus($document);

            $rows[] = [
                'id' => 'source-'.$document->id,
                'kind' => 'source',
                'source_document_id' => $document->id,
                'title' => $savedLegislation?->title
                    ?? (string) ($storedAnalysis['title'] ?? $document->title),
                'subtitle' => $savedLegislation instanceof Legislation ? $document->title : null,
                'relationship' => $savedLegislation instanceof Legislation
                    ? Str::headline((string) $savedLegislation->pivot?->relationship_type)
                    : Str::headline((string) ($storedAnalysis['relationship_type'] ?? '')),
                'legislation_type' => $savedLegislation instanceof Legislation
                    ? Str::headline($savedLegislation->legislation_type->value)
                    : Str::headline((string) ($storedAnalysis['legislation_type'] ?? '')),
                'date_enacted' => $savedLegislation?->date_enacted?->toFormattedDateString()
                    ?? ($this->blankToNull((string) ($storedAnalysis['date_enacted'] ?? '')) ?? '—'),
                'status' => $status,
                'status_label' => $this->statusLabel($status),
                'status_color' => $this->statusColor($status),
                'action' => match ($status) {
                    'needs_review' => 'review',
                    'failed' => 'retry',
                    default => null,
                },
            ];
        }

        foreach ($review->legislation->sortByDesc('id') as $legislation) {
            if (in_array($legislation->id, $sourcedLegislationIds, true)) {
                continue;
            }

            $rows[] = [
                'id' => 'legacy-'.$legislation->id,
                'kind' => 'legacy',
                'source_document_id' => null,
                'title' => $legislation->title,
                'subtitle' => null,
                'relationship' => Str::headline((string) $legislation->pivot->relationship_type),
                'legislation_type' => Str::headline($legislation->legislation_type->value),
                'date_enacted' => $legislation->date_enacted?->toFormattedDateString() ?? '—',
                'status' => 'saved',
                'status_label' => $this->statusLabel('saved'),
                'status_color' => $this->statusColor('saved'),
                'action' => null,
            ];
        }

        return array_values($rows);
    }

    private function sourceRecordStatus(Document $document): string
    {
        $storedStatus = data_get($document->metadata, 'legislation_analysis.status');

        if (is_string($storedStatus) && $storedStatus !== '') {
            return $storedStatus;
        }

        $extractionStatus = data_get($document->metadata, 'extraction.status');

        return match ($extractionStatus) {
            'processing' => 'processing',
            'failed' => 'failed',
            default => 'needs_review',
        };
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'processing' => __('Processing'),
            'needs_review' => __('Needs review'),
            'failed' => __('Needs attention'),
            default => __('Saved'),
        };
    }

    private function statusColor(string $status): string
    {
        return match ($status) {
            'processing' => 'sky',
            'needs_review' => 'amber',
            'failed' => 'rose',
            default => 'emerald',
        };
    }

    private function performSourceDeletion(int $documentId): void
    {
        $document = $this->review->documents()
            ->where('document_type', DocumentType::LegislationText->value)
            ->whereKey($documentId)
            ->first();

        if (! $document instanceof Document) {
            return;
        }

        if ($document->storage_path !== '') {
            Storage::disk((string) data_get($document->metadata, 'disk', config('filesystems.default')))
                ->delete($document->storage_path);
        }

        $document->delete();
        $this->review = $this->loadReview();

        if ($this->analysisSourceDocumentId === (string) $documentId) {
            $this->resetSourceFlow();
        }

        $this->dispatch('review-workspace-updated', status: __('Source removed from records.'));
    }
}
