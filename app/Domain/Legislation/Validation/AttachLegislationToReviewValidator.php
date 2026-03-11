<?php

namespace App\Domain\Legislation\Validation;

use App\Domain\Legislation\Enums\ReviewLegislationRelationshipType;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AttachLegislationToReviewValidator
{
    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     pls_review_id: int,
     *     legislation_id: int,
     *     relationship_type: string
     * }
     */
    public function validate(array $input): array
    {
        return Validator::make(
            $input,
            $this->rules($input),
            $this->messages(),
            $this->attributes(),
        )->validate();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, array<int, \Illuminate\Contracts\Validation\ValidationRule|\Illuminate\Contracts\Validation\Rule|string>>
     */
    public function rules(array $input = []): array
    {
        return [
            'pls_review_id' => ['required', 'integer', Rule::exists('pls_reviews', 'id')],
            'legislation_id' => [
                'required',
                'integer',
                Rule::exists('legislation', 'id'),
                Rule::unique('pls_review_legislation', 'legislation_id')
                    ->where(fn ($query) => $query->where('pls_review_id', (int) ($input['pls_review_id'] ?? 0))),
            ],
            'relationship_type' => ['required', Rule::enum(ReviewLegislationRelationshipType::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'legislation_id.unique' => 'That legislation is already linked to the selected review.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'pls_review_id' => 'review',
            'legislation_id' => 'legislation',
            'relationship_type' => 'relationship type',
        ];
    }
}
