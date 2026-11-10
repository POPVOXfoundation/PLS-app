<?php

namespace App\Support\Documents;

use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Spatie\SimpleExcel\SimpleExcelReader;
use Throwable;
use ZipArchive;

class ReviewRecordSpreadsheet
{
    public const COLUMNS = ['title', 'filename', 'url', 'type', 'date', 'organisation', 'stakeholder', 'notes'];

    /** @return list<array<string, string>> */
    public function readUpload(TemporaryUploadedFile $upload): array
    {
        $scratch = tmpfile();
        if ($scratch === false) {
            throw ValidationException::withMessages(['spreadsheet' => __('The spreadsheet could not be opened.')]);
        }

        try {
            $stream = ReviewUpload::open($upload);
            try {
                if (stream_copy_to_stream($stream, $scratch) !== $upload->getSize()) {
                    throw ValidationException::withMessages(['spreadsheet' => __('The spreadsheet could not be read.')]);
                }
            } finally {
                fclose($stream);
            }

            return $this->read(stream_get_meta_data($scratch)['uri'], strtolower($upload->getClientOriginalExtension()));
        } finally {
            fclose($scratch);
        }
    }

    /** @return list<array<string, string>> */
    public function read(string $path, string $extension): array
    {
        if (! in_array($extension, ['csv', 'xlsx'], true)) {
            throw ValidationException::withMessages(['spreadsheet' => __('Choose a CSV or XLSX file.')]);
        }
        if ($extension === 'xlsx') {
            $zip = new ZipArchive;
            if ($zip->open($path) !== true) {
                throw ValidationException::withMessages(['spreadsheet' => __('The Excel file could not be opened.')]);
            }
            $size = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $size += $zip->statIndex($i)['size'];
            }
            $zip->close();
            if ($size > 20 * 1024 * 1024) {
                throw ValidationException::withMessages(['spreadsheet' => __('The expanded spreadsheet must be at most 20 MB.')]);
            }
        }
        $reader = null;
        try {
            $reader = SimpleExcelReader::create($path, $extension)->keepFormulas();
            if (array_map(fn ($header): string => trim((string) $header, "\xEF\xBB\xBF \r\n\t"), $reader->getHeaders() ?? []) !== self::COLUMNS) {
                throw ValidationException::withMessages(['spreadsheet' => __('Use the template column headings and order.')]);
            }
            $rows = $reader->take(251)->getRows()->map(fn (array $row): array => array_map(fn ($value): string => $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : trim((string) $value), $row))->values()->all();
            if (count($rows) > 250) {
                throw ValidationException::withMessages(['spreadsheet' => __('Import at most 250 rows per batch.')]);
            }

            return $rows;
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages(['spreadsheet' => __('The spreadsheet could not be read. Check the template and save a valid CSV or XLSX file.')]);
        } finally {
            $reader?->close();
        }
    }
}
