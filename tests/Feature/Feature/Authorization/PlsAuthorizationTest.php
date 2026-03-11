<?php

use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Reviews\PlsReview;
use App\Livewire\Pls\Reviews\Create as CreateReviewPage;
use App\Livewire\Pls\Reviews\Show as ShowReviewPage;
use App\Models\User;
use Livewire\Livewire;

test('observer can view reviews but cannot access the create page', function () {
    $observer = User::factory()->observer()->create();
    $review = plsReview();

    $this->actingAs($observer)
        ->get(route('pls.reviews.index'))
        ->assertSuccessful();

    $this->actingAs($observer)
        ->get(route('pls.reviews.show', $review))
        ->assertSuccessful();

    $this->actingAs($observer)
        ->get(route('pls.reviews.create'))
        ->assertForbidden();
});

test('observer cannot mutate review workspace records', function () {
    $observer = User::factory()->observer()->create();
    $review = plsReview();

    Livewire::actingAs($observer)
        ->test(ShowReviewPage::class, ['review' => $review])
        ->set('documentTitle', 'Observer document attempt')
        ->set('documentType', DocumentType::CommitteeReport->value)
        ->set('documentStoragePath', 'documents/observer-attempt.pdf')
        ->call('storeDocument')
        ->assertForbidden();
});

test('reviewer can access create page and create reviews', function () {
    $reviewer = User::factory()->reviewer()->create();
    $hierarchy = plsHierarchy();

    $this->actingAs($reviewer)
        ->get(route('pls.reviews.create'))
        ->assertSuccessful();

    Livewire::actingAs($reviewer)
        ->test(CreateReviewPage::class)
        ->set('committee_id', (string) $hierarchy['committee']->id)
        ->set('title', 'Reviewer-created PLS review')
        ->set('description', 'Created by a reviewer with baseline authorization.')
        ->set('start_date', '2026-03-11')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('pls_reviews', [
        'title' => 'Reviewer-created PLS review',
        'committee_id' => $hierarchy['committee']->id,
    ]);
});

test('admin can update review workspace records', function () {
    $admin = User::factory()->admin()->create();
    $review = plsReview();

    Livewire::actingAs($admin)
        ->test(ShowReviewPage::class, ['review' => $review])
        ->set('documentTitle', 'Admin working paper')
        ->set('documentType', DocumentType::CommitteeReport->value)
        ->set('documentStoragePath', 'documents/admin-working-paper.pdf')
        ->set('documentMimeType', 'application/pdf')
        ->set('documentFileSize', '2048')
        ->call('storeDocument')
        ->assertHasNoErrors()
        ->assertSee('Admin working paper');

    expect(PlsReview::query()->findOrFail($review->id)->documents()->where('title', 'Admin working paper')->exists())->toBeTrue();
});
