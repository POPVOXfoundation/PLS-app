<?php

namespace App\Livewire\Pls\Reviews;

use App\Domain\Reviews\PlsReview;
use App\Domain\Reviews\PlsReviewStep;
use App\Domain\Reviews\Support\PlsReviewStepGuidance;
use Illuminate\Contracts\View\View;

class WorkflowPage extends Workspace
{
    protected string $workspace = 'workflow';

    public function render(): View
    {
        $review = $this->loadReview();

        return $this->renderWorkspaceView('livewire.pls.reviews.workflow-page', [], $review);
    }

    public function stepContext(PlsReviewStep $step): string
    {
        return app(PlsReviewStepGuidance::class)->contextForStep($step);
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
