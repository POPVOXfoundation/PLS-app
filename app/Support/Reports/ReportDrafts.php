<?php

namespace App\Support\Reports;

class ReportDrafts
{
    public static function sections(string $content): array
    {
        $parts = preg_split('/(?=^##\s+)/m', $content, -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_map(fn ($part) => trim($part), $parts ?: ['']));
    }

    public static function label(string $section, int $index): string
    {
        return preg_match('/^##\s+(.+)/', $section, $matches) ? $matches[1] : ($index === 0 ? 'Introduction' : 'Section '.($index + 1));
    }
}
