<?php

namespace App\Domain\Stakeholders\Validation;

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
     *     contact_details?: array{organization?: string|null}|null
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
            'stakeholder_type' => ['required', 'string', 'max:255'],
            'contact_details' => ['nullable', 'array'],
            'contact_details.organization' => ['nullable', 'string', 'max:255'],
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
        ];
    }
}
