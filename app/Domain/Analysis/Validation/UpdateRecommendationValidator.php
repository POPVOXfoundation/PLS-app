<?php

namespace App\Domain\Analysis\Validation;

use App\Domain\Analysis\Finding;
use App\Domain\Analysis\Recommendation;
use App\Domain\Analysis\Enums\RecommendationType;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UpdateRecommendationValidator
{
    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     recommendation_id: int,
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
                ! isset($input['recommendation_id'], $input['pls_review_id'])
                || ! is_numeric($input['recommendation_id'])
                || ! is_numeric($input['pls_review_id'])
            ) {
                return;
            }

            $recommendationBelongsToReview = Recommendation::query()
                ->whereKey((int) $input['recommendation_id'])
                ->where('pls_review_id', (int) $input['pls_review_id'])
                ->exists();

            if (! $recommendationBelongsToReview) {
                $validator->errors()->add('recommendation_id', 'The selected recommendation does not belong to this review.');
            }

            if (! isset($input['finding_id']) || ! is_numeric($input['finding_id'])) {
                return;
            }

            $findingBelongsToReview = Finding::query()
                ->whereKey((int) $input['finding_id'])
                ->where('pls_review_id', (int) $input['pls_review_id'])
                ->exists();

            if (! $findingBelongsToReview) {
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
            'recommendation_id' => ['required', 'integer', Rule::exists('recommendations', 'id')],
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
            'recommendation_id' => 'recommendation',
            'pls_review_id' => 'review',
            'finding_id' => 'finding',
            'recommendation_type' => 'recommendation type',
        ];
    }
}
