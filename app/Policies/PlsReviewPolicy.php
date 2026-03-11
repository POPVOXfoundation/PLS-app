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
        return $user->canViewPlsReviews();
    }

    public function create(User $user): bool
    {
        return $user->canManagePlsReviews();
    }

    public function update(User $user, PlsReview $plsReview): bool
    {
        return $user->canManagePlsReviews();
    }

    public function delete(User $user, PlsReview $plsReview): bool
    {
        return $user->canManagePlsReviews();
    }
}
