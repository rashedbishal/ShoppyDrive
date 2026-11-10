<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SEC-011: an explicit, directly-testable backstop for the request's own
 * Host header, in production only.
 *
 * `trustHosts()` (bootstrap/app.php) was meant to cover this already, by
 * making Symfony's `Request::getHost()` throw for anything outside APP_URL's
 * host/subdomains — confirmed live this was NOT actually rejecting a forged
 * Host over plain HTTP (`curl -H "Host: evil.example" http://shoppydrive.com/forgot-password`
 * returned 200, not a rejection). Hostinger's TLS layer happening to refuse
 * an unrecognized Host/SNI combination over HTTPS is infrastructure, not
 * this app, and plain HTTP has no such layer at all. Rather than keep
 * chasing why Symfony's own static trusted-host state wasn't taking effect,
 * this checks the same thing directly and deterministically, so there's one
 * plain `abort_if()` to audit instead of framework internals, and it's
 * directly testable by forcing `app()->environment('production')` — which
 * `trustHosts()` itself can never be, since Laravel unconditionally skips it
 * whenever `$app->runningUnitTests()` is true, regardless of the configured
 * environment name.
 *
 * Scoped to `production` only (same reasoning as RedirectToHttps, right
 * next to this in the middleware stack): local dev has no real domain to
 * match, and this must stay inert in tests.
 */
class RejectUntrustedHost
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->environment('production')) {
            return $next($request);
        }

        $trustedHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        // Never guess at a host to allow — if APP_URL can't be parsed, let
        // the request through unchanged rather than locking everyone out,
        // same failure mode RedirectToHttps already accepts for the same
        // reason.
        if ($trustedHost === null) {
            return $next($request);
        }

        // The raw header, not $request->getHost() — that method's own
        // validation is exactly the mechanism this exists because it
        // wasn't reliably taking effect; reading it directly here means
        // this check never depends on Symfony's trusted-host static state
        // at all. A missing Host header (HTTP/1.0, vanishingly rare) is
        // treated as untrusted rather than guessed at.
        $host = strtolower((string) ($request->headers->get('host') ?? ''));
        $host = explode(':', $host, 2)[0];
        $pattern = '/^(.+\.)?'.preg_quote(strtolower($trustedHost), '/').'$/';

        abort_unless((bool) preg_match($pattern, $host), 400, 'Invalid host header.');

        return $next($request);
    }
}
