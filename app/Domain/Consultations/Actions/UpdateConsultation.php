<?php

namespace App\Domain\Consultations\Actions;

use App\Domain\Consultations\Consultation;
use App\Domain\Consultations\Validation\UpdateConsultationValidator;
use App\Domain\Reviews\PlsReview;

class UpdateConsultation
{
    public function __construct(private UpdateConsultationValidator $validator)
    {
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(array $input): PlsReview
    {
        $validated = $this->validator->validate($input);

        $consultation = Consultation::query()->findOrFail($validated['consultation_id']);

        $consultation->update([
            'title' => $validated['title'],
            'consultation_type' => $validated['consultation_type'],
            'held_at' => $validated['held_at'] ?? null,
            'summary' => $validated['summary'],
            'document_id' => $validated['document_id'] ?? null,
        ]);

        return PlsReview::query()->findOrFail($validated['pls_review_id'])->fresh();
    }
}
