<?php

namespace Database\Factories\Domain\Reporting;

use App\Domain\Documents\Document;
use App\Domain\Reporting\Enums\ReportStatus;
use App\Domain\Reporting\Enums\ReportType;
use App\Domain\Reporting\Report;
use App\Domain\Reviews\PlsReview;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Reporting\Report>
 */
class ReportFactory extends Factory
{
    protected $model = Report::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pls_review_id' => PlsReview::factory(),
            'title' => fake()->sentence(5),
            'report_type' => fake()->randomElement(ReportType::cases()),
            'status' => fake()->randomElement(ReportStatus::cases()),
            'document_id' => Document::factory(),
            'published_at' => fake()->optional()->dateTimeBetween('-3 months', 'now'),
        ];
    }
}
