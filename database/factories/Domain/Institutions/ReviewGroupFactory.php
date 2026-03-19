<?php

namespace Database\Factories\Domain\Institutions;

use App\Domain\Institutions\Country;
use App\Domain\Institutions\Enums\ReviewGroupType;
use App\Domain\Institutions\Jurisdiction;
use App\Domain\Institutions\Legislature;
use App\Domain\Institutions\ReviewGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Institutions\ReviewGroup>
 */
class ReviewGroupFactory extends Factory
{
    protected $model = ReviewGroup::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $country = Country::factory();
        $jurisdiction = Jurisdiction::factory()->for($country);
        $legislature = Legislature::factory()->for($jurisdiction);

        return [
            'name' => fake()->unique()->company().' Review Group',
            'type' => fake()->randomElement(ReviewGroupType::cases()),
            'country_id' => $country,
            'jurisdiction_id' => $jurisdiction,
            'legislature_id' => $legislature,
        ];
    }
}
