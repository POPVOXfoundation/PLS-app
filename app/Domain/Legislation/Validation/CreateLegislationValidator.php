<?php

namespace App\Domain\Legislation\Validation;

use App\Domain\Legislation\Enums\LegislationType;
use App\Domain\Legislation\Enums\ReviewLegislationRelationshipType;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CreateLegislationValidator
{
    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     pls_review_id: int,
     *     title: string,
     *     short_title?: string|null,
     *     legislation_type: string,
     *     date_enacted?: string|null,
     *     summary?: string|null,
     *     relationship_type: string
     * }
     */
    public function validate(array $input): array
    {
        return Validator::make(
            $input,
            $this->rules(),
            $this->messages(),
            $this->attributes(),
        )->validate();
    }

    /**
     * @return array<string, array<int, \Illuminate\Contracts\Validation\ValidationRule|\Illuminate\Contracts\Validation\Rule|string>>
     */
    public function rules(): array
    {
        return [
            'pls_review_id' => ['required', 'integer', Rule::exists('pls_reviews', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'short_title' => ['nullable', 'string', 'max:255'],
            'legislation_type' => ['required', Rule::enum(LegislationType::class)],
            'date_enacted' => ['nullable', 'date'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'relationship_type' => ['required', Rule::enum(ReviewLegislationRelationshipType::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Enter the title of the legislation being reviewed.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'pls_review_id' => 'review',
            'short_title' => 'short title',
            'legislation_type' => 'legislation type',
            'date_enacted' => 'date enacted',
            'relationship_type' => 'relationship type',
        ];
    }
}
