<?php

namespace Database\Factories\Domain\Analysis;

use App\Domain\Analysis\Enums\RecommendationType;
use App\Domain\Analysis\Finding;
use App\Domain\Analysis\Recommendation;
use App\Domain\Reviews\PlsReview;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Analysis\Recommendation>
 */
class RecommendationFactory extends Factory
{
    protected $model = Recommendation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pls_review_id' => PlsReview::factory(),
            'finding_id' => Finding::factory(),
            'title' => fake()->sentence(5),
            'description' => fake()->paragraph(),
            'recommendation_type' => fake()->randomElement(RecommendationType::cases()),
        ];
    }
}
