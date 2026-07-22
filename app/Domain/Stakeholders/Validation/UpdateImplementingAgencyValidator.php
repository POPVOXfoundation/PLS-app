<?php

namespace App\Domain\Stakeholders\Validation;

use App\Domain\Stakeholders\Enums\ImplementingAgencyType;
use App\Domain\Stakeholders\ImplementingAgency;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UpdateImplementingAgencyValidator
{
    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     agency_id: int,
     *     pls_review_id: int,
     *     name: string,
     *     agency_type: string
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
                ! isset($input['agency_id'], $input['pls_review_id'])
                || ! is_numeric($input['agency_id'])
                || ! is_numeric($input['pls_review_id'])
            ) {
                return;
            }

            $belongsToReview = ImplementingAgency::query()
                ->whereKey((int) $input['agency_id'])
                ->where('pls_review_id', (int) $input['pls_review_id'])
                ->exists();

            if (! $belongsToReview) {
                $validator->errors()->add('agency_id', 'The selected implementing agency does not belong to this review.');
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
            'agency_id' => ['required', 'integer', Rule::exists('implementing_agencies', 'id')],
            'pls_review_id' => ['required', 'integer', Rule::exists('pls_reviews', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'agency_type' => ['required', Rule::enum(ImplementingAgencyType::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Enter the implementing agency name.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'agency_id' => 'implementing agency',
            'pls_review_id' => 'review',
            'agency_type' => 'agency type',
        ];
    }
}
