<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\DailyVisit;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lightweight first-party visit counter for the admin dashboard — not a
 * replacement for the client-side, consent-gated GA tracking in
 * resources/js/lib/analytics.ts. Page views are counted on every qualifying
 * request; a visitor is counted once per calendar day via a same-origin
 * cookie storing the last date they were counted.
 */
class TrackDailyVisit
{
    private const COOKIE_NAME = 'sd_last_seen';

    private const EXCLUDED_PATH_PREFIXES = ['admin', 'api', 'storage', '_boost', 'up', '.well-known'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldTrack($request)) {
            $this->track($request);
        }

        return $response;
    }

    private function shouldTrack(Request $request): bool
    {
        if (! $request->isMethod('GET') || $this->isBot($request)) {
            return false;
        }

        foreach (self::EXCLUDED_PATH_PREFIXES as $prefix) {
            if ($request->is($prefix) || $request->is("{$prefix}/*")) {
                return false;
            }
        }

        return true;
    }

    private function isBot(Request $request): bool
    {
        $userAgent = (string) $request->userAgent();

        return $userAgent === '' || preg_match('/bot|crawl|spider|slurp|facebookexternalhit|bingpreview/i', $userAgent) === 1;
    }

    private function track(Request $request): void
    {
        $today = now('Asia/Dhaka')->toDateString();

        // `firstOrCreate(['date' => $today])` compares the raw string against
        // the stored value, which SQLite's DATE type affinity doesn't always
        // match consistently — `whereDate()` compares just the date portion
        // in a database-agnostic way instead. A create race between two
        // simultaneous first-of-day requests is handled by re-fetching on
        // the (rare) unique constraint failure rather than erroring.
        $visit = DailyVisit::whereDate('date', $today)->first();

        if (! $visit) {
            try {
                $visit = DailyVisit::create(['date' => $today]);
            } catch (QueryException) {
                $visit = DailyVisit::whereDate('date', $today)->first();
            }
        }

        if (! $visit) {
            return;
        }

        $visit->increment('page_view_count');

        if ($request->cookie(self::COOKIE_NAME) !== $today) {
            $visit->increment('visitor_count');
            Cookie::queue(self::COOKIE_NAME, $today, 60 * 24);
        }
    }
}
