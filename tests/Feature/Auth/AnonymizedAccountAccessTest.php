<?php

use App\Actions\Fortify\ResetUserPassword;
use App\Models\SocialAccount;
use App\Models\User;
use App\Notifications\QueuedResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

uses(RefreshDatabase::class);

function fakeSocialiteUserForAnonymizationTest(array $attributes): SocialiteUser
{
    return (new SocialiteUser)->map($attributes);
}

test('password login is rejected for an anonymized customer', function () {
    $customer = User::factory()->create(['email' => 'was-active@example.com']);
    $customer->anonymize(createMotherAdmin());

    $this->post(route('login.store'), [
        'email' => 'was-active@example.com',
        'password' => 'password',
    ]);

    $this->assertGuest();
});

test('the ResetUserPassword action itself rejects an anonymized user', function () {
    // Unit-level check of the guard clause in isolation, kept alongside the
    // full HTTP-level test below (not a substitute for it).
    $customer = User::factory()->create();
    $customer->anonymize(createMotherAdmin());

    $rotatedPasswordHash = $customer->fresh()->password;

    expect(fn () => app(ResetUserPassword::class)->reset($customer->fresh(), ['password' => 'NewPassword123!']))
        ->toThrow(ValidationException::class);

    // The reset attempt must never have actually changed the (rotated, unknown) password.
    expect($customer->fresh()->password)->toBe($rotatedPasswordHash);
});

test('completing a real password-reset HTTP request for an anonymized customer is rejected, not a server error', function () {
    // Full round trip through Fortify's real flow, matching the exact
    // convention tests/Feature/Auth/PasswordResetTest.php already uses:
    // request a reset link, capture the real token from the notification,
    // then submit it to the real password.update route.
    Notification::fake();

    $customer = User::factory()->create(['email' => 'reset-anon@example.com']);

    $this->post(route('password.email'), ['email' => $customer->email]);

    $rotatedPasswordHash = null;

    Notification::assertSentTo($customer, QueuedResetPassword::class, function ($notification) use ($customer, &$rotatedPasswordHash) {
        // Anonymize *after* the reset link is requested but *before* it's
        // used -- the realistic order of events (link already in the
        // customer's inbox when an admin later anonymizes the account).
        $customer->anonymize(createMotherAdmin());
        $rotatedPasswordHash = $customer->fresh()->password;

        $response = $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $customer->email,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        // The intended validation response, not a 500 -- and not a redirect
        // to login implying success.
        $response->assertSessionHasErrors('email');
        $response->assertStatus(302);

        return true;
    });

    expect($customer->fresh()->password)->toBe($rotatedPasswordHash);
    $this->assertGuest();
});

test('a social login attempt for an anonymized customer is rejected and does not create a new link', function () {
    $customer = User::factory()->create(['email' => 'social-anon@example.com']);
    SocialAccount::create([
        'user_id' => $customer->id,
        'provider' => 'google',
        'provider_id' => 'google-anon-1',
    ]);

    $customer->anonymize(createMotherAdmin());

    // The old link above is deleted by anonymize(), so a future attempt
    // (even with a *different* provider_id) always falls through to the
    // email-lookup branch -- this is the exact path that was found, this
    // session, to otherwise silently re-authenticate via the unchanged email.
    Socialite::fake('google', fakeSocialiteUserForAnonymizationTest([
        'id' => 'google-anon-2',
        'name' => 'Trying Again',
        'email' => 'social-anon@example.com',
    ]));

    $response = $this->get('/auth/google/callback');

    $response->assertRedirect(route('login'));
    $this->assertGuest();
    expect(SocialAccount::where('provider_id', 'google-anon-2')->exists())->toBeFalse();
});

test('an anonymized customer with a still-live session cannot connect an additional social provider', function () {
    // connectToCurrentUser() is reached only when Auth::check() is already
    // true -- exercised directly here (bypassing the real login flow, which
    // this specific scenario presupposes has already happened) to prove the
    // explicit guard added to that method specifically, independent of
    // whichever other mechanism closed the session itself.
    $customer = User::factory()->create(['email' => 'connect-anon@example.com']);
    $customer->anonymize(createMotherAdmin());

    $this->actingAs($customer->fresh());

    Socialite::fake('facebook', fakeSocialiteUserForAnonymizationTest([
        'id' => 'facebook-anon-1',
        'name' => 'Trying To Connect',
        'email' => 'different@example.com',
    ]));

    $response = $this->get('/auth/facebook/callback');

    $response->assertRedirect(route('login'));
    $this->assertGuest();
    expect(SocialAccount::where('provider_id', 'facebook-anon-1')->exists())->toBeFalse();
});

test('a customer authenticated before anonymization is rejected on their next request afterward', function () {
    $customer = User::factory()->create(['email' => 'active@example.com']);

    $this->post(route('login.store'), [
        'email' => 'active@example.com',
        'password' => 'password',
    ])->assertRedirect();

    $this->assertAuthenticatedAs($customer);

    // A genuine prior request through the real middleware stack, so the
    // AuthenticateSession baseline is established before anonymization.
    $this->get(route('account.dashboard'))->assertOk();

    $customer->anonymize(createMotherAdmin());

    // Laravel's SessionGuard caches its resolved user for the lifetime of
    // the test method, since every simulated request here shares one
    // application container -- unlike a real request, which always gets a
    // fresh guard. This clears that test-only cache so the next call
    // genuinely re-resolves the user from the database, matching what a
    // real second HTTP request would do in production.
    auth()->forgetGuards();

    $response = $this->get(route('account.dashboard'));

    $response->assertRedirect(route('login'));
    $this->assertGuest();
});

test('remember-token rotation invalidates a previously issued remember-me token', function () {
    // This demonstrates the exact mechanism Laravel's
    // EloquentUserProvider::retrieveByToken() uses to resolve a "remember
    // me" cookie back into a user, rather than fabricating cookie bytes and
    // encryption, which would be fragile and not meaningfully more certain.
    $customer = User::factory()->create();
    $originalToken = $customer->remember_token;

    expect(User::where('id', $customer->id)->where('remember_token', $originalToken)->exists())->toBeTrue();

    $customer->anonymize(createMotherAdmin());

    expect(User::where('id', $customer->id)->where('remember_token', $originalToken)->exists())->toBeFalse();
});

test('anonymizing a customer deletes their database session rows directly, closing an already-open session immediately', function () {
    $customer = User::factory()->create();

    DB::table('sessions')->insert([
        'id' => 'a-genuinely-open-session',
        'user_id' => $customer->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'test',
        'payload' => base64_encode('irrelevant-for-this-assertion'),
        'last_activity' => now()->timestamp,
    ]);

    $customer->anonymize(createMotherAdmin());

    expect(DB::table('sessions')->where('id', 'a-genuinely-open-session')->exists())->toBeFalse();
});

test('a stale in-memory user object does not see anonymization applied by a separate load, demonstrating the in-flight-request limitation', function () {
    // This is a demonstration of the root-cause mechanism (PHP in-memory
    // object staleness across separate loads) honestly labeled as a
    // simulation -- it does not reproduce genuine concurrent HTTP requests,
    // which this environment cannot produce. See the DB-001 proposal's
    // explicit note on this residual, unavoidable limitation.
    $customer = User::factory()->create();

    $staleInMemoryUser = User::find($customer->id);

    $customer->anonymize(createMotherAdmin());

    expect($staleInMemoryUser->anonymized_at)->toBeNull();
    expect(User::find($customer->id)->anonymized_at)->not->toBeNull();
});
