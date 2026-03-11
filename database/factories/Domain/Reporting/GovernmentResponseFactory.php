<?php

namespace Database\Factories\Domain\Reporting;

use App\Domain\Documents\Document;
use App\Domain\Reporting\Enums\GovernmentResponseStatus;
use App\Domain\Reporting\GovernmentResponse;
use App\Domain\Reporting\Report;
use App\Domain\Reviews\PlsReview;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Reporting\GovernmentResponse>
 */
class GovernmentResponseFactory extends Factory
{
    protected $model = GovernmentResponse::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pls_review_id' => PlsReview::factory(),
            'report_id' => Report::factory(),
            'document_id' => Document::factory(),
            'response_status' => fake()->randomElement(GovernmentResponseStatus::cases()),
            'received_at' => fake()->optional()->dateTimeBetween('-2 months', 'now'),
            'summary' => fake()->paragraph(),
        ];
    }
}
