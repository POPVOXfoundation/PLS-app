<?php

namespace App\Domain\Reviews\Actions;

use App\Domain\Reviews\Enums\PlsReviewStatus;
use App\Domain\Reviews\Enums\PlsStepStatus;
use App\Domain\Reviews\PlsReview;
use App\Domain\Reviews\PlsReviewStep;
use Illuminate\Database\DatabaseManager;

class ReopenPlsReviewStep
{
    public function __construct(private DatabaseManager $database)
    {
    }

    public function reopen(PlsReviewStep $step): PlsReview
    {
        return $this->database->transaction(function () use ($step): PlsReview {
            $review = PlsReview::query()
                ->lockForUpdate()
                ->findOrFail($step->pls_review_id);

            $workflowStep = $review->steps()
                ->lockForUpdate()
                ->findOrFail($step->id);

            $workflowStep->forceFill([
                'status' => PlsStepStatus::InProgress,
                'started_at' => $workflowStep->started_at ?? now(),
                'completed_at' => null,
            ])->save();

            $review->steps()
                ->where('step_number', '>', $workflowStep->step_number)
                ->update([
                    'status' => PlsStepStatus::Pending->value,
                    'started_at' => null,
                    'completed_at' => null,
                ]);

            $review->forceFill([
                'status' => PlsReviewStatus::Active,
                'current_step_number' => $workflowStep->step_number,
                'completed_at' => null,
            ])->save();

            return $review->fresh('steps');
        });
    }
}
