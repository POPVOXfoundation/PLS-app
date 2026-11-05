<?php

use App\Ai\Agents\LegislationSourceExtractorAgent;
use App\Ai\Agents\LegislationSourceFallbackExtractorAgent;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

test('legislation extractor request schema is openai strict mode compliant', function () {
    $format = capturedResponsesTextFormat(LegislationSourceExtractorAgent::class);

    expect($format)->toBeArray()
        ->and($format['type'] ?? null)->toBe('json_schema')
        ->and($format['name'] ?? null)->toBe('schema_definition')
        ->and($format['strict'] ?? null)->toBeTrue()
        ->and($format['schema'] ?? null)->toBeArray();

    $violations = strictModeSchemaViolations($format['schema']);

    expect($violations)->toBe([], "Strict mode violations:\n".implode("\n", $violations));
});

test('legislation extractor request schema preserves strict schema structure while adding distinct source identities', function () {
    $baseline = json_decode(primaryExtractorTextFormatBaseline(), true, flags: JSON_THROW_ON_ERROR);
    $baseline['schema']['properties']['legislation_type']['enum'] = ['bill', 'parliamentary_debate', 'amendment', 'unclassified', 'act', 'law', 'statute', 'regulation', 'ordinance'];
    $format = capturedResponsesTextFormat(LegislationSourceExtractorAgent::class);

    expect(array_keys($format))->toBe(array_keys($baseline))
        ->and($format['schema']['additionalProperties'] ?? null)->toBeFalse()
        ->and(withoutNestedAdditionalProperties($format['schema']))->toBe(withoutNestedAdditionalProperties($baseline['schema']))
        ->and(array_diff_key($format, ['schema' => true]))->toBe(array_diff_key($baseline, ['schema' => true]));
});

test('legislation fallback extractor preserves schema structure while adding distinct source identities', function () {
    $baseline = json_decode(fallbackExtractorTextFormatBaseline(), true, flags: JSON_THROW_ON_ERROR);
    $baseline['schema']['properties']['legislation_type']['enum'] = ['bill', 'parliamentary_debate', 'amendment', 'unclassified', 'act', 'law', 'statute', 'regulation', 'ordinance'];
    $format = capturedResponsesTextFormat(LegislationSourceFallbackExtractorAgent::class);

    expect($format)->toBe($baseline)
        ->and(strictModeSchemaViolations($format['schema']))->toBe([]);
});

/**
 * Prompt the agent against a faked OpenAI gateway and return the `text.format` sent to `responses`.
 *
 * @param  class-string  $agentClass
 * @return array<string, mixed>
 */
function capturedResponsesTextFormat(string $agentClass): array
{
    Http::preventStrayRequests();
    config(['ai.providers.openai.key' => 'test-key']);

    Http::fake([
        'api.openai.com/*' => Http::response(minimalResponsesApiBody()),
    ]);

    try {
        app($agentClass)->prompt('Synthetic Act text');
    } catch (Throwable) {
        // The fake body is not a full extraction record; only the outgoing request matters here.
    }

    $recorded = Http::recorded(
        fn (Request $request): bool => str_ends_with($request->url(), '/responses'),
    );

    expect($recorded)->toHaveCount(1);

    return $recorded->first()[0]->data()['text']['format'] ?? [];
}

/**
 * Remove `additionalProperties` from every object node below the root, keeping key order.
 *
 * @param  array<string, mixed>  $node
 * @return array<string, mixed>
 */
function withoutNestedAdditionalProperties(array $node, bool $isRoot = true): array
{
    if (! $isRoot) {
        unset($node['additionalProperties']);
    }

    if (isset($node['properties']) && is_array($node['properties'])) {
        foreach ($node['properties'] as $key => $child) {
            if (is_array($child)) {
                $node['properties'][$key] = withoutNestedAdditionalProperties($child, false);
            }
        }
    }

    if (isset($node['items']) && is_array($node['items'])) {
        $node['items'] = withoutNestedAdditionalProperties($node['items'], false);
    }

    return $node;
}

/**
 * The primary extractor `text.format` captured on the unfixed code (origin/dev cfd793e).
 */
function primaryExtractorTextFormatBaseline(): string
{
    return <<<'JSON'
{"type":"json_schema","name":"schema_definition","schema":{"additionalProperties":false,"properties":{"title":{"type":"string"},"short_title":{"type":["string","null"]},"legislation_type":{"enum":["act","law","statute","regulation","ordinance"],"type":"string"},"date_enacted":{"type":["string","null"]},"summary":{"type":["string","null"]},"key_themes":{"items":{"type":"string"},"type":"array"},"notable_excerpts":{"items":{"type":"string"},"type":"array"},"important_dates":{"items":{"type":"string"},"type":"array"},"scrutiny_preparation":{"properties":{"milestones":{"items":{"properties":{"title":{"type":"string"},"detail":{"type":"string"},"timing":{"type":["string","null"]},"source_text":{"type":"string"}},"type":"object","required":["title","detail","timing","source_text"]},"type":"array"},"implementation_obligations":{"items":{"properties":{"title":{"type":"string"},"detail":{"type":"string"},"timing":{"type":["string","null"]},"source_text":{"type":"string"}},"type":"object","required":["title","detail","timing","source_text"]},"type":"array"},"parliamentary_follow_up":{"items":{"properties":{"title":{"type":"string"},"detail":{"type":"string"},"timing":{"type":["string","null"]},"source_text":{"type":"string"}},"type":"object","required":["title","detail","timing","source_text"]},"type":"array"},"records_to_locate":{"items":{"properties":{"title":{"type":"string"},"detail":{"type":"string"},"timing":{"type":["string","null"]},"source_text":{"type":"string"}},"type":"object","required":["title","detail","timing","source_text"]},"type":"array"}},"type":"object","required":["milestones","implementation_obligations","parliamentary_follow_up","records_to_locate"]},"stakeholder_suggestions":{"items":{"type":"string"},"type":"array"},"relationship_type":{"enum":["primary","related","delegated"],"type":"string"},"warnings":{"items":{"type":"string"},"type":"array"}},"type":"object","required":["title","short_title","legislation_type","date_enacted","summary","key_themes","notable_excerpts","important_dates","scrutiny_preparation","stakeholder_suggestions","relationship_type","warnings"]},"strict":true}
JSON;
}

/**
 * The fallback extractor `text.format` captured before the fix. The fallback agent is not changed by the fix.
 */
function fallbackExtractorTextFormatBaseline(): string
{
    return <<<'JSON'
{"type":"json_schema","name":"schema_definition","schema":{"additionalProperties":false,"properties":{"title":{"type":"string"},"legislation_type":{"enum":["act","law","statute","regulation","ordinance"],"type":"string"},"relationship_type":{"enum":["primary","related","delegated"],"type":"string"},"date_enacted":{"type":["string","null"]},"summary":{"type":["string","null"]},"key_themes":{"items":{"type":"string"},"type":"array"},"important_dates":{"items":{"type":"string"},"type":"array"},"warnings":{"items":{"type":"string"},"type":"array"}},"type":"object","required":["title","legislation_type","relationship_type","date_enacted","summary","key_themes","important_dates","warnings"]},"strict":true}
JSON;
}

/**
 * @return array<string, mixed>
 */
function minimalResponsesApiBody(): array
{
    return [
        'id' => 'resp_test',
        'object' => 'response',
        'status' => 'completed',
        'model' => 'gpt-4.1-mini',
        'output' => [
            [
                'type' => 'message',
                'id' => 'msg_test',
                'role' => 'assistant',
                'status' => 'completed',
                'content' => [
                    ['type' => 'output_text', 'text' => '{}', 'annotations' => []],
                ],
            ],
        ],
        'usage' => ['input_tokens' => 1, 'output_tokens' => 1, 'total_tokens' => 2],
    ];
}

/**
 * Walk every schema node and return the strict mode rule violations, keyed by JSON path.
 *
 * @param  array<string, mixed>  $node
 * @return list<string>
 */
function strictModeSchemaViolations(array $node, string $path = '$'): array
{
    $violations = [];
    $types = (array) ($node['type'] ?? []);

    if (in_array('object', $types, true)) {
        if (($node['additionalProperties'] ?? null) !== false) {
            $violations[] = "{$path}: additionalProperties must be false, got ".json_encode($node['additionalProperties'] ?? '<missing>');
        }

        $propertyKeys = array_keys($node['properties'] ?? []);
        $required = $node['required'] ?? [];
        sort($propertyKeys);
        sort($required);

        if ($required !== $propertyKeys) {
            $violations[] = "{$path}: required must equal property keys, got ".json_encode($node['required'] ?? '<missing>');
        }
    }

    if (in_array('array', $types, true) && ! isset($node['items'])) {
        $violations[] = "{$path}: array node must define items";
    }

    foreach ($node['properties'] ?? [] as $key => $child) {
        if (is_array($child)) {
            $violations = [...$violations, ...strictModeSchemaViolations($child, "{$path}.properties.{$key}")];
        }
    }

    if (isset($node['items']) && is_array($node['items'])) {
        $violations = [...$violations, ...strictModeSchemaViolations($node['items'], "{$path}.items")];
    }

    return $violations;
}
