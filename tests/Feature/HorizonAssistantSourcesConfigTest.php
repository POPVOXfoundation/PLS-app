<?php

test('horizon defines a dedicated assistant sources supervisor', function () {
    $defaults = config('horizon.defaults');
    $production = config('horizon.environments.production');
    $local = config('horizon.environments.local');

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
        ->and($local)->toHaveKey('assistant-sources')
        ->and($local['assistant-sources'])->toMatchArray([
            'maxProcesses' => 1,
        ])
        ->and(config('horizon.waits.redis:assistant-sources'))->toBe(120);
});

test('redis retry_after remains longer than the horizon assistant sources timeout', function () {
    $retryAfter = config('queue.connections.redis.retry_after');
    $timeout = config('horizon.defaults.assistant-sources.timeout');

    expect($retryAfter)->toBe(300)
        ->and($timeout)->toBe(240)
        ->and($retryAfter)->toBeGreaterThan($timeout);
});
