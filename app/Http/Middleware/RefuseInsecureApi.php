<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SEC-013: RedirectToHttps only covers the web group, so a server-to-server
 * caller configured with http:// (the SMS forwarder, a courier webhook)
 * would keep working and send its token unencrypted. In production, plain
 * HTTP is refused before any controller reads the request. A refusal
 * rather than a redirect: the point is that the misconfiguration shows up.
 */
class RefuseInsecureApi
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->isProduction() && ! $request->isSecure()) {
            return response()->json(['error' => 'HTTPS is required.'], 426);
        }

        return $next($request);
    }
}
