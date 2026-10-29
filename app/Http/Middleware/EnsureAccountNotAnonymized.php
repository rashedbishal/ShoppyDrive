<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Closes the gap AuthenticateSession leaves open: that middleware only
 * rejects a request when a password hash it already stored in the session
 * disagrees with the current one, so a session making its first-ever
 * request after an account is anonymized would otherwise just silently
 * adopt the new state instead of being rejected. This checks the
 * anonymized_at flag directly against a freshly loaded user on every
 * request, with no such blind spot.
 */
class EnsureAccountNotAnonymized
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user(); // default ('web') guard

        if ($user instanceof User && $user->anonymized_at !== null) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'This account is no longer active.']);
        }

        return $next($request);
    }
}
