<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Laravel\Fortify\Fortify;

/**
 * SEC-021: must build this response (which calls Inertia::clearHistory())
 * after AuthenticatedSessionController::destroy() has already invalidated
 * the session — session()->invalidate() flushes all session attributes, so
 * calling clearHistory() any earlier (e.g. from a Logout event listener,
 * which fires before that invalidate() call) would just get wiped before
 * the next request ever reads it.
 */
class LogoutResponse implements LogoutResponseContract
{
    public function toResponse($request)
    {
        Inertia::clearHistory();

        return $request->wantsJson()
            ? new JsonResponse('', 204)
            : redirect(Fortify::redirects('logout', '/'));
    }
}
