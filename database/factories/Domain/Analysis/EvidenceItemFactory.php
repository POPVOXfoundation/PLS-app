<?php

namespace Database\Factories\Domain\Analysis;

use App\Domain\Analysis\Enums\EvidenceType;
use App\Domain\Analysis\EvidenceItem;
use App\Domain\Documents\Document;
use App\Domain\Reviews\PlsReview;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Analysis\EvidenceItem>
 */
class EvidenceItemFactory extends Factory
{
    protected $model = EvidenceItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pls_review_id' => PlsReview::factory(),
            'document_id' => Document::factory(),
            'title' => fake()->sentence(4),
            'evidence_type' => fake()->randomElement(EvidenceType::cases()),
            'description' => fake()->paragraph(),
        ];
    }
}
