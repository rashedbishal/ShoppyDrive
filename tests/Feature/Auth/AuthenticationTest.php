<?php

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;

test('the signed-in user shared with every page keeps expected fields and hides secrets', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.id', $user->id)
            ->where('auth.user.email', $user->email)
            ->missing('auth.user.password')
            ->missing('auth.user.remember_token'));
});

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(config('fortify.home'));
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('a social-only account trying to log in with a password is told to use its provider', function () {
    $user = User::factory()->withoutPassword()->create();
    SocialAccount::create([
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_id' => 'google-123',
    ]);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'whatever-they-guess',
    ]);

    $response->assertSessionHasErrors('email');
    expect(session('errors')->first('email'))->toContain('Google');
    $this->assertGuest();
});

test('logging in while unverified redirects to the verification code screen', function () {
    $user = User::factory()->unverified()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('verification.notice'));
});

test('logging in while verified redirects home as usual', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('home'));
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $this->assertGuest();
    $response->assertRedirect(route('home'));
});

test('users are rate limited', function () {
    $user = User::factory()->create();

    RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertTooManyRequests();
});
