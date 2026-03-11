<?php

namespace Database\Factories\Domain\Institutions;

use App\Domain\Institutions\Committee;
use App\Domain\Institutions\Legislature;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Institutions\Committee>
 */
class CommitteeFactory extends Factory
{
    protected $model = Committee::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->randomElement([
            'Justice Committee',
            'Public Accounts Committee',
            'Governance and Oversight Committee',
            'Legislation Review Committee',
        ]);

        return [
            'legislature_id' => Legislature::factory(),
            'name' => $name,
            'slug' => Str::slug($name.' '.fake()->unique()->word()),
            'description' => fake()->sentence(),
        ];
    }
}
