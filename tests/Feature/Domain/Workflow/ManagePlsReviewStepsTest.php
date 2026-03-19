<?php

use App\Domain\Reviews\Actions\CompletePlsReviewStep;
use App\Domain\Reviews\Actions\ReopenPlsReviewStep;
use App\Domain\Reviews\Actions\StartPlsReviewStep;
use App\Domain\Reviews\Enums\PlsReviewStatus;
use App\Domain\Reviews\Enums\PlsStepStatus;
use Illuminate\Validation\ValidationException;

it('starts a review step and moves the review from draft to active', function () {
    $review = plsReview([
        'title' => 'Review of legislative implementation planning',
    ]);

    $review = app(StartPlsReviewStep::class)->start(
        $review->steps()->where('step_number', 1)->firstOrFail(),
    );

    $startedStep = $review->step(1);

    expect($review->status)->toBe(PlsReviewStatus::Active)
        ->and($review->current_step_number)->toBe(1)
        ->and($review->completed_at)->toBeNull()
        ->and($startedStep)->not->toBeNull()
        ->and($startedStep?->status)->toBe(PlsStepStatus::InProgress)
        ->and($startedStep?->started_at)->not->toBeNull()
        ->and($startedStep?->completed_at)->toBeNull();

    $this->assertDatabaseHas('pls_reviews', [
        'id' => $review->id,
        'status' => PlsReviewStatus::Active->value,
        'current_step_number' => 1,
        'completed_at' => null,
    ]);

    $this->assertDatabaseHas('pls_review_steps', [
        'id' => $startedStep->id,
        'status' => PlsStepStatus::InProgress->value,
    ]);
});

it('completes review steps in sequence and marks the review completed on the final step', function () {
    $review = plsReview([
        'title' => 'Review of reporting obligations',
    ]);

    $action = app(CompletePlsReviewStep::class);

    foreach (range(1, 11) as $stepNumber) {
        $review = $action->complete(
            $review->steps()->where('step_number', $stepNumber)->firstOrFail(),
        );
    }

    $review->load('steps');

    expect($review->status)->toBe(PlsReviewStatus::Completed)
        ->and($review->current_step_number)->toBe(11)
        ->and($review->completed_at)->not->toBeNull()
        ->and($review->steps->every(fn ($step) => $step->status === PlsStepStatus::Completed))->toBeTrue();

    expect($review->step(1)?->started_at)->not->toBeNull()
        ->and($review->step(1)?->completed_at)->not->toBeNull()
        ->and($review->step(11)?->completed_at)->not->toBeNull();

    $this->assertDatabaseHas('pls_reviews', [
        'id' => $review->id,
        'status' => PlsReviewStatus::Completed->value,
        'current_step_number' => 11,
    ]);
});

it('reopens a completed step, resets later steps, and returns the review to active status', function () {
    $review = plsReview([
        'title' => 'Review of delegated legislation compliance',
    ]);

    $completeStep = app(CompletePlsReviewStep::class);

    foreach (range(1, 11) as $stepNumber) {
        $review = $completeStep->complete(
            $review->steps()->where('step_number', $stepNumber)->firstOrFail(),
        );
    }

    $review = app(ReopenPlsReviewStep::class)->reopen(
        $review->steps()->where('step_number', 9)->firstOrFail(),
    );

    $review->load('steps');

    expect($review->status)->toBe(PlsReviewStatus::Active)
        ->and($review->current_step_number)->toBe(9)
        ->and($review->completed_at)->toBeNull()
        ->and($review->step(8)?->status)->toBe(PlsStepStatus::Completed)
        ->and($review->step(9)?->status)->toBe(PlsStepStatus::InProgress)
        ->and($review->step(9)?->completed_at)->toBeNull()
        ->and($review->step(10)?->status)->toBe(PlsStepStatus::Pending)
        ->and($review->step(10)?->started_at)->toBeNull()
        ->and($review->step(10)?->completed_at)->toBeNull()
        ->and($review->step(11)?->status)->toBe(PlsStepStatus::Pending)
        ->and($review->step(11)?->started_at)->toBeNull()
        ->and($review->step(11)?->completed_at)->toBeNull();

    $this->assertDatabaseHas('pls_reviews', [
        'id' => $review->id,
        'status' => PlsReviewStatus::Active->value,
        'current_step_number' => 9,
        'completed_at' => null,
    ]);
});

it('rejects attempts to start a later step while an earlier step is still incomplete', function () {
    $review = plsReview([
        'title' => 'Review of sequencing controls for workflow start',
    ]);

    expect(fn () => app(StartPlsReviewStep::class)->start(
        $review->steps()->where('step_number', 2)->firstOrFail(),
    ))->toThrow(ValidationException::class);

    $review->refresh()->load('steps');

    expect($review->status)->toBe(PlsReviewStatus::Draft)
        ->and($review->current_step_number)->toBe(1)
        ->and($review->step(1)?->status)->toBe(PlsStepStatus::Pending)
        ->and($review->step(2)?->status)->toBe(PlsStepStatus::Pending);
});

it('rejects attempts to complete a later step while an earlier step is still incomplete', function () {
    $review = plsReview([
        'title' => 'Review of sequencing controls for workflow completion',
    ]);

    expect(fn () => app(CompletePlsReviewStep::class)->complete(
        $review->steps()->where('step_number', 3)->firstOrFail(),
    ))->toThrow(ValidationException::class);

    $review->refresh()->load('steps');

    expect($review->status)->toBe(PlsReviewStatus::Draft)
        ->and($review->current_step_number)->toBe(1)
        ->and($review->step(1)?->status)->toBe(PlsStepStatus::Pending)
        ->and($review->step(3)?->status)->toBe(PlsStepStatus::Pending);
});
