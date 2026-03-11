<?php

namespace App\Domain\Documents\Actions;

use App\Domain\Documents\Document;
use App\Domain\Documents\Validation\UpdateReviewDocumentValidator;
use App\Domain\Reviews\PlsReview;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class UpdateReviewDocument
{
    public function __construct(private UpdateReviewDocumentValidator $validator)
    {
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(array $input): PlsReview
    {
        $validated = $this->validator->validate($input);
        $document = Document::query()->findOrFail($validated['document_id']);
        $uploadedFile = $validated['file'] ?? null;
        $metadata = $validated['metadata'] ?? $document->metadata ?? null;

        if ($uploadedFile instanceof TemporaryUploadedFile) {
            $storedPath = $uploadedFile->store(
                sprintf('pls/reviews/%d/documents', $validated['pls_review_id']),
                ['disk' => config('filesystems.default')],
            );

            if ($document->storage_path !== '' && $document->storage_path !== null) {
                Storage::disk(config('filesystems.default'))->delete($document->storage_path);
            }

            $metadata = array_filter([
                ...($metadata ?? []),
                'disk' => config('filesystems.default'),
                'original_name' => $uploadedFile->getClientOriginalName(),
            ], static fn (mixed $value): bool => $value !== null && $value !== '');
        } else {
            $storedPath = $validated['storage_path'];
        }

        $document->update([
            'title' => $validated['title'],
            'document_type' => $validated['document_type'],
            'storage_path' => $storedPath,
            'mime_type' => $uploadedFile instanceof TemporaryUploadedFile ? $uploadedFile->getMimeType() : ($validated['mime_type'] ?? null),
            'file_size' => $uploadedFile instanceof TemporaryUploadedFile ? $uploadedFile->getSize() : ($validated['file_size'] ?? null),
            'summary' => $validated['summary'] ?? null,
            'metadata' => $metadata,
        ]);

        return PlsReview::query()->findOrFail($validated['pls_review_id'])->fresh('documents');
    }
}
