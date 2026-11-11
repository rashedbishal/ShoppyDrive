<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Psr\Log\AbstractLogger;
use Stringable;

/**
 * Where the push library's own warnings go. Without a logger it raises PHP
 * notices, which Laravel turns into exceptions: on a host with neither the
 * GMP nor the BCMath extension (common on shared hosting) the library only
 * wants to say "install one to be faster", and every notification then failed
 * with that message. Passing a logger makes it carry on, as it is designed to.
 * That one advisory is dropped; any real warning (missing curl, openssl...)
 * is written to the normal log.
 */
class WebPushLogger extends AbstractLogger
{
    private const ADVISORY = 'highly recommended to install the GMP or BCMath';

    /**
     * @param  mixed  $level
     * @param  array<string, mixed>  $context
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        if (str_contains((string) $message, self::ADVISORY)) {
            return;
        }

        Log::log((string) $level, '[WebPush] '.$message, $context);
    }
}
