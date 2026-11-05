<?php

namespace App\Livewire\Pls\Reviews;

use App\Domain\Reporting\Report;
use App\Domain\Reviews\PlsReview;
use App\Jobs\GenerateReviewReport;
use App\Support\Reports\ReportDrafts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;

class ReportPage extends Workspace
{
    protected string $workspace = 'reports';

    #[Locked]
    public Report $report;

    public bool $editing = false;

    public array $sections = [];

    #[Locked]
    public int $baseRevision = 0;

    public string $saveStatus = '';

    public function editDraft(): void
    {
        $this->authorize('update', $this->review);
        $report = $this->review->reports()->findOrFail($this->report->id);
        $this->sections = ReportDrafts::sections($report->content ?? '');
        $this->baseRevision = $report->revision;
        $this->version = 0;
        $this->editing = true;
        $this->saveStatus = __('Editing current draft');
    }

    public function saveDraft(): void
    {
        $this->authorize('update', $this->review);
        if (! $this->editing) {
            return;
        }
        $this->validate(['sections' => ['array', 'max:100'], 'sections.*' => ['string', 'max:200000']]);
        DB::transaction(function (): void {
            $report = $this->review->reports()->whereKey($this->report->id)->lockForUpdate()->firstOrFail();
            if ($report->revision !== $this->baseRevision) {
                $this->addError('sections', __('A newer version was saved by another contributor or generation job. Your text is still here. Copy it before reopening the latest draft.'));
                $this->saveStatus = __('Not saved — version conflict');

                return;
            }
            $report->update(['content' => implode("\n\n", $this->sections)]);
            $this->baseRevision = $report->revision;
            $this->saveStatus = __('Saved at :time', ['time' => now()->format('H:i:s')]);
            $this->resetValidation('sections');
        });
    }

    public function updatedSections(): void
    {
        $this->saveDraft();
    }

    public function regenerate(?int $section = null): void
    {
        $this->authorize('update', $this->review);
        if ($this->editing) {
            $this->saveDraft();
            if ($this->getErrorBag()->isNotEmpty()) {
                return;
            }
        }
        $queued = DB::transaction(function () use ($section) {
            $report = $this->review->reports()->whereKey($this->report->id)->lockForUpdate()->firstOrFail();
            if (in_array($report->generation_status, ['queued', 'generating']) && $report->generation_started_at?->gt(now()->subMinutes(10))) {
                return null;
            }
            if ($section !== null) {
                abort_unless(array_key_exists($section, ReportDrafts::sections($report->content ?? '')), 422);
            }
            $report->update(['generation_status' => 'queued', 'generation_error' => null, 'generation_started_at' => now(), 'generation_token' => (string) Str::uuid()]);

            return $report;
        });
        if (! $queued) {
            return;
        }
        $this->editing = false;
        GenerateReviewReport::dispatch($queued->id, $queued->revision, $queued->generation_template_id, $section);
    }

    #[Url]
    public int $version = 0;

    public function mount(PlsReview $review, ?Report $report = null): void
    {
        parent::mount($review);
        abort_unless($report && (int) $report->pls_review_id === (int) $review->id, 404);
        $this->report = $report;
    }

    public function render(): View
    {
        $this->authorize('view', $this->review);
        $report = $this->review->reports()->with('document')->findOrFail($this->report->id);
        $versions = $report->content_versions ?? [];
        abort_if($this->version < 0 || $this->version > count($versions), 404);
        $content = $this->version === 0 ? $report->content : $versions[$this->version - 1]['content'];
        $title = $this->version === 0 ? $report->title : $versions[$this->version - 1]['title'];

        return $this->renderWorkspaceView('livewire.pls.reviews.report-page', compact('report', 'versions', 'content', 'title'), $this->review->loadMissing('steps'));
    }
}
