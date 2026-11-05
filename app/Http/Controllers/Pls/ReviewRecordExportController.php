<?php

namespace App\Http\Controllers\Pls;

use App\Domain\Reviews\PlsReview;
use App\Http\Controllers\Controller;
use App\Support\Documents\ReviewRecordSpreadsheet;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Spatie\SimpleExcel\SimpleExcelWriter;

class ReviewRecordExportController extends Controller
{
    public function __invoke(Request $request, PlsReview $review): Response
    {
        Gate::authorize('view', $review);
        $validated = $request->validate(['format' => ['required', Rule::in(['csv', 'xlsx'])], 'template' => ['sometimes', 'boolean']]);
        $format = $validated['format'];
        $path = tempnam(sys_get_temp_dir(), 'pls-register-');
        try {
            $writer = SimpleExcelWriter::create($path, $format);
            if ($request->boolean('template')) {
                $writer->addHeader(ReviewRecordSpreadsheet::COLUMNS);
            } else {
                $writer->addHeader(['Title', 'Type', 'Processing', 'Organisation', 'Source date', 'Source details', 'Original URL', 'Stakeholders', 'Consultations', 'Notes']);
                foreach ($review->documents()->withCount('chunks')->with(['submissions.stakeholder', 'consultationMaterials.consultation'])->lazyById(100) as $document) {
                    $row = [$document->title, ($document->isReportTemplate() ? 'reporting_template' : $document->document_type->value), $document->processingLabel(), data_get($document->metadata, 'organisation', ''), data_get($document->metadata, 'source_date', ''), route('pls.reviews.documents.show', [$review, $document]), $document->originalUrl() ?? '', $document->submissions->pluck('stakeholder.name')->filter()->unique()->implode('; '), $document->consultationMaterials->pluck('consultation.title')->filter()->unique()->implode('; '), data_get($document->metadata, 'notes', '')];
                    $writer->addRow(array_map(fn ($value): string => preg_match('/^[\s]*[=+@-]/u', (string) $value) ? "'".$value : (string) $value, $row));
                }
            }
            $writer->close();
            $bytes = file_get_contents($path);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }

        return response($bytes, 200, ['Content-Type' => $format === 'csv' ? 'text/csv; charset=UTF-8' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Content-Disposition' => 'attachment; filename="'.($request->boolean('template') ? 'record-import-template' : 'review-register').'.'.$format.'"', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
