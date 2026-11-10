<?php

use App\Domain\Documents\Document;
use App\Domain\Reviews\Enums\PlsReviewMembershipRole;
use App\Livewire\Pls\Reviews\WorkflowPage;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->reviewer()->create());
});

test('stages can be completed and reopened without deleting the review record', function () {
    $review = plsReview();
    $document = Document::factory()->create(['pls_review_id' => $review->id]);
    $first = $review->steps()->orderBy('step_number')->first();
    $component = Livewire::test(WorkflowPage::class, ['review' => $review])->assertSee('Stage checklist')
        ->call('completeStep', $first->id)->assertHasNoErrors();
    expect($first->fresh()->isCompleted())->toBeTrue();
    expect($review->fresh()->current_step_number)->toBe(2);
    $component->call('reopenStep', $first->id)->assertHasNoErrors();
    expect($review->fresh()->current_step_number)->toBe(1);
    expect($document->fresh())->not->toBeNull();
});

test('a stale completion request cannot accidentally complete the next stage', function () {
    $review = plsReview();
    $first = $review->steps()->orderBy('step_number')->first();
    $component = Livewire::test(WorkflowPage::class, ['review' => $review]);
    $component->call('completeStep', $first->id)->assertHasNoErrors();
    $component->call('completeStep', $first->id)->assertHasErrors('step');
    expect($review->fresh()->current_step_number)->toBe(2);
});

test('future stages cannot be reopened to skip unfinished stages', function () {
    $review = plsReview();
    $future = $review->steps()->where('step_number', 5)->sole();
    Livewire::test(WorkflowPage::class, ['review' => $review])->call('reopenStep', $future->id)->assertHasErrors('step');
    expect($review->fresh()->current_step_number)->toBe(1);
});

test('stage changes require edit access and cannot target a different review', function () {
    $review = plsReview();
    $first = $review->steps()->first();
    $viewer = User::factory()->reviewer()->create();
    $review->memberships()->create(['user_id' => $viewer->id, 'role' => PlsReviewMembershipRole::Viewer]);
    $this->actingAs($viewer);
    Livewire::test(WorkflowPage::class, ['review' => $review])->call('completeStep', $first->id)->assertForbidden();
});
