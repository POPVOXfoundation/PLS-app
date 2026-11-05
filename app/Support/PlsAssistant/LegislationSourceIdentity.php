<?php

namespace App\Support\PlsAssistant;

use App\Domain\Legislation\Enums\LegislationType;

class LegislationSourceIdentity
{
    public function classify(string $title, string $text): ?LegislationType
    {
        $heading = $title."\n".mb_substr($text, 0, 1500);
        if (preg_match('/(?:^|\n)\s*(?:parliamentary\s+debates?|hansard)\b/iu', $heading)) {
            return LegislationType::ParliamentaryDebate;
        }
        if (preg_match('/(?:^|\n)[^\n]{0,200}\bbill(?:\s*,?\s*(?:19|20)\d{2})?\s*(?:\n|$)/iu', $heading)) {
            return LegislationType::Bill;
        }

        return null;
    }
}
