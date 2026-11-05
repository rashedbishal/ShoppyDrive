<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * SEC-021's history encryption relies on the browser's Web Crypto API
 * (`window.crypto.subtle`), which only exists in a secure context — HTTPS,
 * or the `localhost`/`127.0.0.1` exception browsers make for it. A plain
 * HTTP visit over a LAN IP (e.g. local dev reached from another device)
 * is neither, so `window.crypto.subtle` is `undefined` there. Telling the
 * client to encrypt anyway doesn't fail quietly: Inertia's own
 * `encryptHistory()` throws ("Unable to encrypt history") once it
 * discovers it has no key, as an uncaught promise rejection that stops
 * the page visit from ever finishing — not a cosmetic console warning,
 * the page gets stuck "loading" and never renders. Only telling the
 * client to encrypt when the connection is actually secure avoids ever
 * promising something the browser can't do; production is always HTTPS
 * (RedirectToHttps), so this changes nothing there.
 */
class ConditionallyEncryptHistory
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->secure()) {
            Inertia::encryptHistory();
        }

        return $next($request);
    }
}
