<?php

namespace App\Domain\Documents\Validation;

use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentType;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UpdateReviewDocumentValidator
{
    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     document_id: int,
     *     pls_review_id: int,
     *     title: string,
     *     document_type: string,
     *     storage_path?: string|null,
     *     file?: \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null,
     *     mime_type?: string|null,
     *     file_size?: int|null,
     *     summary?: string|null,
     *     metadata?: array<string, mixed>|null
     * }
     */
    public function validate(array $input): array
    {
        $validator = Validator::make(
            $input,
            $this->rules($input),
            $this->messages(),
            $this->attributes(),
        );

        $validator->after(function ($validator) use ($input): void {
            if (
                ! isset($input['document_id'], $input['pls_review_id'])
                || ! is_numeric($input['document_id'])
                || ! is_numeric($input['pls_review_id'])
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
     * @param  array<string, mixed>  $input
     * @return array<string, array<int, \Illuminate\Contracts\Validation\ValidationRule|\Illuminate\Contracts\Validation\Rule|string>>
     */
    public function rules(array $input): array
    {
        $documentId = isset($input['document_id']) && is_numeric($input['document_id'])
            ? (int) $input['document_id']
            : null;

        return [
            'document_id' => ['required', 'integer', Rule::exists('documents', 'id')],
            'pls_review_id' => ['required', 'integer', Rule::exists('pls_reviews', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'document_type' => ['required', Rule::enum(DocumentType::class)],
            'storage_path' => ['nullable', 'string', 'max:2048', 'required_without:file', Rule::unique('documents', 'storage_path')->ignore($documentId)],
            'file' => ['nullable', 'file', 'max:51200'],
            'mime_type' => ['nullable', 'string', 'max:255'],
            'file_size' => ['nullable', 'integer', 'min:1'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'storage_path.unique' => 'A document with that storage path already exists.',
            'storage_path.required_without' => 'Upload a file or provide a storage path for the document.',
            'file.required_without' => 'Upload a file or provide a storage path for the document.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'document_id' => 'document',
            'pls_review_id' => 'review',
            'document_type' => 'document type',
            'storage_path' => 'storage path',
            'file' => 'document file',
            'file_size' => 'file size',
        ];
    }
}
