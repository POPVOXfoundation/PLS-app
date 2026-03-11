<?php

namespace App\Domain\Documents\Actions;

use App\Domain\Documents\Document;
use App\Domain\Documents\DocumentChunk;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;

class ChunkDocumentText
{
    public function __construct(
        private DatabaseManager $database,
    ) {
    }

    /**
     * @return \Illuminate\Support\Collection<int, DocumentChunk>
     */
    public function chunk(Document $document, string $rawText, int $maxCharacters = 1200): Collection
    {
        $maxCharacters = max(200, $maxCharacters);
        $normalizedText = $this->normalizeText($rawText);

        return $this->database->transaction(function () use ($document, $normalizedText, $maxCharacters): Collection {
            $document->chunks()->delete();

            if ($normalizedText === '') {
                return collect();
            }

            $segments = $this->splitIntoSegments($normalizedText, $maxCharacters);

            $document->chunks()->createMany(
                collect($segments)
                    ->values()
                    ->map(fn (string $segment, int $index): array => [
                        'chunk_index' => $index,
                        'content' => $segment,
                        'token_count' => $this->estimateTokenCount($segment),
                        'embedding' => null,
                        'metadata' => [
                            'character_count' => mb_strlen($segment),
                            'strategy' => 'paragraph_window_v1',
                        ],
                    ])
                    ->all(),
            );

            return $document->chunks()->get();
        });
    }

    private function normalizeText(string $rawText): string
    {
        $normalizedText = preg_replace("/[ \t]+/", ' ', $rawText) ?? $rawText;
        $normalizedText = preg_replace("/\R{3,}/", "\n\n", $normalizedText) ?? $normalizedText;

        return trim($normalizedText);
    }

    /**
     * @return list<string>
     */
    private function splitIntoSegments(string $text, int $maxCharacters): array
    {
        $paragraphs = preg_split("/\R{2,}/", $text) ?: [$text];
        $segments = [];
        $currentSegment = '';

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);

            if ($paragraph === '') {
                continue;
            }

            if (mb_strlen($paragraph) > $maxCharacters) {
                if ($currentSegment !== '') {
                    $segments[] = $currentSegment;
                    $currentSegment = '';
                }

                array_push($segments, ...$this->splitLongSegment($paragraph, $maxCharacters));

                continue;
            }

            $candidate = $currentSegment === '' ? $paragraph : $currentSegment."\n\n".$paragraph;

            if (mb_strlen($candidate) <= $maxCharacters) {
                $currentSegment = $candidate;

                continue;
            }

            if ($currentSegment !== '') {
                $segments[] = $currentSegment;
            }

            $currentSegment = $paragraph;
        }

        if ($currentSegment !== '') {
            $segments[] = $currentSegment;
        }

        return array_values(array_filter($segments, fn (string $segment): bool => $segment !== ''));
    }

    /**
     * @return list<string>
     */
    private function splitLongSegment(string $paragraph, int $maxCharacters): array
    {
        $words = preg_split('/\s+/', $paragraph) ?: [$paragraph];
        $segments = [];
        $currentSegment = '';

        foreach ($words as $word) {
            $candidate = $currentSegment === '' ? $word : $currentSegment.' '.$word;

            if (mb_strlen($candidate) <= $maxCharacters) {
                $currentSegment = $candidate;

                continue;
            }

            if ($currentSegment !== '') {
                $segments[] = $currentSegment;
            }

            $currentSegment = $word;
        }

        if ($currentSegment !== '') {
            $segments[] = $currentSegment;
        }

        return $segments;
    }

    /**
     * TODO: Replace this heuristic count and null embedding with a real tokenizer and embedding pipeline later.
     */
    private function estimateTokenCount(string $content): int
    {
        return max(1, str_word_count($content));
    }
}
