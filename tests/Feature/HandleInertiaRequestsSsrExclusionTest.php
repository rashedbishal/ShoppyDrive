<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Http\Request;

// `$withoutSsr` is matched against the request PATH via $request->is(...)
// (Illuminate\Foundation\Http\Middleware\Concerns\ExcludesPaths), not the
// Inertia component name, and `foo/*` never matches the bare `/foo` path.
// This directly encodes that gotcha against the real route paths it's meant
// to exclude, so a future entry that only lists the wildcard form (or
// assumes a URL prefix a route doesn't actually have) fails loudly here
// instead of silently SSR-ing a page that depends on client-only state.
test('every customer-facing path meant to skip SSR actually matches a withoutSsr pattern', function () {
    $property = new ReflectionProperty(HandleInertiaRequests::class, 'withoutSsr');
    $property->setAccessible(true);
    $patterns = $property->getValue(app(HandleInertiaRequests::class));

    $pathsThatMustBeExcluded = [
        '/admin',
        '/admin/dashboard',
        '/login',
        '/register',
        '/forgot-password',
        '/reset-password/abc123',
        '/email/verify',
        '/email/verify/1/somehash',
        '/user/confirm-password',
        '/wishlist',
        '/account',
        '/account/orders',
        '/settings',
        '/settings/profile',
        '/checkout',
        '/checkout/track-incomplete',
        '/cart',
    ];

    foreach ($pathsThatMustBeExcluded as $path) {
        $request = Request::create($path, 'GET');

        $matches = collect($patterns)->contains(function (string $pattern) use ($request): bool {
            $trimmed = $pattern === '/' ? $pattern : trim($pattern, '/');

            return $request->is($trimmed);
        });

        expect($matches)->toBeTrue("Expected '{$path}' to match one of the withoutSsr patterns, but none did.");
    }
});
