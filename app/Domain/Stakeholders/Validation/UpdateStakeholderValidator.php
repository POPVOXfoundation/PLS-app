<?php

namespace App\Domain\Stakeholders\Validation;

use App\Domain\Stakeholders\Enums\StakeholderType;
use App\Domain\Stakeholders\Stakeholder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UpdateStakeholderValidator
{
    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     stakeholder_id: int,
     *     pls_review_id: int,
     *     name: string,
     *     stakeholder_type: string,
     *     contact_details?: array{
     *         organization?: string|null,
     *         email?: string|null,
     *         phone?: string|null
     *     }|null
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
                ! isset($input['stakeholder_id'], $input['pls_review_id'])
                || ! is_numeric($input['stakeholder_id'])
                || ! is_numeric($input['pls_review_id'])
            ) {
                return;
            }

            $belongsToReview = Stakeholder::query()
                ->whereKey((int) $input['stakeholder_id'])
                ->where('pls_review_id', (int) $input['pls_review_id'])
                ->exists();

            if (! $belongsToReview) {
                $validator->errors()->add('stakeholder_id', 'The selected stakeholder does not belong to this review.');
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
            'stakeholder_id' => ['required', 'integer', Rule::exists('stakeholders', 'id')],
            'pls_review_id' => ['required', 'integer', Rule::exists('pls_reviews', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'stakeholder_type' => ['required', Rule::enum(StakeholderType::class)],
            'contact_details' => ['nullable', 'array'],
            'contact_details.organization' => ['nullable', 'string', 'max:255'],
            'contact_details.email' => ['nullable', 'email:rfc,dns', 'max:255'],
            'contact_details.phone' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Enter the stakeholder name.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'stakeholder_id' => 'stakeholder',
            'pls_review_id' => 'review',
            'stakeholder_type' => 'stakeholder type',
            'contact_details.organization' => 'organization',
            'contact_details.email' => 'email address',
            'contact_details.phone' => 'phone number',
        ];
    }
}
