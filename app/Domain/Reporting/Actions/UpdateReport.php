<?php

namespace App\Domain\Reporting\Actions;

use App\Domain\Reporting\Enums\ReportStatus;
use App\Domain\Reporting\Report;
use App\Domain\Reporting\Validation\UpdateReportValidator;
use App\Domain\Reviews\PlsReview;

class UpdateReport
{
    public function __construct(private UpdateReportValidator $validator)
    {
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(array $input): PlsReview
    {
        $validated = $this->validator->validate($input);
        $report = Report::query()->findOrFail($validated['report_id']);

        $report->update([
            'title' => $validated['title'],
            'report_type' => $validated['report_type'],
            'status' => $validated['status'],
            'document_id' => $validated['document_id'] ?? null,
            'published_at' => $validated['status'] === ReportStatus::Published->value
                ? ($validated['published_at'] ?? $report->published_at ?? now())
                : null,
        ]);

        return PlsReview::query()->findOrFail($validated['pls_review_id'])->fresh([
            'reports',
            'governmentResponses',
        ]);
    }
}
