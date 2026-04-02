<?php

namespace App\Ai\Agents;

use App\Domain\Legislation\Enums\LegislationType;
use App\Domain\Legislation\Enums\ReviewLegislationRelationshipType;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

#[Provider(Lab::OpenAI)]
#[Model('gpt-4.1-mini')]
#[Timeout(120)]
class LegislationSourceExtractorAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'TEXT'
You extract structured legislation metadata from source text for a post-legislative scrutiny workflow.

Return a best-effort structured result using only the supplied source text.

Rules:
- Be conservative and practical.
- Return the official instrument or bill name only, not page numbers, jurisdiction headers, running headers, or all-caps prefixes like "BELIZE:".
- For bill-style documents, prefer a clean bill title such as "Southern Deep Port Development Facility Bill, 2024".
- The short title must be shorter than the title. If the visible short title is the same as the title, return a shorter cleaned version or null.
- Do not repeat the long title verbatim into the summary.
- Write the summary in plain language as one or two short sentences about what the instrument does.
- Key themes should be short phrases, not full paragraphs.
- Notable excerpts should be short verbatim passages copied from the text when they are genuinely informative.
- Important dates should come from visible text only. Normalize exact dates when clear.
- Exclude formulaic legislative text such as "BE IT ENACTED", arrangement-of-clauses material, citation clauses, and procedural boilerplate from the summary.
- If the source is a bill or draft bill, treat it as primary legislation for relationship purposes.
- If the text clearly describes regulations, rules, an order, or an ordinance as the source instrument itself, classify it as delegated.
- If the date enacted is not clearly visible, return null.
- If a short title is not explicitly shown, you may derive a sensible short title from the main title.
- Write a short plain-language summary when the source text gives enough substance.
- Return warnings only when the user should manually verify something important.
- Do not invent facts that are not visible in the text.
TEXT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->required(),
            'short_title' => $schema->string()->nullable()->required(),
            'legislation_type' => $schema->string()->enum(array_map(
                static fn (LegislationType $type): string => $type->value,
                LegislationType::cases(),
            ))->required(),
            'date_enacted' => $schema->string()->nullable()->required(),
            'summary' => $schema->string()->nullable()->required(),
            'key_themes' => $schema->array()->items($schema->string())->required(),
            'notable_excerpts' => $schema->array()->items($schema->string())->required(),
            'important_dates' => $schema->array()->items($schema->string())->required(),
            'relationship_type' => $schema->string()->enum(array_map(
                static fn (ReviewLegislationRelationshipType $type): string => $type->value,
                ReviewLegislationRelationshipType::cases(),
            ))->required(),
            'warnings' => $schema->array()->items($schema->string())->required(),
        ];
    }
}
