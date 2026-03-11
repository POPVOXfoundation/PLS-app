<?php

namespace App\Domain\Legislation\Actions;

use App\Domain\Legislation\Legislation;
use App\Domain\Legislation\Validation\CreateLegislationValidator;
use App\Domain\Reviews\PlsReview;
use Illuminate\Database\DatabaseManager;

class CreateLegislationForReview
{
    public function __construct(
        private DatabaseManager $database,
        private CreateLegislationValidator $validator,
    ) {
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): PlsReview
    {
        $validated = $this->validator->validate($input);

        return $this->database->transaction(function () use ($validated): PlsReview {
            $review = PlsReview::query()
                ->lockForUpdate()
                ->findOrFail($validated['pls_review_id']);

            $legislation = Legislation::query()->create([
                'jurisdiction_id' => $review->jurisdiction_id,
                'title' => $validated['title'],
                'short_title' => $validated['short_title'] ?? null,
                'legislation_type' => $validated['legislation_type'],
                'date_enacted' => $validated['date_enacted'] ?? null,
                'summary' => $validated['summary'] ?? null,
            ]);

            $review->legislation()->attach($legislation->id, [
                'relationship_type' => $validated['relationship_type'],
            ]);

            return $review->fresh([
                'legislation',
                'legislationObjectives',
            ]);
        });
    }
}
