<?php

namespace App\Domain\Analysis\Validation;

use App\Domain\Analysis\Enums\FindingType;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class StoreFindingValidator
{
    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     pls_review_id: int,
     *     title: string,
     *     finding_type: string,
     *     summary?: string|null,
     *     detail?: string|null
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
            'pls_review_id' => 'review',
            'finding_type' => 'finding type',
        ];
    }
}
