<?php

namespace App\Livewire\Pls\Reviews;

use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Reviews\PlsReview;
use App\Support\PlsAssistant\SourceIdentity;
use App\Support\Toast;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

class DocumentPage extends Workspace
{
    use WithPagination;

    public Document $document;

    #[Url]
    public string $search = '';

    public string $citationFindingId = '';

    public string $citationPassage = '';

    public string $citationRelationship = 'supports';

    public string $citationNote = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function markReviewed(): void
    {
        $this->authorize('update', $this->review);
        $document = $this->review->documents()->findOrFail($this->document->id);
        $metadata = $document->metadata ?? [];
        $metadata['human_review'] = ['reviewed_at' => now()->toIso8601String(), 'reviewed_by' => auth()->id()];
        if ($document->isReportingTemplate()) {
            data_set($metadata, 'document_analysis.document_type', DocumentType::ReportingTemplate->value);
            $document->document_type = DocumentType::ReportingTemplate;
        }
        $document->fill(['metadata' => $metadata])->save();
    }

    public function saveCitation(): void
    {
        $this->authorize('update', $this->review);
        $this->validate([
            'citationFindingId' => ['required', 'integer'],
            'citationPassage' => ['required', 'string', 'min:15', 'max:2000'],
            'citationRelationship' => ['required', 'in:supports,contradicts,context'],
            'citationNote' => ['nullable', 'string', 'max:3000'],
        ]);
        $finding = $this->review->findings()->findOrFail($this->citationFindingId);
        $document = $this->review->documents()->with('chunks')->findOrFail($this->document->id);
        $passage = Str::squish($this->citationPassage);
        if (! str_contains(Str::squish($document->extractedText()), $passage)) {
            $this->addError('citationPassage', __('Copy a passage from the extracted source text. The quoted text could not be found in this document.'));

            return;
        }
        $finding->citations()->updateOrCreate([
            'document_id' => $document->id,
            'passage' => $passage,
            'relationship' => $this->citationRelationship,
        ], [
            'note' => trim($this->citationNote),
            'finding_fingerprint' => $finding->supportFingerprint(),
            'reviewed_by' => auth()->id(),
        ]);
        $this->reset('citationPassage', 'citationNote');
        $this->dispatchAppToast(Toast::success(__('Passage linked'), __('The finding now links to this reviewed passage and original document.')));
    }

    public function correctSourceIdentity(): void
    {
        $this->authorize('update', $this->review);
        $document = $this->review->documents()->with('chunks', 'sourcedLegislation')->findOrFail($this->document->id);
        $analysis = (array) data_get($document->metadata, 'legislation_analysis', []);
        $analysis['title'] = $analysis['title'] ?? $document->title;
        $analysis['source_label'] = data_get($document->metadata, 'original_name', $document->title);
        $kind = SourceIdentity::kind($document->extractedText());
        if ($kind === null) {
            return;
        }
        $corrected = SourceIdentity::guardLegislation($analysis, $document->extractedText());
        DB::transaction(function () use ($document, $analysis, $corrected): void {
            $metadata = $document->metadata ?? [];
            $metadata['source_identity_before_correction'] ??= $analysis;
            $metadata['legislation_analysis'] = $corrected;
            $metadata['source_identity_confirmed_at'] = now()->toIso8601String();
            $document->update(['metadata' => $metadata]);
            foreach ($document->sourcedLegislation as $law) {
                $law->update(collect($corrected)->only(['title', 'short_title', 'legislation_type', 'date_enacted'])->map(fn ($value) => $value === '' ? null : $value)->all());
                if (($corrected['relationship_type'] ?? '') === 'related') {
                    $this->review->legislation()->updateExistingPivot($law->id, ['relationship_type' => 'related']);
                }
            }
        });
    }

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
            'textChunks' => $document->chunks()->when(trim($this->search) !== '', function ($query) {
                $query->whereRaw('LOWER(content) LIKE ?', ['%'.mb_strtolower(trim($this->search)).'%']);
            })->paginate(15),
            'chunkCount' => $document->chunks()->count(),
            'findings' => $this->review->findings()->orderBy('title')->get(),
            'sourceKind' => $isLegislation ? SourceIdentity::kind($document->extractedText()) : null,
            'linkedLegislation' => $document->sourcedLegislation()->get(),
        ], $this->review->loadMissing('steps'));
    }
}
