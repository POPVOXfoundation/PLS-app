<?php

namespace App\Domain\Reporting\Validation;

use App\Domain\Documents\Document;
use App\Domain\Reporting\Enums\GovernmentResponseStatus;
use App\Domain\Reporting\Report;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class StoreGovernmentResponseValidator
{
    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     pls_review_id: int,
     *     report_id: int,
     *     document_id?: int|null,
     *     response_status: string,
     *     received_at?: string|null,
     *     summary?: string|null
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
                ! isset($input['pls_review_id'], $input['report_id'])
                || ! is_numeric($input['pls_review_id'])
                || ! is_numeric($input['report_id'])
            ) {
                return;
            }

            $reportBelongsToReview = Report::query()
                ->whereKey((int) $input['report_id'])
                ->where('pls_review_id', (int) $input['pls_review_id'])
                ->exists();

            if (! $reportBelongsToReview) {
                $validator->errors()->add('report_id', 'The selected report does not belong to this review.');
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
            'report_id' => ['required', 'integer', Rule::exists('reports', 'id')],
            'document_id' => ['nullable', 'integer', Rule::exists('documents', 'id')],
            'response_status' => ['required', Rule::enum(GovernmentResponseStatus::class)],
            'received_at' => ['nullable', 'date'],
            'summary' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'report_id.required' => 'Select the report this government response relates to.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'pls_review_id' => 'review',
            'report_id' => 'report',
            'document_id' => 'document',
            'response_status' => 'response status',
            'received_at' => 'received date',
        ];
    }
}
