<?php

use App\Ai\Agents\ReviewAssistantAgent;
use App\Domain\Analysis\Finding;
use App\Domain\Reviews\Enums\PlsReviewMembershipRole;
use App\Domain\Reviews\PlsReviewInvitation;
use App\Domain\Reviews\ReviewActivity;
use App\Livewire\Pls\Reviews\AssistantSidebar;
use App\Livewire\Pls\Reviews\CollaboratorsPage;
use App\Models\User;
use App\Notifications\ReviewInvitationNotification;
use App\Notifications\ReviewMailNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->actingAs(User::factory()->reviewer()->create());
    Notification::fake();
    config()->set('mail.default', 'smtp');
});

test('invitation delivery tracks queueing and mail service acceptance without claiming inbox delivery', function () {
    $review = plsReview();
    Livewire::test(CollaboratorsPage::class, ['review' => $review])->set('inviteCollaboratorEmail', 'new-person@example.com')->call('shareReview')->assertHasNoErrors();
    $invitation = $review->invitations()->sole();
    expect($invitation->mail_status)->toBe('queued');
    $notice = new ReviewInvitationNotification($invitation);
    Event::dispatch(new NotificationSent(new AnonymousNotifiable, $notice, 'mail', null));
    expect($invitation->fresh()->mail_status)->toBe('accepted_by_mail_service');
    expect($invitation->fresh()->mail_accepted_at)->not->toBeNull();
    expect($invitation->fresh()->accepted_at)->toBeNull();
});

test('unconfigured mail is visible and resend rotates both delivery and acceptance tokens', function () {
    $review = plsReview();
    $component = Livewire::test(CollaboratorsPage::class, ['review' => $review])->set('inviteCollaboratorEmail', 'new-person@example.com')->call('shareReview');
    $invitation = $review->invitations()->sole();
    $old = new ReviewInvitationNotification($invitation);
    config()->set('mail.default', 'array');
    expect($old->shouldSend(new AnonymousNotifiable, 'mail'))->toBeFalse();
    expect($invitation->fresh()->mail_status)->toBe('not_configured');
    config()->set('mail.default', 'smtp');
    $token = $invitation->token;
    $component->call('resendInvitation', $invitation->id)->assertHasNoErrors();
    expect($invitation->fresh()->token)->not->toBe($token);
    expect($old->shouldSend(new AnonymousNotifiable, 'mail'))->toBeFalse();
    $old->failed(new RuntimeException('Old job failed'));
    expect($invitation->fresh()->mail_status)->toBe('queued');
    $this->get(route('pls.invitations.accept', $token))->assertNotFound();
});

test('failed delivery is exposed and revoked invitations do not send queued mail', function () {
    $review = plsReview();
    $component = Livewire::test(CollaboratorsPage::class, ['review' => $review])->set('inviteCollaboratorEmail', 'new-person@example.com')->call('shareReview');
    $invitation = $review->invitations()->sole();
    $notice = new ReviewInvitationNotification($invitation);
    $notice->failed(new RuntimeException('Transport failed'));
    expect($invitation->fresh()->mail_status)->toBe('failed');
    $component->call('revokeInvitation', $invitation->id);
    expect($notice->shouldSend(new AnonymousNotifiable, 'mail'))->toBeFalse();
});

test('viewers cannot resend access or rotate invitation links', function () {
    $review = plsReview();
    $viewer = User::factory()->reviewer()->create();
    $review->memberships()->create(['user_id' => $viewer->id, 'role' => PlsReviewMembershipRole::Viewer]);
    $invitation = $review->invitations()->create(['email' => 'new@example.com', 'role' => PlsReviewMembershipRole::Viewer, 'invited_by' => auth()->id()]);
    $this->actingAs($viewer);
    Livewire::test(CollaboratorsPage::class, ['review' => $review])->call('resendInvitation', $invitation->id)->assertForbidden();
});

test('shared activity records real actors and stays within the review while private conversations stay private', function () {
    $review = plsReview();
    $other = plsReview();
    Finding::factory()->create(['pls_review_id' => $review->id, 'title' => 'Public team finding']);
    Finding::factory()->create(['pls_review_id' => $other->id, 'title' => 'Other review private finding']);
    $activity = ReviewActivity::query()->where('pls_review_id', $review->id)->where('subject_type', 'Finding')->sole();
    expect($activity->user_id)->toBe(auth()->id());
    $before = ReviewActivity::query()->count();
    ReviewAssistantAgent::fake(['Secret private assistant response']);
    Livewire::test(AssistantSidebar::class, ['review' => $review, 'workspaceKey' => 'workflow'])->call('sendPrompt', 'What comes next?');
    expect(ReviewActivity::query()->count())->toBe($before);
    $viewer = User::factory()->reviewer()->create();
    $review->memberships()->create(['user_id' => $viewer->id, 'role' => PlsReviewMembershipRole::Viewer]);
    $this->actingAs($viewer)->get(route('pls.reviews.settings', $review))->assertOk()->assertSee('Public team finding')
        ->assertDontSee('Other review private finding')->assertDontSee('Secret private assistant response');
});

test('mail status changes compare tokens at the write so a stale delivery cannot overwrite resend state', function (string $method) {
    $review = plsReview();
    $record = $review->invitations()->create(['email' => 'new@example.com', 'role' => PlsReviewMembershipRole::Viewer, 'invited_by' => auth()->id(), 'mail_token' => '00000000-0000-4000-8000-000000000001', 'mail_status' => 'queued']);
    $notice = new class($record) extends ReviewMailNotification
    {
        public function __construct(public PlsReviewInvitation $record)
        {
            $this->deliveryToken = '00000000-0000-4000-8000-000000000001';
        }

        public function deliveryRecord(): ?Model
        {
            $this->record->newQuery()->whereKey($this->record->id)->update(['mail_token' => '00000000-0000-4000-8000-000000000002']);

            return $this->record;
        }
    };
    $method === 'failed' ? $notice->failed(null) : $notice->markAccepted();
    expect($record->fresh()->mail_status)->toBe('queued');
})->with(['failed', 'accepted']);

test('an invitation loaded before a resend cannot accept the replacement token', function () {
    $review = plsReview();
    $user = User::factory()->reviewer()->create();
    $stale = $review->invitations()->create(['email' => $user->email, 'role' => PlsReviewMembershipRole::Viewer, 'invited_by' => auth()->id(), 'expires_at' => now()->addDay()]);
    $stale->fresh()->update(['token' => Str::random(64)]);
    try {
        $stale->acceptFor($user);
        $this->fail('A rotated invitation must not accept the old token.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(410);
    }
    expect($review->memberships()->where('user_id', $user->id)->exists())->toBeFalse();
    expect($stale->fresh()->accepted_at)->toBeNull();
    expect($stale->fresh()->acceptFor($user)->user_id)->toBe($user->id);
});
