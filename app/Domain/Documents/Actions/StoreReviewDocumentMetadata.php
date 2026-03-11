<?php

namespace App\Domain\Documents\Actions;

use App\Domain\Documents\Document;
use App\Domain\Documents\Validation\StoreDocumentMetadataValidator;
use App\Domain\Reviews\PlsReview;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class StoreReviewDocumentMetadata
{
    public function __construct(private StoreDocumentMetadataValidator $validator)
    {
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function store(array $input): PlsReview
    {
        $validated = $this->validator->validate($input);
        $uploadedFile = $validated['file'] ?? null;
        $metadata = $validated['metadata'] ?? null;

        if ($uploadedFile instanceof TemporaryUploadedFile) {
            $storedPath = $uploadedFile->store(
                sprintf('pls/reviews/%d/documents', $validated['pls_review_id']),
                ['disk' => config('filesystems.default')],
            );

            $metadata = array_filter([
                ...($metadata ?? []),
                'disk' => config('filesystems.default'),
                'original_name' => $uploadedFile->getClientOriginalName(),
            ], static fn (mixed $value): bool => $value !== null && $value !== '');
        } else {
            $storedPath = $validated['storage_path'];
        }

        Document::query()->create([
            'pls_review_id' => $validated['pls_review_id'],
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
