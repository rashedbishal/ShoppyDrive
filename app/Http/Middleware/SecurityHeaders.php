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
 * so these don't apply to them.
 *
 * SEC-013, production only: HSTS for one day on the main domain (deliberately
 * short: browsers can't be told to forget it, so it can be lengthened once
 * proven, and no includeSubDomains), and a report-only Content Security
 * Policy, which blocks nothing and reports to /api/csp-report so an enforced
 * policy can later be written from what the site really loads.
 */
class SecurityHeaders
{
    private const HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'SAMEORIGIN',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        // SEC-013: the site uses none of these browser features.
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
    ];

    private const HSTS = 'max-age=86400';

    // The Custom Header/Footer fields (Admin › Marketing & Tracking) let an
    // admin paste arbitrary third-party code — GTM's container script today,
    // another vendor's tomorrow — so this list is inherently incomplete and
    // needs a manual addition whenever a newly pasted vendor's domain isn't
    // here yet. Harmless today since the policy below is report-only; this
    // becomes a real constraint only if it's ever switched to enforcing.
    private const CSP_REPORT_ONLY = "default-src 'self'; "
        // googletagmanager.com: GTM's own container script. connect.facebook.net:
        // Meta Pixel's base code, in case a pasted snippet loads it directly
        // or via a GTM tag.
        ."script-src 'self' 'unsafe-inline' https://www.googletagmanager.com https://connect.facebook.net; "
        ."style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
        ."font-src 'self' https://fonts.gstatic.com; "
        ."img-src 'self' data: https:; "
        ."media-src 'self' https:; "
        // Meta Pixel's tracked events are sent to facebook.com/tr.
        ."connect-src 'self' https://www.facebook.com; "
        ."frame-ancestors 'self'; "
        ."base-uri 'self'; "
        ."form-action 'self'; "
        .'report-uri /api/csp-report';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->add(self::HEADERS);

        if (app()->isProduction()) {
            $response->headers->set('Content-Security-Policy-Report-Only', self::CSP_REPORT_ONLY);

            if ($request->isSecure()) {
                $response->headers->set('Strict-Transport-Security', self::HSTS);
            }
        }

        return $response;
    }
}
