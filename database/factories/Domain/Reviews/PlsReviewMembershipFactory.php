<?php

namespace Database\Factories\Domain\Reviews;

use App\Domain\Reviews\Enums\PlsReviewMembershipRole;
use App\Domain\Reviews\PlsReview;
use App\Domain\Reviews\PlsReviewMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Reviews\PlsReviewMembership>
 */
class PlsReviewMembershipFactory extends Factory
{
    protected $model = PlsReviewMembership::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pls_review_id' => PlsReview::factory(),
            'user_id' => User::factory(),
            'role' => PlsReviewMembershipRole::Contributor,
            'invited_by' => User::factory(),
        ];
    }

    public function owner(): static
    {
        return $this->state(fn (): array => [
            'role' => PlsReviewMembershipRole::Owner,
        ]);
    }
}
