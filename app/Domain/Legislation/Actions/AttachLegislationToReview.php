<?php

namespace App\Domain\Legislation\Actions;

use App\Domain\Reviews\PlsReview;
use App\Domain\Legislation\Validation\AttachLegislationToReviewValidator;
use Illuminate\Database\DatabaseManager;

class AttachLegislationToReview
{
    public function __construct(
        private DatabaseManager $database,
        private AttachLegislationToReviewValidator $validator,
    ) {
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function attach(array $input): PlsReview
    {
        $validated = $this->validator->validate($input);

        return $this->database->transaction(function () use ($validated): PlsReview {
            $review = PlsReview::query()
                ->lockForUpdate()
                ->findOrFail($validated['pls_review_id']);

            $review->legislation()->attach($validated['legislation_id'], [
                'relationship_type' => $validated['relationship_type'],
            ]);

            return $review->fresh([
                'legislation',
                'legislationObjectives',
            ]);
        });
    }
}
