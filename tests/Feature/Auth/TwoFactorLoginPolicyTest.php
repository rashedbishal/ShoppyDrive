<?php

declare(strict_types=1);

use App\Models\User;

/*
 * Explicit product decision: 2FA verification is never required to log in,
 * for either guard. Password credentials (customer) or admin credentials
 * alone are sufficient — see
 * FortifyServiceProvider::configureAuthenticationPipeline(). This does not
 * remove 2FA setup/management (Settings > Security, QR code, recovery
 * codes) or any stored secret — only the login-time challenge/redirect.
 *
 * The Admin guard has never had 2FA at all (no two_factor_* columns, no
 * Fortify involvement — AdminAuthController is hand-rolled), so "an admin
 * with an existing 2FA secret" has no fixture to construct; the admin
 * coverage here is the normal-login regression confirming that remains
 * true and unaffected.
 */

test('a customer without 2FA can log in normally', function (): void {
    $user = User::factory()->create();

    $response = $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(config('fortify.home'));
});

test('a customer with an existing 2FA secret logs in with password alone, no challenge', function (): void {
    $user = User::factory()->create([
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
        'two_factor_confirmed_at' => now(),
    ]);

    $response = $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(config('fortify.home'));
    $response->assertSessionMissing('login.id');
});

test('an admin can log in normally, with no 2FA involved', function (): void {
    $admin = createAdmin(['email' => 'admin@example.com', 'password' => bcrypt('correct-password')]);

    $this->post(route('admin.login.submit'), ['email' => $admin->email, 'password' => 'correct-password'])
        ->assertRedirect(route('admin.dashboard'));

    expect(auth('admin')->check())->toBeTrue();
});

test('no two-factor route exists, for customers or admins (SEC-014)', function (): void {
    $routes = collect(Route::getRoutes())->map(fn ($route) => $route->uri())->filter(fn (string $uri) => str_contains($uri, 'two-factor'));

    expect($routes)->toBeEmpty();
    $this->get('/two-factor-challenge')->assertNotFound();
    $this->actingAs(User::factory()->create())->post('/user/two-factor-authentication')->assertNotFound();
});
