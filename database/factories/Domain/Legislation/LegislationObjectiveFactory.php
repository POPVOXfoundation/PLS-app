<?php

namespace Database\Factories\Domain\Legislation;

use App\Domain\Legislation\Legislation;
use App\Domain\Legislation\LegislationObjective;
use App\Domain\Reviews\PlsReview;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Legislation\LegislationObjective>
 */
class LegislationObjectiveFactory extends Factory
{
    protected $model = LegislationObjective::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'legislation_id' => Legislation::factory(),
            'pls_review_id' => PlsReview::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
        ];
    }
}
