<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * MED-18A. Production is directly reachable over plain HTTP with no redirect
 * of any kind — confirmed live: the session cookie set on that plain
 * connection carries no Secure flag, so a visitor who ever lands on
 * http://shoppydrive.com gets a genuinely insecure session, not just a
 * theoretical one.
 *
 * `$request->isSecure()` is what Laravel's own session-cookie Secure
 * auto-detection already relies on, and it was confirmed live to already
 * report the true scheme correctly in production (the Secure flag was
 * observed to differ correctly between a direct https:// and http:// request
 * to the same URL) — so this reads it as-is, with no `X-Forwarded-Proto`
 * trust and no trusted-proxy configuration added. An untrusted
 * X-Forwarded-Proto header sent by a client has no effect on this decision.
 *
 * Scoped to the `production` environment only (via config('app.env'), not
 * app()->environment() directly, so a test can flip it with a plain
 * config() call) — local development has no TLS certificate at all, and the
 * test suite never runs as `production`, so neither is ever affected.
 *
 * The redirect target's host is read only from the trusted, admin-configured
 * APP_URL — never from the request's own Host header — so a forged or
 * mismatched Host header can't turn this into an open redirect. If APP_URL
 * can't be parsed, this passes the request through unchanged rather than
 * guessing at a host.
 */
class RedirectToHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isSecure() || config('app.env') !== 'production') {
            return $next($request);
        }

        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        if ($host === null) {
            return $next($request);
        }

        // 308 (not 301/302) preserves the request method and body, matching
        // RedirectToCanonicalDomain's own reasoning — a POST over plain HTTP
        // is still a POST after redirecting, not silently downgraded to GET.
        return redirect()->to('https://'.$host.$request->getRequestUri(), 308);
    }
}
