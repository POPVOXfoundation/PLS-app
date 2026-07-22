<?php

namespace App\Domain\Consultations\Actions;

use App\Domain\Consultations\ConsultationMaterial;
use App\Domain\Consultations\Validation\StoreConsultationMaterialValidator;
use App\Domain\Documents\Actions\StoreReviewDocumentMetadata;
use App\Domain\Documents\Enums\DocumentType;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class StoreConsultationMaterial
{
    public function __construct(
        private StoreConsultationMaterialValidator $validator,
        private StoreReviewDocumentMetadata $documents,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function store(array $input): ConsultationMaterial
    {
        $validated = $this->validator->validate($input);
        $file = $input['file'] ?? null;

        if (! $file instanceof TemporaryUploadedFile) {
            throw new \InvalidArgumentException('A consultation result file is required.');
        }

        $document = $this->documents->storeDocument([
            'pls_review_id' => $validated['pls_review_id'],
            'title' => $input['title'] ?? $file->getClientOriginalName(),
            'document_type' => DocumentType::ConsultationSubmission->value,
            'storage_path' => null,
            'file' => $file,
            'mime_type' => null,
            'file_size' => null,
            'summary' => null,
            'metadata' => [
                'consultation_material_type' => $validated['material_type'],
            ],
        ]);

        return ConsultationMaterial::query()->create([
            'consultation_id' => $validated['consultation_id'],
            'document_id' => $document->id,
            'stakeholder_id' => $validated['stakeholder_id'] ?? null,
            'material_type' => $validated['material_type'],
        ]);
    }
}
