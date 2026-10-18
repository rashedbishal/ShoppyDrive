<?php

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

test('connections page is displayed with linked providers', function () {
    $user = User::factory()->create();
    SocialAccount::create([
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_id' => 'google-123',
    ]);

    $this->actingAs($user)
        ->get(route('connections.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/connections')
            ->where('hasPassword', true)
            ->has('connections', 1)
            ->where('connections.0.provider', 'google'),
        );
});

test('a social-only user can set a password for the first time', function () {
    $user = User::factory()->withoutPassword()->create();

    $response = $this->actingAs($user)
        ->from(route('connections.edit'))
        ->put(route('connections.password.update'), [
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response->assertSessionHasNoErrors()->assertRedirect(route('connections.edit'));

    $user->refresh();
    expect($user->has_password)->toBeTrue();
    expect(Hash::check('new-password', $user->password))->toBeTrue();
});

test('a user who already has a password cannot use the set password endpoint', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->put(route('connections.password.update'), [
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ]);

    $response->assertForbidden();
});

test('a user can disconnect a provider when another sign-in method remains', function () {
    $user = User::factory()->create();
    $account = SocialAccount::create([
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_id' => 'google-123',
    ]);

    $response = $this->actingAs($user)->delete(route('connections.destroy', $account));

    $response->assertRedirect();
    expect(SocialAccount::find($account->id))->toBeNull();
});

test('a social-only user cannot disconnect their only sign-in method', function () {
    $user = User::factory()->withoutPassword()->create();
    $account = SocialAccount::create([
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_id' => 'google-123',
    ]);

    $response = $this->actingAs($user)->delete(route('connections.destroy', $account));

    $response->assertRedirect();
    expect(SocialAccount::find($account->id))->not->toBeNull();
});

test('a user cannot disconnect another user\'s social account', function () {
    $owner = User::factory()->create();
    $account = SocialAccount::create([
        'user_id' => $owner->id,
        'provider' => 'google',
        'provider_id' => 'google-123',
    ]);

    $intruder = User::factory()->create();

    $response = $this->actingAs($intruder)->delete(route('connections.destroy', $account));

    $response->assertForbidden();
    expect(SocialAccount::find($account->id))->not->toBeNull();
});
