<?php

namespace Database\Factories\Domain\Analysis;

use App\Domain\Analysis\Enums\FindingType;
use App\Domain\Analysis\Finding;
use App\Domain\Reviews\PlsReview;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Analysis\Finding>
 */
class FindingFactory extends Factory
{
    protected $model = Finding::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pls_review_id' => PlsReview::factory(),
            'title' => fake()->sentence(5),
            'finding_type' => fake()->randomElement(FindingType::cases()),
            'summary' => fake()->paragraph(),
            'detail' => fake()->paragraphs(2, true),
        ];
    }
}
