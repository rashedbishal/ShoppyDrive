<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class RedirectAdminToDashboard
{
    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->path();

        if (Auth::guard('admin')->check() && ! str_starts_with($path, 'admin') && ! str_starts_with($path, 'lp')) {
            // A full page load, so an open storefront tab swaps its route list
            // for the admin one (SEC-016) instead of staying on the short list.
            return Inertia::location(route('admin.dashboard'));
        }

        return $next($request);
    }
}
