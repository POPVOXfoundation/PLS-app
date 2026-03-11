<?php

namespace App\Domain\Stakeholders\Validation;

use App\Domain\Stakeholders\Enums\ImplementingAgencyType;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class StoreImplementingAgencyValidator
{
    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     pls_review_id: int,
     *     name: string,
     *     agency_type: string
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
            'pls_review_id' => 'review',
            'agency_type' => 'agency type',
        ];
    }
}
