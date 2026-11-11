<?php

declare(strict_types=1);

/*
 * SEC-009: someone using a logged-in session could change a verified
 * account's email without the password, then reset the password and lock
 * the owner out. The change now needs a code sent to the current address.
 */

use App\Mail\EmailChangeCodeMail;
use App\Models\User;
use App\Services\EmailChangeConfirmation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(fn () => Mail::fake());

/** Starts a change and returns the code that was mailed to the current address. */
function requestEmailChange(User $user, string $newEmail): string
{
    test()->actingAs($user)->post(route('account.profile.update'), ['name' => 'New Name', 'email' => $newEmail])
        ->assertRedirect(route('account.profile'));

    $code = null;
    Mail::assertSent(EmailChangeCodeMail::class, function (EmailChangeCodeMail $mail) use ($user, &$code) {
        $code = $mail->code;

        return $mail->hasTo($user->email);
    });

    return (string) $code;
}

test('saving a new email for a verified account changes nothing until the code is entered', function () {
    $user = User::factory()->create(['email' => 'rahima@example.com']);

    requestEmailChange($user, 'attacker@example.com');

    expect($user->refresh()->email)->toBe('rahima@example.com')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->name)->toBe('New Name');

    $this->actingAs($user)->get(route('account.profile'))->assertInertia(fn (Assert $page) => $page
        ->where('pendingEmailChange.new_email', 'attacker@example.com')
        ->where('pendingEmailChange.sent_to', 'rahima@example.com'));
});

test('the code goes to the current address and hides most of the new one', function () {
    $user = User::factory()->create(['email' => 'rahima@example.com']);

    requestEmailChange($user, 'attacker@example.com');

    Mail::assertSent(EmailChangeCodeMail::class, fn (EmailChangeCodeMail $mail) => $mail->hasTo('rahima@example.com')
        && ! $mail->hasTo('attacker@example.com')
        && $mail->maskedNewEmail === 'a***@example.com');
});

test('the right code changes the email and the new address must then be verified', function () {
    $user = User::factory()->create(['email' => 'rahima@example.com']);
    $code = requestEmailChange($user, 'rahima.new@example.com');

    $this->actingAs($user)->post(route('account.profile.email.confirm'), ['code' => $code])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('account.profile'));

    expect($user->refresh()->email)->toBe('rahima.new@example.com')
        ->and($user->email_verified_at)->toBeNull()
        ->and(session(EmailChangeConfirmation::SESSION_KEY))->toBeNull();
});

test('a wrong code is refused, and five wrong codes cancel the change', function () {
    $user = User::factory()->create(['email' => 'rahima@example.com']);
    requestEmailChange($user, 'attacker@example.com');

    foreach (range(1, 4) as $ignored) {
        $this->actingAs($user)->post(route('account.profile.email.confirm'), ['code' => '000000'])
            ->assertSessionHasErrors(['code' => 'That code is not correct.']);
    }
    $this->actingAs($user)->post(route('account.profile.email.confirm'), ['code' => '000000'])
        ->assertSessionHasErrors(['code' => 'Too many wrong codes. Please enter the new email again to get a new code.']);

    expect($user->refresh()->email)->toBe('rahima@example.com')
        ->and(session(EmailChangeConfirmation::SESSION_KEY))->toBeNull();
});

test('an expired code is refused', function () {
    $user = User::factory()->create(['email' => 'rahima@example.com']);
    $code = requestEmailChange($user, 'attacker@example.com');

    $this->travel(EmailChangeConfirmation::CODE_TTL_MINUTES + 1)->minutes();

    $this->actingAs($user)->post(route('account.profile.email.confirm'), ['code' => $code])->assertSessionHasErrors('code');
    expect($user->refresh()->email)->toBe('rahima@example.com');
});

test('cancelling discards the pending change', function () {
    $user = User::factory()->create(['email' => 'rahima@example.com']);
    $code = requestEmailChange($user, 'attacker@example.com');

    $this->actingAs($user)->delete(route('account.profile.email.cancel'))->assertRedirect(route('account.profile'));
    $this->actingAs($user)->post(route('account.profile.email.confirm'), ['code' => $code])->assertSessionHasErrors('code');

    expect($user->refresh()->email)->toBe('rahima@example.com');
});

test('pressing Save again within a minute does not send a second code', function () {
    $user = User::factory()->create(['email' => 'rahima@example.com']);

    requestEmailChange($user, 'attacker@example.com');
    $this->actingAs($user)->post(route('account.profile.update'), ['name' => 'New Name', 'email' => 'attacker@example.com']);

    Mail::assertSentCount(1);
});

test('an address taken by another account while the code was pending is refused', function () {
    $user = User::factory()->create(['email' => 'rahima@example.com']);
    $code = requestEmailChange($user, 'taken@example.com');
    User::factory()->create(['email' => 'taken@example.com']);

    $this->actingAs($user)->post(route('account.profile.email.confirm'), ['code' => $code])->assertSessionHasErrors('code');
    expect($user->refresh()->email)->toBe('rahima@example.com');
});

test('saving the profile without changing the email sends no code', function () {
    $user = User::factory()->create(['email' => 'rahima@example.com']);

    $this->actingAs($user)->post(route('account.profile.update'), ['name' => 'Renamed', 'email' => 'rahima@example.com'])->assertSessionHasNoErrors();

    Mail::assertNothingSent();
    expect($user->refresh()->name)->toBe('Renamed');
});
