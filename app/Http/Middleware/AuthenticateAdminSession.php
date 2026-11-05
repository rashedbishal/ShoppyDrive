<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * SEC-005: Laravel's built-in `auth.session` alias (`AuthenticateSession`)
 * only ever checks the application's *default* guard — it reads
 * `$this->auth->getDefaultDriver()`, hardcoded, with no way to target a
 * second guard — so adding it to the admin route group would silently do
 * nothing (this app's default guard is `web`). This mirrors the same
 * password-hash-fingerprint approach for `admin` specifically: a stolen
 * admin session (or one left open on a shared computer) stops working the
 * moment the account's password changes, on the very next request,
 * instead of staying valid for the rest of its lifetime.
 *
 * AdminController::update() keeps the *current* session's own fingerprint
 * in sync when an admin changes their own password, so that one request
 * doesn't also log itself out — every *other* session for that admin
 * still has the old fingerprint and gets caught here. Remember-me cookies
 * are handled separately, by rotating remember_token in the same place.
 */
class AuthenticateAdminSession
{
    public const SESSION_KEY = 'password_hash_admin';

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $admin = Auth::guard('admin')->user();

        if ($admin === null || ! $request->hasSession()) {
            return $next($request);
        }

        $stored = $request->session()->get(self::SESSION_KEY);

        // Keyed by admin id, not just the hash: a session that starts
        // tracking a *different* admin than it last saw (switching
        // accounts without logging out first — the real path Auth::login()
        // doesn't itself guard against, and the same thing actingAs() does
        // between two calls in a test) is a new identity to adopt, not a
        // stolen session to punish. Only "same admin id, different hash"
        // means this exact account's password changed since this session
        // last confirmed it.
        if (is_array($stored) && ($stored['id'] ?? null) === $admin->getAuthIdentifier()
            && ! hash_equals((string) $admin->getAuthPassword(), (string) ($stored['hash'] ?? ''))) {
            Auth::guard('admin')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')->with('error', 'Your password was changed, so you were signed out. Please log in again.');
        }

        $request->session()->put(self::SESSION_KEY, [
            'id' => $admin->getAuthIdentifier(),
            'hash' => $admin->getAuthPassword(),
        ]);

        return $next($request);
    }
}
