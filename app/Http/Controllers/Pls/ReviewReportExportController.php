<?php

namespace App\Http\Controllers\Pls;

use App\Domain\Reporting\Report;
use App\Domain\Reviews\PlsReview;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ReviewReportExportController extends Controller
{
    public function __invoke(PlsReview $review, Report $report): Response
    {
        Gate::authorize('view', $review);
        abort_unless((int) $report->pls_review_id === (int) $review->id, 404);
        abort_if(blank($report->content), 404);

        return response('# '.$report->title."\n\n".$report->content, 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.(Str::slug($report->title) ?: 'report').'.md"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
