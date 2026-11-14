<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\PasswordPolicy;
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

// ── DISC-32: the strength meter and checklist follow the real policy ────────

test('DISC-32: outside production the page checks only the 8-character fallback', function () {
    expect(PasswordPolicy::checks())->toBe([
        'min_length' => 8,
        'mixed_case' => false,
        'numbers' => false,
        'symbols' => false,
        'uncompromised' => false,
    ]);
});

test('DISC-32: in production the page checks the full policy, matching the requirement list', function () {
    app()->detectEnvironment(fn () => 'production');

    try {
        expect(PasswordPolicy::checks())->toBe([
            'min_length' => 8, // DISC-33: customers need 8+ characters.
            'mixed_case' => true,
            'numbers' => true,
            'symbols' => true,
            'uncompromised' => true,
        ])->and(PasswordPolicy::requirements()[0])->toBe('At least 8 characters');
    } finally {
        app()->detectEnvironment(fn () => 'testing');
    }
});

test('DISC-32: the change-password page receives the policy checks from the server', function () {
    $this->actingAs(User::factory()->create())->get(route('account.password'))
        ->assertInertia(fn ($page) => $page
            ->component('account/ChangePassword')
            ->where('passwordChecks', PasswordPolicy::checks()));
});

test('DISC-32: the page no longer hardcodes the old 8-character checklist', function () {
    // Source-level check (this project has no JavaScript test runner).
    $text = (string) preg_replace('/\s+/', ' ', (string) file_get_contents(resource_path('js/pages/account/ChangePassword.tsx')));

    expect($text)->not->toContain("'8+ Characters'")
        ->and($text)->not->toContain('length > 7')
        ->and($text)->toContain('passwordChecklist(form.data.password, passwordChecks)')
        // The same patterns as Laravel's Password rule.
        ->and($text)->toContain('/(\\p{Ll}+.*\\p{Lu})|(\\p{Lu}+.*\\p{Ll})/u')
        ->and($text)->toContain('/\\p{Z}|\\p{S}|\\p{P}/u')
        ->and($text)->toContain('Not a known leaked password · checked when you save');
});
