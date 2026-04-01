<?php

namespace App\Livewire\Pls\Reviews;

use App\Domain\Documents\Actions\AnalyzeReviewDocument;
use App\Domain\Documents\Actions\ChunkDocumentText;
use App\Domain\Documents\Actions\StoreReviewDocumentMetadata;
use App\Domain\Documents\Actions\UpdateReviewDocument;
use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Reviews\PlsReview;
use App\Support\Toast;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class DocumentsPage extends Workspace
{
    use AuthorizesRequests;
    use WithFileUploads;

    private const MAX_UPLOAD_KB = 51200;

    protected string $workspace = 'documents';

    /**
     * @var array<int, TemporaryUploadedFile>
     */
    public array $documentUploads = [];

    public bool $showEditDocumentModal = false;

    public string $documentEditingId = '';

    public string $documentTitle = '';

    public string $documentType = DocumentType::GroupReport->value;

    public string $documentStoragePath = '';

    public string $documentMimeType = '';

    public string $documentFileSize = '';

    public string $documentSummary = '';

    public string $analysisStatus = 'saved';

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
     * @var list<string>
     */
    public array $analysisWarnings = [];

    public function mount(PlsReview $review): void
    {
        parent::mount($review);
    }

    public function render(): View
    {
        $review = $this->loadReview();

        return $this->renderWorkspaceView('livewire.pls.reviews.documents-page', [
            'review' => $review,
            'documentTypes' => DocumentType::reviewWorkspaceCases(),
            'hasProcessingRecords' => $this->hasProcessingRecords($review),
            'recordRows' => $this->recordRows($review),
        ], $review);
    }

    public function updatedDocumentUploads(): void
    {
        $this->authorizeReviewMutation();

        if ($this->documentUploads === []) {
            return;
        }

        $this->validate([
            'documentUploads' => ['array', 'min:1'],
            'documentUploads.*' => ['file', 'mimes:pdf,docx,txt,md', 'max:'.self::MAX_UPLOAD_KB],
        ], [
            'documentUploads.*.max' => __('Choose files that are 50 MB or smaller.'),
            'documentUploads.*.mimes' => __('Choose PDF, DOCX, TXT, or MD files only.'),
        ]);

        $statuses = [];

        foreach ($this->documentUploads as $upload) {
            $storedReview = app(StoreReviewDocumentMetadata::class)->store([
                'pls_review_id' => $this->review->id,
                'title' => $this->documentTitleFromUpload($upload),
                'document_type' => DocumentType::GroupReport->value,
                'storage_path' => null,
                'file' => $upload,
                'mime_type' => null,
                'file_size' => null,
                'summary' => null,
                'metadata' => [
                    'disk' => $this->documentStorageDisk(),
                    'original_name' => $upload->getClientOriginalName(),
                ],
            ]);

            $this->review = $storedReview->fresh();
            $document = $this->review->documents()
                ->where('document_type', '!=', DocumentType::LegislationText->value)
                ->latest('id')
                ->first();

            if (! $document instanceof Document) {
                $this->addError('documentUploads', __('One of the uploaded documents could not be stored.'));

                continue;
            }

            $statuses[] = $this->processDocument($document);
        }

        $this->documentUploads = [];
        $this->resetValidation(['documentUploads', 'documentUploads.*']);
        $this->review = $this->loadReview();

        $this->dispatchWorkspaceToast(match (true) {
            $statuses === [] => Toast::warning(
                __('Upload skipped'),
                __('No documents were uploaded.'),
            ),
            in_array('needs_attention', $statuses, true) => Toast::warning(
                __('Documents uploaded'),
                __('Documents uploaded. Some records need attention.'),
            ),
            in_array('processing', $statuses, true) => Toast::warning(
                __('Documents uploaded'),
                __('Documents uploaded. Processing has started.'),
            ),
            default => Toast::success(
                __('Documents uploaded'),
                __('Documents uploaded and analyzed.'),
            ),
        });
    }

    public function startEditingDocument(int $documentId): void
    {
        $document = $this->documentsQuery()
            ->whereKey($documentId)
            ->first();

        if (! $document instanceof Document) {
            return;
        }

        $this->fillDocumentState($document);
        $this->showEditDocumentModal = true;
    }

    public function saveDocumentEdits(UpdateReviewDocument $action): void
    {
        $this->authorizeReviewMutation();

        try {
            $this->review = $action->update([
                'document_id' => $this->documentEditingId,
                'pls_review_id' => $this->review->id,
                'title' => $this->documentTitle,
                'document_type' => $this->documentType,
                'storage_path' => $this->blankToNull($this->documentStoragePath),
                'file' => null,
                'mime_type' => $this->blankToNull($this->documentMimeType),
                'file_size' => $this->blankToNull($this->documentFileSize) === null ? null : (int) $this->documentFileSize,
                'summary' => $this->blankToNull($this->documentSummary),
                'metadata' => null,
            ])->fresh();
        } catch (ValidationException $exception) {
            $this->mapValidationErrors($exception, [
                'document_id' => 'documentEditingId',
                'title' => 'documentTitle',
                'document_type' => 'documentType',
                'storage_path' => 'documentStoragePath',
                'mime_type' => 'documentMimeType',
                'file_size' => 'documentFileSize',
                'summary' => 'documentSummary',
            ]);

            return;
        }

        $document = $this->documentsQuery()->find($this->documentEditingId);

        if ($document instanceof Document) {
            $this->fillDocumentState($document);
        }

        $this->resetDocumentState();

        $this->dispatchWorkspaceToast(Toast::success(
            __('Document updated'),
            __('Document updated.'),
        ));
    }

    public function retryDocumentAnalysis(int $documentId): void
    {
        $this->authorizeReviewMutation();

        $document = $this->documentsQuery()->find($documentId);

        if (! $document instanceof Document) {
            return;
        }

        $document = $this->resetDocumentAnalysisAttempts($document);
        $status = $this->processDocument($document);
        $this->review = $this->loadReview();

        if ($this->documentEditingId === (string) $documentId) {
            $refreshedDocument = $this->documentsQuery()->find($documentId);

            if ($refreshedDocument instanceof Document) {
                $this->fillDocumentState($refreshedDocument);
            }
        }

        $this->dispatchWorkspaceToast(match ($status) {
            'processing' => Toast::warning(
                __('Retry started'),
                __('Document retry started. Processing continues in the background.'),
            ),
            'saved' => Toast::success(
                __('Analysis completed'),
                __('Document analysis completed successfully.'),
            ),
            default => Toast::warning(
                __('Needs attention'),
                __('Document still needs attention after retry.'),
            ),
        });
    }

    public function refreshPendingAnalyses(): void
    {
        $documents = $this->documentsQuery()
            ->get()
            ->filter(fn (Document $document): bool => $this->documentRecordStatus($document) === 'processing');

        if ($documents->isEmpty()) {
            return;
        }

        foreach ($documents as $document) {
            $this->processDocument($this->incrementExtractionPollAttempts($document));
        }

        $this->review = $this->loadReview();
    }

    public function confirmDeletion(int $documentId): void
    {
        $this->authorizeReviewMutation();

        $this->performDocumentDeletion($documentId);
    }

    public function documentTypeLabel(DocumentType $type): string
    {
        return match ($type) {
            DocumentType::GroupReport => __('Group report'),
            default => str($type->value)->headline()->toString(),
        };
    }

    public function uploadLimitLabel(): string
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

    private function documentsQuery()
    {
        return $this->review->documents()
            ->where('document_type', '!=', DocumentType::LegislationText->value);
    }

    private function processDocument(Document $document): string
    {
        $result = app(AnalyzeReviewDocument::class)->analyze($document);
        $this->persistExtractionState($document, $result);

        $rawText = trim((string) ($result['raw_text'] ?? ''));

        if ($rawText !== '') {
            app(ChunkDocumentText::class)->chunk($document, $rawText);
        }

        return $this->persistDocumentAnalysisState($document->fresh(), $result);
    }

    /**
     * @param  array{
     *     status?: string,
     *     title?: string,
     *     document_type?: string,
     *     summary?: string,
     *     key_themes?: list<string>,
     *     notable_excerpts?: list<string>,
     *     important_dates?: list<string>,
     *     warnings?: list<string>
     * }  $result
     */
    private function persistDocumentAnalysisState(Document $document, array $result): string
    {
        $metadata = $document->metadata ?? [];

        $status = match ($result['status'] ?? 'completed') {
            'processing' => 'processing',
            'failed' => 'needs_attention',
            default => 'saved',
        };

        data_set($metadata, 'document_analysis', array_filter([
            'analysis_driver' => 'review_document_extractor_v1',
            'status' => $status,
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

    /**
     * @param  array<string, mixed>  $storedAnalysis
     */
    private function fillAnalysisState(array $storedAnalysis): void
    {
        $this->analysisStatus = (string) ($storedAnalysis['status'] ?? 'saved');
        $this->analysisKeyThemes = collect($storedAnalysis['key_themes'] ?? [])
            ->filter(fn (mixed $item): bool => is_string($item) && trim($item) !== '')
            ->values()
            ->all();
        $this->analysisNotableExcerpts = collect($storedAnalysis['notable_excerpts'] ?? [])
            ->filter(fn (mixed $item): bool => is_string($item) && trim($item) !== '')
            ->values()
            ->all();
        $this->analysisImportantDates = collect($storedAnalysis['important_dates'] ?? [])
            ->filter(fn (mixed $item): bool => is_string($item) && trim($item) !== '')
            ->values()
            ->all();
        $this->analysisWarnings = collect($storedAnalysis['warnings'] ?? [])
            ->filter(fn (mixed $item): bool => is_string($item) && trim($item) !== '')
            ->values()
            ->all();
    }

    private function fillDocumentState(Document $document): void
    {
        $this->authorizeReviewMutation();

        $this->documentEditingId = (string) $document->id;
        $this->documentTitle = $document->title;
        $this->documentType = $document->document_type->value;
        $this->documentStoragePath = $document->storage_path;
        $this->documentMimeType = $document->mime_type ?? '';
        $this->documentFileSize = $document->file_size === null ? '' : (string) $document->file_size;
        $this->documentSummary = $document->summary ?? '';

        $this->fillAnalysisState($this->storedAnalysisForDocument($document));

        $this->resetValidation([
            'documentEditingId',
            'documentTitle',
            'documentType',
            'documentSummary',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function storedAnalysisForDocument(Document $document): array
    {
        $analysis = data_get($document->metadata, 'document_analysis', []);

        return is_array($analysis) ? $analysis : [];
    }

    /**
     * @param  array{
     *     extraction_method?: string|null,
     *     extraction_driver?: string|null,
     *     extraction_metadata?: array<string, mixed>,
     *     status?: string,
     *     warnings?: list<string>
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

    private function resetDocumentAnalysisAttempts(Document $document): Document
    {
        $metadata = $document->metadata ?? [];

        data_set($metadata, 'extraction.poll_attempts', 0);

        $document->forceFill([
            'metadata' => $metadata,
        ])->save();

        return $document->fresh();
    }

    private function documentRecordStatus(Document $document): string
    {
        $storedStatus = data_get($document->metadata, 'document_analysis.status');

        if (in_array($storedStatus, ['processing', 'saved', 'needs_attention'], true)) {
            return (string) $storedStatus;
        }

        return match (data_get($document->metadata, 'extraction.status')) {
            'processing' => 'processing',
            'failed' => 'needs_attention',
            default => 'saved',
        };
    }

    private function loadReview(): PlsReview
    {
        return PlsReview::query()
            ->with([
                'documents' => fn ($query) => $query
                    ->where('document_type', '!=', DocumentType::LegislationText->value)
                    ->latest('updated_at')
                    ->latest('id'),
            ])
            ->findOrFail($this->review->getKey());
    }

    private function hasProcessingRecords(PlsReview $review): bool
    {
        return $review->documents->contains(
            fn (Document $document): bool => $this->documentRecordStatus($document) === 'processing',
        );
    }

    /**
     * @return list<array{
     *     id: int,
     *     title: string,
     *     summary: string,
     *     document_type: string,
     *     status: string,
     *     status_label: string,
     *     status_color: string,
     *     updated_at: string
     * }>
     */
    private function recordRows(PlsReview $review): array
    {
        return $review->documents
            ->sortByDesc(fn (Document $document): int => $document->updated_at?->timestamp ?? $document->created_at?->timestamp ?? 0)
            ->map(function (Document $document): array {
                $storedAnalysis = $this->storedAnalysisForDocument($document);
                $status = $this->documentRecordStatus($document);

                return [
                    'id' => $document->id,
                    'title' => (string) ($storedAnalysis['title'] ?? $document->title),
                    'summary' => (string) ($storedAnalysis['summary'] ?? $document->summary ?? ''),
                    'document_type' => Str::headline((string) ($storedAnalysis['document_type'] ?? $document->document_type->value)),
                    'status' => $status,
                    'status_label' => $this->statusLabel($status),
                    'status_color' => $this->statusColor($status),
                    'updated_at' => $document->updated_at?->toFormattedDateString() ?? $document->created_at?->toFormattedDateString() ?? '—',
                ];
            })
            ->values()
            ->all();
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'processing' => __('Processing'),
            'needs_attention' => __('Needs attention'),
            default => __('Saved'),
        };
    }

    private function statusColor(string $status): string
    {
        return match ($status) {
            'processing' => 'sky',
            'needs_attention' => 'rose',
            default => 'emerald',
        };
    }

    private function performDocumentDeletion(int $documentId): void
    {
        $document = $this->documentsQuery()
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

        if ($this->documentEditingId === (string) $documentId) {
            $this->resetDocumentState();
        }

        $this->dispatchWorkspaceToast(Toast::success(
            __('Document removed'),
            __('Document removed from the review.'),
        ));
    }

    private function resetDocumentState(): void
    {
        $this->reset([
            'documentEditingId',
            'documentTitle',
            'documentUploads',
            'documentStoragePath',
            'documentMimeType',
            'documentFileSize',
            'documentSummary',
            'analysisStatus',
            'analysisKeyThemes',
            'analysisNotableExcerpts',
            'analysisImportantDates',
            'analysisWarnings',
        ]);

        $this->showEditDocumentModal = false;
        $this->documentType = DocumentType::GroupReport->value;
    }

    private function documentTitleFromUpload(TemporaryUploadedFile $upload): string
    {
        $baseName = pathinfo($upload->getClientOriginalName(), PATHINFO_FILENAME);
        $baseName = preg_replace('/[_-][a-f0-9]{8,}$/i', '', $baseName) ?? $baseName;

        return Str::of($baseName)
            ->replace(['_', '-'], ' ')
            ->headline()
            ->trim()
            ->toString();
    }

    private function documentStorageDisk(): string
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
}
