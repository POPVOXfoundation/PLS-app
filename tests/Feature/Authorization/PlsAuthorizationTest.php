<?php

use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Reviews\Enums\PlsReviewMembershipRole;
use App\Domain\Reviews\PlsReviewMembership;
use App\Livewire\Pls\Reviews\CollaboratorsPage;
use App\Livewire\Pls\Reviews\Create as CreateReviewPage;
use App\Livewire\Pls\Reviews\DocumentsPage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

test('creator can access and edit their review workspace', function () {
    $owner = User::factory()->reviewer()->create();
    $review = plsReview([
        'created_by' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->get(route('pls.reviews.workflow', $review))
        ->assertSuccessful();

    Livewire::actingAs($owner)
        ->test(DocumentsPage::class, ['review' => $review])
        ->set('documentTitle', 'Owner working paper')
        ->set('documentType', DocumentType::GroupReport->value)
        ->set('documentStoragePath', 'documents/owner-working-paper.pdf')
        ->call('storeDocument')
        ->assertHasNoErrors()
        ->assertSee('Owner working paper');
});

test('created_by alone does not grant access without a membership record', function () {
    $owner = User::factory()->reviewer()->create();
    $review = plsReview([
        'created_by' => $owner->id,
    ]);

    DB::table('pls_review_memberships')
        ->where('pls_review_id', $review->id)
        ->delete();

    $this->actingAs($owner)
        ->get(route('pls.reviews.index'))
        ->assertSuccessful()
        ->assertDontSee($review->title);

    $this->actingAs($owner)
        ->get(route('pls.reviews.show', $review))
        ->assertForbidden();
});

test('non-member cannot access a review', function () {
    $owner = User::factory()->reviewer()->create();
    $outsider = User::factory()->reviewer()->create();
    $review = plsReview([
        'created_by' => $owner->id,
    ]);

    $this->actingAs($outsider)
        ->get(route('pls.reviews.index'))
        ->assertSuccessful()
        ->assertDontSee($review->title);

    $this->actingAs($outsider)
        ->get(route('pls.reviews.show', $review))
        ->assertForbidden();
});

test('invited member can access and edit a review', function () {
    $owner = User::factory()->reviewer()->create();
    $editor = User::factory()->reviewer()->create();
    $review = plsReview([
        'created_by' => $owner->id,
    ]);

    $review->memberships()->create([
        'user_id' => $editor->id,
        'role' => PlsReviewMembershipRole::Editor,
        'invited_by' => $owner->id,
    ]);

    $this->actingAs($editor)
        ->get(route('pls.reviews.workflow', $review))
        ->assertSuccessful();

    Livewire::actingAs($editor)
        ->test(DocumentsPage::class, ['review' => $review])
        ->set('documentTitle', 'Editor working paper')
        ->set('documentType', DocumentType::GroupReport->value)
        ->set('documentStoragePath', 'documents/editor-working-paper.pdf')
        ->call('storeDocument')
        ->assertHasNoErrors()
        ->assertSee('Editor working paper');
});

test('owner can invite and remove collaborators', function () {
    $owner = User::factory()->reviewer()->create();
    $invitee = User::factory()->reviewer()->create();
    $review = plsReview([
        'created_by' => $owner->id,
    ]);

    Livewire::actingAs($owner)
        ->test(CollaboratorsPage::class, ['review' => $review])
        ->set('inviteCollaboratorUserId', (string) $invitee->id)
        ->set('inviteCollaboratorRole', PlsReviewMembershipRole::Editor->value)
        ->call('inviteCollaborator')
        ->assertHasNoErrors()
        ->assertSee($invitee->email);

    $membership = PlsReviewMembership::query()
        ->where('pls_review_id', $review->id)
        ->where('user_id', $invitee->id)
        ->firstOrFail();

    $this->assertDatabaseHas('pls_review_memberships', [
        'pls_review_id' => $review->id,
        'user_id' => $invitee->id,
        'role' => PlsReviewMembershipRole::Editor->value,
        'invited_by' => $owner->id,
    ]);

    Livewire::actingAs($owner)
        ->test(CollaboratorsPage::class, ['review' => $review])
        ->call('removeCollaborator', $membership->id)
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('pls_review_memberships', [
        'id' => $membership->id,
    ]);
});

test('invited editor cannot manage collaborators', function () {
    $owner = User::factory()->reviewer()->create();
    $editor = User::factory()->reviewer()->create();
    $invitee = User::factory()->reviewer()->create();
    $review = plsReview([
        'created_by' => $owner->id,
    ]);

    $review->memberships()->create([
        'user_id' => $editor->id,
        'role' => PlsReviewMembershipRole::Editor,
        'invited_by' => $owner->id,
    ]);

    Livewire::actingAs($editor)
        ->test(CollaboratorsPage::class, ['review' => $review])
        ->set('inviteCollaboratorUserId', (string) $invitee->id)
        ->set('inviteCollaboratorRole', PlsReviewMembershipRole::Editor->value)
        ->call('inviteCollaborator')
        ->assertForbidden();
});

test('collaborators cannot be promoted to owner from the review workspace', function () {
    $creator = User::factory()->reviewer()->create();
    $invitee = User::factory()->reviewer()->create();
    $review = plsReview([
        'created_by' => $creator->id,
    ]);

    Livewire::actingAs($creator)
        ->test(CollaboratorsPage::class, ['review' => $review])
        ->set('inviteCollaboratorUserId', (string) $invitee->id)
        ->set('inviteCollaboratorRole', PlsReviewMembershipRole::Owner->value)
        ->call('inviteCollaborator')
        ->assertHasErrors(['inviteCollaboratorRole']);

    $this->assertDatabaseMissing('pls_review_memberships', [
        'pls_review_id' => $review->id,
        'user_id' => $invitee->id,
        'role' => PlsReviewMembershipRole::Owner->value,
    ]);
});

test('review group does not grant access by itself', function () {
    $owner = User::factory()->reviewer()->create();
    $outsider = User::factory()->reviewer()->create();
    ['reviewGroup' => $reviewGroup, 'legislature' => $legislature] = plsHierarchy();

    $ownersReview = plsReview([
        'created_by' => $owner->id,
        'legislature_id' => $legislature->id,
        'review_group_id' => $reviewGroup->id,
        'title' => 'Owner review in shared group',
    ]);

    $outsiderReview = plsReview([
        'created_by' => $outsider->id,
        'legislature_id' => $legislature->id,
        'review_group_id' => $reviewGroup->id,
        'title' => 'Outsider review in shared group',
    ]);

    $this->actingAs($outsider)
        ->get(route('pls.reviews.index'))
        ->assertSuccessful()
        ->assertDontSee($ownersReview->title)
        ->assertSee($outsiderReview->title);

    $this->actingAs($outsider)
        ->get(route('pls.reviews.show', $ownersReview))
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
        ->set('legislature_id', (string) $hierarchy['legislature']->id)
        ->set('review_group_id', (string) $hierarchy['reviewGroup']->id)
        ->set('title', 'Reviewer-created PLS review')
        ->set('description', 'Created by a reviewer with explicit owner membership.')
        ->set('start_date', '2026-03-11')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('pls_reviews', [
        'title' => 'Reviewer-created PLS review',
        'review_group_id' => $hierarchy['reviewGroup']->id,
        'created_by' => $reviewer->id,
    ]);

    $this->assertDatabaseHas('pls_review_memberships', [
        'user_id' => $reviewer->id,
        'role' => PlsReviewMembershipRole::Owner->value,
    ]);
});
