<?php

namespace App\Support\Reports;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Html;

class ReportExporter
{
    public function html(string $title, string $content, string $status = 'draft'): string
    {
        $html = Str::markdown($content, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
        // External images are neither fetched by the server nor embedded in exports.
        $html = preg_replace('/<img\b[^>]*>/i', '', $html);

        $label = match ($status) {
            'published' => 'Published report', 'archived' => 'Archived report', 'version' => 'Previous saved version',
            default => 'Working draft · Human review required',
        };

        return '<h1>'.e($title).'</h1><p>'.$label.'</p>'.$html;
    }

    public function export(string $format, string $title, string $content, string $status = 'draft'): string
    {
        if ($format === 'md') {
            return '# '.$title."\n\n".$content;
        }
        $html = $this->html($title, $content, $status);
        if ($format === 'pdf') {
            $options = new Options(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false, 'defaultFont' => 'DejaVu Sans']);
            $pdf = new Dompdf($options);
            $pdf->setPaper('A4');
            $pdf->loadHtml('<html><head><meta charset="utf-8"><style>@page{margin:48pt}body{font-size:10pt;line-height:1.5;color:#222}h1{font-size:22pt}h2{font-size:15pt;color:#50376b;page-break-after:avoid}a{color:#50376b}table{border-collapse:collapse;width:100%;font-size:9pt}td,th{border:1px solid #ddd;padding:5pt}pre{white-space:pre-wrap}p{overflow-wrap:break-word}</style></head><body>'.$html.'</body></html>');
            $pdf->render();

            return $pdf->output();
        }
        $word = new PhpWord;
        $word->setDefaultFontName('Calibri');
        $word->setDefaultFontSize(11);
        $word->setDefaultParagraphStyle(['spaceAfter' => 140, 'lineHeight' => 1.15]);
        foreach ([1 => 22, 2 => 15, 3 => 12, 4 => 11, 5 => 11, 6 => 11] as $level => $size) {
            $word->addTitleStyle($level, ['size' => $size, 'bold' => true, 'color' => $level === 1 ? '222222' : '50376B'], ['spaceBefore' => 220, 'spaceAfter' => 120, 'keepNext' => true]);
        }
        $html = preg_replace('/<h([1-6])>/', '<h$1 style="font-weight:bold;color:#50376b;">', $html);
        $html = str_replace('<a href=', '<a style="color:#50376b;text-decoration:underline;" href=', $html);
        $html = str_replace('<table>', '<table style="border:1px solid #cccccc;">', $html);
        $html = str_replace(['<th>', '<td>'], ['<th style="font-weight:bold;border:1px solid #cccccc;padding:5pt;">', '<td style="border:1px solid #cccccc;padding:5pt;">'], $html);
        $section = $word->addSection();
        $section->addFooter()->addPreserveText('Page {PAGE}', ['size' => 9, 'color' => '666666'], ['alignment' => 'right']);
        Html::addHtml($section, $html, false, false);
        $path = tempnam(sys_get_temp_dir(), 'pls-report-');
        try {
            IOFactory::createWriter($word, 'Word2007')->save($path);

            return file_get_contents($path);
        } finally {
            @unlink($path);
        }
    }
}
