<?php

namespace App\Support\PlsAssistant;

use App\Domain\Documents\AssistantSourceDocument;
use App\Domain\Documents\Document;
use App\Domain\Reviews\PlsReview;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ReviewAssistantGroundingRepository
{
    /**
     * @return array{
     *     global: list<array{excerpt: string, label: string, source: string}>,
     *     jurisdiction: list<array{excerpt: string, label: string, source: string}>,
     *     review: list<array{excerpt: string, label: string, source: string}>
     * }
     */
    public function forPrompt(PlsReview $review, string $prompt): array
    {
        return [
            'global' => $this->globalReferences($prompt),
            'jurisdiction' => $this->jurisdictionReferences($review, $prompt),
            'review' => $this->reviewReferences($review, $prompt),
        ];
    }

    /**
     * @return list<array{excerpt: string, label: string, source: string}>
     */
    private function globalReferences(string $prompt): array
    {
        $references = $this->storedSourceReferences(
            AssistantSourceDocument::query()->forGlobalGrounding()->get(),
            $prompt,
            'global',
        );

        if ($references !== []) {
            return $references;
        }

        return collect(config('pls_assistant.reference_documents.global', []))
            ->map(fn (array $reference): array => [
                'label' => (string) ($reference['label'] ?? 'Global reference'),
                'source' => 'global',
                'excerpt' => (string) ($reference['content'] ?? ''),
            ])
            ->filter(fn (array $reference): bool => $reference['excerpt'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return list<array{excerpt: string, label: string, source: string}>
     */
    private function jurisdictionReferences(PlsReview $review, string $prompt): array
    {
        $references = $this->storedSourceReferences(
            AssistantSourceDocument::query()->forJurisdictionContext($review)->get(),
            $prompt,
            'jurisdiction',
            fn (AssistantSourceDocument $document): int => $this->jurisdictionContextScore($document, $review),
        );

        if ($references !== []) {
            return $references;
        }

        $countryName = Str::lower((string) $review->country?->name);
        $jurisdictionName = Str::lower((string) $review->jurisdiction?->name);
        $legislatureName = Str::lower((string) $review->legislature?->name);

        return collect(config('pls_assistant.reference_documents.jurisdictions', []))
            ->filter(function (array $reference) use ($countryName, $jurisdictionName, $legislatureName): bool {
                $scope = collect($reference['scope'] ?? [])
                    ->filter(fn (?string $value): bool => filled($value))
                    ->map(fn (string $value): string => Str::lower($value));

                if ($scope->isEmpty()) {
                    return false;
                }

                return $scope->contains($countryName)
                    || $scope->contains($jurisdictionName)
                    || $scope->contains($legislatureName);
            })
            ->map(fn (array $reference): array => [
                'label' => (string) ($reference['label'] ?? 'Jurisdiction reference'),
                'source' => 'jurisdiction',
                'excerpt' => (string) ($reference['content'] ?? ''),
            ])
            ->filter(fn (array $reference): bool => $reference['excerpt'] !== '')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, AssistantSourceDocument>  $documents
     * @param  callable(AssistantSourceDocument): int|null  $contextScore
     * @return list<array{excerpt: string, label: string, source: string}>
     */
    private function storedSourceReferences(
        Collection $documents,
        string $prompt,
        string $source,
        ?callable $contextScore = null,
    ): array {
        $tokens = $this->promptTokens($prompt);

        return $documents
            ->map(function (AssistantSourceDocument $document) use ($tokens, $source, $contextScore): array {
                $excerpt = $this->bestStoredSourceExcerpt($document, $tokens);
                $score = $this->scoreText(
                    implode(' ', array_filter([
                        $document->title,
                        $document->summary,
                        $document->content,
                        $excerpt,
                    ])),
                    $tokens,
                );

                if ($contextScore !== null) {
                    $score += $contextScore($document);
                }

                return [
                    'label' => $document->title,
                    'source' => $source,
                    'excerpt' => $excerpt,
                    'score' => $score,
                ];
            })
            ->filter(fn (array $reference): bool => $reference['excerpt'] !== '')
            ->sortByDesc('score')
            ->take(3)
            ->map(fn (array $reference): array => [
                'label' => $reference['label'],
                'source' => $reference['source'],
                'excerpt' => $reference['excerpt'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{excerpt: string, label: string, source: string}>
     */
    private function reviewReferences(PlsReview $review, string $prompt): array
    {
        $tokens = $this->promptTokens($prompt);

        return $review->documents
            ->map(function (Document $document) use ($tokens): array {
                $chunk = $document->relationLoaded('chunks')
                    ? $document->chunks
                        ->sortByDesc(fn ($chunk) => $this->scoreText((string) $chunk->content, $tokens))
                        ->first()
                    : null;

                $excerpt = $chunk?->content ?: ($document->summary ?? '');
                $score = $this->scoreText($document->title.' '.$excerpt, $tokens);

                return [
                    'label' => $document->title,
                    'source' => 'review',
                    'excerpt' => Str::limit(trim($excerpt), 280),
                    'score' => $score,
                ];
            })
            ->filter(fn (array $reference): bool => $reference['excerpt'] !== '')
            ->sortByDesc('score')
            ->take(3)
            ->map(fn (array $reference): array => [
                'label' => $reference['label'],
                'source' => 'review',
                'excerpt' => $reference['excerpt'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, string>
     */
    private function promptTokens(string $prompt): Collection
    {
        return collect(preg_split('/[^a-z0-9]+/i', Str::lower($prompt)) ?: [])
            ->filter(fn (string $token): bool => mb_strlen($token) >= 4)
            ->values();
    }

    /**
     * @param  Collection<int, string>  $tokens
     */
    private function bestStoredSourceExcerpt(AssistantSourceDocument $document, Collection $tokens): string
    {
        $sections = collect([
            trim((string) $document->summary),
            ...collect(preg_split('/\n\s*\n+/u', (string) $document->content) ?: [])
                ->map(fn (string $section): string => trim($section))
                ->all(),
        ])->filter();

        if ($sections->isEmpty()) {
            return '';
        }

        /** @var string $excerpt */
        $excerpt = $sections
            ->sortByDesc(fn (string $section): int => $this->scoreText($section, $tokens))
            ->first();

        return Str::limit($excerpt, 280);
    }

    private function jurisdictionContextScore(AssistantSourceDocument $document, PlsReview $review): int
    {
        return collect([
            $document->country_id !== null && $document->country_id === $review->country_id ? 5 : 0,
            $document->jurisdiction_id !== null && $document->jurisdiction_id === $review->jurisdiction_id ? 10 : 0,
            $document->legislature_id !== null && $document->legislature_id === $review->legislature_id ? 15 : 0,
        ])->sum();
    }

    /**
     * @param  Collection<int, string>  $tokens
     */
    private function scoreText(string $text, Collection $tokens): int
    {
        if ($tokens->isEmpty()) {
            return 0;
        }

        $haystack = Str::lower($text);

        return $tokens->sum(fn (string $token): int => Str::contains($haystack, $token) ? 1 : 0);
    }
}
