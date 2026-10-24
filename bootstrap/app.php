<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureCheckoutAccess;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\HandleTheme;
use App\Http\Middleware\RedirectAdminToDashboard;
use App\Http\Middleware\RedirectCustomerFromAdmin;
use App\Http\Middleware\TrackDailyVisit;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'redirect-customer-from-admin' => RedirectCustomerFromAdmin::class,
            'checkout.access' => EnsureCheckoutAccess::class,
        ]);
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state', 'theme_variant']);

        $middleware->web(append: [
            RedirectAdminToDashboard::class,
            HandleAppearance::class,
            HandleTheme::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            TrackDailyVisit::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
