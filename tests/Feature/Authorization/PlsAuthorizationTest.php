<?php

use App\Ai\Agents\ReviewDocumentExtractorAgent;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Reviews\Enums\PlsReviewMembershipRole;
use App\Domain\Reviews\PlsReviewMembership;
use App\Livewire\Pls\Reviews\CollaboratorsPage;
use App\Livewire\Pls\Reviews\Create as CreateReviewPage;
use App\Livewire\Pls\Reviews\DocumentsPage;
use App\Models\User;
use App\Support\PlsAssistant\AssistantSourceExtractionResult;
use App\Support\PlsAssistant\AssistantSourceTextExtractor;
use App\Support\PlsAssistant\AssistantSourceTextExtractorFactory;
use App\Support\Toast;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('creator can access and edit their review workspace', function () {
    Storage::fake(config('filesystems.default'));

    $owner = User::factory()->reviewer()->create();
    $review = plsReview([
        'created_by' => $owner->id,
    ]);

    $extractor = new class implements AssistantSourceTextExtractor
    {
        public function extract(\App\Domain\Documents\AssistantSourceDocument|\App\Domain\Documents\Document $document): AssistantSourceExtractionResult
        {
            return AssistantSourceExtractionResult::completed(
                driver: 'stub',
                method: 'stubbed shared extractor',
                content: 'Owner working paper content.',
            );
        }
    };

    $factory = Mockery::mock(AssistantSourceTextExtractorFactory::class);
    $factory->shouldReceive('make')->once()->andReturn($extractor);
    app()->instance(AssistantSourceTextExtractorFactory::class, $factory);
    ReviewDocumentExtractorAgent::fake([[
        'title' => 'Owner working paper',
        'document_type' => DocumentType::GroupReport->value,
        'summary' => 'Owner workspace upload.',
        'key_themes' => ['owner upload'],
        'notable_excerpts' => [],
        'important_dates' => [],
        'warnings' => [],
    ]]);

    $this->actingAs($owner)
        ->get(route('pls.reviews.workflow', $review))
        ->assertSuccessful();

    Livewire::actingAs($owner)
        ->test(DocumentsPage::class, ['review' => $review])
        ->set('documentUploads', [
            UploadedFile::fake()->create('owner-working-paper.pdf', 256, 'application/pdf'),
        ])
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

test('contributor can access and edit a review', function () {
    Storage::fake(config('filesystems.default'));

    $owner = User::factory()->reviewer()->create();
    $contributor = User::factory()->reviewer()->create();
    $review = plsReview([
        'created_by' => $owner->id,
    ]);

    $review->memberships()->create([
        'user_id' => $contributor->id,
        'role' => PlsReviewMembershipRole::Contributor,
        'invited_by' => $owner->id,
    ]);

    $extractor = new class implements AssistantSourceTextExtractor
    {
        public function extract(\App\Domain\Documents\AssistantSourceDocument|\App\Domain\Documents\Document $document): AssistantSourceExtractionResult
        {
            return AssistantSourceExtractionResult::completed(
                driver: 'stub',
                method: 'stubbed shared extractor',
                content: 'Contributor working paper content.',
            );
        }
    };

    $factory = Mockery::mock(AssistantSourceTextExtractorFactory::class);
    $factory->shouldReceive('make')->once()->andReturn($extractor);
    app()->instance(AssistantSourceTextExtractorFactory::class, $factory);
    ReviewDocumentExtractorAgent::fake([[
        'title' => 'Contributor working paper',
        'document_type' => DocumentType::GroupReport->value,
        'summary' => 'Contributor workspace upload.',
        'key_themes' => ['contributor upload'],
        'notable_excerpts' => [],
        'important_dates' => [],
        'warnings' => [],
    ]]);

    $this->actingAs($contributor)
        ->get(route('pls.reviews.workflow', $review))
        ->assertSuccessful();

    Livewire::actingAs($contributor)
        ->test(DocumentsPage::class, ['review' => $review])
        ->set('documentUploads', [
            UploadedFile::fake()->create('contributor-working-paper.pdf', 256, 'application/pdf'),
        ])
        ->assertHasNoErrors()
        ->assertSee('Contributor working paper');
});

test('viewer can access but cannot edit a review', function () {
    Storage::fake(config('filesystems.default'));

    $owner = User::factory()->reviewer()->create();
    $viewer = User::factory()->reviewer()->create();
    $review = plsReview([
        'created_by' => $owner->id,
    ]);

    $review->memberships()->create([
        'user_id' => $viewer->id,
        'role' => PlsReviewMembershipRole::Viewer,
        'invited_by' => $owner->id,
    ]);

    $factory = Mockery::mock(AssistantSourceTextExtractorFactory::class);
    $factory->shouldReceive('make')->never();
    app()->instance(AssistantSourceTextExtractorFactory::class, $factory);
    ReviewDocumentExtractorAgent::fake();

    $this->actingAs($viewer)
        ->get(route('pls.reviews.workflow', $review))
        ->assertSuccessful();

    Livewire::actingAs($viewer)
        ->test(DocumentsPage::class, ['review' => $review])
        ->set('documentUploads', [
            UploadedFile::fake()->create('viewer-working-paper.pdf', 256, 'application/pdf'),
        ])
        ->assertForbidden();
});

test('owner can add contributor by existing user email', function () {
    Notification::fake();

    $owner = User::factory()->reviewer()->create();
    $invitee = User::factory()->reviewer()->create();
    $review = plsReview([
        'created_by' => $owner->id,
    ]);

    Livewire::actingAs($owner)
        ->test(CollaboratorsPage::class, ['review' => $review])
        ->set('inviteCollaboratorEmail', $invitee->email)
        ->set('inviteCollaboratorRole', PlsReviewMembershipRole::Contributor->value)
        ->call('shareReview')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('pls_review_memberships', [
        'pls_review_id' => $review->id,
        'user_id' => $invitee->id,
        'role' => PlsReviewMembershipRole::Contributor->value,
        'invited_by' => $owner->id,
    ]);
});

test('owner can add viewer by existing user email', function () {
    Notification::fake();

    $owner = User::factory()->reviewer()->create();
    $invitee = User::factory()->reviewer()->create();
    $review = plsReview([
        'created_by' => $owner->id,
    ]);

    Livewire::actingAs($owner)
        ->test(CollaboratorsPage::class, ['review' => $review])
        ->set('inviteCollaboratorEmail', $invitee->email)
        ->set('inviteCollaboratorRole', PlsReviewMembershipRole::Viewer->value)
        ->call('shareReview')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('pls_review_memberships', [
        'pls_review_id' => $review->id,
        'user_id' => $invitee->id,
        'role' => PlsReviewMembershipRole::Viewer->value,
    ]);
});

test('owner can invite unknown email as contributor', function () {
    Notification::fake();

    $owner = User::factory()->reviewer()->create();
    $review = plsReview([
        'created_by' => $owner->id,
    ]);

    Livewire::actingAs($owner)
        ->test(CollaboratorsPage::class, ['review' => $review])
        ->set('inviteCollaboratorEmail', 'newperson@example.com')
        ->set('inviteCollaboratorRole', PlsReviewMembershipRole::Contributor->value)
        ->call('shareReview')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('pls_review_invitations', [
        'pls_review_id' => $review->id,
        'email' => 'newperson@example.com',
        'role' => PlsReviewMembershipRole::Contributor->value,
        'invited_by' => $owner->id,
    ]);

    Notification::assertSentOnDemand(\App\Notifications\ReviewInvitationNotification::class);
});

test('owner can invite unknown email as viewer', function () {
    Notification::fake();

    $owner = User::factory()->reviewer()->create();
    $review = plsReview([
        'created_by' => $owner->id,
    ]);

    Livewire::actingAs($owner)
        ->test(CollaboratorsPage::class, ['review' => $review])
        ->set('inviteCollaboratorEmail', 'viewer@example.com')
        ->set('inviteCollaboratorRole', PlsReviewMembershipRole::Viewer->value)
        ->call('shareReview')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('pls_review_invitations', [
        'pls_review_id' => $review->id,
        'email' => 'viewer@example.com',
        'role' => PlsReviewMembershipRole::Viewer->value,
    ]);
});

test('duplicate existing collaborator is rejected', function () {
    $owner = User::factory()->reviewer()->create();
    $existing = User::factory()->reviewer()->create();
    $review = plsReview([
        'created_by' => $owner->id,
    ]);

    $review->memberships()->create([
        'user_id' => $existing->id,
        'role' => PlsReviewMembershipRole::Contributor,
        'invited_by' => $owner->id,
    ]);

    Livewire::actingAs($owner)
        ->test(CollaboratorsPage::class, ['review' => $review])
        ->set('inviteCollaboratorEmail', $existing->email)
        ->set('inviteCollaboratorRole', PlsReviewMembershipRole::Contributor->value)
        ->call('shareReview')
        ->assertHasErrors(['inviteCollaboratorEmail']);
});

test('duplicate pending invite is rejected', function () {
    Notification::fake();

    $owner = User::factory()->reviewer()->create();
    $review = plsReview([
        'created_by' => $owner->id,
    ]);

    $review->invitations()->create([
        'email' => 'pending@example.com',
        'role' => PlsReviewMembershipRole::Contributor->value,
        'invited_by' => $owner->id,
    ]);

    Livewire::actingAs($owner)
        ->test(CollaboratorsPage::class, ['review' => $review])
        ->set('inviteCollaboratorEmail', 'pending@example.com')
        ->set('inviteCollaboratorRole', PlsReviewMembershipRole::Contributor->value)
        ->call('shareReview')
        ->assertHasErrors(['inviteCollaboratorEmail']);
});

test('contributor cannot manage collaborators', function () {
    $owner = User::factory()->reviewer()->create();
    $contributor = User::factory()->reviewer()->create();
    $invitee = User::factory()->reviewer()->create();
    $review = plsReview([
        'created_by' => $owner->id,
    ]);

    $review->memberships()->create([
        'user_id' => $contributor->id,
        'role' => PlsReviewMembershipRole::Contributor,
        'invited_by' => $owner->id,
    ]);

    Livewire::actingAs($contributor)
        ->test(CollaboratorsPage::class, ['review' => $review])
        ->set('inviteCollaboratorEmail', $invitee->email)
        ->set('inviteCollaboratorRole', PlsReviewMembershipRole::Contributor->value)
        ->call('shareReview')
        ->assertForbidden();
});

test('viewer cannot manage collaborators', function () {
    $owner = User::factory()->reviewer()->create();
    $viewer = User::factory()->reviewer()->create();
    $invitee = User::factory()->reviewer()->create();
    $review = plsReview([
        'created_by' => $owner->id,
    ]);

    $review->memberships()->create([
        'user_id' => $viewer->id,
        'role' => PlsReviewMembershipRole::Viewer,
        'invited_by' => $owner->id,
    ]);

    Livewire::actingAs($viewer)
        ->test(CollaboratorsPage::class, ['review' => $review])
        ->set('inviteCollaboratorEmail', $invitee->email)
        ->set('inviteCollaboratorRole', PlsReviewMembershipRole::Contributor->value)
        ->call('shareReview')
        ->assertForbidden();
});

test('owner cannot assign owner role from collaborators page', function () {
    $owner = User::factory()->reviewer()->create();
    $invitee = User::factory()->reviewer()->create();
    $review = plsReview([
        'created_by' => $owner->id,
    ]);

    Livewire::actingAs($owner)
        ->test(CollaboratorsPage::class, ['review' => $review])
        ->set('inviteCollaboratorEmail', $invitee->email)
        ->set('inviteCollaboratorRole', PlsReviewMembershipRole::Owner->value)
        ->call('shareReview')
        ->assertHasErrors(['inviteCollaboratorRole']);

    $this->assertDatabaseMissing('pls_review_memberships', [
        'pls_review_id' => $review->id,
        'user_id' => $invitee->id,
        'role' => PlsReviewMembershipRole::Owner->value,
    ]);
});

test('owner can remove collaborator membership', function () {
    $owner = User::factory()->reviewer()->create();
    $contributor = User::factory()->reviewer()->create();
    $review = plsReview([
        'created_by' => $owner->id,
    ]);

    $review->memberships()->create([
        'user_id' => $contributor->id,
        'role' => PlsReviewMembershipRole::Contributor,
        'invited_by' => $owner->id,
    ]);

    $membership = PlsReviewMembership::query()
        ->where('pls_review_id', $review->id)
        ->where('user_id', $contributor->id)
        ->firstOrFail();

    Livewire::actingAs($owner)
        ->test(CollaboratorsPage::class, ['review' => $review])
        ->call('removeCollaborator', $membership->id)
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('pls_review_memberships', [
        'id' => $membership->id,
    ]);
});

test('owner can revoke pending invitation', function () {
    Notification::fake();

    $owner = User::factory()->reviewer()->create();
    $review = plsReview([
        'created_by' => $owner->id,
    ]);

    $invitation = $review->invitations()->create([
        'email' => 'revokeme@example.com',
        'role' => PlsReviewMembershipRole::Contributor->value,
        'invited_by' => $owner->id,
    ]);

    Livewire::actingAs($owner)
        ->test(CollaboratorsPage::class, ['review' => $review])
        ->call('revokeInvitation', $invitation->id)
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('pls_review_invitations', [
        'id' => $invitation->id,
    ]);
});

test('invitation accept flow creates membership with correct role', function () {
    $owner = User::factory()->reviewer()->create();
    $invitee = User::factory()->reviewer()->create();
    $review = plsReview([
        'created_by' => $owner->id,
    ]);

    $invitation = $review->invitations()->create([
        'email' => $invitee->email,
        'role' => PlsReviewMembershipRole::Viewer->value,
        'invited_by' => $owner->id,
    ]);

    $this->actingAs($invitee)
        ->get(route('pls.invitations.accept', ['token' => $invitation->token]))
        ->assertRedirect(route('pls.reviews.workflow', ['review' => $review]))
        ->assertSessionHas('toast', Toast::success(
            __('Invitation accepted'),
            __('Invitation accepted. Welcome to the review.'),
        ));

    $this->assertDatabaseHas('pls_review_memberships', [
        'pls_review_id' => $review->id,
        'user_id' => $invitee->id,
        'role' => PlsReviewMembershipRole::Viewer->value,
    ]);

    expect($invitation->fresh()->accepted_at)->not->toBeNull();
});

test('existing collaborators receive a warning toast when accepting an invitation again', function () {
    $owner = User::factory()->reviewer()->create();
    $invitee = User::factory()->reviewer()->create();
    $review = plsReview([
        'created_by' => $owner->id,
    ]);

    $review->memberships()->create([
        'user_id' => $invitee->id,
        'role' => PlsReviewMembershipRole::Viewer->value,
        'invited_by' => $owner->id,
    ]);

    $invitation = $review->invitations()->create([
        'email' => $invitee->email,
        'role' => PlsReviewMembershipRole::Viewer->value,
        'invited_by' => $owner->id,
    ]);

    $this->actingAs($invitee)
        ->get(route('pls.invitations.accept', ['token' => $invitation->token]))
        ->assertRedirect(route('pls.reviews.workflow', ['review' => $review]))
        ->assertSessionHas('toast', Toast::warning(
            __('Access already granted'),
            __('You already have access to this review.'),
        ));
});

test('accepted invitation cannot be reused', function () {
    $owner = User::factory()->reviewer()->create();
    $invitee = User::factory()->reviewer()->create();
    $review = plsReview([
        'created_by' => $owner->id,
    ]);

    $invitation = $review->invitations()->create([
        'email' => $invitee->email,
        'role' => PlsReviewMembershipRole::Contributor->value,
        'invited_by' => $owner->id,
    ]);

    $invitation->update(['accepted_at' => now()]);

    $this->actingAs($invitee)
        ->get(route('pls.invitations.accept', ['token' => $invitation->token]))
        ->assertNotFound();
});

test('email mismatch prevents invitation acceptance', function () {
    $owner = User::factory()->reviewer()->create();
    $wrongUser = User::factory()->reviewer()->create();
    $review = plsReview([
        'created_by' => $owner->id,
    ]);

    $invitation = $review->invitations()->create([
        'email' => 'someone-else@example.com',
        'role' => PlsReviewMembershipRole::Contributor->value,
        'invited_by' => $owner->id,
    ]);

    $this->actingAs($wrongUser)
        ->get(route('pls.invitations.accept', ['token' => $invitation->token]))
        ->assertForbidden();

    $this->assertDatabaseMissing('pls_review_memberships', [
        'pls_review_id' => $review->id,
        'user_id' => $wrongUser->id,
    ]);
});

test('review group does not grant access by itself', function () {
    ['country' => $country, 'reviewGroup' => $reviewGroup, 'legislature' => $legislature] = plsHierarchy();
    $owner = User::factory()->reviewer()->create([
        'country_id' => $country->id,
    ]);
    $outsider = User::factory()->reviewer()->create([
        'country_id' => $country->id,
    ]);

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
    $hierarchy = plsHierarchy();
    $reviewer = User::factory()->reviewer()->create([
        'country_id' => $hierarchy['country']->id,
    ]);

    $this->actingAs($reviewer)
        ->get(route('pls.reviews.create'))
        ->assertSuccessful();

    Livewire::actingAs($reviewer)
        ->test(CreateReviewPage::class)
        ->set('jurisdiction_id', (string) $hierarchy['jurisdiction']->id)
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
