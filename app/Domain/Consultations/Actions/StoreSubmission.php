<?php

namespace App\Domain\Consultations\Actions;

use App\Domain\Consultations\Submission;
use App\Domain\Consultations\Validation\StoreSubmissionValidator;
use App\Domain\Reviews\PlsReview;

class StoreSubmission
{
    public function __construct(private StoreSubmissionValidator $validator)
    {
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function store(array $input): PlsReview
    {
        $validated = $this->validator->validate($input);

        Submission::query()->create([
            'pls_review_id' => $validated['pls_review_id'],
            'stakeholder_id' => $validated['stakeholder_id'],
            'document_id' => $validated['document_id'] ?? null,
            'submitted_at' => $validated['submitted_at'] ?? null,
            'summary' => $validated['summary'],
        ]);

        return PlsReview::query()->findOrFail($validated['pls_review_id'])->fresh();
    }
}
