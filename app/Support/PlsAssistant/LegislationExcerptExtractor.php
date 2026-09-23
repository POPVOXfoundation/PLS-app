<?php

namespace App\Support\PlsAssistant;

use Illuminate\Support\Str;

class LegislationExcerptExtractor
{
    /**
     * @return list<string>
     */
    public function extract(string $sourceText, int $limit = 3): array
    {
        $sourceText = trim($sourceText);

        if ($sourceText === '' || $limit < 1) {
            return [];
        }

        $candidates = collect(preg_split('/(?<=[.!?;:])\s+|\R{2,}/u', $sourceText) ?: [])
            ->map(fn (string $passage): string => Str::of($passage)
                ->replaceMatches('/\s+/', ' ')
                ->trim()
                ->limit(320, '')
                ->toString());

        $passages = $candidates
            ->filter(fn (string $passage): bool => mb_strlen($passage) >= 55
                && ! Str::startsWith($passage, ['http://', 'https://'])
                && preg_match('/\b(shall|must|may|duty|responsible|establish|appoint|report|review|implement|comply|require|entitled|prohibit|offence|penalty|regulation|committee|minister|authority)\b/i', $passage) === 1)
            ->unique()
            ->take($limit)
            ->values();

        if ($passages->count() < $limit) {
            $fallbacks = $candidates
                ->filter(fn (string $passage): bool => mb_strlen($passage) >= 70
                    && ! Str::startsWith($passage, ['http://', 'https://']))
                ->reject(fn (string $passage): bool => $passages->contains($passage))
                ->take($limit - $passages->count());

            $passages = $passages->concat($fallbacks);
        }

        return $passages->take($limit)->values()->all();
    }
}
