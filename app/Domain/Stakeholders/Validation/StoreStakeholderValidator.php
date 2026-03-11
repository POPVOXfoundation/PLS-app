<?php

namespace App\Domain\Stakeholders\Validation;

use App\Domain\Stakeholders\Enums\StakeholderType;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class StoreStakeholderValidator
{
    /**
     * @param  array<string, mixed>  $input
     * @return array{
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
            'pls_review_id' => 'review',
            'stakeholder_type' => 'stakeholder type',
            'contact_details.organization' => 'organization',
            'contact_details.email' => 'email address',
            'contact_details.phone' => 'phone number',
        ];
    }
}
