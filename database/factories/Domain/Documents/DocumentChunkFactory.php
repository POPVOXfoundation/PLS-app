<?php

namespace Database\Factories\Domain\Documents;

use App\Domain\Documents\Document;
use App\Domain\Documents\DocumentChunk;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Documents\DocumentChunk>
 */
class DocumentChunkFactory extends Factory
{
    protected $model = DocumentChunk::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'chunk_index' => fake()->numberBetween(0, 25),
            'content' => fake()->paragraphs(2, true),
            'token_count' => fake()->numberBetween(50, 500),
            'embedding' => null,
            'metadata' => [
                'page' => fake()->numberBetween(1, 30),
            ],
        ];
    }
}
