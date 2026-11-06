<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * FE-009: pages with nothing for a search engine to show. This is a header
 * and not a robots.txt rule because a crawler blocked by robots.txt never
 * sees the instruction and can still list the bare URL.
 */
class HidePrivatePagesFromSearchEngines
{
    /** @var list<string> */
    private const array PRIVATE_PATHS = [
        'admin', 'admin/*',
        'account', 'account/*',
        'settings', 'settings/*',
        'cart', 'cart/*',
        'checkout', 'checkout/*',
        'wishlist',
        'search',
        'login', 'register', 'forgot-password', 'reset-password', 'reset-password/*',
        'email/*', 'user/*', 'auth/*',
        'order/*', 'payment/*',
        'lp/confirmation/*', 'lp/invoice/*', 'lp/*/payment/*',
        'newsletter/*',
    ];

    public static function isPrivate(Request $request): bool
    {
        return $request->is(...self::PRIVATE_PATHS);
    }

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (self::isPrivate($request)) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }
}
