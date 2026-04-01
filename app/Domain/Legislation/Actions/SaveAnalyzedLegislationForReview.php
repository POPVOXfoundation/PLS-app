<?php

namespace App\Domain\Legislation\Actions;

use App\Domain\Legislation\Legislation;
use App\Domain\Legislation\Validation\SaveAnalyzedLegislationValidator;
use App\Domain\Reviews\PlsReview;
use Illuminate\Database\DatabaseManager;

class SaveAnalyzedLegislationForReview
{
    public function __construct(
        private DatabaseManager $database,
        private SaveAnalyzedLegislationValidator $validator,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function save(array $input): PlsReview
    {
        $validated = $this->validator->validate($input);

        return $this->database->transaction(function () use ($validated): PlsReview {
            $review = PlsReview::query()
                ->lockForUpdate()
                ->findOrFail($validated['pls_review_id']);

            $attributes = [
                'title' => $validated['title'],
                'short_title' => $validated['short_title'] ?? null,
                'legislation_type' => $validated['legislation_type'],
                'date_enacted' => $validated['date_enacted'] ?? null,
                'summary' => $validated['summary'] ?? null,
                'source_document_id' => $validated['source_document_id'] ?? null,
            ];

            if (($validated['save_mode'] ?? 'create') === 'update') {
                $legislation = Legislation::query()
                    ->whereKey($validated['legislation_id'])
                    ->where('jurisdiction_id', $review->jurisdiction_id)
                    ->firstOrFail();

                $legislation->fill($attributes)->save();
            } else {
                $legislation = Legislation::query()->create([
                    'jurisdiction_id' => $review->jurisdiction_id,
                    ...$attributes,
                ]);
            }

            $review->legislation()->syncWithoutDetaching([
                $legislation->id => [
                    'relationship_type' => $validated['relationship_type'],
                ],
            ]);

            $review->legislation()->updateExistingPivot($legislation->id, [
                'relationship_type' => $validated['relationship_type'],
            ]);

            return $review->fresh([
                'legislation',
                'documents',
            ]);
        });
    }
}
