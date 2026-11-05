<?php

namespace App\Domain\Reporting\Actions;

use App\Domain\Reporting\Enums\ReportStatus;
use App\Domain\Reporting\Report;
use App\Domain\Reporting\Validation\UpdateReportValidator;
use App\Domain\Reviews\PlsReview;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateReport
{
    public function __construct(private UpdateReportValidator $validator) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(array $input): PlsReview
    {
        $validated = $this->validator->validate($input);
        DB::transaction(function () use ($validated): void {
            $report = Report::query()->where('pls_review_id', $validated['pls_review_id'])->whereKey($validated['report_id'])->lockForUpdate()->firstOrFail();
            if (isset($validated['expected_revision']) && $report->revision !== (int) $validated['expected_revision']) {
                throw ValidationException::withMessages(['content' => __('This report changed while you were editing. Your text has not been saved over the newer version. Copy it before reopening the report.')]);
            }

            $report->update([
                'title' => $validated['title'],
                'content' => $validated['content'] ?? $report->content,
                'report_type' => $validated['report_type'],
                'status' => $validated['status'],
                'document_id' => $validated['document_id'] ?? null,
                'published_at' => $validated['status'] === ReportStatus::Published->value
                    ? ($validated['published_at'] ?? $report->published_at ?? now())
                    : null,
            ]);

        });

        return PlsReview::query()->findOrFail($validated['pls_review_id'])->fresh([
            'reports',
            'governmentResponses',
        ]);
    }
}
