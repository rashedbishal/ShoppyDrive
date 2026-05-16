<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectAdminToDashboard
{
    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->path();

        if (Auth::guard('admin')->check() && ! str_starts_with($path, 'admin') && ! str_starts_with($path, 'lp')) {
            return redirect()->route('admin.dashboard');
        }

        return $next($request);
    }
}
