<?php

use App\Http\Middleware\BlockBlockedIp;
use App\Http\Middleware\CaptureTrafficSource;
use App\Http\Middleware\EnsureAccountNotAnonymized;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureAdminPermission;
use App\Http\Middleware\EnsureCheckoutAccess;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RedirectAdminToDashboard;
use App\Http\Middleware\RedirectCustomerFromAdmin;
use App\Http\Middleware\RedirectToCanonicalDomain;
use App\Http\Middleware\ThrottleFortifyActions;
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
            'admin.can' => EnsureAdminPermission::class,
            'redirect-customer-from-admin' => RedirectCustomerFromAdmin::class,
            'checkout.access' => EnsureCheckoutAccess::class,
            'fortify.throttle' => ThrottleFortifyActions::class,
            'account.active' => EnsureAccountNotAnonymized::class,
        ]);
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // Runs before session/cookie middleware so a www. visitor is
        // redirected before anything gets set for the wrong host, and so a
        // blocked IP is declined before a session/cart is ever started for it.
        $middleware->web(prepend: [
            RedirectToCanonicalDomain::class,
            BlockBlockedIp::class,
        ]);

        $middleware->web(append: [
            RedirectAdminToDashboard::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            TrackDailyVisit::class,
            CaptureTrafficSource::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
