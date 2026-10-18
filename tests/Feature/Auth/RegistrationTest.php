<?php

use App\Models\SocialAccount;
use App\Models\User;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    expect(User::where('email', 'test@example.com')->first()->has_password)->toBeTrue();
});

test('registering with an email already used by a social-only account shows a provider-specific message', function () {
    $existing = User::factory()->withoutPassword()->create(['email' => 'social@example.com']);
    SocialAccount::create([
        'user_id' => $existing->id,
        'provider' => 'google',
        'provider_id' => 'google-123',
    ]);

    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'social@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors('email');
    expect(session('errors')->first('email'))->toContain('Google');
    $this->assertGuest();
    expect(User::count())->toBe(1);
});

test('registering with an email already used by a regular account shows the generic message', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'taken@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors('email');
    expect(session('errors')->first('email'))->toBe('The email has already been taken.');
});
