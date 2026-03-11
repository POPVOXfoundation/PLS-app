<?php

namespace Database\Factories\Domain\Stakeholders;

use App\Domain\Reviews\PlsReview;
use App\Domain\Stakeholders\Enums\StakeholderType;
use App\Domain\Stakeholders\Stakeholder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Stakeholders\Stakeholder>
 */
class StakeholderFactory extends Factory
{
    protected $model = Stakeholder::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pls_review_id' => PlsReview::factory(),
            'name' => fake()->company(),
            'stakeholder_type' => fake()->randomElement(StakeholderType::cases()),
            'contact_details' => [
                'email' => fake()->companyEmail(),
                'phone' => fake()->phoneNumber(),
            ],
        ];
    }
}
