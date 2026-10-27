<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\BlockedIp;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applied to the customer-initiated order-placement routes only (checkout
 * submission, mobile-payment submission, landing-page buy-now) — never to
 * the ZiniPay callback/webhook routes, since those are server-to-server and
 * don't carry the customer's own IP.
 */
class BlockBlockedIp
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (BlockedIp::isBlocked($request->ip())) {
            $message = 'Your IP address has been blocked from placing orders. Please contact support if you believe this is a mistake.';

            // Set both channels: checkout/mobile-payment pages read the
            // "error" flash message, landing-page order/payment forms read
            // the validation errors bag — this covers all four routes this
            // middleware guards without needing per-route branching.
            return back()->with('error', $message)->withErrors(['ip' => $message]);
        }

        return $next($request);
    }
}
