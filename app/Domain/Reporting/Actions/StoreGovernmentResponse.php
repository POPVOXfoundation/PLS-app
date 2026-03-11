<?php

namespace App\Domain\Reporting\Actions;

use App\Domain\Reporting\Enums\GovernmentResponseStatus;
use App\Domain\Reporting\GovernmentResponse;
use App\Domain\Reporting\Validation\StoreGovernmentResponseValidator;
use App\Domain\Reviews\PlsReview;

class StoreGovernmentResponse
{
    public function __construct(private StoreGovernmentResponseValidator $validator)
    {
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function store(array $input): PlsReview
    {
        $validated = $this->validator->validate($input);

        GovernmentResponse::query()->create([
            'pls_review_id' => $validated['pls_review_id'],
            'report_id' => $validated['report_id'],
            'document_id' => $validated['document_id'] ?? null,
            'response_status' => $validated['response_status'],
            'received_at' => ($validated['response_status'] ?? null) === GovernmentResponseStatus::Received->value
                ? ($validated['received_at'] ?? now())
                : ($validated['received_at'] ?? null),
            'summary' => $validated['summary'] ?? null,
        ]);

        return PlsReview::query()->findOrFail($validated['pls_review_id'])->fresh();
    }
}
