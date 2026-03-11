<?php

namespace App\Domain\Consultations\Validation;

use App\Domain\Documents\Document;
use App\Domain\Stakeholders\Stakeholder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class StoreSubmissionValidator
{
    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     pls_review_id: int,
     *     stakeholder_id: int,
     *     document_id?: int|null,
     *     submitted_at?: string|null,
     *     summary: string
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
                ! isset($input['pls_review_id'], $input['stakeholder_id'])
                || ! is_numeric($input['pls_review_id'])
                || ! is_numeric($input['stakeholder_id'])
            ) {
                return;
            }

            $stakeholderBelongsToReview = Stakeholder::query()
                ->whereKey((int) $input['stakeholder_id'])
                ->where('pls_review_id', (int) $input['pls_review_id'])
                ->exists();

            if (! $stakeholderBelongsToReview) {
                $validator->errors()->add('stakeholder_id', 'The selected stakeholder does not belong to this review.');
            }

            if (
                empty($input['document_id'])
                || ! is_numeric($input['document_id'])
            ) {
                return;
            }

            $documentBelongsToReview = Document::query()
                ->whereKey((int) $input['document_id'])
                ->where('pls_review_id', (int) $input['pls_review_id'])
                ->exists();

            if (! $documentBelongsToReview) {
                $validator->errors()->add('document_id', 'The selected document does not belong to this review.');
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
            'pls_review_id' => ['required', 'integer', Rule::exists('pls_reviews', 'id')],
            'stakeholder_id' => ['required', 'integer', Rule::exists('stakeholders', 'id')],
            'document_id' => ['nullable', 'integer', Rule::exists('documents', 'id')],
            'submitted_at' => ['nullable', 'date'],
            'summary' => ['required', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'stakeholder_id.required' => 'Select the stakeholder who made the submission.',
            'summary.required' => 'Summarize the submission received.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'pls_review_id' => 'review',
            'stakeholder_id' => 'stakeholder',
            'document_id' => 'document',
            'submitted_at' => 'submitted date',
        ];
    }
}
