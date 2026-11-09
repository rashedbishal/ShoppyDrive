<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Sends a customer back to the page they clicked "Log in" from. Laravel's own
 * `url.intended` only exists when a protected page forced the login, so it
 * still wins here. The Referer header is untrusted input, so only the path of
 * a same-site address is ever stored, which cannot redirect off the site.
 */
class PostLoginRedirect
{
    private const string SESSION_KEY = 'login_return_path';

    /** @var list<string> Pages a customer passes through while logging in, or can't use. */
    private const array NEVER_RETURN_TO = [
        'login',
        'register',
        'forgot-password',
        'reset-password/*',
        'email/*',
        'user/*',
        'auth/*',
        'admin',
        'admin/*',
    ];

    /**
     * Call when the login page is shown. A visit from another auth page keeps
     * the page already remembered, so a failed attempt doesn't lose it.
     */
    public static function rememberPreviousPage(Request $request): void
    {
        $previousPage = self::sameSitePath($request);

        if ($previousPage === null) {
            $request->session()->forget(self::SESSION_KEY);

            return;
        }

        if (! Str::is(self::NEVER_RETURN_TO, ltrim((string) parse_url($previousPage, PHP_URL_PATH), '/'))) {
            $request->session()->put(self::SESSION_KEY, $previousPage);
        }
    }

    public static function redirect(string $default): RedirectResponse
    {
        return redirect()->intended(session()->pull(self::SESSION_KEY, $default));
    }

    /**
     * The path and query of the Referer, or null when it is missing, belongs
     * to another site, or is shaped to be read as another site (`//host`).
     */
    private static function sameSitePath(Request $request): ?string
    {
        $referer = parse_url((string) $request->headers->get('referer'));

        if (! is_array($referer) || ($referer['host'] ?? null) !== $request->getHost()) {
            return null;
        }

        $path = $referer['path'] ?? '/';

        if (str_starts_with($path, '//') || str_contains($path, '\\')) {
            return null;
        }

        return isset($referer['query']) ? $path.'?'.$referer['query'] : $path;
    }
}
