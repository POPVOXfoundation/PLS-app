<?php

namespace Database\Factories\Domain\Institutions;

use App\Domain\Institutions\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Institutions\Country>
 */
class CountryFactory extends Factory
{
    protected $model = Country::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->country(),
            'iso2' => fake()->unique()->regexify('[A-Z]{2}'),
            'iso3' => fake()->unique()->regexify('[A-Z]{3}'),
            'default_locale' => fake()->randomElement(['en', 'en_US', 'en_GB']),
        ];
    }
}
