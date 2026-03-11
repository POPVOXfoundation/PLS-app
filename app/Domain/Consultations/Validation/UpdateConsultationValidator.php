<?php

namespace App\Domain\Consultations\Validation;

use App\Domain\Consultations\Consultation;
use App\Domain\Consultations\Enums\ConsultationType;
use App\Domain\Documents\Document;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UpdateConsultationValidator
{
    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     consultation_id: int,
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
                ! isset($input['consultation_id'], $input['pls_review_id'])
                || ! is_numeric($input['consultation_id'])
                || ! is_numeric($input['pls_review_id'])
            ) {
                return;
            }

            $belongsToReview = Consultation::query()
                ->whereKey((int) $input['consultation_id'])
                ->where('pls_review_id', (int) $input['pls_review_id'])
                ->exists();

            if (! $belongsToReview) {
                $validator->errors()->add('consultation_id', 'The selected consultation does not belong to this review.');
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
            'consultation_id' => ['required', 'integer', Rule::exists('consultations', 'id')],
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
            'consultation_id' => 'consultation',
            'pls_review_id' => 'review',
            'consultation_type' => 'consultation type',
            'held_at' => 'date held',
            'document_id' => 'document',
        ];
    }
}
