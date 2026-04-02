<?php

test('horizon defines a dedicated assistant sources supervisor', function () {
    $defaults = config('horizon.defaults');
    $production = config('horizon.environments.production');
    $local = config('horizon.environments.local');
    $reviewSourceQueue = config('pls_assistant.review_source_enrichment.queue');

    expect($defaults)->toHaveKey('assistant-sources')
        ->and($defaults['assistant-sources'])->toMatchArray([
            'connection' => 'redis',
            'queue' => ['assistant-sources'],
            'balance' => false,
            'minProcesses' => 1,
            'maxProcesses' => 1,
            'memory' => 256,
            'tries' => 1,
            'timeout' => 240,
        ])
        ->and($production)->toHaveKey('assistant-sources')
        ->and($production['assistant-sources'])->toMatchArray([
            'maxProcesses' => 2,
        ])
        ->and($defaults)->toHaveKey('review-source-enrichment')
        ->and($defaults['review-source-enrichment'])->toMatchArray([
            'connection' => 'redis',
            'queue' => [$reviewSourceQueue],
            'balance' => false,
            'minProcesses' => 1,
            'maxProcesses' => 1,
            'memory' => 256,
            'tries' => 1,
            'timeout' => 240,
        ])
        ->and($production)->toHaveKey('review-source-enrichment')
        ->and($production['review-source-enrichment'])->toMatchArray([
            'maxProcesses' => 3,
        ])
        ->and($local)->toHaveKey('assistant-sources')
        ->and($local['assistant-sources'])->toMatchArray([
            'maxProcesses' => 1,
        ])
        ->and($local)->toHaveKey('review-source-enrichment')
        ->and($local['review-source-enrichment'])->toMatchArray([
            'maxProcesses' => 2,
        ])
        ->and(config('horizon.waits.redis:assistant-sources'))->toBe(120)
        ->and(config('horizon.waits.redis:'.$reviewSourceQueue))->toBe(120);
});

test('redis retry_after remains longer than the horizon assistant queue timeouts', function () {
    $retryAfter = config('queue.connections.redis.retry_after');
    $assistantSourcesTimeout = config('horizon.defaults.assistant-sources.timeout');
    $reviewSourceEnrichmentTimeout = config('horizon.defaults.review-source-enrichment.timeout');

    expect($retryAfter)->toBe(300)
        ->and($assistantSourcesTimeout)->toBe(240)
        ->and($reviewSourceEnrichmentTimeout)->toBe(240)
        ->and($retryAfter)->toBeGreaterThan($assistantSourcesTimeout)
        ->and($retryAfter)->toBeGreaterThan($reviewSourceEnrichmentTimeout);
});
