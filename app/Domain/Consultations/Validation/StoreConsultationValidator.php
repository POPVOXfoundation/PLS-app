<?php

namespace App\Domain\Consultations\Validation;

use App\Domain\Consultations\Enums\ConsultationType;
use App\Domain\Documents\Document;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class StoreConsultationValidator
{
    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     pls_review_id: int,
     *     title: string,
     *     consultation_type: string,
     *     held_at?: string|null,
     *     summary: string,
     *     document_id?: int|null
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
                empty($input['document_id'])
                || ! isset($input['pls_review_id'])
                || ! is_numeric($input['pls_review_id'])
                || ! is_numeric($input['document_id'])
            ) {
                return;
            }

            $belongsToReview = Document::query()
                ->whereKey((int) $input['document_id'])
                ->where('pls_review_id', (int) $input['pls_review_id'])
                ->exists();

            if (! $belongsToReview) {
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
            'title' => ['required', 'string', 'max:255'],
            'consultation_type' => ['required', Rule::enum(ConsultationType::class)],
            'held_at' => ['nullable', 'date'],
            'summary' => ['required', 'string', 'max:5000'],
            'document_id' => ['nullable', 'integer', Rule::exists('documents', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Enter a title for the consultation activity.',
            'summary.required' => 'Summarize the consultation purpose or outcome.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'pls_review_id' => 'review',
            'consultation_type' => 'consultation type',
            'held_at' => 'date held',
            'document_id' => 'document',
        ];
    }
}
