<?php

namespace App\Domain\Stakeholders\Actions;

use App\Domain\Reviews\PlsReview;
use App\Domain\Stakeholders\Stakeholder;
use App\Domain\Stakeholders\Validation\StoreStakeholderValidator;

class StoreStakeholder
{
    public function __construct(private StoreStakeholderValidator $validator)
    {
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function store(array $input): PlsReview
    {
        $validated = $this->validator->validate($input);

        Stakeholder::query()->create([
            'pls_review_id' => $validated['pls_review_id'],
            'name' => $validated['name'],
            'stakeholder_type' => $validated['stakeholder_type'],
            'contact_details' => $this->pruneEmptyValues($validated['contact_details'] ?? null),
        ]);

        return PlsReview::query()->findOrFail($validated['pls_review_id'])->fresh();
    }

    /**
     * @param  array<string, string|null>|null  $contactDetails
     * @return array<string, string>|null
     */
    private function pruneEmptyValues(?array $contactDetails): ?array
    {
        if ($contactDetails === null) {
            return null;
        }

        $filtered = array_filter(
            $contactDetails,
            static fn (?string $value): bool => filled($value),
        );

        return $filtered === [] ? null : $filtered;
    }
}
