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

/**
 * setRaw() is called here to mirror what every real Socialite provider
 * does (GoogleProvider/FacebookProvider both call setRaw($user)->map(...)
 * with the full raw payload) — the SEC-01 guard reads getRaw(), which the
 * previous version of this helper (map() only, no setRaw()) never
 * populated, so a test asserting against it would have been exercising a
 * shape that cannot occur in production.
 */
function fakeSocialiteUser(array $attributes): SocialiteUser
{
    return (new SocialiteUser)->setRaw($attributes)->map($attributes);
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
        // FSA-09: linking by email needs Google's verified-email claim.
        'email_verified' => true,
    ]));

    $response = $this->get('/auth/google/callback');

    $response->assertRedirect();
    $this->assertAuthenticatedAs($user);

    expect(User::count())->toBe(1);
    expect($user->has_password)->toBeTrue();
    expect($user->socialAccounts()->where('provider', 'google')->exists())->toBeTrue();
});

test('Google login with a verified email claim links to an existing account', function () {
    $user = User::factory()->create(['email' => 'existing@example.com']);

    Socialite::fake('google', fakeSocialiteUser([
        'id' => 'google-verified-1',
        'name' => 'Existing User',
        'email' => 'existing@example.com',
        'email_verified' => true,
    ]));

    $response = $this->get('/auth/google/callback');

    $response->assertRedirect();
    $this->assertAuthenticatedAs($user);
    expect(User::count())->toBe(1);
    expect($user->socialAccounts()->where('provider', 'google')->exists())->toBeTrue();
});

test('Google login with an explicitly unverified email claim is blocked from linking to an existing account', function () {
    $user = User::factory()->create(['email' => 'existing@example.com', 'password' => bcrypt('secret-password')]);
    $originalPasswordHash = $user->password;

    Socialite::fake('google', fakeSocialiteUser([
        'id' => 'google-unverified-1',
        'name' => 'Attacker Claim',
        'email' => 'existing@example.com',
        'email_verified' => false,
    ]));

    $response = $this->get('/auth/google/callback');

    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors('email');
    $this->assertGuest();

    expect(User::count())->toBe(1);
    expect($user->socialAccounts()->where('provider', 'google')->exists())->toBeFalse();
    expect(SocialAccount::where('provider_id', 'google-unverified-1')->exists())->toBeFalse();
    expect($user->refresh()->password)->toBe($originalPasswordHash);
});

// FSA-09 replaced SEC-01's "a missing claim keeps auto-linking" rule: only an
// explicit `email_verified: true` proves the address.
test('Google login with the email_verified claim missing does not link an existing account', function () {
    $user = User::factory()->create(['email' => 'existing@example.com']);

    Socialite::fake('google', fakeSocialiteUser([
        'id' => 'google-no-claim-1',
        'name' => 'Existing User',
        'email' => 'existing@example.com',
    ]));

    $this->get('/auth/google/callback')
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
    expect($user->socialAccounts()->exists())->toBeFalse();
});

// FSA-09 replaced SEC-01's "Facebook is unaffected" rule: Facebook gives no
// email-verification claim, so it never links an existing account by email.
test('Facebook login matching an existing account does not link it by email', function () {
    $user = User::factory()->create(['email' => 'existing@example.com']);

    Socialite::fake('facebook', fakeSocialiteUser([
        'id' => 'facebook-1',
        'name' => 'Existing User',
        'email' => 'existing@example.com',
        'verified' => true,
    ]));

    $this->get('/auth/facebook/callback')
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => 'An account with this email already exists. Please log in with your password or Google, then connect Facebook from your account settings.']);

    $this->assertGuest();
    expect($user->socialAccounts()->exists())->toBeFalse();
});

test('a previously linked Google identity still logs in even if the current response reports an unverified email', function () {
    $user = User::factory()->create(['email' => 'existing@example.com']);
    SocialAccount::create([
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_id' => 'google-already-linked-1',
    ]);

    Socialite::fake('google', fakeSocialiteUser([
        'id' => 'google-already-linked-1',
        'name' => 'Existing User',
        'email' => 'existing@example.com',
        'email_verified' => false,
    ]));

    $response = $this->get('/auth/google/callback');

    $response->assertRedirect();
    $this->assertAuthenticatedAs($user);
});

test('a provider exception during callback redirects safely to login without creating any account', function () {
    Socialite::shouldReceive('driver')->with('google')->andThrow(new Exception('provider down'));

    $response = $this->get('/auth/google/callback');

    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors('email');
    $this->assertGuest();
    expect(User::count())->toBe(0);
    expect(SocialAccount::count())->toBe(0);
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

    $response->assertRedirect(route('account.connections'));
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

    $response->assertRedirect(route('account.connections'));
    $response->assertSessionHasErrors('connections');
    expect($user->socialAccounts()->where('provider', 'google')->exists())->toBeFalse();
});
