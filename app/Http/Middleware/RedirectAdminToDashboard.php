<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\ThemeResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectAdminToDashboard
{
    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->path();
        $isThemePreview = in_array($request->query('preview_theme'), ThemeResolver::THEMES, true);

        if (Auth::guard('admin')->check() && ! str_starts_with($path, 'admin') && ! str_starts_with($path, 'lp') && ! $isThemePreview) {
            return redirect()->route('admin.dashboard');
        }

        return $next($request);
    }
}
