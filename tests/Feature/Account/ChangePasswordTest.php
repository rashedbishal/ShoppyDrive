<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('a logged-in customer can change their password from the account page', function () {
    $user = User::factory()->create(['password' => Hash::make('old-password-123')]);

    $response = $this->actingAs($user)->put(route('account.password.update'), [
        'current_password' => 'old-password-123',
        'password' => 'new-password-456',
        'password_confirmation' => 'new-password-456',
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    expect(Hash::check('new-password-456', $user->refresh()->password))->toBeTrue();
});

test('changing the password is rejected when the current password is wrong', function () {
    $user = User::factory()->create(['password' => Hash::make('old-password-123')]);

    $response = $this->actingAs($user)->put(route('account.password.update'), [
        'current_password' => 'totally-wrong',
        'password' => 'new-password-456',
        'password_confirmation' => 'new-password-456',
    ]);

    $response->assertSessionHasErrors('current_password');
    expect(Hash::check('old-password-123', $user->refresh()->password))->toBeTrue();
});

test('the account change-password page route resolves to the authenticated settings endpoint, not the token-reset one', function () {
    // Regression guard for ACCT-001: the frontend used to call
    // route('password.update'), which is Fortify's own token-based
    // reset-password route (POST /reset-password) and 405s under PUT.
    expect(route('account.password.update'))->toContain('/account/password');
});

// ── DISC-31: Change Password only for customers who have a password ─────────

test('DISC-31: a customer with a password can open the change-password page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('account.password'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('account/ChangePassword'));
});

test('DISC-31: a Google/Facebook-only customer gets a 404 on the change-password page', function () {
    $user = User::factory()->create(['has_password' => false]);

    $this->actingAs($user)->get(route('account.password'))->assertNotFound();
});

test('DISC-31: a Google/Facebook-only customer cannot submit a password change', function () {
    $user = User::factory()->create(['has_password' => false]);
    $originalHash = $user->password;

    $this->actingAs($user)->put(route('account.password.update'), [
        'current_password' => 'password',
        'password' => 'new-password-456',
        'password_confirmation' => 'new-password-456',
    ])->assertNotFound();

    expect($user->refresh()->password)->toBe($originalHash);
});

test('DISC-31: once a social-only customer sets a password in Connected Accounts, Change Password opens for them', function () {
    $user = User::factory()->create(['has_password' => false]);

    $this->actingAs($user)->put(route('account.connections.password.update'), [
        'password' => 'brand-new-pass-789',
        'password_confirmation' => 'brand-new-pass-789',
    ])->assertSessionHasNoErrors();

    $this->actingAs($user->refresh())->get(route('account.password'))->assertOk();
});

test('DISC-31: the account sidebar hides Change Password only for customers without a password', function () {
    // Source-level check (this project has no JavaScript test runner).
    $layout = (string) preg_replace('/\s+/', ' ', (string) file_get_contents(resource_path('js/components/layout/AccountLayout.tsx')));

    expect($layout)->toContain("href !== '/account/password' || auth.user?.has_password !== false")
        ->and($layout)->toContain('{visibleNavItems.map(');
});

test('DISC-31: the account dashboard no longer has the Account Security card', function () {
    $dashboard = (string) file_get_contents(resource_path('js/pages/account/Dashboard.tsx'));

    expect($dashboard)->not->toContain('Account Security')
        ->and($dashboard)->not->toContain("route('account.password')");
});
