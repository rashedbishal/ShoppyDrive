<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Replaces the plain `auth`+`verified` gate on checkout/payment routes so a
 * guest can be let through when "Allow Guest Checkout" is turned on in
 * Admin > Settings, while a logged-in-but-unverified customer is still sent
 * to verify their email either way.
 */
class EnsureCheckoutAccess
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null) {
            if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) {
                return redirect()->route('verification.notice');
            }

            return $next($request);
        }

        if (! self::guestCheckoutEnabled()) {
            return redirect()->guest(route('login'));
        }

        return $next($request);
    }

    public static function guestCheckoutEnabled(): bool
    {
        return (bool) (Setting::find('guest_checkout_enabled')?->value ?? false);
    }
}
