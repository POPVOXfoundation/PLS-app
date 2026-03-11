<?php

namespace Database\Factories\Domain\Consultations;

use App\Domain\Consultations\Consultation;
use App\Domain\Consultations\Enums\ConsultationType;
use App\Domain\Documents\Document;
use App\Domain\Reviews\PlsReview;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Consultations\Consultation>
 */
class ConsultationFactory extends Factory
{
    protected $model = Consultation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pls_review_id' => PlsReview::factory(),
            'title' => fake()->sentence(4),
            'consultation_type' => fake()->randomElement(ConsultationType::cases()),
            'held_at' => fake()->dateTimeBetween('-6 months', 'now'),
            'summary' => fake()->paragraph(),
            'document_id' => Document::factory(),
        ];
    }
}
