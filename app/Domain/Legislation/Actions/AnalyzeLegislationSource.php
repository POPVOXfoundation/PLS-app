<?php

namespace App\Domain\Legislation\Actions;

use App\Ai\Agents\LegislationSourceExtractorAgent;
use App\Domain\Documents\Document;
use App\Domain\Legislation\Enums\LegislationType;
use App\Domain\Legislation\Enums\ReviewLegislationRelationshipType;
use App\Domain\Legislation\Legislation;
use App\Support\PlsAssistant\AssistantSourceTextExtractorFactory;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

class AnalyzeLegislationSource
{
    public function __construct(private readonly AssistantSourceTextExtractorFactory $extractorFactory) {}

    /**
     * @return array{
     *     status: string,
     *     extraction_driver: string|null,
     *     extraction_method: string|null,
     *     extraction_metadata: array<string, mixed>,
     *     source_document_id: int,
     *     source_label: string,
     *     title: string,
     *     short_title: string,
     *     legislation_type: string,
     *     date_enacted: string,
     *     summary: string,
     *     relationship_type: string,
     *     signals: list<string>,
     *     hints: list<string>,
     *     warnings: list<string>,
     *     duplicate_candidates: list<array{id: int, title: string, short_title: string, legislation_type: string, date_enacted: string}>,
     *     raw_text: string
     * }
     */
    public function analyze(Document $document, int $jurisdictionId): array
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
                warnings: [
                    $this->aiFailureWarning($aiError),
                ],
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
            'source_document_id' => $document->id,
            'source_label' => $document->title,
            'title' => $aiExtraction['title'],
            'short_title' => $aiExtraction['short_title'] ?? '',
            'legislation_type' => $aiExtraction['legislation_type'],
            'date_enacted' => $aiExtraction['date_enacted'] ?? '',
            'summary' => $aiExtraction['summary'] ?? '',
            'relationship_type' => $aiExtraction['relationship_type'],
            'signals' => [],
            'hints' => [],
            'warnings' => $aiExtraction['warnings'],
            'duplicate_candidates' => $this->duplicateCandidates(
                jurisdictionId: $jurisdictionId,
                title: $aiExtraction['title'],
                shortTitle: $aiExtraction['short_title'],
            ),
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
            return ['failed', '', ['This source document does not have a stored file path.'], null, null, []];
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
                ['Text extraction is taking longer than expected. Retry the source to start again.'],
                $result->driver,
                $result->method,
                $result->metadata,
            ];
        }

        return match ($result->status) {
            'completed' => ['completed', trim((string) $result->content), [], $result->driver, $result->method, $result->metadata],
            'processing' => ['processing', '', ['The source file is still being processed.'], $result->driver, $result->method, $result->metadata],
            default => ['failed', '', [trim((string) $result->error) !== '' ? (string) $result->error : 'Unable to extract text from the source file.'], $result->driver, $result->method, $result->metadata],
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
     *     short_title: string|null,
     *     legislation_type: string,
     *     date_enacted: string|null,
     *     summary: string|null,
     *     relationship_type: string,
     *     warnings: list<string>
     * }|null
     */
    private function extractWithAi(Document $document, string $rawText, ?string &$error = null): ?array
    {
        if (trim($rawText) === '') {
            return null;
        }

        try {
            $response = app(LegislationSourceExtractorAgent::class)->prompt($this->aiPrompt($document, $rawText));
        } catch (Throwable $exception) {
            $error = $exception->getMessage();

            return null;
        }

        $title = $this->normalizeTitle((string) ($response['title'] ?? ''));
        $shortTitle = $this->normalizeShortTitle($response['short_title'] ?? null, $title);
        $legislationType = $this->normalizeLegislationType($response['legislation_type'] ?? null);
        $dateEnacted = $this->normalizeDate($response['date_enacted'] ?? null);
        $summary = $this->normalizeSummary($response['summary'] ?? null);
        $relationshipType = $this->normalizeRelationshipType($response['relationship_type'] ?? null);
        $warnings = $this->normalizeWarnings($response['warnings'] ?? []);

        if ($title === '' || $legislationType === null || $relationshipType === null) {
            return null;
        }

        return [
            'title' => $title,
            'short_title' => $shortTitle,
            'legislation_type' => $legislationType->value,
            'date_enacted' => $dateEnacted,
            'summary' => $summary,
            'relationship_type' => $relationshipType->value,
            'warnings' => $warnings,
        ];
    }

    private function aiPrompt(Document $document, string $rawText): string
    {
        $sourceExcerpt = $this->sourceExcerptForAi($rawText);

        return implode("\n\n", array_filter([
            'Document title: '.$document->title,
            'Extract the legislation fields from this source text.',
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
            ->filter(fn (string $line): bool => preg_match('/\b(short title|long title|bill|act|regulation|ordinance|enacted|assented|dated|made on|gazetted|commenced)\b/i', $line) === 1)
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
            return 'The source text was extracted, but the AI record step timed out. Retry the source to try again.';
        }

        return 'The source text was extracted, but the AI record step failed. Retry the source to try again.';
    }

    /**
     * @param  list<string>  $warnings
     * @param  array<string, mixed>  $extractionMetadata
     * @return array{
     *     status: string,
     *     extraction_driver: string|null,
     *     extraction_method: string|null,
     *     extraction_metadata: array<string, mixed>,
     *     source_document_id: int,
     *     source_label: string,
     *     title: string,
     *     short_title: string,
     *     legislation_type: string,
     *     date_enacted: string,
     *     summary: string,
     *     relationship_type: string,
     *     signals: list<string>,
     *     hints: list<string>,
     *     warnings: list<string>,
     *     duplicate_candidates: list<array{id: int, title: string, short_title: string, legislation_type: string, date_enacted: string}>,
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
            'source_document_id' => $document->id,
            'source_label' => $document->title,
            'title' => $document->title,
            'short_title' => '',
            'legislation_type' => '',
            'date_enacted' => '',
            'summary' => '',
            'relationship_type' => '',
            'signals' => [],
            'hints' => [],
            'warnings' => array_values(array_unique(array_filter($warnings))),
            'duplicate_candidates' => [],
            'raw_text' => $rawText,
        ];
    }

    private function normalizeDate(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse(trim($value))->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    private function normalizeTitle(string $value): string
    {
        return Str::of($value)
            ->replaceMatches('/\s+/', ' ')
            ->trim(" \t\n\r\0\x0B-:")
            ->limit(255, '')
            ->toString();
    }

    private function normalizeShortTitle(mixed $value, string $title): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $shortTitle = Str::of($value)
            ->replaceMatches('/\s+/', ' ')
            ->trim(" \t\n\r\0\x0B-:")
            ->limit(255, '')
            ->toString();

        if ($shortTitle === '' || Str::lower($shortTitle) === Str::lower($title)) {
            return null;
        }

        return $shortTitle;
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

    private function normalizeLegislationType(mixed $value): ?LegislationType
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return LegislationType::tryFrom(trim($value));
    }

    private function normalizeRelationshipType(mixed $value): ?ReviewLegislationRelationshipType
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return ReviewLegislationRelationshipType::tryFrom(trim($value));
    }

    /**
     * @return list<string>
     */
    private function normalizeWarnings(mixed $value): array
    {
        return Collection::wrap($value)
            ->filter(fn (mixed $warning): bool => is_string($warning) && trim($warning) !== '')
            ->map(fn (mixed $warning): string => trim((string) $warning))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, title: string, short_title: string, legislation_type: string, date_enacted: string}>
     */
    private function duplicateCandidates(int $jurisdictionId, string $title, ?string $shortTitle): array
    {
        $normalizedCandidates = collect([$title, $shortTitle])
            ->filter(fn (?string $value): bool => $value !== null && trim($value) !== '')
            ->map(fn (string $value): string => $this->normalizeTitleKey($value))
            ->unique()
            ->values();

        if ($normalizedCandidates->isEmpty()) {
            return [];
        }

        return Legislation::query()
            ->where('jurisdiction_id', $jurisdictionId)
            ->get()
            ->filter(function (Legislation $legislation) use ($normalizedCandidates): bool {
                return $normalizedCandidates->contains($this->normalizeTitleKey($legislation->title))
                    || ($legislation->short_title !== null && $normalizedCandidates->contains($this->normalizeTitleKey($legislation->short_title)));
            })
            ->map(fn (Legislation $legislation): array => [
                'id' => $legislation->id,
                'title' => $legislation->title,
                'short_title' => $legislation->short_title ?? '',
                'legislation_type' => $legislation->legislation_type->value,
                'date_enacted' => $legislation->date_enacted?->toDateString() ?? '',
            ])
            ->values()
            ->all();
    }

    private function normalizeTitleKey(string $value): string
    {
        return Str::of($value)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->toString();
    }
}
