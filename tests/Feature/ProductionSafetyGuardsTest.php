<?php

declare(strict_types=1);

/*
 * BE-003: the demo-data seeder empties the catalogue, overwrites settings
 * and creates a known-password login, so it must refuse to run in
 * production.
 * SEC-011: only APP_URL's host (and subdomains) may be used, so a reset
 * link can never be built from an attacker-supplied Host header. Laravel
 * skips TrustHosts while running tests, so its wiring and rule are
 * checked directly.
 */

use App\Models\Product;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Middleware\TrustHosts;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Exception\SuspiciousOperationException;

uses(RefreshDatabase::class);

afterEach(function () {
    Request::setTrustedHosts([]);
});

test('the demo seeder refuses to run in production and leaves the catalogue intact', function () {
    Product::factory()->count(3)->create();
    $this->app->detectEnvironment(fn () => 'production');

    try {
        expect(fn () => $this->app->make(DatabaseSeeder::class)->setContainer($this->app)->run())
            ->toThrow(RuntimeException::class, 'Refusing to seed demo data in production');
    } finally {
        $this->app->detectEnvironment(fn () => 'testing');
    }

    expect(Product::count())->toBe(3);
});

test('trusted hosts are enforced on every request', function () {
    expect($this->app->make(Kernel::class)->hasMiddleware(TrustHosts::class))->toBeTrue();
});

test('only the APP_URL host and its subdomains are trusted', function () {
    config(['app.url' => 'https://shoppydrive.com']);
    Request::setTrustedHosts($this->app->make(TrustHosts::class)->hosts());

    foreach (['shoppydrive.com', 'www.shoppydrive.com'] as $host) {
        expect(Request::create("https://{$host}/forgot-password")->getHost())->toBe($host);
    }

    foreach (['evil.example', 'shoppydrive.com.evil.example', 'evilshoppydrive.com'] as $host) {
        expect(fn () => Request::create("https://{$host}/forgot-password")->getHost())
            ->toThrow(SuspiciousOperationException::class);
    }
});
