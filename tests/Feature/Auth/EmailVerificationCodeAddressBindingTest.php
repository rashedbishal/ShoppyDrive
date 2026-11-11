<?php

use App\Mail\VerifyEmailCodeMail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

function sendVerificationCodeTo(User $user): string
{
    Mail::fake();

    $user->sendEmailVerificationNotification();

    $capturedCode = null;

    Mail::assertSent(VerifyEmailCodeMail::class, function (VerifyEmailCodeMail $mail) use (&$capturedCode, $user): bool {
        if ($mail->hasTo($user->email)) {
            $capturedCode = $mail->code;
        }

        return true;
    });

    return $capturedCode;
}

test('SEC-003: the profile API cannot swap in another email while an old code is still inside the resend cooldown', function () {
    $user = User::factory()->unverified()->create(['email' => 'attacker@example.com']);
    $codeSentToAttacker = sendVerificationCodeTo($user);

    $this->actingAs($user)->putJson('/user/profile-information', [
        'name' => $user->name,
        'email' => 'victim@example.com',
    ]);

    $this->actingAs($user)->post(route('verification.verify-code'), ['code' => $codeSentToAttacker]);

    expect($user->refresh()->email === 'victim@example.com' && $user->hasVerifiedEmail())->toBeFalse();
});

test('SEC-009: a verified account cannot change its email through the profile API without the current-address code', function () {
    $user = User::factory()->create(['email' => 'owner@example.com']);

    $this->actingAs($user)->putJson('/user/profile-information', [
        'name' => $user->name,
        'email' => 'hijacker@example.com',
    ]);

    expect($user->refresh()->email)->toBe('owner@example.com');
});

test('SEC-003: a code stops working as soon as the address on the row differs, however the row was changed', function () {
    $user = User::factory()->unverified()->create(['email' => 'attacker@example.com']);
    $codeSentToAttacker = sendVerificationCodeTo($user);

    DB::table('users')->where('id', $user->id)->update(['email' => 'victim@example.com']);

    $this->actingAs($user->refresh())
        ->post(route('verification.verify-code'), ['code' => $codeSentToAttacker])
        ->assertSessionHasErrors('code');

    expect($user->refresh()->hasVerifiedEmail())->toBeFalse()
        ->and($user->email_verification_code)->not->toBeNull();
});

test('SEC-003: a code issued to one account cannot verify a different account', function () {
    $owner = User::factory()->unverified()->create(['email' => 'owner@example.com']);
    $codeSentToOwner = sendVerificationCodeTo($owner);

    $other = User::factory()->unverified()->create(['email' => 'other@example.com']);
    sendVerificationCodeTo($other);

    $this->actingAs($other)
        ->post(route('verification.verify-code'), ['code' => $codeSentToOwner])
        ->assertSessionHasErrors('code');

    expect($other->refresh()->hasVerifiedEmail())->toBeFalse();
});

test('SEC-003: the verification step re-reads the row, so a request that loaded the old address cannot verify the new one', function () {
    $user = User::factory()->unverified()->create(['email' => 'attacker@example.com']);
    $codeSentToAttacker = sendVerificationCodeTo($user);

    $requestUser = User::findOrFail($user->id);
    $user->changeEmailTo('victim@example.com');

    $this->actingAs($requestUser)
        ->post(route('verification.verify-code'), ['code' => $codeSentToAttacker])
        ->assertSessionHasErrors('code');

    expect($user->refresh()->hasVerifiedEmail())->toBeFalse();
});

test('SEC-003: an email change clears a verification that landed after the row was loaded', function () {
    $user = User::factory()->unverified()->create(['email' => 'attacker@example.com']);

    $staleUser = User::findOrFail($user->id);
    DB::table('users')->where('id', $user->id)->update(['email_verified_at' => now()]);

    $staleUser->changeEmailTo('victim@example.com');

    expect($user->refresh()->email)->toBe('victim@example.com')
        ->and($user->hasVerifiedEmail())->toBeFalse();
});

test('after an email change, a code mailed to the new address verifies it', function () {
    $user = User::factory()->unverified()->create(['email' => 'typo@example.com']);
    sendVerificationCodeTo($user);

    $this->actingAs($user)->post(route('account.profile.update'), [
        'name' => $user->name,
        'email' => 'correct@example.com',
    ])->assertSessionHasNoErrors();

    $codeSentToNewAddress = sendVerificationCodeTo($user->refresh());

    $this->actingAs($user)
        ->post(route('verification.verify-code'), ['code' => $codeSentToNewAddress])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->email)->toBe('correct@example.com')
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->email_verification_code)->toBeNull();
});
