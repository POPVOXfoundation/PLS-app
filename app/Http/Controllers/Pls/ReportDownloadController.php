<?php

namespace App\Http\Controllers\Pls;

use App\Domain\Reporting\Report;
use App\Domain\Reviews\PlsReview;
use App\Http\Controllers\Controller;
use App\Support\Reports\ReportDocumentExporter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ReportDownloadController extends Controller
{
    public function __invoke(Request $request, PlsReview $review, Report $report, ReportDocumentExporter $exporter): Response
    {
        Gate::authorize('view', $review);
        abort_unless((int) $report->pls_review_id === (int) $review->id, 404);
        $validated = $request->validate(['format' => ['required', Rule::in(['pdf', 'docx', 'md'])], 'revision' => ['nullable', 'integer', 'min:0']]);
        $version = isset($validated['revision']) ? $report->versions()->where('revision', $validated['revision'])->firstOrFail() : $report;
        abort_if(blank($version->content), 422, __('This report has no saved text to export.'));
        $status = $version instanceof Report ? $version->status->value : $version->status;

        return $exporter->download($validated['format'], $version->title, $version->content, $status, (int) $version->revision);
    }
}
