<?php

namespace Database\Factories\Domain\Documents;

use App\Domain\Documents\AssistantSourceDocument;
use App\Domain\Documents\Enums\AssistantSourceScope;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssistantSourceDocument>
 */
class AssistantSourceDocumentFactory extends Factory
{
    protected $model = AssistantSourceDocument::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'scope' => AssistantSourceScope::Global,
            'country_id' => null,
            'jurisdiction_id' => null,
            'legislature_id' => null,
            'pls_review_id' => null,
            'storage_path' => 'assistant-sources/'.fake()->uuid().'.pdf',
            'mime_type' => fake()->randomElement(['application/pdf', 'text/plain']),
            'file_size' => fake()->numberBetween(10_000, 5_000_000),
            'summary' => fake()->sentence(),
            'content' => fake()->paragraphs(3, true),
            'metadata' => [
                'disk' => 'local',
                'original_name' => fake()->slug().'.pdf',
            ],
        ];
    }
}
