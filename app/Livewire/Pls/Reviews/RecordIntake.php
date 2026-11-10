<?php

namespace App\Livewire\Pls\Reviews;

use App\Domain\Documents\Actions\QueueReviewSourceProcessing;
use App\Domain\Documents\Actions\StoreReviewDocumentMetadata;
use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Reviews\PlsReview;
use App\Support\Documents\ReviewRecordSpreadsheet;
use App\Support\Documents\ReviewUpload;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

class RecordIntake extends Component
{
    use AuthorizesRequests;
    use WithFileUploads;

    #[Locked]
    public PlsReview $review;

    public array $files = [];

    public $spreadsheet = null;

    public string $title = '';

    public string $url = '';

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

    public function updated(string $property): void
    {
        if ($property !== 'allowDuplicates') {
            $this->preview = [];
        }
    }

    public function previewImport(): void
    {
        $this->authorize('update', $this->review);
        $this->resetValidation();
        $this->validate([
            'files' => ['array', 'max:20'], 'files.*' => ['file', 'mimes:pdf,docx,txt,md', 'max:51200'],
            'spreadsheet' => ['nullable', 'file', 'mimes:csv,txt,xlsx', 'max:2048'],
            'title' => ['string', 'max:255'], 'url' => ['nullable', 'url:http,https', 'max:2048'], 'type' => [Rule::enum(DocumentType::class)],
        ]);
        $rows = $this->spreadsheet ? app(ReviewRecordSpreadsheet::class)->readUpload($this->spreadsheet) : [];
        $names = array_map(fn ($file): string => $file->getClientOriginalName(), $this->files);
        if ($this->spreadsheet === null) {
            foreach ($names as $name) {
                $rows[] = ['title' => pathinfo($name, PATHINFO_FILENAME), 'filename' => $name, 'type' => $this->type];
            }
            if ($this->url !== '') {
                $rows[] = ['title' => $this->title, 'url' => $this->url, 'type' => $this->type];
            }
        }
        $this->preview = [];
        if ($rows === []) {
            $this->addError('files', __('Choose files, a source link, or a spreadsheet.'));

            return;
        }
        $seenHashes = [];
        $seenUrls = [];
        $existing = $this->review->documents()->get();
        foreach ($rows as $index => $row) {
            $row = array_merge(array_fill_keys(ReviewRecordSpreadsheet::COLUMNS, ''), $row);
            $row['type'] = $row['type'] ?: $this->type;
            $errors = Validator::make($row, [
                'title' => ['required', 'string', 'max:255'], 'filename' => ['nullable', 'string', 'max:255'],
                'url' => ['nullable', 'url:http,https', 'max:2048'], 'type' => [Rule::enum(DocumentType::class)],
                'date' => ['nullable', 'date_format:Y-m-d'], 'organisation' => ['nullable', 'string', 'max:255'],
                'stakeholder' => ['nullable', 'string', 'max:255'], 'notes' => ['nullable', 'string', 'max:5000'],
            ])->errors()->all();
            $matches = array_keys($names, $row['filename'], true);
            $fileIndex = count($matches) === 1 ? $matches[0] : null;
            if ($row['filename'] !== '' && $fileIndex === null) {
                $errors[] = __('Choose exactly one file matching the filename.');
            }
            if ($fileIndex === null && $row['url'] === '') {
                $errors[] = __('Provide a matching file or source URL.');
            }
            $hash = $fileIndex === null ? '' : ReviewUpload::sha256($this->files[$fileIndex]);
            $duplicate = isset($seenHashes[$hash]) || isset($seenUrls[$row['url']])
                || $existing->contains(fn (Document $document): bool => ($hash !== '' && data_get($document->metadata, 'content_sha256') === $hash) || ($row['url'] !== '' && data_get($document->metadata, 'source_url') === $row['url']));
            if ($hash !== '') {
                $seenHashes[$hash] = true;
            }
            if ($row['url'] !== '') {
                $seenUrls[$row['url']] = true;
            }
            $this->preview[] = ['row' => $index + 1, 'data' => $row, 'file_index' => $fileIndex, 'hash' => $hash, 'duplicate' => $duplicate, 'errors' => $errors];
        }
        if ($this->spreadsheet && array_diff($names, array_column($rows, 'filename'))) {
            $this->addError('files', __('Every uploaded file must match a spreadsheet row.'));
        }
    }

    public function import(): void
    {
        $this->authorize('update', $this->review);
        if ($this->preview === []) {
            return;
        }
        $stored = [];
        try {
            DB::transaction(function () use (&$stored): void {
                $this->review->newQuery()->whereKey($this->review->id)->lockForUpdate()->firstOrFail();
                $this->previewImport();
                if ($this->getErrorBag()->isNotEmpty() || collect($this->preview)->contains(fn (array $row): bool => $row['errors'] !== [] || ($row['duplicate'] && ! $this->allowDuplicates))) {
                    $this->addError('files', __('Correct the row errors and explicitly confirm any intentional duplicates.'));

                    return;
                }
                foreach ($this->preview as $item) {
                    $row = $item['data'];
                    $metadata = ['source_url' => $row['url'], 'source_date' => $row['date'], 'organisation' => $row['organisation'], 'notes' => $row['notes'], 'content_sha256' => $item['hash'], 'added_by' => auth()->id()];
                    if ($row['type'] === DocumentType::ReportingTemplate->value) {
                        $metadata['purpose'] = 'report_template';
                    }
                    $document = $item['file_index'] === null
                            ? $this->review->documents()->create(['title' => $row['title'], 'document_type' => $row['type'], 'metadata' => $metadata])
                            : app(StoreReviewDocumentMetadata::class)->storeDocument(['pls_review_id' => $this->review->id, 'title' => $row['title'], 'document_type' => $row['type'], 'file' => $this->files[$item['file_index']], 'storage_path' => null, 'metadata' => $metadata], preserveUpload: true);
                    $stored[] = $document;
                    if ($row['stakeholder'] !== '' && $row['type'] === DocumentType::ConsultationSubmission->value) {
                        $stakeholder = $this->review->stakeholders()->firstOrCreate(['name' => $row['stakeholder']], ['stakeholder_type' => 'Unclassified']);
                        $this->review->submissions()->create(['document_id' => $document->id, 'stakeholder_id' => $stakeholder->id, 'submitted_at' => $row['date'] ?: null, 'summary' => $row['notes'] ?: null]);
                    }
                }
            });
        } catch (Throwable $exception) {
            foreach ($stored as $document) {
                if (filled($document->storage_path)) {
                    Storage::disk(data_get($document->metadata, 'disk', config('filesystems.default')))->delete($document->storage_path);
                }
            }
            report($exception);
            $this->addError('files', __('The sources could not be saved. No records from this batch were added. Your preview is still available to retry.'));

            return;
        }
        if ($stored === []) {
            return;
        }
        foreach ($stored as $document) {
            if (blank($document->storage_path)) {
                continue;
            }
            app(QueueReviewSourceProcessing::class)->queue($document);
        }
        $this->notice = __(':count sources added. Source links are references; their contents have not been fetched.', ['count' => count($stored)]);
        $this->reset('files', 'spreadsheet', 'preview', 'url', 'title', 'allowDuplicates');
        $this->dispatch('review-workspace-updated');
    }

    public function render(): View
    {
        $this->authorize('view', $this->review);

        return view('livewire.pls.reviews.record-intake', ['types' => DocumentType::cases()]);
    }
}
