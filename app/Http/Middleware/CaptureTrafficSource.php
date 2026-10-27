<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Classifies where a visit came from (Google, Facebook/Instagram, a referral
 * site, or direct) and remembers it in a cookie so Order::booted() can stamp
 * it onto whichever order the visitor eventually places — same first-party,
 * cookie-based pattern as TrackDailyVisit's sd_last_seen.
 *
 * An explicit ad click (?gclid=…, ?fbclid=…, ?utm_source=…) always overwrites
 * the stored source, since that's a deliberate new campaign touch worth
 * crediting over whatever brought the visitor here last time. A bare
 * referrer (no click params) only fills in the cookie if nothing is stored
 * yet — first-touch wins for ordinary organic/referral traffic so browsing
 * the site internally afterwards doesn't overwrite it with "direct".
 */
class CaptureTrafficSource
{
    private const SOURCE_COOKIE = 'sd_traffic_source';

    private const REFERRER_COOKIE = 'sd_traffic_referrer';

    private const TTL_MINUTES = 60 * 24 * 30;

    private const EXCLUDED_PATH_PREFIXES = ['admin', 'api', 'storage', '_boost', 'up', '.well-known'];

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldCapture($request)) {
            $this->capture($request);
        }

        return $next($request);
    }

    private function shouldCapture(Request $request): bool
    {
        if (! $request->isMethod('GET')) {
            return false;
        }

        foreach (self::EXCLUDED_PATH_PREFIXES as $prefix) {
            if ($request->is($prefix) || $request->is("{$prefix}/*")) {
                return false;
            }
        }

        return true;
    }

    private function capture(Request $request): void
    {
        [$source, $referrer, $forceOverwrite] = $this->classify($request);

        if ($source === null) {
            return;
        }

        if ($forceOverwrite || $request->cookie(self::SOURCE_COOKIE) === null) {
            Cookie::queue(self::SOURCE_COOKIE, $source, self::TTL_MINUTES);
            Cookie::queue(self::REFERRER_COOKIE, $referrer ?? '', self::TTL_MINUTES);
        }
    }

    /**
     * @return array{0: ?string, 1: ?string, 2: bool} [source, referrer host, forceOverwrite]
     */
    private function classify(Request $request): array
    {
        if ($request->filled('gclid')) {
            return ['google', null, true];
        }

        if ($request->filled('fbclid')) {
            return ['facebook', null, true];
        }

        $utmSource = strtolower(trim((string) $request->query('utm_source', '')));

        if ($utmSource !== '') {
            return [match (true) {
                str_contains($utmSource, 'google') => 'google',
                str_contains($utmSource, 'facebook'), $utmSource === 'fb' => 'facebook',
                str_contains($utmSource, 'instagram'), $utmSource === 'ig' => 'instagram',
                default => $utmSource,
            }, null, true];
        }

        $referer = (string) $request->headers->get('referer', '');

        if ($referer === '') {
            return ['direct', null, false];
        }

        $host = strtolower((string) parse_url($referer, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host) ?? $host;
        $ownHost = strtolower((string) parse_url(config('app.url'), PHP_URL_HOST));

        if ($host === '' || $host === $ownHost) {
            // Internal navigation or an unparsable referrer tells us nothing new.
            return [null, null, false];
        }

        return [match (true) {
            str_contains($host, 'google.') => 'google',
            str_contains($host, 'facebook.'), str_contains($host, 'fb.com') => 'facebook',
            str_contains($host, 'instagram.') => 'instagram',
            default => 'other',
        }, $host, false];
    }
}
