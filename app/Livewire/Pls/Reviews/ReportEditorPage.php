<?php

namespace App\Livewire\Pls\Reviews;

use App\Domain\Reporting\Actions\QueueReportDraft;
use App\Domain\Reporting\Actions\UpdateReport;
use App\Domain\Reporting\Report;
use App\Domain\Reviews\PlsReview;
use App\Jobs\GenerateReviewReportDraft;
use App\Support\Reports\ReportDocumentExporter;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

class ReportEditorPage extends Workspace
{
    protected string $workspace = 'reports';

    #[Locked]
    public int $reportId;

    #[Locked]
    public int $baseRevision = 0;

    /** @var list<string> */
    public array $sections = [];

    public int $selectedRevision = -1;

    public bool $editing = false;

    public int $sectionToRegenerate = 0;

    public string $saveStatus = '';

    public function mount(PlsReview $review, ?Report $report = null): void
    {
        parent::mount($review);
        abort_unless($report !== null && (int) $report->pls_review_id === (int) $review->id, 404);
        $this->reportId = $report->id;
    }

    public function edit(): void
    {
        $this->authorize('update', $this->review);
        $report = $this->review->reports()->findOrFail($this->reportId);
        $this->sections = GenerateReviewReportDraft::sections($report->content ?? '');
        if ($this->sections === []) {
            $this->sections = [''];
        }
        $this->baseRevision = $report->revision;
        $this->selectedRevision = -1;
        $this->editing = true;
        $this->saveStatus = __('Unsaved changes stay here until you save.');
    }

    public function save(UpdateReport $action): void
    {
        $this->authorize('update', $this->review);
        $this->validate(['sections' => ['required', 'array', 'max:100'], 'sections.*' => ['string', 'max:200000']]);
        $content = implode("\n\n", $this->sections);
        if (mb_strlen($content) > 200000) {
            $this->addError('sections', __('The complete report must be at most 200,000 characters.'));

            return;
        }
        $report = $this->review->reports()->findOrFail($this->reportId);
        try {
            $action->update([
                'report_id' => $report->id, 'pls_review_id' => $this->review->id, 'expected_revision' => $this->baseRevision,
                'title' => $report->title, 'content' => $content, 'report_type' => $report->report_type->value, 'status' => $report->status->value,
                'document_id' => $report->document_id, 'published_at' => $report->published_at?->toIso8601String(),
            ]);
        } catch (ValidationException $exception) {
            $this->addError('sections', $exception->errors()['content'][0] ?? __('The report could not be saved. Your edits are still here.'));

            return;
        }
        $this->baseRevision++;
        $this->saveStatus = __('Saved at :time', ['time' => now()->format('H:i:s')]);
        $this->resetValidation();
    }

    public function regenerate(QueueReportDraft $action): void
    {
        $this->queueGeneration($action);
    }

    public function regenerateSection(QueueReportDraft $action, int $revision): void
    {
        $this->queueGeneration($action, $this->sectionToRegenerate, $revision);
    }

    private function queueGeneration(QueueReportDraft $action, ?int $section = null, ?int $expectedRevision = null): void
    {
        $this->authorize('update', $this->review);
        if ($this->editing) {
            $this->addError('generation', __('Save your edits before generating.'));

            return;
        }
        $report = $this->review->reports()->findOrFail($this->reportId);
        $action->queue($this->review, $report, $report->generation_template_id, $section, $expectedRevision);
    }

    public function finishEditing(): void
    {
        $this->editing = false;
    }

    public function render(): View
    {
        $this->authorize('view', $this->review);
        $report = $this->review->reports()->findOrFail($this->reportId);
        $version = $this->selectedRevision < 0 ? $report : $report->versions()->where('revision', $this->selectedRevision)->firstOrFail();

        return $this->renderWorkspaceView('livewire.pls.reviews.report-editor-page', [
            'report' => $report, 'version' => $version, 'versions' => $report->versions()->get(['revision', 'created_at']),
            'currentSections' => GenerateReviewReportDraft::sections($report->content ?? ''),
            'contentHtml' => app(ReportDocumentExporter::class)->safeHtml($version->content ?? ''),
        ], $this->review->loadMissing('steps'));
    }
}
