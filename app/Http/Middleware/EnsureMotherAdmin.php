<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mother-admin-only actions (DB-001: permanent order deletion, clearing the
 * audit trail). No section permission is enough — only `admins.is_mother`.
 */
class EnsureMotherAdmin
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('admin')->user()?->is_mother) {
            abort(403, 'Only the mother admin can do this.');
        }

        return $next($request);
    }
}
