<?php

namespace App\Domain\Reviews\Actions;

use App\Domain\Reviews\Enums\PlsReviewStatus;
use App\Domain\Reviews\Enums\PlsStepStatus;
use App\Domain\Reviews\PlsReview;
use App\Domain\Reviews\PlsReviewStep;
use Illuminate\Database\DatabaseManager;
use Illuminate\Validation\ValidationException;

class StartPlsReviewStep
{
    public function __construct(private DatabaseManager $database)
    {
    }

    public function start(PlsReviewStep $step): PlsReview
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
                    'step' => __('Only the first incomplete workflow step can be started.'),
                ]);
            }

            $workflowStep->forceFill([
                'status' => PlsStepStatus::InProgress,
                'started_at' => $workflowStep->started_at ?? now(),
                'completed_at' => null,
            ])->save();

            $review->forceFill([
                'status' => PlsReviewStatus::Active,
                'current_step_number' => $firstOpenStep->step_number,
                'completed_at' => null,
            ])->save();

            return $review->fresh('steps');
        });
    }
}
