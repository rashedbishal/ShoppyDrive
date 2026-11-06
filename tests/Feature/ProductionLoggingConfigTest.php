<?php

declare(strict_types=1);

/*
 * OPS-007: production must use one log file per day, kept 30 days, at info
 * level, even if the server's .env still holds the framework defaults. The
 * config is read at boot, so this starts a real artisan process.
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

test('production logs to daily files for 30 days at info level, whatever .env says', function () {
    expect(loggingConfigFor(['APP_ENV' => 'production'], 'logging.default'))->toContain('daily');

    $daily = loggingConfigFor(['APP_ENV' => 'production'], 'logging.channels.daily');

    expect($daily)->toContain('info')->toContain('30')->not->toContain('debug');
});

test('other environments keep their own logging settings', function () {
    expect(loggingConfigFor(['APP_ENV' => 'local'], 'logging.default'))->toContain('stack');
});
