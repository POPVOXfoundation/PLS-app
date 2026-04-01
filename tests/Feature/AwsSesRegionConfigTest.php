<?php

test('ses region can be overridden independently from the default aws region', function () {
    setTestEnv('AWS_DEFAULT_REGION', 'us-east-2');
    setTestEnv('AWS_SES_REGION', 'us-east-1');

    $services = require base_path('config/services.php');

    expect($services['ses']['region'])->toBe('us-east-1');
});

test('ses region falls back to the default aws region when no override is present', function () {
    setTestEnv('AWS_DEFAULT_REGION', 'us-east-2');
    setTestEnv('AWS_SES_REGION', null);

    $services = require base_path('config/services.php');

    expect($services['ses']['region'])->toBe('us-east-2');
});

function setTestEnv(string $key, ?string $value): void
{
    if ($value === null) {
        putenv($key);
        unset($_ENV[$key], $_SERVER[$key]);

        return;
    }

    putenv("{$key}={$value}");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}
