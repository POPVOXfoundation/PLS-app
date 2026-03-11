<?php

namespace App\Domain\Stakeholders\Actions;

use App\Domain\Reviews\PlsReview;
use App\Domain\Stakeholders\ImplementingAgency;
use App\Domain\Stakeholders\Validation\StoreImplementingAgencyValidator;

class StoreImplementingAgency
{
    public function __construct(private StoreImplementingAgencyValidator $validator)
    {
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function store(array $input): PlsReview
    {
        $validated = $this->validator->validate($input);

        ImplementingAgency::query()->create([
            'pls_review_id' => $validated['pls_review_id'],
            'name' => $validated['name'],
            'agency_type' => $validated['agency_type'],
        ]);

        return PlsReview::query()->findOrFail($validated['pls_review_id'])->fresh();
    }
}
