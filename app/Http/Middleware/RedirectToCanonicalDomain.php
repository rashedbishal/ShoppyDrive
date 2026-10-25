<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * shoppydrive.com is the canonical domain — www.shoppydrive.com must never
 * serve content directly. Without this, a visitor who lands on www gets a
 * different origin than the server's configured APP_URL for any absolute
 * link, redirect, or cookie scope, which splits sessions across the two
 * hosts and (for anything built from an absolute, APP_URL-rooted URL)
 * triggers CORS failures the browser refuses to route around on its own.
 */
class RedirectToCanonicalDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();

        if (str_starts_with($host, 'www.')) {
            $canonicalUrl = $request->getScheme().'://'.substr($host, 4).$request->getRequestUri();

            // 308 (not 301/302) preserves the request method and body, so a
            // POST to www is still a POST after redirecting, not silently
            // downgraded to GET.
            return redirect()->to($canonicalUrl, 308);
        }

        return $next($request);
    }
}
