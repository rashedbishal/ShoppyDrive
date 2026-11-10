<?php

declare(strict_types=1);

/*
 * SEC-011: a tester sent POST /forgot-password with Host: evil.example and
 * got back a reset link built from it — confirmed live that trustHosts()
 * (bootstrap/app.php) was not actually rejecting a forged Host over plain
 * HTTP (the TLS/SNI layer happening to reject it over HTTPS is Hostinger's
 * infrastructure, not this app, and doesn't cover port 80 at all).
 * RejectUntrustedHost checks the same thing directly, independent of
 * Symfony's own trusted-host static state, and is scoped to `production`
 * only — unlike trustHosts(), which Laravel unconditionally skips whenever
 * $app->runningUnitTests() is true (always true under Pest/PHPUnit,
 * regardless of the configured environment name), so this is the first
 * version of this protection that can actually be exercised end-to-end
 * in a test at all.
 */

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function asProductionWithTrustedDomain(): void
{
    app()->detectEnvironment(fn () => 'production');
    config(['app.url' => 'https://shoppydrive.com']);
}

afterEach(function () {
    app()->detectEnvironment(fn () => 'testing');
    // Application::runningUnitTests() is literally `environment() ===
    // 'testing'`, so forcing 'production' above also makes the real
    // trustHosts() middleware (bootstrap/app.php) activate for real and
    // call Request::setTrustedHosts() — a static that would otherwise leak
    // into later tests using other host names (same cleanup
    // ProductionTransportSecurityTest's own afterEach does).
    Request::setTrustedHosts([]);
});

test('a forged Host is refused with a plain 400, over plain HTTP, in production', function () {
    asProductionWithTrustedDomain();

    $this->get('http://evil.example/forgot-password')->assertStatus(400);
});

test('a forged Host is refused even when it merely contains the real domain as a suffix or prefix', function (string $host) {
    asProductionWithTrustedDomain();

    $this->get("http://{$host}/forgot-password")->assertStatus(400);
})->with([
    'shoppydrive.com.evil.example',
    'evilshoppydrive.com',
]);

test('the real domain and its subdomains are let through untouched', function (string $host) {
    asProductionWithTrustedDomain();

    $this->get("https://{$host}/up")->assertOk();
})->with(['shoppydrive.com', 'www.shoppydrive.com', 'checkout.shoppydrive.com']);

test('a forged Host cannot reach the password reset form at all, so no reset link is ever built from it', function () {
    asProductionWithTrustedDomain();
    Mail::fake();
    User::factory()->create(['email' => 'victim@example.com', 'email_verified_at' => now()]);

    $this->call('POST', 'http://evil.example/forgot-password', ['email' => 'victim@example.com'])
        ->assertStatus(400);

    Mail::assertNothingQueued();
});

test('this protection is completely inert outside production', function () {
    $this->get('http://evil.example/up')->assertOk();
});

test('an unparseable APP_URL fails open rather than locking everyone out', function () {
    app()->detectEnvironment(fn () => 'production');
    config(['app.url' => '']);

    $this->get('http://anything-at-all.example/up')->assertOk();
});

test('the host check runs independently of any admin-configured Setting row', function () {
    asProductionWithTrustedDomain();

    $this->get('http://evil.example/up')->assertStatus(400);
});
