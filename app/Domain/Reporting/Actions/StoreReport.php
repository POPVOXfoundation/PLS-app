<?php

namespace App\Domain\Reporting\Actions;

use App\Domain\Reporting\Enums\ReportStatus;
use App\Domain\Reporting\Report;
use App\Domain\Reporting\Validation\StoreReportValidator;
use App\Domain\Reviews\PlsReview;

class StoreReport
{
    public function __construct(private StoreReportValidator $validator)
    {
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function store(array $input): PlsReview
    {
        $validated = $this->validator->validate($input);

        Report::query()->create([
            'pls_review_id' => $validated['pls_review_id'],
            'title' => $validated['title'],
            'report_type' => $validated['report_type'],
            'status' => $validated['status'],
            'document_id' => $validated['document_id'] ?? null,
            'published_at' => ($validated['status'] ?? null) === ReportStatus::Published->value
                ? ($validated['published_at'] ?? now())
                : null,
        ]);

        return PlsReview::query()->findOrFail($validated['pls_review_id'])->fresh('reports');
    }
}
