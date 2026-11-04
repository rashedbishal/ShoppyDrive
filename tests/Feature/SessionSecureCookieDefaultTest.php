<?php

declare(strict_types=1);

/*
 * MED-18: config/session.php's 'secure' default is a plain expression
 * evaluated once at config-file load time, not re-read per request (unlike
 * RedirectToHttps's config('app.env') check in its own handle() method) —
 * so this re-requires the file fresh after flipping config('app.env'),
 * rather than asserting on the already-booted, frozen config('session.secure')
 * value, which would never change mid-test no matter what config() is set to.
 */

afterEach(function () {
    config(['app.env' => 'testing']);
});

test('production defaults session cookies to secure with no SESSION_SECURE_COOKIE set', function () {
    expect(getenv('SESSION_SECURE_COOKIE'))->toBeFalsy();

    config(['app.env' => 'production']);
    $fresh = require config_path('session.php');

    expect($fresh['secure'])->toBeTrue();
});

test('local and testing leave session cookies insecure by default, so plain HTTP keeps working', function (string $env) {
    config(['app.env' => $env]);
    $fresh = require config_path('session.php');

    expect($fresh['secure'])->toBeFalsy();
})->with(['local' => ['local'], 'testing' => ['testing']]);

test('an explicit SESSION_SECURE_COOKIE override still wins over the production default', function () {
    putenv('SESSION_SECURE_COOKIE=false');

    try {
        config(['app.env' => 'production']);
        $fresh = require config_path('session.php');

        expect($fresh['secure'])->toBeFalsy();
    } finally {
        putenv('SESSION_SECURE_COOKIE');
    }
});
