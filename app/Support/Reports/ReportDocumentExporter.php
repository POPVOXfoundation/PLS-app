<?php

namespace App\Support\Reports;

use Illuminate\Http\Response;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\Shared\Html;
use Spatie\LaravelPdf\Facades\Pdf;

class ReportDocumentExporter
{
    public function safeHtml(string $content): string
    {
        $html = Str::markdown($content, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
        $html = preg_replace('/<img\b[^>]*>/i', '', $html) ?? '';

        return preg_replace_callback('/<a href="([^"]*)"([^>]*)>(.*?)<\/a>/s', function (array $match): string {
            $url = html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');

            return preg_match('/^https?:\/\//i', $url) ? $match[0] : $match[3];
        }, $html) ?? '';
    }

    public function download(string $format, string $title, string $content, string $status, int $revision): Response
    {
        $html = $this->safeHtml($content);
        $label = $status === 'published' ? __('Published report') : ($status === 'archived' ? __('Archived report') : __('Working draft — human review required'));
        if ($format === 'pdf') {
            return Pdf::view('reports.export', compact('title', 'html', 'label', 'revision'))->driver('dompdf')->download('pls-report.pdf')
                ->headers(['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'])->toResponse(request());
        }
        if ($format === 'md') {
            return response('# '.$title."\n\n".$label."\n\n".$content, 200, $this->headers('text/markdown; charset=UTF-8', 'md'));
        }
        Settings::setOutputEscapingEnabled(true);
        $word = new PhpWord;
        $word->setDefaultFontName('Calibri');
        $word->setDefaultFontSize(11);
        foreach ([1 => 22, 2 => 16, 3 => 13] as $level => $size) {
            $word->addTitleStyle($level, ['bold' => true, 'size' => $size], ['spaceAfter' => 180, 'keepNext' => true]);
        }
        $section = $word->addSection();
        $section->addTitle($title, 1);
        $section->addText($label.' · '.__('Revision :number', ['number' => $revision]));
        $html = str_replace('<a href=', '<a style="color:#50376b;text-decoration:underline;" href=', $html);
        $html = str_replace(['<th>', '<td>'], ['<th style="font-weight:bold;border:1px solid #bbb;padding:5pt;">', '<td style="border:1px solid #bbb;padding:5pt;">'], $html);
        Html::addHtml($section, $html, false, false);
        $section->addFooter()->addPreserveText('Page {PAGE} of {NUMPAGES}', ['size' => 9, 'color' => '666666'], ['alignment' => 'right']);
        $path = tempnam(sys_get_temp_dir(), 'pls-docx-');
        try {
            IOFactory::createWriter($word, 'Word2007')->save($path);
            $bytes = file_get_contents($path);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }

        return response($bytes, 200, $this->headers('application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'docx'));
    }

    /** @return array<string, string> */
    private function headers(string $type, string $extension): array
    {
        return ['Content-Type' => $type, 'Content-Disposition' => 'attachment; filename="pls-report.'.$extension.'"', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'];
    }
}
