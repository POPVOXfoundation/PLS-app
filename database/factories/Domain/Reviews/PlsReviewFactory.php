<?php

namespace Database\Factories\Domain\Reviews;

use App\Domain\Institutions\Committee;
use App\Domain\Institutions\Country;
use App\Domain\Institutions\Jurisdiction;
use App\Domain\Institutions\Legislature;
use App\Domain\Reviews\Enums\PlsReviewStatus;
use App\Domain\Reviews\PlsReview;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Reviews\PlsReview>
 */
class PlsReviewFactory extends Factory
{
    protected $model = PlsReview::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(5);

        return [
            'committee_id' => Committee::factory(),
            'legislature_id' => Legislature::factory(),
            'jurisdiction_id' => Jurisdiction::factory(),
            'country_id' => Country::factory(),
            'title' => $title,
            'slug' => Str::slug($title),
            'description' => fake()->paragraph(),
            'status' => PlsReviewStatus::Draft,
            'current_step_number' => 1,
            'start_date' => fake()->dateTimeBetween('-6 months', 'now'),
            'completed_at' => null,
        ];
    }
}
