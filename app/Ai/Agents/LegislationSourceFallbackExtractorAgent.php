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
#[Timeout(60)]
class LegislationSourceFallbackExtractorAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'TEXT'
You extract a small, reliable legislation record from source text for a post-legislative scrutiny workflow.

Return only the structured result, using visible source text. Keep it concise.

Rules:
- Every field is required. If unsure, use the closest allowed classification and add a concise technical warning.
- Return the official instrument or bill name, without page numbers, jurisdiction headers, or running headers.
- Bills and draft bills are primary legislation. Regulations, rules, orders, and ordinances are delegated legislation when they are the source instrument.
- Use null for an enactment date or summary when the visible text does not support one.
- Key themes are short phrases. Important dates must be visible in the source text.
- Do not infer policy effects, performance, or implementation outcomes.
TEXT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->required(),
            'legislation_type' => $schema->string()->enum(array_map(
                static fn (LegislationType $type): string => $type->value,
                LegislationType::cases(),
            ))->required(),
            'relationship_type' => $schema->string()->enum(array_map(
                static fn (ReviewLegislationRelationshipType $type): string => $type->value,
                ReviewLegislationRelationshipType::cases(),
            ))->required(),
            'date_enacted' => $schema->string()->nullable()->required(),
            'summary' => $schema->string()->nullable()->required(),
            'key_themes' => $schema->array()->items($schema->string())->required(),
            'important_dates' => $schema->array()->items($schema->string())->required(),
            'warnings' => $schema->array()->items($schema->string())->required(),
        ];
    }
}
