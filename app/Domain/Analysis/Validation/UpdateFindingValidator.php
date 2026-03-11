<?php

namespace App\Domain\Analysis\Validation;

use App\Domain\Analysis\Enums\FindingType;
use App\Domain\Analysis\Finding;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UpdateFindingValidator
{
    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     finding_id: int,
     *     pls_review_id: int,
     *     title: string,
     *     finding_type: string,
     *     summary?: string|null,
     *     detail?: string|null
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
                ! isset($input['finding_id'], $input['pls_review_id'])
                || ! is_numeric($input['finding_id'])
                || ! is_numeric($input['pls_review_id'])
            ) {
                return;
            }

            $belongsToReview = Finding::query()
                ->whereKey((int) $input['finding_id'])
                ->where('pls_review_id', (int) $input['pls_review_id'])
                ->exists();

            if (! $belongsToReview) {
                $validator->errors()->add('finding_id', 'The selected finding does not belong to this review.');
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
            'finding_id' => ['required', 'integer', Rule::exists('findings', 'id')],
            'pls_review_id' => ['required', 'integer', Rule::exists('pls_reviews', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'finding_type' => ['required', Rule::enum(FindingType::class)],
            'summary' => ['nullable', 'string', 'max:5000', 'required_without:detail'],
            'detail' => ['nullable', 'string', 'max:20000', 'required_without:summary'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'summary.required_without' => 'Add a summary or a detailed explanation for the finding.',
            'detail.required_without' => 'Add a detailed explanation or at least a summary for the finding.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'finding_id' => 'finding',
            'pls_review_id' => 'review',
            'finding_type' => 'finding type',
        ];
    }
}
