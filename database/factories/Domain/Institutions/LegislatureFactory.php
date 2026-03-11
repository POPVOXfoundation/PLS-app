<?php

namespace Database\Factories\Domain\Institutions;

use App\Domain\Institutions\Enums\LegislatureType;
use App\Domain\Institutions\Jurisdiction;
use App\Domain\Institutions\Legislature;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Institutions\Legislature>
 */
class LegislatureFactory extends Factory
{
    protected $model = Legislature::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->randomElement(['Parliament', 'Congress', 'National Assembly', 'General Assembly']);

        return [
            'jurisdiction_id' => Jurisdiction::factory(),
            'name' => $name,
            'slug' => Str::slug($name.' '.fake()->unique()->word()),
            'legislature_type' => fake()->randomElement(LegislatureType::cases()),
            'description' => fake()->sentence(),
        ];
    }
}
