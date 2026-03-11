<?php

namespace Database\Factories\Domain\Documents;

use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Reviews\PlsReview;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Documents\Document>
 */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

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
            'document_type' => fake()->randomElement(DocumentType::cases()),
            'storage_path' => 'documents/'.fake()->uuid().'.pdf',
            'mime_type' => fake()->randomElement(['application/pdf', 'text/plain']),
            'file_size' => fake()->numberBetween(10_000, 5_000_000),
            'summary' => fake()->paragraph(),
            'metadata' => [
                'source' => fake()->url(),
                'language' => fake()->randomElement(['en', 'fr']),
            ],
        ];
    }
}
