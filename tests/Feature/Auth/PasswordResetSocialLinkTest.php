<?php

declare(strict_types=1);

/*
 * SEC-008: a Google/Facebook link attached to an account whose email was
 * never verified could survive the real owner recovering the account
 * through "Forgot password", and keep logging in (social logins skip the
 * email code). Completing a reset on such an account removes those links.
 */

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

uses(RefreshDatabase::class);

function linkFacebook(User $user, string $providerId = 'fb-stranger'): void
{
    SocialAccount::create(['user_id' => $user->id, 'provider' => 'facebook', 'provider_id' => $providerId]);
}

function resetPasswordFor(User $user): void
{
    test()->post(route('password.update'), [
        'token' => Password::broker()->createToken($user),
        'email' => $user->email,
        'password' => 'N3w-Strong-Passw0rd!',
        'password_confirmation' => 'N3w-Strong-Passw0rd!',
    ])->assertSessionHasNoErrors();
}

test('resetting the password of a never-verified account removes its social links', function () {
    $user = User::factory()->unverified()->create(['email' => 'rahima@example.com']);
    linkFacebook($user);

    resetPasswordFor($user);

    expect($user->socialAccounts()->count())->toBe(0)
        ->and(SocialAccount::where('provider_id', 'fb-stranger')->exists())->toBeFalse();
});

test('a verified account keeps its social links when the password is reset', function () {
    $user = User::factory()->create(['email' => 'karim@example.com']);
    linkFacebook($user, 'fb-karim');

    resetPasswordFor($user);

    expect($user->socialAccounts()->count())->toBe(1);
});

test('the stranger can no longer log in with that Facebook account after the owner resets', function () {
    $user = User::factory()->unverified()->create(['email' => 'rahima@example.com']);
    linkFacebook($user);
    $stranger = (new SocialiteUser)->map(['id' => 'fb-stranger', 'name' => 'Stranger', 'email' => 'rahima@example.com']);

    // Before the reset the planted link logs straight in (the gap).
    Socialite::fake('facebook', $stranger);
    $this->get('/auth/facebook/callback');
    expect(auth()->id())->toBe($user->id);
    auth()->logout();

    resetPasswordFor($user);

    // After it, Facebook never links by email (FSA-09), so nothing logs in.
    Socialite::fake('facebook', $stranger);
    $this->get('/auth/facebook/callback');
    expect(auth()->check())->toBeFalse();
});
