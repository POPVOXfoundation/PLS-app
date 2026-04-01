<?php

namespace App\Domain\Documents\Actions;

use App\Domain\Documents\Document;
use App\Domain\Documents\Validation\StoreDocumentMetadataValidator;
use App\Domain\Reviews\PlsReview;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

class StoreReviewDocumentMetadata
{
    public function __construct(private StoreDocumentMetadataValidator $validator) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function store(array $input): PlsReview
    {
        $validated = $this->validator->validate($input);
        $uploadedFile = $validated['file'] ?? null;
        $metadata = $validated['metadata'] ?? null;
        $storageDisk = $this->storageDisk($metadata);
        $uploadedOriginalName = $uploadedFile instanceof TemporaryUploadedFile ? $uploadedFile->getClientOriginalName() : null;
        $uploadedMimeType = $this->uploadedMimeType($uploadedFile);
        $uploadedFileSize = $this->uploadedFileSize($uploadedFile);

        if ($uploadedFile instanceof TemporaryUploadedFile) {
            $storedPath = $uploadedFile->store(
                sprintf('pls/reviews/%d/documents', $validated['pls_review_id']),
                ['disk' => $storageDisk],
            );

            $metadata = array_filter([
                ...($metadata ?? []),
                'disk' => $storageDisk,
                'original_name' => $uploadedOriginalName,
            ], static fn (mixed $value): bool => $value !== null && $value !== '');
        } else {
            $storedPath = $validated['storage_path'];
        }

        Document::query()->create([
            'pls_review_id' => $validated['pls_review_id'],
            'title' => $validated['title'],
            'document_type' => $validated['document_type'],
            'storage_path' => $storedPath,
            'mime_type' => $uploadedFile instanceof TemporaryUploadedFile ? $uploadedMimeType : ($validated['mime_type'] ?? null),
            'file_size' => $uploadedFile instanceof TemporaryUploadedFile ? $uploadedFileSize : ($validated['file_size'] ?? null),
            'summary' => $validated['summary'] ?? null,
            'metadata' => $metadata,
        ]);

        return PlsReview::query()->findOrFail($validated['pls_review_id'])->fresh('documents');
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    private function storageDisk(?array $metadata): string
    {
        $configuredDisk = trim((string) data_get($metadata, 'disk', ''));

        return $configuredDisk !== '' ? $configuredDisk : (string) config('filesystems.default');
    }

    private function uploadedMimeType(?TemporaryUploadedFile $uploadedFile): ?string
    {
        if (! $uploadedFile instanceof TemporaryUploadedFile) {
            return null;
        }

        try {
            return $uploadedFile->getMimeType() ?? $uploadedFile->getClientMimeType();
        } catch (Throwable) {
            return $uploadedFile->getClientMimeType();
        }
    }

    private function uploadedFileSize(?TemporaryUploadedFile $uploadedFile): ?int
    {
        if (! $uploadedFile instanceof TemporaryUploadedFile) {
            return null;
        }

        try {
            $size = $uploadedFile->getSize();

            if (is_int($size) && $size > 0) {
                return $size;
            }
        } catch (Throwable) {
            // Fall back to the temporary file path when Livewire metadata is unavailable.
        }

        $realPath = $uploadedFile->getRealPath();

        if (is_string($realPath) && $realPath !== '' && is_file($realPath)) {
            $size = filesize($realPath);

            return $size === false ? null : $size;
        }

        try {
            return $uploadedFile->getSize();
        } catch (Throwable) {
            return null;
        }
    }
}
