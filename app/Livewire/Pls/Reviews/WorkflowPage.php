<?php

namespace App\Livewire\Pls\Reviews;

use App\Domain\Reviews\PlsReview;
use App\Domain\Reviews\PlsReviewStep;
use Illuminate\Contracts\View\View;

class WorkflowPage extends Workspace
{
    protected string $workspace = 'workflow';

    public int $selectedStepNumber = 1;

    public function mount(PlsReview $review): void
    {
        parent::mount($review);

        $this->selectedStepNumber = max(1, $review->current_step_number);
    }

    public function render(): View
    {
        $review = $this->loadReview();
        $selectedStep = $review->steps->firstWhere('step_number', $this->selectedStepNumber) ?? $review->steps->first();

        if ($selectedStep !== null && $selectedStep->step_number !== $this->selectedStepNumber) {
            $this->selectedStepNumber = $selectedStep->step_number;
        }

        return $this->renderWorkspaceView('livewire.pls.reviews.workflow-page', [
            'selectedStep' => $selectedStep,
            'workflowSummary' => $this->workflowSummary($review),
            'workspaceGuidance' => $this->workspaceGuidance($review),
        ], $review);
    }

    public function selectStep(int $stepNumber): void
    {
        if (! $this->review->steps()->where('step_number', $stepNumber)->exists()) {
            return;
        }

        $this->selectedStepNumber = $stepNumber;
    }

    public function stepContext(PlsReviewStep $step): string
    {
        return match ($step->step_key) {
            'define_scope' => __('Confirm the legislation under review, the institutional context, and the boundaries of the inquiry.'),
            'background_data_plan' => __('Assemble source material, implementation records, and baseline evidence to guide the review.'),
            'stakeholder_plan' => __('Map the institutions and external actors that should inform the scrutiny process.'),
            'implementation_review' => __('Examine delivery agencies, delegated powers, and operational bottlenecks in implementation.'),
            'consultations' => __('Capture written and oral input from the public, experts, and implementing institutions.'),
            'analysis' => __('Synthesize evidence into findings and identify the strongest recommendation themes.'),
            'draft_report' => __('Translate the inquiry record into a review report with clear conclusions and actions.'),
            'dissemination' => __('Track publication readiness, accessibility, and the materials needed for public release.'),
            'government_response' => __('Monitor whether the executive has responded and whether commitments are on record.'),
            'follow_up' => __('Keep sight of implementation progress after the report phase concludes.'),
            'evaluation' => __('Assess whether the review process produced usable lessons, evidence, and institutional value.'),
            default => __('Review the current materials attached to this workflow step.'),
        };
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    public function stepMetricCards(PlsReview $review, PlsReviewStep $step): array
    {
        return match ($step->step_key) {
            'define_scope' => [
                ['label' => __('Legislation linked'), 'value' => (string) $review->legislation->count()],
                ['label' => __('Objectives captured'), 'value' => (string) $review->legislationObjectives->count()],
                ['label' => __('Working documents'), 'value' => (string) $review->documents->count()],
            ],
            'background_data_plan', 'implementation_review' => [
                ['label' => __('Documents'), 'value' => (string) $review->documents->count()],
                ['label' => __('Evidence items'), 'value' => (string) $review->evidenceItems->count()],
                ['label' => __('Agencies reviewed'), 'value' => (string) $review->implementingAgencies->count()],
            ],
            'stakeholder_plan', 'consultations' => [
                ['label' => __('Stakeholders'), 'value' => (string) $review->stakeholders->count()],
                ['label' => __('Consultations'), 'value' => (string) $review->consultations->count()],
                ['label' => __('Submissions'), 'value' => (string) $review->submissions->count()],
            ],
            'analysis' => [
                ['label' => __('Findings'), 'value' => (string) $review->findings->count()],
                ['label' => __('Recommendations'), 'value' => (string) $review->recommendations->count()],
                ['label' => __('Evidence items'), 'value' => (string) $review->evidenceItems->count()],
            ],
            'draft_report', 'dissemination' => [
                ['label' => __('Reports'), 'value' => (string) $review->reports->count()],
                ['label' => __('Final documents'), 'value' => (string) $review->documents->count()],
                ['label' => __('Recommendations'), 'value' => (string) $review->recommendations->count()],
            ],
            'government_response', 'follow_up', 'evaluation' => [
                ['label' => __('Reports'), 'value' => (string) $review->reports->count()],
                ['label' => __('Responses'), 'value' => (string) $review->governmentResponses->count()],
                ['label' => __('Recommendations'), 'value' => (string) $review->recommendations->count()],
            ],
            default => [
                ['label' => __('Documents'), 'value' => (string) $review->documents->count()],
                ['label' => __('Findings'), 'value' => (string) $review->findings->count()],
                ['label' => __('Recommendations'), 'value' => (string) $review->recommendations->count()],
            ],
        };
    }

    private function loadReview(): PlsReview
    {
        return $this->review->load([
            'owner',
            'reviewGroup.legislature.jurisdiction.country',
            'legislature.jurisdiction.country',
            'memberships.user',
            'memberships.invitedBy',
            'steps',
            'legislation',
            'legislationObjectives',
            'documents',
            'evidenceItems',
            'stakeholders',
            'implementingAgencies',
            'consultations',
            'submissions',
            'findings',
            'recommendations',
            'reports',
            'governmentResponses',
        ]);
    }
}
