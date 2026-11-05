<?php

namespace App\Livewire\Pls\Reviews;

use App\Domain\Documents\Actions\PersistReviewDocumentState;
use App\Domain\Documents\Actions\StoreReviewDocumentMetadata;
use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Legislation\Actions\PersistLegislationSourceState;
use App\Domain\Reviews\PlsReview;
use App\Jobs\ProcessReviewDocument;
use App\Jobs\ProcessReviewLegislationSource;
use App\Support\Documents\RecordSpreadsheet;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

class RecordIntake extends Component
{
    use AuthorizesRequests, WithFileUploads;

    #[Locked]
    public PlsReview $review;

    public array $files = [];

    public $spreadsheet = null;

    public string $url = '';

    public string $title = '';

    public string $type = 'group_report';

    public bool $allowDuplicates = false;

    #[Locked]
    public array $preview = [];

    public string $notice = '';

    public function mount(PlsReview $review): void
    {
        $this->authorize('view', $review);
        $this->review = $review;
    }

    public function updated($property): void
    {
        if ($property !== 'allowDuplicates') {
            $this->preview = [];
        }
    }

    public function previewImport(): void
    {
        $this->authorize('update', $this->review);
        $this->notice = '';
        $this->validate([
            'files' => ['array', 'max:20'], 'files.*' => ['file', 'mimes:pdf,docx,txt,md', 'max:51200'],
            'spreadsheet' => ['nullable', 'file', 'mimes:csv,txt,xlsx', 'max:2048'],
            'url' => ['nullable', 'url:http,https', 'max:2048'], 'title' => ['string', 'max:255'],
            'type' => [Rule::enum(DocumentType::class)],
        ]);
        $rows = $this->spreadsheet ? app(RecordSpreadsheet::class)->read($this->spreadsheet->getRealPath(), strtolower($this->spreadsheet->getClientOriginalExtension())) : [];
        if (! $this->spreadsheet) {
            foreach ($this->files as $file) {
                $rows[] = ['title' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME), 'filename' => $file->getClientOriginalName(), 'url' => '', 'type' => $this->type];
            }
            if ($this->url !== '') {
                $rows[] = ['title' => $this->title, 'filename' => '', 'url' => $this->url, 'type' => $this->type];
            }
        }
        if ($rows === []) {
            $this->addError('files', __('Choose files, enter a source link, or upload a spreadsheet first.'));

            return;
        }
        $names = array_map(fn ($file) => $file->getClientOriginalName(), $this->files);
        $existing = $this->review->documents()->get();
        $seen = [];
        $this->preview = [];
        foreach ($rows as $index => $row) {
            $row = array_merge(array_fill_keys(RecordSpreadsheet::COLUMNS, ''), $row);
            $row['type'] = $row['type'] ?: $this->type;
            $validation = Validator::make($row, [
                'title' => ['required', 'string', 'max:255'], 'filename' => ['nullable', 'string', 'max:255'],
                'url' => ['nullable', 'url:http,https', 'max:2048'], 'type' => [Rule::enum(DocumentType::class)],
                'date' => ['nullable', 'date_format:Y-m-d'], 'organisation' => ['nullable', 'string', 'max:255'],
                'stakeholder' => ['nullable', 'string', 'max:255'], 'notes' => ['nullable', 'string', 'max:5000'],
            ]);
            $errors = $validation->errors()->all();
            $fileIndex = $row['filename'] !== '' ? array_search($row['filename'], $names, true) : false;
            if ($row['filename'] !== '' && ($fileIndex === false || count(array_keys($names, $row['filename'])) > 1)) {
                $errors[] = 'Choose exactly one file matching this filename.';
            }
            if ($fileIndex === false && $row['url'] === '') {
                $errors[] = 'Provide a matching file or an original source URL.';
            }
            $key = mb_strtolower($row['filename'] ?: $row['url']);
            $duplicate = isset($seen[$key]) || $existing->contains(fn ($doc) => mb_strtolower($doc->title) === mb_strtolower($row['title']) || ($row['url'] && data_get($doc->metadata, 'source_url') === $row['url']) || ($row['filename'] && data_get($doc->metadata, 'original_name') === $row['filename']));
            $seen[$key] = true;
            $this->preview[] = ['row' => $index + 2, 'data' => $row, 'file_index' => $fileIndex === false ? null : $fileIndex, 'errors' => $errors, 'duplicate' => $duplicate];
        }
        $unmatched = array_diff($names, array_column($rows, 'filename'));
        if ($unmatched) {
            $this->addError('files', 'Files not listed in the spreadsheet will not be imported: '.implode(', ', $unmatched));
        }
    }

    public function import(): void
    {
        $this->authorize('update', $this->review);
        if ($this->preview === []) {
            return;
        }
        // Revalidate temporary files and matching immediately before writing.
        $this->previewImport();
        if (collect($this->preview)->contains(fn ($row) => $row['errors'] !== [] || ($row['duplicate'] && ! $this->allowDuplicates))) {
            $this->addError('files', __('Correct the row errors and confirm any intentional duplicates before importing.'));

            return;
        }
        $documents = DB::transaction(function () {
            return collect($this->preview)->map(function ($item) {
                $row = $item['data'];
                $metadata = ['source_url' => $row['url'], 'source_date' => $row['date'], 'organisation' => $row['organisation'], 'stakeholder' => $row['stakeholder'], 'notes' => $row['notes']];
                if ($row['type'] === DocumentType::ReportingTemplate->value) {
                    $metadata['purpose'] = 'report_template';
                }
                if ($item['file_index'] !== null) {
                    $document = app(StoreReviewDocumentMetadata::class)->storeDocument([
                        'pls_review_id' => $this->review->id, 'title' => $row['title'], 'document_type' => $row['type'],
                        'file' => $this->files[$item['file_index']], 'storage_path' => null, 'metadata' => $metadata,
                    ]);
                } else {
                    // A link is a reference, never a claim that the remote document has been read.
                    $document = $this->review->documents()->create(['title' => $row['title'], 'document_type' => $row['type'], 'metadata' => $metadata]);
                }
                if ($row['stakeholder'] !== '' && $row['type'] === DocumentType::ConsultationSubmission->value) {
                    $stakeholder = $this->review->stakeholders()->firstOrCreate(['name' => $row['stakeholder']], ['stakeholder_type' => 'Unclassified']);
                    $this->review->submissions()->create(['document_id' => $document->id, 'stakeholder_id' => $stakeholder->id, 'submitted_at' => $row['date'] ?: null, 'summary' => $row['notes'] ?: null]);
                }

                return $document;
            });
        });
        foreach ($documents as $document) {
            if (! $document->storage_path) {
                continue;
            }
            if ($document->document_type === DocumentType::LegislationText) {
                app(PersistLegislationSourceState::class)->markQueued($document);
                ProcessReviewLegislationSource::dispatch($document->id);
            } else {
                app(PersistReviewDocumentState::class)->markQueued($document);
                ProcessReviewDocument::dispatch($document->id);
            }
        }
        $this->notice = __(':count records added. Processing status is shown in the register below. Source links need full-text uploads before their contents can be reviewed.', ['count' => $documents->count()]);
        $this->reset('files', 'spreadsheet', 'preview', 'url', 'title', 'allowDuplicates');
        $this->resetValidation();
        $this->dispatch('review-workspace-updated');
    }

    public function render()
    {
        $this->authorize('view', $this->review);

        return view('livewire.pls.reviews.record-intake');
    }
}
