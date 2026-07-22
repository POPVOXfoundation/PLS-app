<?php

namespace App\Domain\Consultations\Validation;

use App\Domain\Consultations\Consultation;
use App\Domain\Consultations\Enums\ConsultationMaterialType;
use App\Domain\Stakeholders\Stakeholder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class StoreConsultationMaterialValidator
{
    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     pls_review_id: int,
     *     consultation_id: int,
     *     stakeholder_id?: int|null,
     *     material_type: string
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
            if (! isset($input['pls_review_id']) || ! is_numeric($input['pls_review_id'])) {
                return;
            }

            $reviewId = (int) $input['pls_review_id'];

            if (isset($input['consultation_id']) && is_numeric($input['consultation_id'])) {
                $consultationBelongsToReview = Consultation::query()
                    ->whereKey((int) $input['consultation_id'])
                    ->where('pls_review_id', $reviewId)
                    ->exists();

                if (! $consultationBelongsToReview) {
                    $validator->errors()->add('consultation_id', 'The selected consultation does not belong to this review.');
                }
            }

            if (filled($input['stakeholder_id'] ?? null) && is_numeric($input['stakeholder_id'])) {
                $stakeholderBelongsToReview = Stakeholder::query()
                    ->whereKey((int) $input['stakeholder_id'])
                    ->where('pls_review_id', $reviewId)
                    ->exists();

                if (! $stakeholderBelongsToReview) {
                    $validator->errors()->add('stakeholder_id', 'The selected stakeholder does not belong to this review.');
                }
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
            'consultation_id' => ['required', 'integer', Rule::exists('consultations', 'id')],
            'stakeholder_id' => ['nullable', 'integer', Rule::exists('stakeholders', 'id')],
            'material_type' => ['required', Rule::enum(ConsultationMaterialType::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'pls_review_id' => 'review',
            'consultation_id' => 'consultation',
            'stakeholder_id' => 'stakeholder',
            'material_type' => 'result type',
        ];
    }
}
