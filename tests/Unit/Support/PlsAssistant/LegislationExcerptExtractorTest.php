<?php

use App\Support\PlsAssistant\LegislationExcerptExtractor;

test('it derives verbatim excerpts when ai enrichment returns none', function () {
    $source = <<<'TEXT'
    The purpose of this Act is to advance gender equality in public and private institutions.

    The Minister shall establish a Gender Equality Committee responsible for monitoring compliance and submitting an annual report to Parliament.

    Every covered authority must prepare an implementation plan within twelve months after commencement.
    TEXT;

    $excerpts = app(LegislationExcerptExtractor::class)->extract($source);

    expect($excerpts)
        ->toHaveCount(3)
        ->toContain('The Minister shall establish a Gender Equality Committee responsible for monitoring compliance and submitting an annual report to Parliament.')
        ->toContain('Every covered authority must prepare an implementation plan within twelve months after commencement.');
});
