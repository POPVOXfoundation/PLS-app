<?php

namespace App\Domain\Analysis\Validation;

use App\Domain\Analysis\Enums\RecommendationType;
use App\Domain\Analysis\Finding;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class StoreRecommendationValidator
{
    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     pls_review_id: int,
     *     finding_id: int,
     *     title: string,
     *     description?: string|null,
     *     recommendation_type: string
     * }
     */
    public function validate(array $input): array
    {
        $validator = Validator::make(
            $input,
            $this->rules(),
            $this->messages(),
            $this->attributes(),
        );

        $validator->after(function ($validator) use ($input): void {
            if (
                ! isset($input['pls_review_id'], $input['finding_id'])
                || ! is_numeric($input['pls_review_id'])
                || ! is_numeric($input['finding_id'])
            ) {
                return;
            }

            $belongsToReview = Finding::query()
                ->whereKey((int) $input['finding_id'])
                ->where('pls_review_id', (int) $input['pls_review_id'])
                ->exists();

            if (! $belongsToReview) {
                $validator->errors()->add('finding_id', 'The selected finding does not belong to the selected review.');
            }
        });

        return $validator->validate();
    }

    /**
     * @return array<string, array<int, \Illuminate\Contracts\Validation\ValidationRule|\Illuminate\Contracts\Validation\Rule|string>>
     */
    public function rules(): array
    {
        return [
            'pls_review_id' => ['required', 'integer', Rule::exists('pls_reviews', 'id')],
            'finding_id' => ['required', 'integer', Rule::exists('findings', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'recommendation_type' => ['required', Rule::enum(RecommendationType::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'finding_id.exists' => 'Select a valid finding for the recommendation.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'pls_review_id' => 'review',
            'finding_id' => 'finding',
            'recommendation_type' => 'recommendation type',
        ];
    }
}
