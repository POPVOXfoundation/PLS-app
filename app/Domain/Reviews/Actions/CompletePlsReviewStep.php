<?php

namespace App\Domain\Reviews\Actions;

use App\Domain\Reviews\Enums\PlsReviewStatus;
use App\Domain\Reviews\Enums\PlsStepStatus;
use App\Domain\Reviews\PlsReview;
use App\Domain\Reviews\PlsReviewStep;
use App\Domain\Reviews\Support\PlsReviewWorkflow;
use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\ValidationException;

class CompletePlsReviewStep
{
    public function __construct(private DatabaseManager $database)
    {
    }

    public function complete(PlsReviewStep $step): PlsReview
    {
        return $this->database->transaction(function () use ($step): PlsReview {
            $review = PlsReview::query()
                ->lockForUpdate()
                ->findOrFail($step->pls_review_id);

            $steps = $review->steps()
                ->lockForUpdate()
                ->orderBy('step_number')
                ->get();

            $review->setRelation('steps', $steps);

            $workflowStep = $steps->firstWhere('id', $step->id);
            $firstOpenStep = $review->firstOpenStep();

            if ($workflowStep === null || $firstOpenStep === null || ! $firstOpenStep->is($workflowStep)) {
                throw ValidationException::withMessages([
                    'step' => __('Only the first incomplete workflow step can be completed.'),
                ]);
            }

            $completedAt = now();

            $workflowStep->forceFill([
                'status' => PlsStepStatus::Completed,
                'started_at' => $workflowStep->started_at ?? $completedAt,
                'completed_at' => $completedAt,
            ])->save();

            $review = $review->fresh('steps');
            $firstOpenStep = $review->firstOpenStep();
            $allStepsTerminal = $firstOpenStep === null;

            $review->forceFill([
                'status' => $allStepsTerminal ? PlsReviewStatus::Completed : PlsReviewStatus::Active,
                'current_step_number' => $allStepsTerminal
                    ? PlsReviewWorkflow::lastStepNumber()
                    : $firstOpenStep->step_number,
                'completed_at' => $allStepsTerminal ? $completedAt : null,
            ])->save();

            return $review->fresh('steps');
        });
    }
}
