<?php

namespace App\Livewire\Pls\Reviews;

use App\Domain\Documents\Actions\StoreReviewDocumentMetadata;
use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Legislation\Actions\PersistLegislationSourceState;
use App\Domain\Legislation\Actions\SaveAnalyzedLegislationForReview;
use App\Domain\Legislation\Enums\LegislationType;
use App\Domain\Legislation\Enums\ReviewLegislationRelationshipType;
use App\Domain\Legislation\Legislation;
use App\Domain\Reviews\PlsReview;
use App\Jobs\ProcessReviewLegislationSource;
use App\Support\Toast;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
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
     * @var list<string>
     */
    public array $analysisKeyThemes = [];

    /**
     * @var list<string>
     */
    public array $analysisNotableExcerpts = [];

    /**
     * @var list<string>
     */
    public array $analysisImportantDates = [];

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

        $document = app(PersistLegislationSourceState::class)->markQueued($document);
        ProcessReviewLegislationSource::dispatch($document->id);

        $this->sourceUpload = null;
        $this->resetValidation(['sourceUpload']);
        $this->review = $this->loadReview();

        $this->dispatchWorkspaceToast(Toast::warning(
            __('Source added'),
            __('Source added. Processing has started.'),
        ));
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
            $document = $this->review->documents()->find($sourceDocumentId);

            if ($document instanceof Document) {
                app(PersistLegislationSourceState::class)->markSaved(
                    $document,
                    $this->analysisSaveMode === 'update'
                        ? (int) $this->analysisExistingLegislationId
                        : (int) $this->review->legislation()
                            ->where('source_document_id', $sourceDocumentId)
                            ->latest('legislation.id')
                            ->value('legislation.id'),
                );
            }
        }

        $this->resetSourceFlow();

        $this->dispatchWorkspaceToast(Toast::success(
            __('Legislation saved'),
            __('Legislation saved and linked to the review.'),
        ));
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

        $state = app(PersistLegislationSourceState::class);
        $document = $this->review->documents()
            ->where('document_type', DocumentType::LegislationText->value)
            ->findOrFail($documentId);

        $storedAnalysis = $state->storedAnalysis($document);
        $linkedLegislationId = $this->linkedLegislationIdForDocument($document);

        if (! in_array(($storedAnalysis['status'] ?? null), ['needs_review', 'saved'], true)) {
            return;
        }

        if (($storedAnalysis['status'] ?? null) === 'needs_review' && $state->storedAnalysisNeedsRefresh($document)) {
            return;
        }

        $this->fillAnalysisStateFromStored($document, $storedAnalysis, $linkedLegislationId);
        $this->dispatch('modal-show', name: 'review-record');
    }

    public function retrySourceAnalysis(int $documentId): void
    {
        $this->authorizeReviewMutation();

        $document = $this->review->documents()
            ->where('document_type', DocumentType::LegislationText->value)
            ->find($documentId);

        if (! $document instanceof Document) {
            return;
        }

        app(PersistLegislationSourceState::class)->resetForRetry($document);
        ProcessReviewLegislationSource::dispatch($document->id);

        $this->review = $this->loadReview();

        $this->dispatchWorkspaceToast(Toast::warning(
            __('Retry started'),
            __('Source retry started. Processing continues in the background.'),
        ));
    }

    public function hasAnalysisState(): bool
    {
        return $this->analysisSourceDocumentId !== '';
    }

    public function analysisModalHeading(): string
    {
        return $this->analysisStatus === 'saved'
            ? __('Edit record')
            : __('Review record');
    }

    public function analysisSubmitLabel(): string
    {
        return $this->analysisStatus === 'saved'
            ? __('Save changes')
            : __('Save record');
    }

    public function analysisWarningsHeading(): string
    {
        return $this->analysisStatus === 'saved'
            ? __('Warnings')
            : __('Needs review');
    }

    public function refreshPendingAnalyses(): void
    {
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

    /**
     * @param  array<string, mixed>  $storedAnalysis
     */
    private function fillAnalysisStateFromStored(Document $document, array $storedAnalysis, ?int $linkedLegislationId = null): void
    {
        $this->analysisSourceDocumentId = (string) ($storedAnalysis['source_document_id'] ?? $document->id);
        $this->analysisSourceLabel = (string) ($storedAnalysis['source_label'] ?? $document->title);
        $this->analysisStatus = (string) ($storedAnalysis['status'] ?? 'needs_review');
        $this->analysisWarnings = collect($storedAnalysis['warnings'] ?? [])
            ->filter(fn (mixed $warning): bool => is_string($warning) && trim($warning) !== '')
            ->map(fn (mixed $warning): string => trim((string) $warning))
            ->values()
            ->all();
        $this->analysisKeyThemes = collect($storedAnalysis['key_themes'] ?? [])
            ->filter(fn (mixed $theme): bool => is_string($theme) && trim($theme) !== '')
            ->map(fn (mixed $theme): string => trim((string) $theme))
            ->values()
            ->all();
        $this->analysisNotableExcerpts = collect($storedAnalysis['notable_excerpts'] ?? [])
            ->filter(fn (mixed $excerpt): bool => is_string($excerpt) && trim($excerpt) !== '')
            ->map(fn (mixed $excerpt): string => trim((string) $excerpt))
            ->values()
            ->all();
        $this->analysisImportantDates = collect($storedAnalysis['important_dates'] ?? [])
            ->filter(fn (mixed $date): bool => is_string($date) && trim($date) !== '')
            ->map(fn (mixed $date): string => trim((string) $date))
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

        if ($this->analysisStatus === 'saved' && $linkedLegislationId !== null) {
            $this->analysisExistingLegislationId = (string) $linkedLegislationId;
            $this->analysisSaveMode = 'update';
        } else {
            $this->analysisExistingLegislationId = $this->analysisDuplicateCandidates === []
                ? ''
                : (string) $this->analysisDuplicateCandidates[0]['id'];
            $this->analysisSaveMode = $this->analysisDuplicateCandidates === [] ? 'create' : 'update';
        }

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

    private function linkedLegislationIdForDocument(Document $document): ?int
    {
        $linkedLegislationId = data_get($document->metadata, 'legislation_analysis.legislation_id');

        if (is_numeric($linkedLegislationId) && (int) $linkedLegislationId > 0) {
            return (int) $linkedLegislationId;
        }

        $review = $document->relationLoaded('review') ? $document->review : $this->review;

        return $review?->legislation()
            ->where('source_document_id', $document->id)
            ->value('legislation.id');
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
        $this->analysisKeyThemes = [];
        $this->analysisNotableExcerpts = [];
        $this->analysisImportantDates = [];
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
     *     progress_stage: string|null,
     *     status_label: string,
     *     status_color: string,
     *     status_badge_class: string,
     *     status_detail: string|null,
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

            $storedAnalysis = app(PersistLegislationSourceState::class)->storedAnalysis($document);
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
                'progress_stage' => $progressStage = $this->sourceRecordProgressStage($document, $status),
                'status_label' => $this->statusLabel($status, $progressStage),
                'status_color' => $this->statusColor($status, $progressStage),
                'status_badge_class' => $this->statusBadgeClass($status, $progressStage),
                'status_detail' => $this->statusDetail($status, $progressStage),
                'action' => match ($status) {
                    'needs_review' => 'review',
                    'saved' => 'edit',
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
                'progress_stage' => null,
                'status_label' => $this->statusLabel('saved'),
                'status_color' => $this->statusColor('saved'),
                'status_badge_class' => $this->statusBadgeClass('saved'),
                'status_detail' => $this->statusDetail('saved'),
                'action' => null,
            ];
        }

        return array_values($rows);
    }

    private function sourceRecordStatus(Document $document): string
    {
        $state = app(PersistLegislationSourceState::class);
        $storedStatus = data_get($document->metadata, 'legislation_analysis.status');

        if (is_string($storedStatus) && $storedStatus !== '') {
            if ($storedStatus === 'needs_review' && $state->storedAnalysisNeedsRefresh($document)) {
                return 'failed';
            }

            return $storedStatus;
        }

        $extractionStatus = data_get($document->metadata, 'extraction.status');

        return match ($extractionStatus) {
            'queued', 'processing' => 'processing',
            'failed' => 'failed',
            default => 'needs_review',
        };
    }

    private function sourceRecordProgressStage(Document $document, string $status): ?string
    {
        if ($status !== 'processing') {
            return null;
        }

        $progressStage = data_get($document->metadata, 'legislation_analysis.progress_stage');

        return is_string($progressStage) && $progressStage !== ''
            ? $progressStage
            : 'queued';
    }

    private function statusLabel(string $status, ?string $progressStage = null): string
    {
        if ($status === 'processing') {
            return match ($progressStage) {
                'queued' => __('Queued'),
                'extracting_text' => __('Extracting text'),
                'filling_record' => __('Filling record'),
                default => __('Processing'),
            };
        }

        return match ($status) {
            'needs_review' => __('Needs review'),
            'failed' => __('Needs attention'),
            default => __('Saved'),
        };
    }

    private function statusColor(string $status, ?string $progressStage = null): string
    {
        if ($status === 'processing') {
            return match ($progressStage) {
                'queued' => 'zinc',
                'extracting_text' => 'sky',
                'filling_record' => 'violet',
                default => 'sky',
            };
        }

        return match ($status) {
            'needs_review' => 'amber',
            'failed' => 'rose',
            default => 'emerald',
        };
    }

    private function statusBadgeClass(string $status, ?string $progressStage = null): string
    {
        if ($status === 'processing') {
            return match ($progressStage) {
                'queued' => 'ring-1 ring-zinc-300/70 dark:ring-zinc-700/70',
                'extracting_text' => 'ring-1 ring-sky-300/70 dark:ring-sky-700/60',
                'filling_record' => 'ring-1 ring-violet-300/70 dark:ring-violet-700/60',
                default => 'ring-1 ring-sky-300/70 dark:ring-sky-700/60',
            };
        }

        return match ($status) {
            'needs_review' => 'ring-1 ring-amber-300/70 dark:ring-amber-700/60',
            'failed' => 'ring-1 ring-rose-300/70 dark:ring-rose-700/60',
            default => 'ring-1 ring-emerald-300/70 dark:ring-emerald-700/60',
        };
    }

    private function statusDetail(string $status, ?string $progressStage = null): ?string
    {
        if ($status === 'processing') {
            return match ($progressStage) {
                'queued' => __('Waiting for the background worker.'),
                'extracting_text' => __('Reading the uploaded file.'),
                'filling_record' => __('Using AI to draft the record.'),
                default => __('Work is still running in the background.'),
            };
        }

        return match ($status) {
            'needs_review' => __('Review before saving.'),
            'failed' => __('Open retry when you are ready.'),
            default => __('Saved to this review.'),
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

        DB::transaction(function () use ($document): void {
            $sourcedLegislation = Legislation::query()
                ->where('source_document_id', $document->id)
                ->get();

            foreach ($sourcedLegislation as $legislation) {
                $this->review->legislation()->detach($legislation->id);

                if ($legislation->reviews()->exists()) {
                    $legislation->forceFill([
                        'source_document_id' => null,
                    ])->save();

                    continue;
                }

                $legislation->delete();
            }

            if ($document->storage_path !== '') {
                Storage::disk((string) data_get($document->metadata, 'disk', config('filesystems.default')))
                    ->delete($document->storage_path);
            }

            $document->delete();
        });

        $this->review = $this->loadReview();

        if ($this->analysisSourceDocumentId === (string) $documentId) {
            $this->resetSourceFlow();
        }

        $this->dispatchWorkspaceToast(Toast::success(
            __('Source removed'),
            __('Source removed from records.'),
        ));
    }
}
