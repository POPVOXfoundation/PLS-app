<?php

namespace App\Domain\Stakeholders\Actions;

use App\Domain\Reviews\PlsReview;
use App\Domain\Stakeholders\ImplementingAgency;
use App\Domain\Stakeholders\Validation\UpdateImplementingAgencyValidator;

class UpdateImplementingAgency
{
    public function __construct(private UpdateImplementingAgencyValidator $validator) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(array $input): PlsReview
    {
        $validated = $this->validator->validate($input);

        $agency = ImplementingAgency::query()->findOrFail($validated['agency_id']);

        $agency->update([
            'name' => $validated['name'],
            'agency_type' => $validated['agency_type'],
        ]);

        return PlsReview::query()->findOrFail($validated['pls_review_id'])->fresh();
    }
}
