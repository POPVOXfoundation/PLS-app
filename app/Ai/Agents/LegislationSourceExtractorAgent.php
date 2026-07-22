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
- Build a source-grounded scrutiny preparation list in four categories: milestones and deadlines, implementation obligations, parliamentary follow-up, and records to locate.
- Include only items that are explicit in, or directly required by, the visible source text. Do not infer implementation performance, policy effects, or missing evidence.
- For each scrutiny preparation item, provide a short title, a concise factual detail, and a short verbatim source phrase that lets a reviewer find the relevant passage. Use an empty list when the source does not support a category.
- For milestones and deadlines, include Royal Assent, commencement, phased implementation, statutory review, reporting, sunset, and consultation dates only when they are visible in the source. The timing field may be null when the obligation is clear but no date is stated.
- For records to locate, name only a record that the legislation itself calls for or directly implies, such as regulations, guidance, a report, a code, or a consultation response. Do not suggest general research or an assessment of effectiveness.
- Keep each scrutiny preparation category to no more than twelve items. Prioritize the clearest and most material provisions when there are many.
- Stakeholder suggestions should identify bodies or groups that appear relevant to implementation, oversight, consultation, or affected communities.
- For stakeholder suggestions, use this exact string format: kind=<stakeholder|implementing_agency>; name=<name>; category=<enum value>; rationale=<short reason>; source=<short visible phrase>.
- For stakeholder category use one of: ministry, government_agency, ngo, academic, expert, industry_group, citizen_group.
- For implementing_agency category use one of: ministry, department, agency, regulator, authority, secretariat.
- Suggest only entities or groups grounded in the visible source text. Do not infer views, support, opposition, or political positions.
- Return no more than eight stakeholder suggestions.
- Exclude formulaic legislative text such as "BE IT ENACTED", arrangement-of-clauses material, citation clauses, and procedural boilerplate from the summary.
- If the source is a bill or draft bill, treat it as primary legislation for relationship purposes.
- If the text clearly describes regulations, rules, an order, or an ordinance as the source instrument itself, classify it as delegated.
- If the date enacted is not clearly visible, return null.
- If a short title is not explicitly shown, you may derive a sensible short title from the main title.
- Write a short plain-language summary when the source text gives enough substance.
- Return warnings only for technical extraction issues, such as incomplete OCR, partial text, unclear title/date, unreadable pages, or ambiguous document classification.
- Do not return warnings that advise the user to assess implementation, effectiveness, impact, compliance, commencement status, or policy outcomes.
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
            'scrutiny_preparation' => $schema->object([
                'milestones' => $schema->array()->items($schema->object([
                    'title' => $schema->string()->required(),
                    'detail' => $schema->string()->required(),
                    'timing' => $schema->string()->nullable()->required(),
                    'source_text' => $schema->string()->required(),
                ]))->required(),
                'implementation_obligations' => $schema->array()->items($schema->object([
                    'title' => $schema->string()->required(),
                    'detail' => $schema->string()->required(),
                    'timing' => $schema->string()->nullable()->required(),
                    'source_text' => $schema->string()->required(),
                ]))->required(),
                'parliamentary_follow_up' => $schema->array()->items($schema->object([
                    'title' => $schema->string()->required(),
                    'detail' => $schema->string()->required(),
                    'timing' => $schema->string()->nullable()->required(),
                    'source_text' => $schema->string()->required(),
                ]))->required(),
                'records_to_locate' => $schema->array()->items($schema->object([
                    'title' => $schema->string()->required(),
                    'detail' => $schema->string()->required(),
                    'timing' => $schema->string()->nullable()->required(),
                    'source_text' => $schema->string()->required(),
                ]))->required(),
            ])->required(),
            'stakeholder_suggestions' => $schema->array()->items($schema->string())->required(),
            'relationship_type' => $schema->string()->enum(array_map(
                static fn (ReviewLegislationRelationshipType $type): string => $type->value,
                ReviewLegislationRelationshipType::cases(),
            ))->required(),
            'warnings' => $schema->array()->items($schema->string())->required(),
        ];
    }
}
