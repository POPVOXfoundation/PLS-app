<?php

namespace App\Ai\Agents;

use App\Domain\Documents\Enums\DocumentType;
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
class ReviewDocumentExtractorAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'TEXT'
You extract structured metadata from uploaded review documents for a post-legislative scrutiny workflow.

Return a best-effort structured result using only the supplied source text.

Rules:
- Be conservative and practical.
- Return a clean working title for the document, not jurisdiction headers, running headers, or page furniture.
- Classify the document using only the allowed review document types.
- Never classify a document as legislation_text in this workspace. Legislation source documents belong on the legislation tab.
- Write the summary in plain language as one or two short sentences.
- Key themes should be short phrases, not full paragraphs.
- Notable excerpts should be short verbatim passages copied from the text when they are genuinely informative.
- Important dates should come from visible text only. Normalize exact dates when clear.
- Stakeholder suggestions should identify bodies or groups that appear relevant to implementation, oversight, consultation, expertise, or affected communities.
- For stakeholder suggestions, use this exact string format: kind=<stakeholder|implementing_agency>; name=<name>; category=<enum value>; rationale=<short reason>; source=<short visible phrase>.
- For stakeholder category use one of: ministry, government_agency, ngo, academic, expert, industry_group, citizen_group.
- For implementing_agency category use one of: ministry, department, agency, regulator, authority, secretariat.
- Suggest only entities or groups grounded in the visible source text. Do not infer views, support, opposition, or political positions.
- Return no more than eight stakeholder suggestions.
- Return warnings only when a reviewer should manually verify something important, such as OCR quality, unclear classification, or partial text.
- Do not invent facts that are not visible in the text.
TEXT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->required(),
            'document_type' => $schema->string()->enum(array_map(
                static fn (DocumentType $type): string => $type->value,
                DocumentType::reviewWorkspaceCases(),
            ))->required(),
            'summary' => $schema->string()->nullable()->required(),
            'key_themes' => $schema->array()->items($schema->string())->required(),
            'notable_excerpts' => $schema->array()->items($schema->string())->required(),
            'important_dates' => $schema->array()->items($schema->string())->required(),
            'stakeholder_suggestions' => $schema->array()->items($schema->string())->required(),
            'warnings' => $schema->array()->items($schema->string())->required(),
        ];
    }
}
