<?php

declare(strict_types=1);

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

/*
 * FSA-17: User::$hidden lacked `two_factor_secret`/`two_factor_recovery_codes`,
 * so Laravel-encrypted ciphertext for both rode along on every page's
 * `auth.user` Inertia prop (HandleInertiaRequests::share()) and on the admin
 * customer list/detail pages (Admin\CustomerController), unused by any
 * frontend component, needlessly widening what travels off the server for
 * values that matter only if a separate APP_KEY compromise ever occurs.
 */
function twoFactorUser(): User
{
    return User::factory()->create([
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
        'two_factor_confirmed_at' => now(),
    ]);
}

test('a logged-in customer\'s own page never serializes their 2FA secret or recovery codes', function (): void {
    $user = twoFactorUser();

    actingAs($user)->get('/')
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.id', $user->id)
            ->missing('auth.user.two_factor_secret')
            ->missing('auth.user.two_factor_recovery_codes'));
});

test('the admin customer index does not serialize any customer\'s 2FA secret or recovery codes', function (): void {
    $admin = createAdmin();
    twoFactorUser();

    actingAs($admin, 'admin')->get(route('admin.customers.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->missing('customers.data.0.two_factor_secret')
            ->missing('customers.data.0.two_factor_recovery_codes'));
});

test('the admin customer detail page does not serialize that customer\'s 2FA secret or recovery codes', function (): void {
    $admin = createAdmin();
    $user = twoFactorUser();

    actingAs($admin, 'admin')->get(route('admin.customers.show', $user))
        ->assertInertia(fn (Assert $page) => $page
            ->where('customer.id', $user->id)
            ->missing('customer.two_factor_secret')
            ->missing('customer.two_factor_recovery_codes'));
});

test('other expected user fields remain serialized correctly', function (): void {
    $user = twoFactorUser();

    actingAs($user)->get('/')
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.id', $user->id)
            ->where('auth.user.name', $user->name)
            ->where('auth.user.email', $user->email)
            ->missing('auth.user.password')
            ->missing('auth.user.remember_token'));
});

test('hiding both columns does not break reading hasEnabledTwoFactorAuthentication()', function (): void {
    $user = twoFactorUser();

    expect($user->hasEnabledTwoFactorAuthentication())->toBeTrue();
    expect($user->recoveryCodes())->toBe(['code1', 'code2']);
});

test('a freshly-loaded model (not the in-memory instance) still exposes both for internal use after hiding them from serialization', function (): void {
    $created = twoFactorUser();

    $reloaded = User::findOrFail($created->id);

    expect($reloaded->hasEnabledTwoFactorAuthentication())->toBeTrue();
    expect($reloaded->recoveryCodes())->toBe(['code1', 'code2']);
    // Confirms $hidden only affects serialization, not direct attribute access.
    expect($reloaded->two_factor_secret)->not->toBeNull();
});
