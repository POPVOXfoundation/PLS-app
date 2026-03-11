<?php

namespace Database\Factories\Domain\Consultations;

use App\Domain\Consultations\Submission;
use App\Domain\Documents\Document;
use App\Domain\Reviews\PlsReview;
use App\Domain\Stakeholders\Stakeholder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Consultations\Submission>
 */
class SubmissionFactory extends Factory
{
    protected $model = Submission::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pls_review_id' => PlsReview::factory(),
            'stakeholder_id' => Stakeholder::factory(),
            'document_id' => Document::factory(),
            'submitted_at' => fake()->dateTimeBetween('-6 months', 'now'),
            'summary' => fake()->paragraph(),
        ];
    }
}
