<?php

namespace Database\Factories\Domain\Institutions;

use App\Domain\Institutions\Country;
use App\Domain\Institutions\Enums\JurisdictionType;
use App\Domain\Institutions\Jurisdiction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Institutions\Jurisdiction>
 */
class JurisdictionFactory extends Factory
{
    protected $model = Jurisdiction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->city().' '.fake()->randomElement(['State', 'Province', 'Region']);

        return [
            'country_id' => Country::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'jurisdiction_type' => fake()->randomElement(JurisdictionType::cases()),
            'parent_id' => null,
        ];
    }
}
