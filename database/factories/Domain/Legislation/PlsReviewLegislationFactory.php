<?php

namespace Database\Factories\Domain\Legislation;

use App\Domain\Legislation\Legislation;
use App\Domain\Legislation\Enums\ReviewLegislationRelationshipType;
use App\Domain\Legislation\PlsReviewLegislation;
use App\Domain\Reviews\PlsReview;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Legislation\PlsReviewLegislation>
 */
class PlsReviewLegislationFactory extends Factory
{
    protected $model = PlsReviewLegislation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pls_review_id' => PlsReview::factory(),
            'legislation_id' => Legislation::factory(),
            'relationship_type' => fake()->randomElement(ReviewLegislationRelationshipType::cases()),
        ];
    }
}
