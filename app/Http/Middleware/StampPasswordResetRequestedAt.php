<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A tester could spam the "Send Reset Link" button with no client-side
 * feedback until the real server-side limiter (SecurityRateLimits'
 * password_reset_per_*) eventually refuses the request. Runs globally on
 * every Fortify route (config/fortify.php `middleware`), like
 * ThrottleFortifyActions, but stamps the session regardless of outcome
 * (even a throttled or validation-failed attempt still counts as "just
 * tried") so Fortify::requestPasswordResetLinkView() can share a cooldown
 * deadline the forgot-password page shows as a countdown — the same
 * pattern User::verificationCodeResendAvailableAt() already uses, just
 * session-backed instead of column-backed, since a forgot-password
 * request has no account row to persist against (SEC-015: including for
 * an email that doesn't exist).
 */
class StampPasswordResetRequestedAt
{
    public const string SESSION_KEY = 'password_reset_requested_at';

    public const int COOLDOWN_SECONDS = 90;

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->route()?->getName() === 'password.email') {
            $request->session()->put(self::SESSION_KEY, now());
        }

        return $next($request);
    }
}
