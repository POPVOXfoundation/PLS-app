<?php

namespace App\Support\Documents;

use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

class RecordSpreadsheet
{
    public const COLUMNS = ['title', 'filename', 'url', 'type', 'date', 'organisation', 'stakeholder', 'notes'];

    public function read(string $path, string $extension): array
    {
        try {
            return $this->readRows($path, $extension);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            report($exception);
            $this->invalid('The spreadsheet could not be read. Use the CSV template or save a valid XLSX file and try again.');
        }
    }

    private function readRows(string $path, string $extension): array
    {
        if (! in_array($extension, ['csv', 'xlsx'])) {
            $this->invalid('Choose a CSV or XLSX file.');
        }
        if ($extension === 'xlsx') {
            $zip = new \ZipArchive;
            if ($zip->open($path) !== true) {
                $this->invalid('The spreadsheet could not be opened.');
            }
            $size = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $size += $zip->statIndex($i)['size'];
            }
            $zip->close();
            if ($size > 20 * 1024 * 1024) {
                $this->invalid('The expanded spreadsheet must be smaller than 20 MB.');
            }
        }
        $reader = IOFactory::createReader($extension === 'csv' ? 'Csv' : 'Xlsx');
        $sheetInfo = $reader->listWorksheetInfo($path);
        if (($sheetInfo[0]['totalRows'] ?? 0) > 251) {
            $this->invalid('Import at most 250 rows at a time.');
        }
        if ($extension === 'xlsx' && isset($sheetInfo[0]['worksheetName'])) {
            $reader->setLoadSheetsOnly($sheetInfo[0]['worksheetName']);
        }
        $reader->setReadDataOnly(true);
        $reader->setReadFilter(new class implements IReadFilter
        {
            public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
            {
                return $row <= 252 && strlen($columnAddress) === 1 && $columnAddress <= 'H';
            }
        });
        $book = $reader->load($path);
        try {
            $sheet = $book->getSheet(0);
            if ($sheet->getHighestDataRow() > 251) {
                $this->invalid('Import at most 250 rows at a time.');
            }
            $data = $sheet->toArray(null, false, false, false); // Never evaluate spreadsheet formulas.
            $headers = array_map(fn ($value) => strtolower(trim((string) $value, "\xEF\xBB\xBF \t\r\n")), array_shift($data) ?? []);
            if ($headers !== self::COLUMNS) {
                $this->invalid('Use the template column headings and order. Dates should use YYYY-MM-DD.');
            }

            return collect($data)->filter(fn ($row) => count(array_filter($row, fn ($cell) => $cell !== null && $cell !== '')))->map(fn ($row) => array_combine(self::COLUMNS, array_map(fn ($cell) => trim((string) ($cell ?? '')), array_pad(array_slice($row, 0, 8), 8, ''))))->values()->all();
        } finally {
            $book->disconnectWorksheets();
        }
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['spreadsheet' => $message]);
    }
}
