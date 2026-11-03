<?php

use App\Models\User;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
});

test('two factor challenge redirects to login when not authenticated', function () {
    $response = $this->get(route('two-factor.login'));

    $response->assertRedirect(route('login'));
});

/*
 * Product decision: no mandatory 2FA challenge or redirect after successful
 * password authentication — see
 * FortifyServiceProvider::configureAuthenticationPipeline(). Logging in
 * never puts a pending challenge in session, so this screen is no longer
 * reachable through the normal login flow; replaces the stock
 * Fortify-scaffolded test that rendered it after login.
 */
test('logging in with two factor enabled never reaches the challenge screen', function () {
    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

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

    // Already fully logged in (no pending challenge was ever created), so
    // the route's own `guest` middleware bounces this away before the
    // controller's "no challenged user" check ever runs.
    $this->get(route('two-factor.login'))
        ->assertRedirect(config('fortify.home'));
});
