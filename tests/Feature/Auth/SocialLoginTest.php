<?php

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function fakeSocialiteUser(array $attributes): SocialiteUser
{
    return (new SocialiteUser)->map($attributes);
}

test('a new user registered via a social provider is flagged as having no password', function () {
    Socialite::fake('google', fakeSocialiteUser([
        'id' => 'google-123',
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
    ]));

    $response = $this->get('/auth/google/callback');

    $this->assertAuthenticated();
    $response->assertRedirect();

    $user = User::where('email', 'jane@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user->has_password)->toBeFalse();
    expect($user->socialAccounts()->where('provider', 'google')->exists())->toBeTrue();
});

test('logging in via a social provider links to an existing manually registered account', function () {
    $user = User::factory()->create(['email' => 'existing@example.com']);

    expect($user->has_password)->toBeTrue();

    Socialite::fake('google', fakeSocialiteUser([
        'id' => 'google-456',
        'name' => 'Existing User',
        'email' => 'existing@example.com',
    ]));

    $response = $this->get('/auth/google/callback');

    $response->assertRedirect();
    $this->assertAuthenticatedAs($user);

    expect(User::count())->toBe(1);
    expect($user->has_password)->toBeTrue();
    expect($user->socialAccounts()->where('provider', 'google')->exists())->toBeTrue();
});

test('a social login with no email is rejected with a friendly error', function () {
    Socialite::fake('google', fakeSocialiteUser([
        'id' => 'google-789',
        'name' => 'No Email',
        'email' => null,
    ]));

    $response = $this->get('/auth/google/callback');

    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors('email');
    $this->assertGuest();
    expect(User::count())->toBe(0);
});

test('an authenticated user can connect an additional provider from settings', function () {
    $user = User::factory()->create();
    actingAs($user);

    Socialite::fake('facebook', fakeSocialiteUser([
        'id' => 'facebook-111',
        'name' => $user->name,
        'email' => 'completely-different@example.com',
    ]));

    $response = $this->get('/auth/facebook/callback');

    $response->assertRedirect(route('connections.edit'));
    $this->assertAuthenticatedAs($user);

    expect(User::count())->toBe(1);
    expect($user->socialAccounts()->where('provider', 'facebook')->exists())->toBeTrue();
});

test('a social account left dangling by a deleted user self-heals instead of crashing', function () {
    $deletedOwnerId = User::factory()->create()->id;

    SocialAccount::create([
        'user_id' => $deletedOwnerId,
        'provider' => 'google',
        'provider_id' => 'google-stale',
    ]);

    // Simulate a dangling social account whose user is gone (e.g. removed
    // outside the normal delete path) without the FK cascade also wiping
    // this row, which would just prove the cascade works, not the self-heal.
    Schema::disableForeignKeyConstraints();
    DB::table('users')->where('id', $deletedOwnerId)->delete();
    Schema::enableForeignKeyConstraints();

    Socialite::fake('google', fakeSocialiteUser([
        'id' => 'google-stale',
        'name' => 'Rehabilitated User',
        'email' => 'rehabilitated@example.com',
    ]));

    $response = $this->get('/auth/google/callback');

    $response->assertRedirect();
    $this->assertAuthenticated();

    $newUser = User::where('email', 'rehabilitated@example.com')->first();
    expect($newUser)->not->toBeNull();
    expect($newUser->id)->not->toBe($deletedOwnerId);
    expect(SocialAccount::where('provider_id', 'google-stale')->first()->user_id)->toBe($newUser->id);
});

test('an authenticated user cannot connect a provider account already linked to someone else', function () {
    $otherUser = User::factory()->create();
    SocialAccount::create([
        'user_id' => $otherUser->id,
        'provider' => 'google',
        'provider_id' => 'google-999',
    ]);

    $user = User::factory()->create();
    actingAs($user);

    Socialite::fake('google', fakeSocialiteUser([
        'id' => 'google-999',
        'name' => $user->name,
        'email' => $user->email,
    ]));

    $response = $this->get('/auth/google/callback');

    $response->assertRedirect(route('connections.edit'));
    $response->assertSessionHasErrors('connections');
    expect($user->socialAccounts()->where('provider', 'google')->exists())->toBeFalse();
});
