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
            'contact_details' => $this->contactDetailsWithOrganization(
                $stakeholder->contact_details ?? [],
                $validated['contact_details']['organization'] ?? null,
            ),
        ]);

        return PlsReview::query()->findOrFail($validated['pls_review_id'])->fresh();
    }

    /**
     * @param  array<string, string|null>  $contactDetails
     * @return array<string, string>|null
     */
    private function contactDetailsWithOrganization(array $contactDetails, ?string $organization): ?array
    {
        if (filled($organization)) {
            $contactDetails['organization'] = $organization;
        } else {
            unset($contactDetails['organization']);
        }

        $filtered = array_filter(
            $contactDetails,
            static fn (?string $value): bool => filled($value),
        );

        return $filtered === [] ? null : $filtered;
    }
}
