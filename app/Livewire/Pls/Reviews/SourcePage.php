<?php

namespace App\Livewire\Pls\Reviews;

use App\Domain\Analysis\Actions\LinkFindingSourcePassage;
use App\Domain\Documents\Actions\QueueReviewSourceProcessing;
use App\Domain\Documents\Document;
use App\Domain\Legislation\Enums\LegislationType;
use App\Domain\Reviews\PlsReview;
use App\Support\Documents\ReviewUpload;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\WithPagination;
use Throwable;

class SourcePage extends Workspace
{
    use WithPagination;

    protected string $workspace = 'documents';

    #[Locked]
    public int $sourceId;

    public string $findingId = '';

    public string $passage = '';

    public string $relationship = 'supports';

    public string $note = '';

    public string $classification = 'unclassified';

    public $sourceUpload = null;

    public function mount(PlsReview $review, ?Document $document = null): void
    {
        parent::mount($review);
        abort_unless($document !== null && (int) $document->pls_review_id === (int) $review->id, 404);
        $this->sourceId = $document->id;
        $this->classification = (string) data_get($document->metadata, 'source_review.classification', data_get($document->metadata, 'legislation_analysis.legislation_type', 'unclassified'));
    }

    public function attachOriginal(QueueReviewSourceProcessing $queue): void
    {
        $this->authorize('update', $this->review);
        $this->validate(['sourceUpload' => ['required', 'file', 'mimes:pdf,docx,txt,md', 'max:51200']]);
        $disk = (string) config('filesystems.default');
        $uploadMetadata = [
            'disk' => $disk,
            'original_name' => $this->sourceUpload->getClientOriginalName(),
            'content_sha256' => ReviewUpload::sha256($this->sourceUpload),
        ];
        $mimeType = $this->sourceUpload->getMimeType();
        $fileSize = $this->sourceUpload->getSize();
        try {
            $path = ReviewUpload::copyTo($this->sourceUpload, $disk, 'pls/reviews/'.$this->review->id.'/documents');
        } catch (\RuntimeException $exception) {
            report($exception);
            $this->addError('sourceUpload', __('The original could not be stored. Please retry.'));

            return;
        }
        try {
            $document = DB::transaction(function () use ($uploadMetadata, $path, $mimeType, $fileSize): Document {
                $document = $this->review->documents()->lockForUpdate()->findOrFail($this->sourceId);
                abort_if(filled($document->storage_path), 409, __('This source already has an original file.'));
                $document->update([
                    'storage_path' => $path,
                    'mime_type' => $mimeType,
                    'file_size' => $fileSize,
                    'metadata' => array_merge($document->metadata ?? [], $uploadMetadata),
                ]);

                return $document;
            });
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($path);
            throw $exception;
        }
        $queue->queue($document);
        $this->sourceUpload = null;
    }

    public function retryProcessing(QueueReviewSourceProcessing $queue): void
    {
        $this->authorize('update', $this->review);
        $document = $this->review->documents()->findOrFail($this->sourceId);
        abort_if(blank($document->storage_path), 422, __('Attach an original file first.'));
        $started = data_get($document->metadata, 'extraction.queued_at');
        if (in_array(data_get($document->metadata, 'extraction.status'), ['queued', 'processing'], true) && $started && Carbon::parse($started)->gt(now()->subMinutes(10))) {
            $this->addError('processing', __('This source is still queued or processing. Check again shortly.'));

            return;
        }
        $queue->queue($document, retry: true);
    }

    public function markChecked(): void
    {
        $this->authorize('update', $this->review);
        DB::transaction(function (): void {
            $document = $this->review->documents()->lockForUpdate()->findOrFail($this->sourceId);
            $metadata = $document->metadata ?? [];
            $hash = hash('sha256', $document->extractedText());
            data_set($metadata, 'source_review.checked_text_hash', $hash);
            data_set($metadata, 'extraction.text_sha256', $hash);
            data_set($metadata, 'source_review.checked_by', auth()->id());
            data_set($metadata, 'source_review.checked_at', now()->toIso8601String());
            $document->update(['metadata' => $metadata]);
        });
    }

    public function setExcluded(bool $excluded): void
    {
        $this->authorize('update', $this->review);
        DB::transaction(function () use ($excluded): void {
            $document = $this->review->documents()->lockForUpdate()->findOrFail($this->sourceId);
            $metadata = $document->metadata ?? [];
            data_set($metadata, 'source_review.excluded', $excluded);
            $document->update(['metadata' => $metadata]);
        });
    }

    public function saveClassification(): void
    {
        $this->authorize('update', $this->review);
        $this->validate(['classification' => ['required', Rule::enum(LegislationType::class)]]);
        DB::transaction(function (): void {
            $document = $this->review->documents()->lockForUpdate()->findOrFail($this->sourceId);
            $metadata = $document->metadata ?? [];
            data_set($metadata, 'source_review.classification', $this->classification);
            data_set($metadata, 'source_review.classified_by', auth()->id());
            data_set($metadata, 'source_review.classified_at', now()->toIso8601String());
            $document->update(['metadata' => $metadata]);
            if ($this->classification === LegislationType::ParliamentaryDebate->value) {
                foreach ($this->review->legislation()->where('source_document_id', $document->id)->get()->modelKeys() as $legislationId) {
                    $this->review->legislation()->updateExistingPivot($legislationId, ['relationship_type' => 'related']);
                }
            }
        });
        $this->dispatch('review-workspace-updated');
    }

    public function linkPassage(LinkFindingSourcePassage $action): void
    {
        $this->authorize('update', $this->review);
        $document = $this->review->documents()->findOrFail($this->sourceId);
        try {
            $action->link($this->review, $document, [
                'finding_id' => $this->findingId,
                'passage' => $this->passage,
                'relationship' => $this->relationship,
                'note' => $this->note,
            ]);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $this->addError($field === 'finding_id' ? 'findingId' : $field, $messages[0]);
            }

            return;
        }
        $this->reset('passage', 'note');
        $this->resetValidation();
    }

    public function render(): View
    {
        $this->authorize('view', $this->review);
        $document = $this->review->documents()->with(['submissions.stakeholder', 'consultationMaterials.consultation'])->findOrFail($this->sourceId);
        $citations = $document->findingCitations()->whereHas('finding', fn ($query) => $query->where('pls_review_id', $this->review->id))->with(['finding', 'document.chunks'])->latest()->get();

        return $this->renderWorkspaceView('livewire.pls.reviews.source-page', [
            'document' => $document,
            'chunks' => $document->chunks()->paginate(10),
            'findings' => $this->review->findings()->orderBy('title')->get(),
            'citations' => $citations,
            'sourceTypes' => LegislationType::cases(),
            'warnings' => (array) data_get($document->metadata, 'legislation_analysis.warnings', data_get($document->metadata, 'document_analysis.warnings', [])),
        ], $this->review->loadMissing('steps'));
    }
}
