<?php

namespace App\Domain\Stakeholders\Actions;

use App\Domain\Reviews\PlsReview;
use App\Domain\Stakeholders\Stakeholder;
use App\Domain\Stakeholders\Validation\UpdateStakeholderValidator;

class UpdateStakeholder
{
    public function __construct(private UpdateStakeholderValidator $validator) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(array $input): PlsReview
    {
        $validated = $this->validator->validate($input);

        $stakeholder = Stakeholder::query()->findOrFail($validated['stakeholder_id']);

        $stakeholder->update([
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
