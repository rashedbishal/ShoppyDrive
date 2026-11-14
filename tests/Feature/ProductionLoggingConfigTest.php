<?php

declare(strict_types=1);

/*
 * OPS-007: production's logging is fixed in config, at info level, even if
 * the server's .env still holds the framework defaults. DISC-44 (owner
 * decision 2026-11-14) replaced its daily files with the one laravel.log,
 * kept in check by `logs:trim` (TrimLogCommandTest). The config is read at
 * boot, so this starts a real artisan process.
 */

use Illuminate\Support\Facades\Process;

function loggingConfigFor(array $env, string $key): string
{
    return Process::path(base_path())
        ->env([...$env, 'LOG_STACK' => 'single', 'LOG_LEVEL' => 'debug', 'LOG_CHANNEL' => 'stack'])
        ->run(['php', 'artisan', 'config:show', $key])
        ->throw()
        ->output();
}

test('DISC-44: production logs to the one laravel.log at info level, whatever .env says', function () {
    expect(loggingConfigFor(['APP_ENV' => 'production'], 'logging.default'))->toContain('single');

    $single = loggingConfigFor(['APP_ENV' => 'production'], 'logging.channels.single');

    expect($single)->toContain('info')->toContain('laravel.log')->not->toContain('debug');
});

test('other environments keep their own logging settings', function () {
    expect(loggingConfigFor(['APP_ENV' => 'local'], 'logging.default'))->toContain('stack');
});
