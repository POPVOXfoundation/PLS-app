<?php

namespace App\Http\Controllers\Pls;

use App\Domain\Reporting\Report;
use App\Domain\Reviews\PlsReview;
use App\Http\Controllers\Controller;
use App\Support\Reports\ReportExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ReviewReportExportController extends Controller
{
    public function __invoke(Request $request, PlsReview $review, Report $report): Response
    {
        Gate::authorize('view', $review);
        abort_unless((int) $report->pls_review_id === (int) $review->id, 404);
        $input = $request->validate(['format' => ['sometimes', 'in:md,pdf,docx'], 'version' => ['sometimes', 'integer', 'min:0']]);
        $version = (int) ($input['version'] ?? 0);
        $versions = $report->content_versions ?? [];
        abort_if($version > count($versions), 404);
        $title = $version === 0 ? $report->title : $versions[$version - 1]['title'];
        $content = $version === 0 ? $report->content : $versions[$version - 1]['content'];
        abort_if(blank($content), 404);
        $format = $input['format'] ?? 'md';
        $bytes = app(ReportExporter::class)->export($format, $title, $content, $version ? 'version' : $report->status->value);

        return response($bytes, 200, [
            'Content-Type' => match ($format) {
                'pdf' => 'application/pdf', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', default => 'text/markdown; charset=UTF-8'
            },
            'Content-Disposition' => 'attachment; filename="'.(Str::slug($title) ?: 'report').($version ? '-v'.$version : '').'.'.$format.'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
