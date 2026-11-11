<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\AdminListUrl;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records which page/filters of each admin list the admin is looking at, so
 * saving or deleting a record returns them to that same page instead of
 * page 1. See AdminListUrl.
 */
class RememberAdminListUrl
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $routeName = $request->route()?->getName();

        if (
            $request->isMethod('GET')
            && $request->hasSession()
            && $response->isSuccessful()
            && $request->header('Purpose') !== 'prefetch'
            && AdminListUrl::isListRoute($routeName)
        ) {
            AdminListUrl::remember($request, $routeName);
        }

        return $response;
    }
}
