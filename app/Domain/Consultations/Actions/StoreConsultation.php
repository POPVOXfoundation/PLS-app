<?php

namespace App\Domain\Consultations\Actions;

use App\Domain\Consultations\Consultation;
use App\Domain\Consultations\Validation\StoreConsultationValidator;
use App\Domain\Reviews\PlsReview;

class StoreConsultation
{
    public function __construct(private StoreConsultationValidator $validator)
    {
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function store(array $input): PlsReview
    {
        $validated = $this->validator->validate($input);

        Consultation::query()->create([
            'pls_review_id' => $validated['pls_review_id'],
            'title' => $validated['title'],
            'consultation_type' => $validated['consultation_type'],
            'held_at' => $validated['held_at'] ?? null,
            'summary' => $validated['summary'],
            'document_id' => $validated['document_id'] ?? null,
        ]);

        return PlsReview::query()->findOrFail($validated['pls_review_id'])->fresh();
    }
}
