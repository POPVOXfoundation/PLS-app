<?php

namespace App\Livewire\Pls\Reviews;

use App\Domain\Documents\Actions\StoreReviewDocumentMetadata;
use App\Domain\Documents\Actions\UpdateReviewDocument;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Reviews\PlsReview;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class DocumentsPage extends Workspace
{
    use AuthorizesRequests;
    use WithFileUploads;

    protected string $workspace = 'documents';

    public string $documentTitle = '';

    public string $documentType = DocumentType::GroupReport->value;

    public string $documentEditingId = '';

    public string $documentStoragePath = '';

    public string $documentMimeType = 'application/pdf';

    public string $documentFileSize = '';

    public string $documentSummary = '';

    public ?TemporaryUploadedFile $documentUpload = null;

    public function mount(PlsReview $review): void
    {
        parent::mount($review);
    }

    public function render(): View
    {
        $review = $this->loadReview();

        return $this->renderWorkspaceView('livewire.pls.reviews.documents-page', [
            'review' => $review,
            'documentTypes' => DocumentType::cases(),
        ], $review);
    }

    public function storeDocument(StoreReviewDocumentMetadata $action): void
    {
        $this->authorizeReviewMutation();

        try {
            $this->review = $action->store([
                'pls_review_id' => $this->review->id,
                'title' => $this->documentTitle,
                'document_type' => $this->documentType,
                'storage_path' => $this->blankToNull($this->documentStoragePath),
                'file' => $this->documentUpload,
                'mime_type' => $this->blankToNull($this->documentMimeType),
                'file_size' => $this->blankToNull($this->documentFileSize) === null ? null : (int) $this->documentFileSize,
                'summary' => $this->blankToNull($this->documentSummary),
                'metadata' => null,
            ])->fresh();
        } catch (ValidationException $exception) {
            $this->mapValidationErrors($exception, [
                'title' => 'documentTitle',
                'document_type' => 'documentType',
                'storage_path' => 'documentStoragePath',
                'file' => 'documentUpload',
                'mime_type' => 'documentMimeType',
                'file_size' => 'documentFileSize',
                'summary' => 'documentSummary',
            ]);

            return;
        }

        $this->resetDocumentForm();

        $this->dispatch('review-workspace-updated', status: __('Document added to the review.'));
    }

    public function startEditingDocument(int $documentId): void
    {
        $this->authorizeReviewMutation();

        $document = $this->review->documents()
            ->whereKey($documentId)
            ->first();

        if ($document === null) {
            return;
        }

        $this->documentEditingId = (string) $document->id;
        $this->documentTitle = $document->title;
        $this->documentType = $document->document_type->value;
        $this->documentStoragePath = $document->storage_path;
        $this->documentMimeType = $document->mime_type ?? '';
        $this->documentFileSize = $document->file_size === null ? '' : (string) $document->file_size;
        $this->documentSummary = $document->summary ?? '';
        $this->documentUpload = null;

        $this->resetValidation([
            'documentEditingId',
            'documentTitle',
            'documentType',
            'documentStoragePath',
            'documentUpload',
            'documentMimeType',
            'documentFileSize',
            'documentSummary',
        ]);
    }

    public function updateDocument(UpdateReviewDocument $action): void
    {
        $this->authorizeReviewMutation();

        try {
            $this->review = $action->update([
                'document_id' => $this->documentEditingId,
                'pls_review_id' => $this->review->id,
                'title' => $this->documentTitle,
                'document_type' => $this->documentType,
                'storage_path' => $this->blankToNull($this->documentStoragePath),
                'file' => $this->documentUpload,
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
                'file' => 'documentUpload',
                'mime_type' => 'documentMimeType',
                'file_size' => 'documentFileSize',
                'summary' => 'documentSummary',
            ]);

            return;
        }

        $this->resetDocumentForm();

        $this->dispatch('review-workspace-updated', status: __('Document updated.'));
    }

    public function confirmDeletion(int $id): void
    {
        $this->authorizeReviewMutation();

        $this->performDocumentDeletion($id);
    }

    public function documentTypeLabel(DocumentType $type): string
    {
        return match ($type) {
            DocumentType::GroupReport => __('Group report'),
            default => str($type->value)->headline()->toString(),
        };
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

    private function loadReview(): PlsReview
    {
        return PlsReview::query()
            ->with([
                'documents',
            ])
            ->findOrFail($this->review->getKey());
    }

    private function performDocumentDeletion(int $documentId): void
    {
        $document = $this->review->documents()
            ->whereKey($documentId)
            ->first();

        if ($document === null) {
            return;
        }

        if ($document->storage_path !== '') {
            Storage::disk(config('filesystems.default'))->delete($document->storage_path);
        }

        $document->delete();
        $this->review = $this->loadReview();

        if ($this->documentEditingId === (string) $documentId) {
            $this->resetDocumentForm();
        }

        $this->dispatch('review-workspace-updated', status: __('Document removed from the review.'));
    }

    private function resetDocumentForm(): void
    {
        $this->reset([
            'documentEditingId',
            'documentTitle',
            'documentStoragePath',
            'documentFileSize',
            'documentSummary',
            'documentUpload',
        ]);

        $this->documentType = DocumentType::GroupReport->value;
        $this->documentMimeType = 'application/pdf';

        $this->resetValidation([
            'documentTitle',
            'documentType',
            'documentStoragePath',
            'documentUpload',
            'documentMimeType',
            'documentFileSize',
            'documentSummary',
        ]);
    }
}
