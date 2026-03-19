<?php

namespace App\Domain\Reviews\Validation;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CreatePlsReviewValidator
{
    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     legislature_id: int,
     *     review_group_id?: int|null,
     *     title: string,
     *     description?: string|null,
     *     start_date?: string|null,
     *     created_by: int
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
            'legislature_id' => ['required', 'integer', Rule::exists('legislatures', 'id')],
            'review_group_id' => ['nullable', 'integer', Rule::exists('review_groups', 'id')],
            'created_by' => ['required', 'integer', Rule::exists('users', 'id')],
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
            'legislature_id.required' => 'Choose the legislature for this review.',
            'legislature_id.exists' => 'Select a valid legislature for the review.',
            'review_group_id.exists' => 'Select a valid review group for the review.',
            'created_by.required' => 'A review owner is required.',
            'created_by.exists' => 'Select a valid review owner.',
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
            'legislature_id' => 'legislature',
            'review_group_id' => 'review group',
            'created_by' => 'review owner',
            'start_date' => 'start date',
        ];
    }
}
