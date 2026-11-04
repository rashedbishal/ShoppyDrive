<?php

declare(strict_types=1);

/*
 * FSA-09 legacy-account follow-up. See UnprovenSocialVerificationAudit's
 * docblock for the exact heuristic: a current account can only match it if
 * it predates the FSA-09 fix, since the fixed code never sets
 * email_verified_at for a Facebook-only signup. Purely read-only — these
 * tests confirm the audit finds/excludes the right accounts and that
 * nothing it touches is ever modified.
 */

use App\Models\SocialAccount;
use App\Models\User;
use App\Support\UnprovenSocialVerificationAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function legacyFacebookAccount(array $overrides = []): User
{
    $user = User::factory()->create(array_merge([
        'email_verified_at' => now()->subMonths(6),
        'has_password' => false,
    ], $overrides));

    SocialAccount::create([
        'user_id' => $user->id,
        'provider' => 'facebook',
        'provider_id' => (string) random_int(100000, 999999),
    ]);

    return $user;
}

test('an account verified only via a pre-fix Facebook login is found', function () {
    $user = legacyFacebookAccount();

    $found = UnprovenSocialVerificationAudit::find();

    expect($found->pluck('id'))->toContain($user->id);
});

test('a normally-registered, password-verified account is not found', function () {
    $user = User::factory()->create(['email_verified_at' => now(), 'has_password' => true]);

    $found = UnprovenSocialVerificationAudit::find();

    expect($found->pluck('id'))->not->toContain($user->id);
});

test('an unverified account is not found, regardless of a Facebook link', function () {
    $user = User::factory()->unverified()->create(['has_password' => false]);
    SocialAccount::create(['user_id' => $user->id, 'provider' => 'facebook', 'provider_id' => '12345']);

    $found = UnprovenSocialVerificationAudit::find();

    expect($found->pluck('id'))->not->toContain($user->id);
});

test('a verified Google-only account with no password is not found, since Google already proves ownership', function () {
    $user = User::factory()->create(['email_verified_at' => now(), 'has_password' => false]);
    SocialAccount::create(['user_id' => $user->id, 'provider' => 'google', 'provider_id' => '67890']);

    $found = UnprovenSocialVerificationAudit::find();

    expect($found->pluck('id'))->not->toContain($user->id);
});

test('an anonymized account is excluded even if it would otherwise match', function () {
    $user = legacyFacebookAccount();
    $user->anonymize();

    $found = UnprovenSocialVerificationAudit::find();

    expect($found->pluck('id'))->not->toContain($user->id);
});

test('the audit command lists matching accounts and modifies nothing', function () {
    $user = legacyFacebookAccount(['email' => 'legacy-fb@example.com']);
    $balanceBefore = $user->refresh();

    $this->artisan('audit:unproven-social-accounts')
        ->expectsOutputToContain('1 account(s) found')
        ->assertSuccessful();

    expect($user->refresh()->email_verified_at->eq($balanceBefore->email_verified_at))->toBeTrue();
    expect(SocialAccount::where('user_id', $user->id)->count())->toBe(1);
});

test('the audit command reports nothing found when there are no matching accounts', function () {
    User::factory()->create(['email_verified_at' => now(), 'has_password' => true]);

    $this->artisan('audit:unproven-social-accounts')
        ->expectsOutputToContain('No accounts found')
        ->assertSuccessful();
});

// --summary mode: for a context (e.g. production) where even seeing name/email must be avoided.

test('the summarize helper never selects name or email, even internally', function () {
    legacyFacebookAccount(['name' => 'Should Never Appear', 'email' => 'should-never-appear@example.com']);

    $summary = UnprovenSocialVerificationAudit::summarize();

    expect($summary['total'])->toBe(1);
    expect(json_encode($summary))
        ->not->toContain('Should Never Appear')
        ->not->toContain('should-never-appear@example.com');
});

test('the summarize helper reports zero with no breakdown when nothing matches', function () {
    $summary = UnprovenSocialVerificationAudit::summarize();

    expect($summary)->toBe(['total' => 0, 'earliest_created_at' => null, 'latest_created_at' => null, 'by_month' => []]);
});

test('the summarize helper groups matching accounts by creation month', function () {
    legacyFacebookAccount(['created_at' => '2026-01-15 10:00:00']);
    legacyFacebookAccount(['created_at' => '2026-01-20 10:00:00']);
    legacyFacebookAccount(['created_at' => '2026-03-01 10:00:00']);

    $summary = UnprovenSocialVerificationAudit::summarize();

    expect($summary['total'])->toBe(3);
    expect($summary['by_month'])->toBe(['2026-01' => 2, '2026-03' => 1]);
});

test('the --summary flag prints only aggregate counts, never a name or email', function () {
    legacyFacebookAccount(['name' => 'Should Never Appear', 'email' => 'should-never-appear@example.com']);

    $this->artisan('audit:unproven-social-accounts', ['--summary' => true])
        ->expectsOutputToContain('1 account(s) found')
        ->doesntExpectOutputToContain('Should Never Appear')
        ->doesntExpectOutputToContain('should-never-appear@example.com')
        ->assertSuccessful();
});

test('the --summary flag reports nothing found cleanly', function () {
    $this->artisan('audit:unproven-social-accounts', ['--summary' => true])
        ->expectsOutputToContain('No accounts found')
        ->assertSuccessful();
});
