<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Registered as the `admin.can:<section>,<ability>` route middleware alias
 * (bootstrap/app.php) — gates every section-specific admin route per the
 * catalog in App\Support\AdminSections. The mother admin always passes
 * (Admin::canAccess()); everyone else needs that section+ability explicitly
 * granted via Admin > Admins.
 */
class EnsureAdminPermission
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $section, string $ability): Response
    {
        $admin = Auth::guard('admin')->user();

        if (! $admin?->canAccess($section, $ability)) {
            abort(403, "You don't have permission to {$ability} this section.");
        }

        return $next($request);
    }
}
