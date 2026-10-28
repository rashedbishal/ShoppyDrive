<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\BlockedIp;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Registered globally on the `web` middleware group (bootstrap/app.php), so
 * a blocked IP is declined on every storefront, auth, account and checkout
 * page/route — not just order placement. Two deliberate exceptions:
 *
 *  - `/admin*` is skipped so the CMS that manages the block list can never
 *    lock itself out (an admin's own network IP could otherwise collide
 *    with a blocked customer IP).
 *  - Payment/courier webhooks (ZiniPay, Steadfast, SMS forwarder) live under
 *    the separate `api` routing group, which this middleware is never
 *    attached to — they're server-to-server and don't carry the customer's
 *    own IP anyway.
 */
class BlockBlockedIp
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('admin') || $request->is('admin/*')) {
            return $next($request);
        }

        if (BlockedIp::isBlocked($request->ip())) {
            abort(403, 'Your IP address has been restricted from accessing this site. Please contact support if you believe this is a mistake.');
        }

        return $next($request);
    }
}
