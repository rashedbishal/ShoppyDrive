<?php

use App\Mail\VerifyEmailCodeMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::emailVerification());
});

test('sends a verification code by email', function () {
    Mail::fake();

    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->post(route('verification.send'))
        ->assertRedirect(route('home'));

    Mail::assertSent(VerifyEmailCodeMail::class, fn ($mail) => $mail->hasTo($user->email));
    expect($user->fresh()->email_verification_code)->not->toBeNull();
});

test('does not send a verification code if email is already verified', function () {
    Mail::fake();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('verification.send'))
        ->assertRedirect(route('home'));

    Mail::assertNothingSent();
});
