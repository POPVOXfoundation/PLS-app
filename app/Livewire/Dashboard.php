<?php

namespace App\Livewire;

use App\Domain\Reviews\Enums\PlsReviewStatus;
use App\Domain\Reviews\PlsReview;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;

class Dashboard extends Component
{
    public function render(): View
    {
        return view('livewire.dashboard', [
            'heroChips' => $this->heroChips(),
            'assignmentSummaries' => $this->assignmentSummaries(),
            'attentionReviews' => $this->attentionReviews(),
            'phasePipeline' => $this->phasePipeline(),
            'recentReviews' => $this->recentReviews(),
        ])->layout('layouts.app', [
            'title' => __('Dashboard'),
        ]);
    }

    public function reviewAssignmentLabel(PlsReview $review): string
    {
        return $review->assignmentLabel();
    }

    /**
     * @return list<array{label: string, value: string, detail: string}>
     */
    private function heroChips(): array
    {
        $reviews = $this->accessibleReviewsQuery();

        return [
            [
                'label' => __('Active reviews'),
                'value' => (string) (clone $reviews)->where('status', PlsReviewStatus::Active)->count(),
                'detail' => __('Currently moving through the workflow'),
            ],
            [
                'label' => __('Review groups engaged'),
                'value' => (string) (clone $reviews)->whereNotNull('review_group_id')->distinct('review_group_id')->count('review_group_id'),
                'detail' => __('Review groups with at least one live review record'),
            ],
            [
                'label' => __('Needs attention'),
                'value' => (string) (clone $reviews)->whereIn('status', [
                    PlsReviewStatus::Draft,
                    PlsReviewStatus::Active,
                ])->count(),
                'detail' => __('Draft and active reviews that need movement'),
            ],
        ];
    }

    /**
     * @return array<int, array{
     *     review_id: int,
     *     title: string,
     *     assignment_name: string,
     *     current_step: string,
     *     phase: string,
     *     reason: string,
     *     status_label: string,
     *     urgency_label: string,
     *     tone: string,
     *     progress: int,
     *     priority: int
     * }>
     */
    private function attentionReviews(): array
    {
        return $this->accessibleReviewsQuery()
            ->with([
                'reviewGroup',
                'legislature',
                'jurisdiction',
                'steps',
            ])
            ->whereIn('status', [PlsReviewStatus::Draft, PlsReviewStatus::Active])
            ->get()
            ->map(function (PlsReview $review): array {
                $currentStep = $review->currentStep();
                $phase = $this->phaseForStep($review->current_step_number);

                if ($review->status === PlsReviewStatus::Draft) {
                    $reason = __('Review exists but the workflow has not been started.');
                    $tone = 'draft';
                    $urgencyLabel = __('Not started');
                    $priority = 3;
                } elseif ($currentStep?->isPending()) {
                    $reason = __('The current step is selected but no work has been recorded yet.');
                    $tone = 'urgent';
                    $urgencyLabel = __('Action needed');
                    $priority = 4;
                } elseif ($review->current_step_number >= 7) {
                    $reason = __('Late-stage report or response work is in motion and needs close follow-through.');
                    $tone = 'watch';
                    $urgencyLabel = __('Monitor closely');
                    $priority = 2;
                } else {
                    $reason = __('Workflow is active and should be advanced to the next milestone soon.');
                    $tone = 'active';
                    $urgencyLabel = __('In progress');
                    $priority = 1;
                }

                return [
                    'review_id' => $review->id,
                    'title' => $review->title,
                    'assignment_name' => $this->reviewAssignmentLabel($review),
                    'current_step' => __('Step :number', ['number' => $review->current_step_number]).' · '.$review->currentStepTitle(),
                    'phase' => $phase['label'],
                    'reason' => $reason,
                    'status_label' => $review->statusLabel(),
                    'urgency_label' => $urgencyLabel,
                    'tone' => $tone,
                    'progress' => $review->progressPercentage(),
                    'priority' => $priority,
                ];
            })
            ->sortByDesc('priority')
            ->take(4)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{
     *     key: string,
     *     label: string,
     *     step_range: string,
     *     description: string,
     *     reviews_count: int,
     *     active_count: int,
     *     completed_count: int,
     *     ratio: int
     * }>
     */
    private function phasePipeline(): array
    {
        $reviews = $this->accessibleReviewsQuery()->get(['id', 'current_step_number', 'status']);
        $portfolioCount = max($reviews->count(), 1);

        return collect($this->phaseDefinitions())
            ->map(function (array $phase) use ($reviews, $portfolioCount): array {
                $phaseReviews = $reviews->filter(
                    fn (PlsReview $review): bool => in_array($review->current_step_number, $phase['steps'], true),
                );

                return [
                    'key' => $phase['key'],
                    'label' => $phase['label'],
                    'step_range' => $phase['step_range'],
                    'description' => $phase['description'],
                    'reviews_count' => $phaseReviews->count(),
                    'active_count' => $phaseReviews->filter(
                        fn (PlsReview $review): bool => $review->status === PlsReviewStatus::Active,
                    )->count(),
                    'completed_count' => $phaseReviews->filter(
                        fn (PlsReview $review): bool => $review->status === PlsReviewStatus::Completed,
                    )->count(),
                    'ratio' => (int) round(($phaseReviews->count() / $portfolioCount) * 100),
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array{
     *     assignment_name: string,
     *     legislature_name: string,
     *     reviews_count: int,
     *     active_reviews_count: int,
     *     attention_reviews_count: int,
     *     average_progress: int,
     *     latest_review_title: ?string,
     *     latest_review_id: ?int
     * }>
     */
    private function assignmentSummaries(): array
    {
        return $this->accessibleReviewsQuery()
            ->with(['reviewGroup.legislature', 'legislature', 'jurisdiction', 'steps'])
            ->get()
            ->groupBy(function (PlsReview $review): string {
                if ($review->review_group_id !== null) {
                    return 'review-group:'.$review->review_group_id;
                }

                if ($review->legislature_id !== null) {
                    return 'legislature:'.$review->legislature_id;
                }

                if ($review->jurisdiction_id !== null) {
                    return 'jurisdiction:'.$review->jurisdiction_id;
                }

                return 'unassigned';
            })
            ->map(function (Collection $reviews): array {
                /** @var PlsReview $firstReview */
                $firstReview = $reviews->first();
                /** @var PlsReview $latestReview */
                $latestReview = $reviews->sortByDesc('created_at')->first();

                return [
                    'assignment_name' => $this->reviewAssignmentLabel($firstReview),
                    'legislature_name' => $firstReview->legislature?->name
                        ?? $firstReview->jurisdiction?->name
                        ?? __('Unassigned'),
                    'reviews_count' => $reviews->count(),
                    'active_reviews_count' => $reviews->filter(
                        fn (PlsReview $review): bool => $review->status === PlsReviewStatus::Active,
                    )->count(),
                    'attention_reviews_count' => $reviews->filter(
                        fn (PlsReview $review): bool => in_array($review->status, [PlsReviewStatus::Draft, PlsReviewStatus::Active], true),
                    )->count(),
                    'average_progress' => (int) round($reviews->avg(
                        fn (PlsReview $review): int => $review->progressPercentage(),
                    ) ?? 0),
                    'latest_review_title' => $latestReview?->title,
                    'latest_review_id' => $latestReview?->id,
                ];
            })
            ->sortByDesc('active_reviews_count')
            ->values()
            ->all();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, PlsReview>
     */
    private function recentReviews(): Collection
    {
        return $this->accessibleReviewsQuery()
            ->with([
                'reviewGroup',
                'legislature',
                'jurisdiction',
                'steps',
            ])
            ->latest()
            ->limit(5)
            ->get();
    }

    private function accessibleReviewsQuery(): Builder
    {
        return PlsReview::query()->visibleTo(auth()->user());
    }

    public function reviewPhaseLabel(PlsReview $review): string
    {
        return $this->phaseForStep($review->current_step_number)['label'];
    }

    /**
     * @return array{key: string, label: string, step_range: string, description: string, steps: list<int>}
     */
    private function phaseForStep(int $stepNumber): array
    {
        foreach ($this->phaseDefinitions() as $phase) {
            if (in_array($stepNumber, $phase['steps'], true)) {
                return $phase;
            }
        }

        return $this->phaseDefinitions()[0];
    }

    /**
     * @return list<array{key: string, label: string, step_range: string, description: string, steps: list<int>}>
     */
    private function phaseDefinitions(): array
    {
        return [
            [
                'key' => 'scoping',
                'label' => __('Scoping'),
                'step_range' => '1–2',
                'description' => __('Mandate, objectives, and review framing'),
                'steps' => [1, 2],
            ],
            [
                'key' => 'evidence',
                'label' => __('Evidence gathering'),
                'step_range' => '3–4',
                'description' => __('Stakeholders, agencies, and source material'),
                'steps' => [3, 4],
            ],
            [
                'key' => 'consultation',
                'label' => __('Consultation'),
                'step_range' => '5',
                'description' => __('Public engagement and submission intake'),
                'steps' => [5],
            ],
            [
                'key' => 'analysis',
                'label' => __('Analysis'),
                'step_range' => '6',
                'description' => __('Findings and recommendation synthesis'),
                'steps' => [6],
            ],
            [
                'key' => 'reporting',
                'label' => __('Report drafting'),
                'step_range' => '7–8',
                'description' => __('Drafting, publication, and dissemination'),
                'steps' => [7, 8],
            ],
            [
                'key' => 'response',
                'label' => __('Government response'),
                'step_range' => '9',
                'description' => __('Comply-or-explain response tracking'),
                'steps' => [9],
            ],
            [
                'key' => 'follow_up',
                'label' => __('Follow-up'),
                'step_range' => '10–11',
                'description' => __('Follow-through and evaluation'),
                'steps' => [10, 11],
            ],
        ];
    }
}
