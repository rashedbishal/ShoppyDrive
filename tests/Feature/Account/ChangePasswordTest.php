<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('a logged-in customer can change their password from the account page', function () {
    $user = User::factory()->create(['password' => Hash::make('old-password-123')]);

    $response = $this->actingAs($user)->put(route('user-password.update'), [
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

    $response = $this->actingAs($user)->put(route('user-password.update'), [
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
    expect(route('user-password.update'))->toContain('/settings/password');
});
