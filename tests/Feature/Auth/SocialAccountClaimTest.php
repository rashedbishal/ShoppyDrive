<?php

use App\Mail\VerifyEmailCodeMail;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

/*
 * FSA-09: a social identity may take over an existing account by email only
 * when the provider proves the address (Google `email_verified: true`).
 * Someone who pre-registered that address without verifying it must lose
 * every way back in: password, sessions, remember-me and planted links.
 */

/**
 * @param  array<string, mixed>  $attributes
 */
function claimTestSocialiteUser(array $attributes): SocialiteUser
{
    return (new SocialiteUser)->setRaw($attributes)->map($attributes);
}

function fakeVerifiedGoogle(string $email, string $id = 'google-owner'): void
{
    Socialite::fake('google', claimTestSocialiteUser([
        'id' => $id, 'name' => 'Real Owner', 'email' => $email, 'email_verified' => true,
    ]));
}

test('a verified local account links to a Google identity with a verified email and keeps its password', function () {
    $user = User::factory()->create(['email' => 'owner@example.com', 'password' => 'owner-password']);
    $passwordHash = $user->password;
    fakeVerifiedGoogle('owner@example.com');

    $this->get('/auth/google/callback')->assertRedirect(config('fortify.home'));

    $this->assertAuthenticatedAs($user);
    expect($user->refresh()->password)->toBe($passwordHash)
        ->and($user->has_password)->toBeTrue()
        ->and($user->socialAccounts()->where('provider', 'google')->exists())->toBeTrue();
});

test('a pre-registered unverified account is claimed by the verified Google owner and the registrant is locked out', function () {
    $referrer = User::factory()->create();
    $victimAccount = User::factory()->unverified()->create([
        'email' => 'victim@example.com',
        'password' => 'attacker-password',
        'referred_by_id' => $referrer->id,
    ]);
    $victimAccount->socialAccounts()->create(['provider' => 'facebook', 'provider_id' => 'attacker-facebook']);
    $rememberToken = $victimAccount->remember_token;

    // The registrant's live session, captured before the claim.
    $this->post(route('login.store'), ['email' => 'victim@example.com', 'password' => 'attacker-password']);
    $this->get(route('account.profile'))->assertOk();
    $attackerSession = session()->all();

    auth()->logout();
    $this->flushSession();
    fakeVerifiedGoogle('victim@example.com');

    $this->get('/auth/google/callback')->assertRedirect(config('fortify.home'));

    $claimed = $victimAccount->refresh();
    $this->assertAuthenticatedAs($claimed);
    expect($claimed->hasVerifiedEmail())->toBeTrue()
        ->and(Hash::check('attacker-password', $claimed->password))->toBeFalse()
        ->and($claimed->has_password)->toBeFalse()
        ->and($claimed->remember_token)->not->toBe($rememberToken)
        ->and($claimed->referred_by_id)->toBeNull()
        ->and(SocialAccount::where('provider_id', 'attacker-facebook')->exists())->toBeFalse()
        ->and($claimed->socialAccounts()->where('provider', 'google')->exists())->toBeTrue();

    // The registrant's old session is logged out on its next request…
    auth()->logout();
    $this->app['auth']->forgetGuards();
    $this->flushSession();
    $this->withSession($attackerSession)->get(route('account.profile'))->assertRedirect(route('login'));
    $this->assertGuest();

    // …and the old password no longer works.
    $this->post(route('login.store'), ['email' => 'victim@example.com', 'password' => 'attacker-password'])
        ->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('an unverified local account is not linked when Google does not verify the email', function (array $claim) {
    $user = User::factory()->unverified()->create(['email' => 'victim@example.com', 'password' => 'attacker-password']);
    $passwordHash = $user->password;
    Socialite::fake('google', claimTestSocialiteUser([
        'id' => 'google-unproven', 'name' => 'Someone', 'email' => 'victim@example.com', ...$claim,
    ]));

    $this->get('/auth/google/callback')->assertRedirect(route('login'))->assertSessionHasErrors('email');

    $this->assertGuest();
    expect($user->refresh()->password)->toBe($passwordHash)
        ->and($user->hasVerifiedEmail())->toBeFalse()
        ->and($user->socialAccounts()->exists())->toBeFalse();
})->with([
    'claim false' => [['email_verified' => false]],
    'claim missing' => [[]],
]);

test('Facebook never claims or links an unverified local account by email', function () {
    $user = User::factory()->unverified()->create(['email' => 'victim@example.com', 'password' => 'attacker-password']);
    $passwordHash = $user->password;
    Socialite::fake('facebook', claimTestSocialiteUser([
        'id' => 'facebook-x', 'name' => 'Someone', 'email' => 'victim@example.com', 'verified' => true,
    ]));

    $this->get('/auth/facebook/callback')->assertRedirect(route('login'))->assertSessionHasErrors('email');

    $this->assertGuest();
    expect($user->refresh()->password)->toBe($passwordHash)
        ->and($user->socialAccounts()->exists())->toBeFalse();
});

test('a new Google account with a verified email is created verified', function () {
    fakeVerifiedGoogle('new@example.com', 'google-new');

    $this->get('/auth/google/callback')->assertRedirect(config('fortify.home'));

    $user = User::where('email', 'new@example.com')->sole();
    $this->assertAuthenticatedAs($user);
    expect($user->hasVerifiedEmail())->toBeTrue();
});

test('a new Facebook account is created unverified but logs straight in, with no OTP challenge', function () {
    Mail::fake();
    Socialite::fake('facebook', claimTestSocialiteUser([
        'id' => 'facebook-new', 'name' => 'New Person', 'email' => 'fbnew@example.com',
    ]));

    // FSA-09 policy update, 2026-11-03: social login is itself the
    // authentication method and must never be followed by a mandatory
    // email-OTP challenge — not even for a brand-new, unverified account.
    $this->get('/auth/facebook/callback')->assertRedirect(config('fortify.home'));

    $user = User::where('email', 'fbnew@example.com')->sole();
    $this->assertAuthenticatedAs($user);
    // The account is still genuinely unverified — Facebook's claim is
    // never trusted as proof of email ownership. Only the proactive OTP
    // redirect/send is gone; `email_verified_at` is untouched.
    expect($user->hasVerifiedEmail())->toBeFalse()
        ->and($user->socialAccounts()->where('provider', 'facebook')->exists())->toBeTrue();
    Mail::assertNotSent(VerifyEmailCodeMail::class);
});

test('an already linked Facebook identity still logs in', function () {
    $user = User::factory()->create(['email' => 'linked@example.com']);
    $user->socialAccounts()->create(['provider' => 'facebook', 'provider_id' => 'facebook-linked']);
    Socialite::fake('facebook', claimTestSocialiteUser([
        'id' => 'facebook-linked', 'name' => 'Linked', 'email' => 'linked@example.com',
    ]));

    $this->get('/auth/facebook/callback')->assertRedirect(config('fortify.home'));

    $this->assertAuthenticatedAs($user);
});

test('an already linked but still-unverified Facebook identity logs straight in, with no OTP challenge', function () {
    // FSA-09 policy update: this is exactly the "previously created
    // Facebook-linked account" case the owner explicitly named — a
    // standing link, still unverified (e.g. the owner never completed a
    // first-visit OTP under the old code, or signed up after this fix
    // without yet verifying), must not be OTP-challenged on repeat login.
    Mail::fake();
    $user = User::factory()->unverified()->create(['email' => 'legacy-linked@example.com', 'has_password' => false]);
    $user->socialAccounts()->create(['provider' => 'facebook', 'provider_id' => 'facebook-legacy-linked']);
    Socialite::fake('facebook', claimTestSocialiteUser([
        'id' => 'facebook-legacy-linked', 'name' => 'Legacy Linked', 'email' => 'legacy-linked@example.com',
    ]));

    $this->get('/auth/facebook/callback')->assertRedirect(config('fortify.home'));

    $this->assertAuthenticatedAs($user);
    expect($user->refresh()->hasVerifiedEmail())->toBeFalse();
    Mail::assertNotSent(VerifyEmailCodeMail::class);
});

test('a new Google account with a missing email_verified claim is created unverified but logs straight in, with no OTP challenge', function () {
    Mail::fake();
    Socialite::fake('google', claimTestSocialiteUser([
        'id' => 'google-unclaimed', 'name' => 'New Google Person', 'email' => 'googlenew@example.com',
    ]));

    $this->get('/auth/google/callback')->assertRedirect(config('fortify.home'));

    $user = User::where('email', 'googlenew@example.com')->sole();
    $this->assertAuthenticatedAs($user);
    expect($user->hasVerifiedEmail())->toBeFalse();
    Mail::assertNotSent(VerifyEmailCodeMail::class);
});

test('an unverified account cannot connect a provider from settings', function () {
    $user = User::factory()->unverified()->create();
    Socialite::fake('facebook', claimTestSocialiteUser([
        'id' => 'facebook-planted', 'name' => 'Planted', 'email' => 'other@example.com',
    ]));

    $this->actingAs($user)
        ->get('/auth/facebook/callback')
        ->assertRedirect(route('account.connections'))
        ->assertSessionHasErrors(['connections' => 'Verify your email address before connecting Facebook.']);

    expect($user->socialAccounts()->exists())->toBeFalse();
});
