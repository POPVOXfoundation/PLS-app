<?php

namespace App\Livewire\Pls\Reviews;

use App\Domain\Documents\Enums\DocumentType;
use Illuminate\Contracts\View\View;
use Livewire\WithPagination;

class SourceRegisterPage extends Workspace
{
    use WithPagination;

    protected string $workspace = 'documents';

    public string $search = '';

    public string $type = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $this->authorize('view', $this->review);
        $documents = $this->review->documents()->withCount('chunks')->with(['submissions.stakeholder', 'consultationMaterials.consultation'])
            ->when(trim($this->search) !== '', fn ($query) => $query->where('title', 'like', '%'.trim($this->search).'%'))
            ->when($this->type !== '', fn ($query) => $query->where('document_type', $this->type))->latest('id')->paginate(25);

        return $this->renderWorkspaceView('livewire.pls.reviews.source-register-page', [
            'documents' => $documents, 'types' => DocumentType::cases(),
        ], $this->review->loadMissing('steps'));
    }
}
