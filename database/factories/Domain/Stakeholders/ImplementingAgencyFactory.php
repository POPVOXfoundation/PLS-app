<?php

namespace Database\Factories\Domain\Stakeholders;

use App\Domain\Reviews\PlsReview;
use App\Domain\Stakeholders\Enums\ImplementingAgencyType;
use App\Domain\Stakeholders\ImplementingAgency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Stakeholders\ImplementingAgency>
 */
class ImplementingAgencyFactory extends Factory
{
    protected $model = ImplementingAgency::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pls_review_id' => PlsReview::factory(),
            'name' => fake()->company().' '.fake()->randomElement(['Ministry', 'Authority', 'Department']),
            'agency_type' => fake()->randomElement(ImplementingAgencyType::cases()),
        ];
    }
}
