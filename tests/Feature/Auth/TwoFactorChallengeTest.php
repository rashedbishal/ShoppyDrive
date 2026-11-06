<?php

use App\Models\User;

beforeEach(function () {});

/*
 * Product decision: no mandatory 2FA challenge or redirect after successful
 * password authentication — see
 * FortifyServiceProvider::configureAuthenticationPipeline(). Logging in
 * never puts a pending challenge in session, and since SEC-014 the challenge
 * screen no longer exists at all.
 */
test('logging in with two factor enabled never reaches the challenge screen', function () {
    $user = User::factory()->create();

    $user->forceFill([
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
});
