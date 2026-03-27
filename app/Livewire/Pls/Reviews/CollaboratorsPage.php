<?php

namespace App\Livewire\Pls\Reviews;

use App\Domain\Reviews\Enums\PlsReviewMembershipRole;
use App\Domain\Reviews\PlsReview;
use App\Domain\Reviews\PlsReviewMembership;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;

class CollaboratorsPage extends Workspace
{
    use AuthorizesRequests;

    protected string $workspace = 'collaborators';

    public string $inviteCollaboratorUserId = '';

    public string $inviteCollaboratorRole = PlsReviewMembershipRole::Editor->value;

    /**
     * @var array<int|string, string>
     */
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
            'collaboratorRoleOptions' => [PlsReviewMembershipRole::Editor],
            'availableCollaborators' => $this->availableCollaborators($review),
            'canManageCollaborators' => auth()->user()?->can('manageCollaborators', $review) ?? false,
        ], $review);
    }

    public function inviteCollaborator(): void
    {
        $this->authorize('manageCollaborators', $this->review);

        validator(
            [
                'inviteCollaboratorUserId' => $this->inviteCollaboratorUserId,
                'inviteCollaboratorRole' => $this->inviteCollaboratorRole,
            ],
            [
                'inviteCollaboratorUserId' => [
                    'required',
                    'integer',
                    Rule::exists('users', 'id'),
                    Rule::unique('pls_review_memberships', 'user_id')
                        ->where(fn ($query) => $query->where('pls_review_id', $this->review->id)),
                ],
                'inviteCollaboratorRole' => [
                    'required',
                    Rule::in([PlsReviewMembershipRole::Editor->value]),
                ],
            ],
            [
                'inviteCollaboratorUserId.required' => __('Select a user to invite.'),
                'inviteCollaboratorUserId.unique' => __('That user is already a collaborator on this review.'),
                'inviteCollaboratorRole.in' => __('Only editor access can be granted here.'),
            ],
        )->validate();

        $this->review->memberships()->create([
            'user_id' => (int) $this->inviteCollaboratorUserId,
            'role' => $this->inviteCollaboratorRole,
            'invited_by' => auth()->id(),
        ]);

        $this->review = $this->loadReview();
        $this->syncCollaboratorRoles($this->review, true);
        $this->inviteCollaboratorUserId = '';
        $this->inviteCollaboratorRole = PlsReviewMembershipRole::Editor->value;
        $this->resetValidation(['inviteCollaboratorUserId', 'inviteCollaboratorRole']);

        $this->dispatch('review-workspace-updated', status: __('Collaborator invited to this review.'));
    }

    public function updateCollaboratorRole(int $membershipId): void
    {
        $this->authorize('manageCollaborators', $this->review);

        $membership = $this->review->memberships()
            ->whereKey($membershipId)
            ->first();

        if ($membership === null || $membership->role === PlsReviewMembershipRole::Owner) {
            return;
        }

        validator(
            [
                'role' => $this->collaboratorRoles[$membershipId] ?? null,
            ],
            [
                'role' => ['required', Rule::in([PlsReviewMembershipRole::Editor->value])],
            ],
            [
                'role.in' => __('Review ownership cannot be reassigned from this screen.'),
            ],
        )->validate();

        $membership->update([
            'role' => $this->collaboratorRoles[$membershipId],
        ]);

        $this->review = $this->loadReview();
        $this->syncCollaboratorRoles($this->review, true);

        $this->dispatch('review-workspace-updated', status: __('Collaborator role updated.'));
    }

    public function removeCollaborator(int $membershipId): void
    {
        $this->authorize('manageCollaborators', $this->review);

        $membership = $this->review->memberships()
            ->whereKey($membershipId)
            ->first();

        if ($membership === null || $membership->role === PlsReviewMembershipRole::Owner) {
            return;
        }

        $membership->delete();

        unset($this->collaboratorRoles[$membershipId]);

        $this->review = $this->loadReview();
        $this->syncCollaboratorRoles($this->review, true);

        $this->dispatch('review-workspace-updated', status: __('Collaborator removed from this review.'));
    }

    private function loadReview(): PlsReview
    {
        return PlsReview::query()
            ->with([
                'memberships.user',
                'memberships.invitedBy',
            ])
            ->findOrFail($this->review->getKey());
    }

    /**
     * @return EloquentCollection<int, User>
     */
    private function availableCollaborators(PlsReview $review): EloquentCollection
    {
        return User::query()
            ->whereNotIn('id', $review->memberships->pluck('user_id'))
            ->orderBy('name')
            ->get();
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
