<?php

namespace App\Livewire\Pls\Reviews;

use App\Domain\Reviews\Enums\PlsReviewMembershipRole;
use App\Domain\Reviews\PlsReview;
use App\Domain\Reviews\PlsReviewMembership;
use App\Domain\Reviews\ReviewActivity;
use App\Jobs\SendReviewInvitation;
use App\Models\User;
use App\Support\Toast;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CollaboratorsPage extends Workspace
{
    use AuthorizesRequests;

    protected string $workspace = 'settings';

    public string $inviteCollaboratorEmail = '';

    public string $inviteCollaboratorRole = PlsReviewMembershipRole::Contributor->value;

    /** @var array<int, array{id: int, name: string, email: string}> */
    public array $emailMatches = [];

    /** @var array<int|string, string> */
    public array $collaboratorRoles = [];

    public function mount(PlsReview $review): void
    {
        parent::mount($review);
    }

    public function render(): View
    {
        $review = $this->loadReview();
        $this->syncCollaboratorRoles($review);

        return $this->renderWorkspaceView('livewire.pls.reviews.collaborators-page', [
            'review' => $review,
            'activities' => ReviewActivity::where('pls_review_id', $review->id)->with('user')->latest('id')->limit(100)->get(),
            'collaboratorRoleOptions' => [PlsReviewMembershipRole::Contributor, PlsReviewMembershipRole::Viewer],
            'canManageCollaborators' => auth()->user()?->can('manageCollaborators', $review) ?? false,
        ], $review);
    }

    public function updatedInviteCollaboratorEmail(): void
    {
        // Invitations use an exact address; do not expose the site's user directory.
        $this->emailMatches = [];
    }

    public function shareReview(): void
    {
        $this->authorize('manageCollaborators', $this->review);

        validator(
            [
                'inviteCollaboratorEmail' => $this->inviteCollaboratorEmail,
                'inviteCollaboratorRole' => $this->inviteCollaboratorRole,
            ],
            [
                'inviteCollaboratorEmail' => ['required', 'email'],
                'inviteCollaboratorRole' => ['required', Rule::in([
                    PlsReviewMembershipRole::Contributor->value,
                    PlsReviewMembershipRole::Viewer->value,
                ])],
            ],
            [
                'inviteCollaboratorEmail.required' => __('Enter an email address.'),
                'inviteCollaboratorEmail.email' => __('Enter a valid email address.'),
                'inviteCollaboratorRole.in' => __('Only contributor or viewer access can be granted here.'),
            ],
        )->validate();

        $email = mb_strtolower(trim($this->inviteCollaboratorEmail));
        if ($this->review->memberships()->whereHas('user', fn ($q) => $q->whereRaw('LOWER(email) = ?', [$email]))->exists()) {
            $this->addError('inviteCollaboratorEmail', __('This person already has access. See the team below.'));

            return;
        }
        // Serialize duplicate invitation attempts within this review.
        $invitation = DB::transaction(function () use ($email) {
            $this->review->newQuery()->whereKey($this->review->id)->lockForUpdate()->firstOrFail();
            if ($this->review->pendingInvitations()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
                return null;
            }

            return $this->review->invitations()->create([
                'email' => $email, 'role' => $this->inviteCollaboratorRole,
                'invited_by' => auth()->id(), 'mail_status' => 'queued',
                'mail_queued_at' => now(), 'expires_at' => now()->addDays(14),
            ]);
        });
        if (! $invitation) {
            $this->addError('inviteCollaboratorEmail', __('An invitation is already pending. Use Resend or copy its link below.'));

            return;
        }
        SendReviewInvitation::dispatch($invitation->id, $invitation->token);
        $toast = Toast::success(__('Invitation queued'), __('Watch the email status below. Membership begins when the invitation is accepted.'));

        $this->review = $this->loadReview();
        $this->syncCollaboratorRoles($this->review, true);
        $this->inviteCollaboratorEmail = '';
        $this->inviteCollaboratorRole = PlsReviewMembershipRole::Contributor->value;
        $this->emailMatches = [];
        $this->resetValidation(['inviteCollaboratorEmail', 'inviteCollaboratorRole']);

        $this->dispatchWorkspaceToast($toast);
    }

    public function updateCollaboratorRole(int $membershipId): void
    {
        $this->authorize('manageCollaborators', $this->review);

        $membership = $this->review->memberships()->whereKey($membershipId)->first();

        if ($membership === null || $membership->role === PlsReviewMembershipRole::Owner) {
            return;
        }

        validator(
            ['role' => $this->collaboratorRoles[$membershipId] ?? null],
            ['role' => ['required', Rule::in([
                PlsReviewMembershipRole::Contributor->value,
                PlsReviewMembershipRole::Viewer->value,
            ])]],
            ['role.in' => __('Review ownership cannot be reassigned from this screen.')],
        )->validate();

        $membership->update(['role' => $this->collaboratorRoles[$membershipId]]);

        $this->review = $this->loadReview();
        $this->syncCollaboratorRoles($this->review, true);

        $this->dispatchWorkspaceToast(Toast::success(
            __('Collaborator updated'),
            __('Collaborator role updated.'),
        ));
    }

    public function removeCollaborator(int $membershipId): void
    {
        $this->authorize('manageCollaborators', $this->review);

        $membership = $this->review->memberships()->whereKey($membershipId)->first();

        if ($membership === null || $membership->role === PlsReviewMembershipRole::Owner) {
            return;
        }

        $membership->delete();
        unset($this->collaboratorRoles[$membershipId]);

        $this->review = $this->loadReview();
        $this->syncCollaboratorRoles($this->review, true);

        $this->dispatchWorkspaceToast(Toast::success(
            __('Collaborator removed'),
            __('Collaborator removed from this review.'),
        ));
    }

    public function resendInvitation(int $invitationId): void
    {
        $this->authorize('manageCollaborators', $this->review);
        $invitation = $this->review->pendingInvitations()->findOrFail($invitationId);
        if ($invitation->mail_queued_at?->gt(now()->subMinute())) {
            $this->addError('inviteCollaboratorEmail', __('Please wait one minute before resending.'));

            return;
        }
        $invitation->update(['token' => Str::random(64), 'mail_status' => 'queued', 'mail_sent_at' => null, 'mail_queued_at' => now(), 'expires_at' => now()->addDays(14)]);
        SendReviewInvitation::dispatch($invitation->id, $invitation->token);
        $this->dispatchWorkspaceToast(Toast::success(__('Invitation queued'), __('The new link replaces the previous invitation link.')));
    }

    public function revokeInvitation(int $invitationId): void
    {
        $this->authorize('manageCollaborators', $this->review);

        $invitation = $this->review->pendingInvitations()->whereKey($invitationId)->first();

        if ($invitation === null) {
            return;
        }

        $invitation->delete();

        $this->review = $this->loadReview();

        $this->dispatchWorkspaceToast(Toast::success(
            __('Invitation revoked'),
            __('Invitation revoked.'),
        ));
    }

    private function loadReview(): PlsReview
    {
        return PlsReview::query()
            ->with([
                'memberships.user',
                'memberships.invitedBy',
                'pendingInvitations.invitedBy',
                'invitations',
            ])
            ->findOrFail($this->review->getKey());
    }

    private function syncCollaboratorRoles(PlsReview $review, bool $replace = false): void
    {
        $roles = $review->memberships
            ->mapWithKeys(fn (PlsReviewMembership $membership): array => [
                $membership->id => $membership->role->value,
            ])
            ->all();

        if ($replace) {
            $this->collaboratorRoles = $roles;

            return;
        }

        foreach ($roles as $membershipId => $role) {
            if (! array_key_exists($membershipId, $this->collaboratorRoles)) {
                $this->collaboratorRoles[$membershipId] = $role;
            }
        }
    }
}
