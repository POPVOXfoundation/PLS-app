<?php

namespace App\Livewire\Pls\Reviews;

use App\Domain\Reporting\Report;
use App\Domain\Reviews\PlsReview;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;

class ReportPage extends Workspace
{
    protected string $workspace = 'reports';

    public Report $report;

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
