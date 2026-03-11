<?php

namespace Database\Factories\Domain\Reviews;

use App\Domain\Reviews\Enums\PlsStepStatus;
use App\Domain\Reviews\PlsReview;
use App\Domain\Reviews\PlsReviewStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Reviews\PlsReviewStep>
 */
class PlsReviewStepFactory extends Factory
{
    protected $model = PlsReviewStep::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pls_review_id' => PlsReview::factory(),
            'step_number' => fake()->numberBetween(1, 11),
            'step_key' => fake()->randomElement([
                'define_scope',
                'background_and_data_plan',
                'stakeholder_plan',
                'implementation_review',
                'consultations',
                'analysis',
                'draft_report',
                'dissemination',
                'government_response',
                'follow_up',
                'evaluation',
            ]),
            'status' => PlsStepStatus::Pending,
            'started_at' => null,
            'completed_at' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
