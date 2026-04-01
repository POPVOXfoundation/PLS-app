<?php

namespace App\Livewire\Pls\Reviews;

use App\Domain\Reviews\Enums\PlsReviewMembershipRole;
use App\Domain\Reviews\PlsReview;
use App\Domain\Reviews\PlsReviewMembership;
use App\Models\User;
use App\Notifications\ReviewInvitationNotification;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CollaboratorsPage extends Workspace
{
    use AuthorizesRequests;

    protected string $workspace = 'collaborators';

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
            'collaboratorRoleOptions' => [PlsReviewMembershipRole::Contributor, PlsReviewMembershipRole::Viewer],
            'canManageCollaborators' => auth()->user()?->can('manageCollaborators', $review) ?? false,
        ], $review);
    }

    public function updatedInviteCollaboratorEmail(): void
    {
        $query = trim($this->inviteCollaboratorEmail);

        if (mb_strlen($query) < 2) {
            $this->emailMatches = [];

            return;
        }

        $existingIds = $this->review->memberships->pluck('user_id')->all();

        $this->emailMatches = User::query()
            ->where(fn ($q) => $q->where('email', 'like', "%{$query}%")->orWhere('name', 'like', "%{$query}%"))
            ->whereNotIn('id', $existingIds)
            ->limit(5)
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email])
            ->values()
            ->all();
    }

    public function selectMatch(int $userId): void
    {
        $user = User::find($userId);

        if ($user) {
            $this->inviteCollaboratorEmail = $user->email;
            $this->emailMatches = [];
        }
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

        $existingUser = User::where('email', $this->inviteCollaboratorEmail)->first();

        if ($existingUser) {
            $alreadyMember = $this->review->memberships()->where('user_id', $existingUser->id)->exists();

            if ($alreadyMember) {
                $this->addError('inviteCollaboratorEmail', __('That user is already a collaborator on this review.'));

                return;
            }

            $this->review->memberships()->create([
                'user_id' => $existingUser->id,
                'role' => $this->inviteCollaboratorRole,
                'invited_by' => auth()->id(),
            ]);

            $statusMessage = __('Access granted.');
        } else {
            $alreadyInvited = $this->review->pendingInvitations()
                ->where('email', $this->inviteCollaboratorEmail)
                ->exists();

            if ($alreadyInvited) {
                $this->addError('inviteCollaboratorEmail', __('An invitation is already pending for that email.'));

                return;
            }

            $invitation = $this->review->invitations()->create([
                'email' => $this->inviteCollaboratorEmail,
                'role' => $this->inviteCollaboratorRole,
                'invited_by' => auth()->id(),
            ]);

            Notification::route('mail', $invitation->email)
                ->notify(new ReviewInvitationNotification($invitation));

            $statusMessage = __('Invitation sent.');
        }

        $this->review = $this->loadReview();
        $this->syncCollaboratorRoles($this->review, true);
        $this->inviteCollaboratorEmail = '';
        $this->inviteCollaboratorRole = PlsReviewMembershipRole::Contributor->value;
        $this->emailMatches = [];
        $this->resetValidation(['inviteCollaboratorEmail', 'inviteCollaboratorRole']);

        $this->dispatch('review-workspace-updated', status: $statusMessage);
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

        $this->dispatch('review-workspace-updated', status: __('Collaborator role updated.'));
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

        $this->dispatch('review-workspace-updated', status: __('Collaborator removed from this review.'));
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

        $this->dispatch('review-workspace-updated', status: __('Invitation revoked.'));
    }

    private function loadReview(): PlsReview
    {
        return PlsReview::query()
            ->with([
                'memberships.user',
                'memberships.invitedBy',
                'pendingInvitations.invitedBy',
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
