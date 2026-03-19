<?php

namespace App\Policies;

use App\Domain\Reviews\PlsReview;
use App\Models\User;

class PlsReviewPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canViewPlsReviews();
    }

    public function view(User $user, PlsReview $plsReview): bool
    {
        return $user->canViewPlsReviews() && $plsReview->canBeViewedBy($user);
    }

    public function create(User $user): bool
    {
        return $user->canManagePlsReviews();
    }

    public function update(User $user, PlsReview $plsReview): bool
    {
        return $plsReview->canBeUpdatedBy($user);
    }

    public function manageCollaborators(User $user, PlsReview $plsReview): bool
    {
        return $plsReview->canManageCollaborators($user);
    }

    public function delete(User $user, PlsReview $plsReview): bool
    {
        return $plsReview->canManageCollaborators($user);
    }
}
