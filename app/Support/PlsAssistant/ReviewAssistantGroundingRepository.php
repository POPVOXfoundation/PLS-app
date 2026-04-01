<?php

namespace App\Support\PlsAssistant;

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
            'global' => $this->globalReferences(),
            'jurisdiction' => $this->jurisdictionReferences($review),
            'review' => $this->reviewReferences($review, $prompt),
        ];
    }

    /**
     * @return list<array{excerpt: string, label: string, source: string}>
     */
    private function globalReferences(): array
    {
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
    private function jurisdictionReferences(PlsReview $review): array
    {
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
     * @return list<array{excerpt: string, label: string, source: string}>
     */
    private function reviewReferences(PlsReview $review, string $prompt): array
    {
        $tokens = collect(preg_split('/[^a-z0-9]+/i', Str::lower($prompt)) ?: [])
            ->filter(fn (string $token): bool => mb_strlen($token) >= 4)
            ->values();

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
