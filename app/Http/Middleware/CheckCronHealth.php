<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\CronHealth;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * OPS-002: lets an ordinary page view notice that cron has stopped, because a
 * stopped cron can't report itself. The work happens in terminate(), after
 * the response has already gone to the visitor, so a slow mail server can
 * never delay a page; CronHealth::check() is throttled to once a minute and
 * never throws.
 */
class CheckCronHealth
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        CronHealth::check();
    }
}
