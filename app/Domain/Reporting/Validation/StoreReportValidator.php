<?php

namespace App\Domain\Reporting\Validation;

use App\Domain\Documents\Document;
use App\Domain\Reporting\Enums\ReportStatus;
use App\Domain\Reporting\Enums\ReportType;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class StoreReportValidator
{
    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     pls_review_id: int,
     *     title: string,
     *     report_type: string,
     *     status: string,
     *     document_id?: int|null,
     *     published_at?: string|null
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
            'report_type' => ['required', Rule::enum(ReportType::class)],
            'status' => ['required', Rule::enum(ReportStatus::class)],
            'document_id' => ['nullable', 'integer', Rule::exists('documents', 'id')],
            'published_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Enter a title for the report record.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'pls_review_id' => 'review',
            'report_type' => 'report type',
            'document_id' => 'document',
            'published_at' => 'published date',
        ];
    }
}
