<?php

namespace App\Domain\Legislation\Validation;

use App\Domain\Legislation\Enums\LegislationType;
use App\Domain\Legislation\Enums\ReviewLegislationRelationshipType;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SaveAnalyzedLegislationValidator
{
    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     pls_review_id: int,
     *     source_document_id?: int|null,
     *     save_mode: string,
     *     legislation_id?: int|null,
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
            $this->rules($input),
            attributes: $this->attributes(),
        )->validate();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, array<int, \Illuminate\Contracts\Validation\ValidationRule|\Illuminate\Contracts\Validation\Rule|string>>
     */
    public function rules(array $input): array
    {
        return [
            'pls_review_id' => ['required', 'integer', Rule::exists('pls_reviews', 'id')],
            'source_document_id' => [
                'nullable',
                'integer',
                Rule::exists('documents', 'id')->where(
                    fn ($query) => $query->where('pls_review_id', (int) ($input['pls_review_id'] ?? 0)),
                ),
            ],
            'save_mode' => ['required', Rule::in(['create', 'update'])],
            'legislation_id' => [
                'nullable',
                'integer',
                Rule::requiredIf(($input['save_mode'] ?? null) === 'update'),
                Rule::exists('legislation', 'id'),
            ],
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
    public function attributes(): array
    {
        return [
            'pls_review_id' => 'review',
            'source_document_id' => 'source document',
            'save_mode' => 'save mode',
            'legislation_id' => 'existing legislation',
            'short_title' => 'short title',
            'legislation_type' => 'legislation type',
            'date_enacted' => 'date enacted',
            'relationship_type' => 'relationship type',
        ];
    }
}
