<?php

namespace Database\Factories\Domain\Legislation;

use App\Domain\Institutions\Jurisdiction;
use App\Domain\Legislation\Enums\LegislationType;
use App\Domain\Legislation\Legislation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Legislation\Legislation>
 */
class LegislationFactory extends Factory
{
    protected $model = Legislation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(4);

        return [
            'jurisdiction_id' => Jurisdiction::factory(),
            'title' => $title,
            'short_title' => fake()->optional()->words(2, true),
            'legislation_type' => fake()->randomElement(LegislationType::cases()),
            'date_enacted' => fake()->dateTimeBetween('-10 years', '-1 month'),
            'summary' => fake()->paragraph(),
        ];
    }
}
