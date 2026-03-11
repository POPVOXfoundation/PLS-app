<?php

namespace App\Domain\Reviews\Validation;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CreatePlsReviewValidator
{
    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     committee_id: int,
     *     title: string,
     *     description?: string|null,
     *     start_date?: string|null
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
            'committee_id' => ['required', 'integer', Rule::exists('committees', 'id')],
            'title' => ['required', 'string', 'min:5', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'start_date' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'committee_id.required' => 'Choose the committee responsible for this review.',
            'committee_id.exists' => 'Select a valid committee for the review.',
            'title.required' => 'Enter the public-facing review title.',
            'title.min' => 'The review title must be at least 5 characters.',
            'start_date.date' => 'Enter a valid start date.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'committee_id' => 'committee',
            'start_date' => 'start date',
        ];
    }
}
