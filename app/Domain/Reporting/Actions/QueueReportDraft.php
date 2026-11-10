<?php

namespace App\Domain\Reporting\Actions;

use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Reporting\Report;
use App\Domain\Reviews\PlsReview;
use App\Jobs\GenerateReviewReportDraft;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class QueueReportDraft
{
    public function queue(PlsReview $review, ?Report $report = null, ?int $templateId = null, ?int $section = null, ?int $expectedRevision = null): Report
    {
        if ($templateId !== null && ! $review->documents()->whereKey($templateId)->where(fn ($query) => $query->where('metadata->purpose', 'report_template')->orWhere('document_type', DocumentType::ReportingTemplate->value))->exists()) {
            throw ValidationException::withMessages(['templateId' => __('Choose a report template from this review.')]);
        }
        $report = DB::transaction(function () use ($review, $report, $templateId, $section, $expectedRevision): Report {
            $current = $report === null ? $review->reports()->create([
                'title' => Str::limit(__('Draft report: :title', ['title' => $review->title]), 255, ''),
                'report_type' => 'draft_report', 'status' => 'draft',
            ]) : $review->reports()->lockForUpdate()->findOrFail($report->id);
            if ($expectedRevision !== null && $current->revision !== $expectedRevision) {
                throw ValidationException::withMessages(['generation' => __('The report changed. Select a section from the current draft and try again.')]);
            }
            if ($section !== null && ! array_key_exists($section, GenerateReviewReportDraft::sections($current->content ?? ''))) {
                abort(422);
            }
            if ($current->status->value !== 'draft') {
                throw ValidationException::withMessages(['generation' => __('Start a new draft to generate report text. Published and archived reports are preserved.')]);
            }
            if (in_array($current->generation_status, ['queued', 'generating'], true) && $current->generation_requested_at?->gt(now()->subMinutes(10))) {
                throw ValidationException::withMessages(['generation' => __('This report is already queued or generating.')]);
            }
            $current->update([
                'generation_token' => (string) Str::uuid(), 'generation_status' => 'queued',
                'generation_error' => null, 'generation_requested_at' => now(), 'generation_template_id' => $templateId,
            ]);

            return $current;
        });
        $job = new GenerateReviewReportDraft($report->id, $report->generation_token, $report->revision, $templateId, $section);
        DB::afterCommit(function () use ($job): void {
            try {
                Bus::dispatch($job);
            } catch (Throwable $exception) {
                report($exception);
                $job->failed($exception);
            }
        });

        return $report->fresh();
    }
}
