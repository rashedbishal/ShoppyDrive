<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * MED-16: baseline headers on everything PHP answers. Registered as the
 * outermost global middleware, so redirects, API responses, downloads and
 * rendered error pages all carry them. Nothing in the app embeds itself across
 * origins: payment and social login redirect rather than frame, so SAMEORIGIN
 * blocks only clickjacking.
 *
 * Uploads and build assets are served by the web server without reaching PHP,
 * so these don't apply to them. HSTS and a Content-Security-Policy are
 * deliberately absent; see the remediation tracker.
 */
class SecurityHeaders
{
    private const HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'SAMEORIGIN',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->add(self::HEADERS);

        return $response;
    }
}
