<?php

namespace App\Http\Controllers\Pls;

use App\Domain\Reviews\PlsReview;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Documents\RecordSpreadsheet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReviewRegisterExportController extends Controller
{
    public function __invoke(Request $request, PlsReview $review)
    {
        Gate::authorize('view', $review);
        $request->validate(['format' => ['sometimes', 'in:csv,xlsx'], 'template' => ['sometimes', 'boolean']]);
        $format = $request->input('format', 'csv');
        $rows = [RecordSpreadsheet::COLUMNS];
        if (! $request->boolean('template')) {
            $rows = [['Title', 'Type', 'Organisation', 'Source date', 'Processing', 'Team review', 'Stakeholder / consultation', 'Added by', 'Added at', 'Details', 'Original', 'Notes']];
            $users = User::whereIn('id', $review->documents()->get()->pluck('metadata.added_by')->filter())->pluck('name', 'id');
            foreach ($review->documents()->withCount('chunks')->with(['submissions.stakeholder', 'consultationMaterials.consultation'])->orderBy('id')->get() as $doc) {
                $links = $doc->submissions->pluck('stakeholder.name')->merge($doc->consultationMaterials->pluck('consultation.title'))->push(data_get($doc->metadata, 'stakeholder'))->filter()->unique()->implode('; ');
                $rows[] = [$doc->title, $doc->document_type->value, data_get($doc->metadata, 'organisation', ''), data_get($doc->metadata, 'source_date', ''), $doc->processingLabel(), $doc->reviewStatusLabel(), $links, $users[data_get($doc->metadata, 'added_by')] ?? 'Not recorded', $doc->created_at?->toIso8601String(), route('pls.reviews.documents.show', [$review, $doc]), $doc->originalUrl() ?? '', data_get($doc->metadata, 'notes', '')];
            }
        }
        if ($format === 'xlsx') {
            $book = new Spreadsheet;
            $sheet = $book->getActiveSheet();
            foreach ($rows as $r => $row) {
                foreach ($row as $c => $value) {
                    $sheet->setCellValueExplicit([$c + 1, $r + 1], (string) $value, DataType::TYPE_STRING);
                }
                if ($r > 0 && ! $request->boolean('template')) {
                    foreach ([0 => $row[9], 9 => $row[9], 10 => $row[10]] as $column => $url) {
                        if ($url !== '') {
                            $sheet->getCell([$column + 1, $r + 1])->getHyperlink()->setUrl($url);
                            $sheet->getStyle([$column + 1, $r + 1])->getFont()->setColor(new Color('50376B'))->setUnderline(true);
                        }
                    }
                }
            }
            $sheet->freezePane('A2');
            $sheet->setAutoFilter($sheet->calculateWorksheetDimension());
            $sheet->getStyle('1:1')->getFont()->setBold(true);
            foreach (range('A', 'L') as $col) {
                $sheet->getColumnDimension($col)->setWidth(in_array($col, ['A', 'K', 'J']) ? 45 : 24);
            }
            $path = tempnam(sys_get_temp_dir(), 'pls-register-');
            try {
                (new Xlsx($book))->save($path);
                $bytes = file_get_contents($path);
            } finally {
                @unlink($path);
                $book->disconnectWorksheets();
            }
        } else {
            $stream = fopen('php://temp', 'w+');
            foreach ($rows as $row) {
                fputcsv($stream, array_map(fn ($cell) => preg_match('/^[\s]*[=+@-]/u', (string) $cell) ? "'".$cell : (string) $cell, $row), ',', '"', '');
            }
            rewind($stream);
            $bytes = stream_get_contents($stream);
            fclose($stream);
        }

        return response($bytes, 200, ['Content-Type' => $format === 'xlsx' ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="'.($request->boolean('template') ? 'record-import-template' : 'document-register').'.'.$format.'"', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
