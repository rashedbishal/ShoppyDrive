<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\ThemeResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class HandleTheme
{
    public function __construct(private readonly ThemeResolver $resolver) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $result = $this->resolver->resolve($request);

        View::share('theme', $result['theme']);
        $request->attributes->set('resolved_theme', $result['theme']);

        $response = $next($request);

        if ($result['setCookie']) {
            $response->headers->setCookie(Cookie::make(
                'theme_variant',
                $result['theme'],
                60 * 24 * 365,
                httpOnly: false,
            ));
        }

        return $response;
    }
}
