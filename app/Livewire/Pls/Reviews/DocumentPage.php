<?php

namespace App\Livewire\Pls\Reviews;

use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Reviews\PlsReview;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Livewire\WithPagination;

class DocumentPage extends Workspace
{
    use WithPagination;

    public Document $document;

    protected string $workspace = 'documents';

    public function mount(PlsReview $review, ?Document $document = null): void
    {
        parent::mount($review);

        abort_unless($document && (int) $document->pls_review_id === (int) $review->id, 404);
        $this->document = $document;
    }

    public function render(): View
    {
        $this->authorize('view', $this->review);
        $document = $this->review->documents()->findOrFail($this->document->id);
        $isLegislation = $document->document_type === DocumentType::LegislationText;
        $this->workspace = $isLegislation ? 'legislation' : 'documents';
        $analysis = data_get($document->metadata, $isLegislation ? 'legislation_analysis' : 'document_analysis', []);

        return $this->renderWorkspaceView('livewire.pls.reviews.document-page', [
            'document' => $document,
            'analysis' => $analysis,
            'isLegislation' => $isLegislation,
            'originalAvailable' => filled($document->storage_path) && Storage::disk((string) data_get($document->metadata, 'disk', config('filesystems.default')))->exists($document->storage_path),
            'textChunks' => $document->chunks()->paginate(15),
            'linkedLegislation' => $document->sourcedLegislation()->get(),
        ], $this->review->loadMissing('steps'));
    }
}
