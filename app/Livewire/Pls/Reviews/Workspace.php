<?php

namespace App\Livewire\Pls\Reviews;

use App\Domain\Reviews\PlsReview;
use App\Domain\Reviews\Support\PlsReviewStepGuidance;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class Workspace extends Component
{
    use AuthorizesRequests;
    use WithFileUploads;

    public PlsReview $review;

    protected string $workspace = 'workflow';

    public function mount(PlsReview $review): void
    {
        $this->authorize('view', $review);

        $this->review = $review;
    }

    #[On('review-workspace-updated')]
    public function refreshReviewWorkspace(?array $toast = null): void
    {
        $this->review = $this->review->fresh();

        if ($toast !== null) {
            $this->dispatchAppToast($toast);
        }
    }

    protected function renderWorkspaceView(string $view, array $data, PlsReview $review): View
    {
        return view($view, array_merge($data, [
            'review' => $review,
        ]))->layout('livewire.pls.reviews.workspace', $this->workspaceLayoutData($review));
    }

    /**
     * @return array{
     *     currentWorkspaceKey: string,
     *     review: PlsReview,
     *     title: string,
     *     workflowSummary: array{current_step_number: int, current_step: string, total_steps: int, progress_percentage: int},
     *     workspaceNavigation: list<array{icon: string, key: string, label: string, route: string}>
     * }
     */
    protected function workspaceLayoutData(PlsReview $review): array
    {
        return [
            'currentWorkspaceKey' => $this->workspace,
            'review' => $review,
            'title' => $review->title,
            'workflowSummary' => $this->workflowSummary($review),
            'workspaceNavigation' => $this->workspaceNavigation(),
        ];
    }

    /**
     * @return array{title: string, summary: string, tab: string, action: string}|null
     */
    public function workspaceGuidance(PlsReview $review): ?array
    {
        $currentStep = $review->steps->firstWhere('step_number', $review->current_step_number);

        if ($currentStep === null) {
            return null;
        }

        return app(PlsReviewStepGuidance::class)->guidanceForStep($currentStep);
    }

    /**
     * @return array{current_step_number: int, current_step: string, total_steps: int, progress_percentage: int}
     */
    public function workflowSummary(PlsReview $review): array
    {
        return [
            'current_step_number' => $review->current_step_number,
            'current_step' => $review->currentStepTitle(),
            'total_steps' => $review->steps->count(),
            'progress_percentage' => $review->progressPercentage(),
        ];
    }

    /**
     * @return list<array{icon: string, key: string, label: string, route: string}>
     */
    public function workspaceNavigation(): array
    {
        return [
            [
                'key' => 'workflow',
                'label' => __('Workflow'),
                'icon' => 'list-bullet',
                'route' => 'pls.reviews.workflow',
            ],
            [
                'key' => 'collaborators',
                'label' => __('Collaborators'),
                'icon' => 'user-plus',
                'route' => 'pls.reviews.collaborators',
            ],
            [
                'key' => 'legislation',
                'label' => __('Legislation'),
                'icon' => 'scale',
                'route' => 'pls.reviews.legislation',
            ],
            [
                'key' => 'documents',
                'label' => __('Documents'),
                'icon' => 'document-text',
                'route' => 'pls.reviews.documents',
            ],
            [
                'key' => 'stakeholders',
                'label' => __('Stakeholders'),
                'icon' => 'users',
                'route' => 'pls.reviews.stakeholders',
            ],
            [
                'key' => 'consultations',
                'label' => __('Consultations'),
                'icon' => 'chat-bubble-left-right',
                'route' => 'pls.reviews.consultations',
            ],
            [
                'key' => 'analysis',
                'label' => __('Analysis'),
                'icon' => 'light-bulb',
                'route' => 'pls.reviews.analysis',
            ],
            [
                'key' => 'reports',
                'label' => __('Reports'),
                'icon' => 'clipboard-document-list',
                'route' => 'pls.reviews.reports',
            ],
        ];
    }

    /**
     * @param  array{heading: string, text: string, variant: 'success'|'warning'|'danger', duration: int}  $toast
     */
    protected function dispatchAppToast(array $toast): void
    {
        $this->dispatch('app-toast', ...$toast);
    }

    /**
     * @param  array{heading: string, text: string, variant: 'success'|'warning'|'danger', duration: int}  $toast
     */
    protected function dispatchWorkspaceToast(array $toast): void
    {
        $this->dispatch('review-workspace-updated', toast: $toast);
    }
}
