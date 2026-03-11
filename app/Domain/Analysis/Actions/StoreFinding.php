<?php

namespace App\Domain\Analysis\Actions;

use App\Domain\Analysis\Finding;
use App\Domain\Analysis\Validation\StoreFindingValidator;
use App\Domain\Reviews\PlsReview;

class StoreFinding
{
    public function __construct(private StoreFindingValidator $validator)
    {
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function store(array $input): PlsReview
    {
        $validated = $this->validator->validate($input);

        Finding::query()->create([
            'pls_review_id' => $validated['pls_review_id'],
            'title' => $validated['title'],
            'finding_type' => $validated['finding_type'],
            'summary' => $validated['summary'] ?? null,
            'detail' => $validated['detail'] ?? null,
        ]);

        return PlsReview::query()->findOrFail($validated['pls_review_id'])->fresh('findings');
    }
}
