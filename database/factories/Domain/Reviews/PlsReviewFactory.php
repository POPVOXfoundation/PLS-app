<?php

namespace Database\Factories\Domain\Reviews;

use App\Domain\Institutions\Country;
use App\Domain\Institutions\Jurisdiction;
use App\Domain\Institutions\Legislature;
use App\Domain\Institutions\ReviewGroup;
use App\Domain\Reviews\Enums\PlsReviewStatus;
use App\Domain\Reviews\PlsReview;
use App\Models\User;
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
        $country = Country::factory();
        $jurisdiction = Jurisdiction::factory()->for($country);
        $legislature = Legislature::factory()->for($jurisdiction);
        $reviewGroup = ReviewGroup::factory()
            ->for($country)
            ->for($jurisdiction)
            ->for($legislature);

        return [
            'review_group_id' => $reviewGroup,
            'legislature_id' => $legislature,
            'jurisdiction_id' => $jurisdiction,
            'country_id' => $country,
            'created_by' => User::factory(),
            'title' => $title,
            'slug' => Str::slug($title),
            'description' => fake()->paragraph(),
            'status' => PlsReviewStatus::Draft,
            'current_step_number' => 1,
            'start_date' => fake()->dateTimeBetween('-6 months', 'now'),
            'completed_at' => null,
        ];
    }

    public function withoutReviewGroup(): static
    {
        return $this->state(fn (): array => [
            'review_group_id' => null,
        ]);
    }
}
