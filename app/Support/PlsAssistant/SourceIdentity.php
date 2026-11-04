<?php

namespace App\Support\PlsAssistant;

use Illuminate\Support\Str;

class SourceIdentity
{
    public static function kind(string $text): ?string
    {
        $heading = Str::upper(Str::squish(mb_substr($text, 0, 4000)));

        if (preg_match('/PARLIAMENTARY DEBATES.{0,80}OFFICIAL REPORT/u', $heading)) {
            return 'parliamentary_record';
        }

        if (preg_match('/A BILL (?:ENTITLED|INTITULED)|BILL,?\s+20\d{2}.{0,150}ARRANGEMENT OF CLAUSES/u', $heading)) {
            return 'bill';
        }

        return null;
    }

    public static function guardLegislation(array $analysis, string $text): array
    {
        $kind = self::kind($text);

        if ($kind === null) {
            return $analysis;
        }

        $analysis['legislation_type'] = $kind;
        $analysis['date_enacted'] = '';
        $analysis['warnings'] = array_values(array_unique([
            ...($analysis['warnings'] ?? []),
            $kind === 'bill'
                ? 'This source identifies itself as a bill. Confirm the enacted version and assent separately before treating its provisions as law.'
                : 'This is a parliamentary debate record, not an Act. Statements and proposed amendments are not enacted provisions.',
        ]));

        if ($kind === 'parliamentary_record') {
            $analysis['title'] = 'Parliamentary debates — '.($analysis['source_label'] ?? 'Official report');
            $analysis['short_title'] = '';
            $analysis['relationship_type'] = 'related';
        } else {
            $analysis['title'] = preg_replace('/\bAct\b/u', 'Bill', $analysis['title']);
            if (isset($analysis['short_title'])) {
                $analysis['short_title'] = preg_replace('/\bAct\b/u', 'Bill', $analysis['short_title']);
            }
        }

        return $analysis;
    }
}
