<?php

use App\Actions\Fortify\CreateNewUser;
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
    $response->assertRedirect(route('verification.notice'));

    expect(User::where('email', 'test@example.com')->first()->has_password)->toBeTrue();
});

test('SEC-015: registering with an email used by a social-only account does not name its provider', function () {
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
    expect(session('errors')->first('email'))->toBe(CreateNewUser::EMAIL_UNAVAILABLE_MESSAGE);
    $this->assertGuest();
    expect(User::count())->toBe(1);
});

test('SEC-015: registering with an email used by a regular account shows the same message', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'taken@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors('email');
    expect(session('errors')->first('email'))->toBe(CreateNewUser::EMAIL_UNAVAILABLE_MESSAGE);
});
