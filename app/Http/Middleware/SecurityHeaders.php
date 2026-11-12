<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
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
 * proven, and no includeSubDomains), and an enforced Content Security Policy.
 * Only scripts carrying this request's nonce run, and 'strict-dynamic' lets
 * those load others, which is how Tag Manager adds vendors without a deploy.
 * Styles keep 'unsafe-inline': they can't run code, and rich-text content and
 * UI libraries depend on inline styles.
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

    /**
     * 'self' and https: only matter to old browsers without 'strict-dynamic';
     * current ones ignore them. Network, image and frame sources stay open to
     * https: because vendors added through Tag Manager choose their own hosts.
     */
    private static function contentSecurityPolicy(string $nonce): string
    {
        return "default-src 'self'; "
            ."script-src 'nonce-{$nonce}' 'strict-dynamic' 'self' https:; "
            ."style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
            ."font-src 'self' data: https://fonts.gstatic.com; "
            ."img-src 'self' data: blob: https:; "
            ."media-src 'self' blob: https:; "
            ."connect-src 'self' https:; "
            ."frame-src 'self' https:; "
            ."object-src 'none'; "
            ."frame-ancestors 'self'; "
            ."base-uri 'self'; "
            // The Meta Pixel sends larger events (e.g. Purchase) by submitting
            // a hidden form to facebook.com/tr; only that host is added.
            ."form-action 'self' https://www.facebook.com; "
            .'report-uri /api/csp-report';
    }

    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        $response->headers->add(self::HEADERS);

        if (app()->isProduction()) {
            $response->headers->set('Content-Security-Policy', self::contentSecurityPolicy($nonce));

            if ($request->isSecure()) {
                $response->headers->set('Strict-Transport-Security', self::HSTS);
            }
        }

        return $response;
    }
}
