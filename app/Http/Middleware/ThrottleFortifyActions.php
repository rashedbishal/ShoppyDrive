<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fortify registers its own routes, and only lets login/two-factor/
 * verification pick a named limiter (config/fortify.php `limiters`). This
 * runs on every Fortify route via `fortify.middleware` and applies the
 * Admin > Security limiter to the bot targets that have none: sign-up,
 * password-reset emails, confirming your password, and profile updates.
 * Every other Fortify route passes straight through. Keyed on route
 * names, so it survives route:cache.
 */
class ThrottleFortifyActions
{
    /** @var array<string, string> route name => named rate limiter */
    private const LIMITERS = [
        'register.store' => 'register',
        'password.email' => 'password-reset',
        'password.confirm.store' => 'password-check',
        'user-profile-information.update' => 'account-action',
    ];

    public function __construct(private readonly ThrottleRequests $throttle) {}

    public function handle(Request $request, Closure $next): Response
    {
        $limiter = self::LIMITERS[$request->route()?->getName()] ?? null;

        if ($limiter === null) {
            return $next($request);
        }

        return $this->throttle->handle($request, $next, $limiter);
    }
}
