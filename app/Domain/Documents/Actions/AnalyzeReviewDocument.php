<?php

namespace App\Domain\Documents\Actions;

use App\Ai\Agents\ReviewDocumentExtractorAgent;
use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentType;
use App\Support\PlsAssistant\AssistantSourceTextExtractorFactory;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

class AnalyzeReviewDocument
{
    public function __construct(private readonly AssistantSourceTextExtractorFactory $extractorFactory) {}

    /**
     * @return array{
     *     status: string,
     *     extraction_driver: string|null,
     *     extraction_method: string|null,
     *     extraction_metadata: array<string, mixed>,
     *     document_id: int,
     *     title: string,
     *     document_type: string,
     *     summary: string,
     *     key_themes: list<string>,
     *     notable_excerpts: list<string>,
     *     important_dates: list<string>,
     *     warnings: list<string>,
     *     raw_text: string
     * }
     */
    public function analyze(Document $document): array
    {
        [$status, $rawText, $warnings, $extractionDriver, $extractionMethod, $extractionMetadata] = $this->extractRawText($document);

        if ($status !== 'completed') {
            return $this->buildFailureResult(
                document: $document,
                status: $status,
                warnings: $warnings,
                extractionDriver: $extractionDriver,
                extractionMethod: $extractionMethod,
                extractionMetadata: $extractionMetadata,
                rawText: $rawText,
            );
        }

        $aiError = null;
        $aiExtraction = $this->extractWithAi($document, $rawText, $aiError);

        if ($aiExtraction === null) {
            return $this->buildFailureResult(
                document: $document,
                status: 'failed',
                warnings: [$this->aiFailureWarning($aiError)],
                extractionDriver: $extractionDriver,
                extractionMethod: $extractionMethod,
                extractionMetadata: $extractionMetadata,
                rawText: $rawText,
            );
        }

        return [
            'status' => 'completed',
            'extraction_driver' => $extractionDriver,
            'extraction_method' => $extractionMethod,
            'extraction_metadata' => $extractionMetadata,
            'document_id' => $document->id,
            'title' => $aiExtraction['title'],
            'document_type' => $aiExtraction['document_type'],
            'summary' => $aiExtraction['summary'] ?? '',
            'key_themes' => $aiExtraction['key_themes'],
            'notable_excerpts' => $aiExtraction['notable_excerpts'],
            'important_dates' => $aiExtraction['important_dates'],
            'warnings' => $aiExtraction['warnings'],
            'raw_text' => $rawText,
        ];
    }

    /**
     * @return array{0: string, 1: string, 2: list<string>, 3: string|null, 4: string|null, 5: array<string, mixed>}
     */
    private function extractRawText(Document $document): array
    {
        $existingChunkText = trim($document->chunks()->pluck('content')->implode("\n\n"));

        if ($existingChunkText !== '') {
            return ['completed', $existingChunkText, [], null, null, []];
        }

        $storagePath = trim($document->storage_path);

        if ($storagePath === '') {
            return ['failed', '', ['This document does not have a stored file path.'], null, null, []];
        }

        $extension = $this->documentExtension($document);

        if (! in_array($extension, ['pdf', 'docx', 'txt', 'text', 'md'], true)) {
            return ['failed', '', [sprintf('The selected document type [%s] is not supported yet.', $extension)], null, null, []];
        }

        $result = $this->extractorFactory->make()->extract($document);

        if ($result->status === 'processing' && $this->pollAttemptsExceeded($document)) {
            return [
                'failed',
                '',
                ['Text extraction is taking longer than expected. Retry the document to start again.'],
                $result->driver,
                $result->method,
                $result->metadata,
            ];
        }

        return match ($result->status) {
            'completed' => ['completed', trim((string) $result->content), [], $result->driver, $result->method, $result->metadata],
            'processing' => ['processing', '', ['The document file is still being processed.'], $result->driver, $result->method, $result->metadata],
            default => ['failed', '', [trim((string) $result->error) !== '' ? (string) $result->error : 'Unable to extract text from the document file.'], $result->driver, $result->method, $result->metadata],
        };
    }

    private function pollAttemptsExceeded(Document $document): bool
    {
        $pollAttempts = (int) data_get($document->metadata, 'extraction.poll_attempts', 0);
        $maxPollAttempts = (int) config('pls_assistant.assistant_sources.textract.max_poll_attempts', 20);

        return $pollAttempts >= $maxPollAttempts;
    }

    private function documentExtension(Document $document): string
    {
        $originalName = trim((string) data_get($document->metadata, 'original_name', ''));
        $path = $originalName !== '' ? $originalName : $document->storage_path;

        return Str::lower(pathinfo($path, PATHINFO_EXTENSION));
    }

    /**
     * @return array{
     *     title: string,
     *     document_type: string,
     *     summary: string|null,
     *     key_themes: list<string>,
     *     notable_excerpts: list<string>,
     *     important_dates: list<string>,
     *     warnings: list<string>
     * }|null
     */
    private function extractWithAi(Document $document, string $rawText, ?string &$error = null): ?array
    {
        if (trim($rawText) === '') {
            return null;
        }

        try {
            $response = app(ReviewDocumentExtractorAgent::class)->prompt($this->aiPrompt($document, $rawText));
        } catch (Throwable $exception) {
            $error = $exception->getMessage();

            return null;
        }

        $title = $this->normalizeTitle((string) ($response['title'] ?? ''));
        $documentType = $this->normalizeDocumentType($response['document_type'] ?? null);
        $summary = $this->normalizeSummary($response['summary'] ?? null);
        $keyThemes = $this->normalizeStringList($response['key_themes'] ?? [], 5, 120);
        $notableExcerpts = $this->normalizeStringList($response['notable_excerpts'] ?? [], 3, 320);
        $importantDates = $this->normalizeImportantDates($response['important_dates'] ?? []);
        $warnings = $this->normalizeStringList($response['warnings'] ?? [], 5, 200);

        if ($title === '' || $documentType === null) {
            return null;
        }

        return [
            'title' => $title,
            'document_type' => $documentType->value,
            'summary' => $summary,
            'key_themes' => $keyThemes,
            'notable_excerpts' => $notableExcerpts,
            'important_dates' => $importantDates,
            'warnings' => $warnings,
        ];
    }

    private function aiPrompt(Document $document, string $rawText): string
    {
        $sourceExcerpt = $this->sourceExcerptForAi($rawText);

        return implode("\n\n", array_filter([
            'Document title: '.$document->title,
            'Extract the review document fields from this source text.',
            'Return the structured fields only.',
            'Source text excerpt:'."\n".$sourceExcerpt,
        ]));
    }

    private function sourceExcerptForAi(string $rawText): string
    {
        $normalizedText = Str::of($rawText)
            ->replace("\r", '')
            ->replaceMatches("/\n{3,}/", "\n\n")
            ->trim()
            ->toString();

        $frontMatter = Str::limit($normalizedText, 18000, '');

        $signalLines = collect(preg_split("/\R/", $normalizedText) ?: [])
            ->filter(fn (string $line): bool => preg_match('/\b(report|submission|transcript|minutes|policy|response|recommendation|finding|dated|published|meeting|hearing)\b/i', $line) === 1)
            ->map(fn (string $line): string => trim($line))
            ->filter()
            ->unique()
            ->take(20)
            ->implode("\n");

        return trim(implode("\n\n", array_filter([
            $frontMatter,
            $signalLines !== '' ? "Key lines:\n".$signalLines : null,
        ])));
    }

    private function aiFailureWarning(?string $error): string
    {
        if (is_string($error) && Str::contains(Str::lower($error), ['timed out', 'timeout', 'curl error 28'])) {
            return 'The document text was extracted, but the AI review step timed out. Retry the document to try again.';
        }

        return 'The document text was extracted, but the AI review step failed. Retry the document to try again.';
    }

    /**
     * @param  list<string>  $warnings
     * @param  array<string, mixed>  $extractionMetadata
     * @return array{
     *     status: string,
     *     extraction_driver: string|null,
     *     extraction_method: string|null,
     *     extraction_metadata: array<string, mixed>,
     *     document_id: int,
     *     title: string,
     *     document_type: string,
     *     summary: string,
     *     key_themes: list<string>,
     *     notable_excerpts: list<string>,
     *     important_dates: list<string>,
     *     warnings: list<string>,
     *     raw_text: string
     * }
     */
    private function buildFailureResult(
        Document $document,
        string $status,
        array $warnings,
        ?string $extractionDriver,
        ?string $extractionMethod,
        array $extractionMetadata,
        string $rawText,
    ): array {
        return [
            'status' => $status,
            'extraction_driver' => $extractionDriver,
            'extraction_method' => $extractionMethod,
            'extraction_metadata' => $extractionMetadata,
            'document_id' => $document->id,
            'title' => $document->title,
            'document_type' => $document->document_type->value,
            'summary' => (string) ($document->summary ?? ''),
            'key_themes' => [],
            'notable_excerpts' => [],
            'important_dates' => [],
            'warnings' => array_values(array_unique(array_filter($warnings))),
            'raw_text' => $rawText,
        ];
    }

    private function normalizeTitle(string $value): string
    {
        return Str::of($value)
            ->replaceMatches('/\s+/', ' ')
            ->trim(" \t\n\r\0\x0B-:")
            ->limit(255, '')
            ->toString();
    }

    private function normalizeSummary(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return Str::of($value)
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->limit(5000, '')
            ->toString();
    }

    private function normalizeDocumentType(mixed $value): ?DocumentType
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $type = DocumentType::tryFrom(trim($value));

        if ($type === null || $type === DocumentType::LegislationText) {
            return null;
        }

        return $type;
    }

    /**
     * @return list<string>
     */
    private function normalizeStringList(mixed $value, int $limit, int $maxLength): array
    {
        return Collection::wrap($value)
            ->filter(fn (mixed $item): bool => is_string($item) && trim($item) !== '')
            ->map(fn (mixed $item): string => Str::of((string) $item)
                ->replaceMatches('/\s+/', ' ')
                ->trim()
                ->limit($maxLength, '')
                ->toString())
            ->filter()
            ->unique()
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function normalizeImportantDates(mixed $value): array
    {
        return Collection::wrap($value)
            ->filter(fn (mixed $item): bool => is_string($item) && trim($item) !== '')
            ->map(fn (mixed $item): ?string => $this->normalizeDate((string) $item))
            ->filter()
            ->unique()
            ->take(5)
            ->values()
            ->all();
    }

    private function normalizeDate(string $value): ?string
    {
        $value = Str::of($value)
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->limit(120, '')
            ->toString();

        if ($value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->toDateString();
        } catch (Throwable) {
            return $value;
        }
    }
}
