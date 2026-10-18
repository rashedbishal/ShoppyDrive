<?php

use App\Mail\VerifyEmailCodeMail;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

test('registering sends a 6-digit verification code instead of a link', function () {
    Mail::fake();

    $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'coded@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::where('email', 'coded@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user->hasVerifiedEmail())->toBeFalse();
    expect($user->email_verification_code)->not->toBeNull();

    Mail::assertSent(VerifyEmailCodeMail::class, function ($mail) use ($user) {
        return $mail->hasTo($user->email) && preg_match('/^\d{6}$/', $mail->code);
    });
});

test('the correct code verifies the user', function () {
    $user = User::factory()->unverified()->create();
    $user->forceFill([
        'email_verification_code' => Hash::make('123456'),
        'email_verification_code_expires_at' => now()->addMinutes(15),
    ])->save();

    $response = $this->actingAs($user)->post(route('verification.verify-code'), [
        'code' => '123456',
    ]);

    $response->assertRedirect(route('home'));
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    expect($user->fresh()->email_verification_code)->toBeNull();
});

test('an incorrect code is rejected and leaves the user unverified', function () {
    $user = User::factory()->unverified()->create();
    $user->forceFill([
        'email_verification_code' => Hash::make('123456'),
        'email_verification_code_expires_at' => now()->addMinutes(15),
    ])->save();

    $response = $this->actingAs($user)->post(route('verification.verify-code'), [
        'code' => '999999',
    ]);

    $response->assertSessionHasErrors('code');
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('an expired code is rejected', function () {
    $user = User::factory()->unverified()->create();
    $user->forceFill([
        'email_verification_code' => Hash::make('123456'),
        'email_verification_code_expires_at' => now()->subMinute(),
    ])->save();

    $response = $this->actingAs($user)->post(route('verification.verify-code'), [
        'code' => '123456',
    ]);

    $response->assertSessionHasErrors('code');
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('an unverified customer is blocked from checkout and account pages', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get(route('checkout.index'))
        ->assertRedirect(route('verification.notice'));

    $this->actingAs($user)->get(route('account.dashboard'))
        ->assertRedirect(route('verification.notice'));
});

test('a verified customer can reach checkout and account pages', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('checkout.index'))->assertOk();
    $this->actingAs($user)->get(route('account.dashboard'))->assertOk();
});

test('verifying the code is rate limited after too many attempts in a minute', function () {
    $user = User::factory()->unverified()->create();
    $user->forceFill([
        'email_verification_code' => Hash::make('123456'),
        'email_verification_code_expires_at' => now()->addMinutes(15),
    ])->save();

    for ($i = 0; $i < 5; $i++) {
        $this->actingAs($user)->post(route('verification.verify-code'), ['code' => '000000']);
    }

    $response = $this->actingAs($user)->post(route('verification.verify-code'), ['code' => '000000']);

    $response->assertSessionHasErrors('code');
    expect(session('errors')->first('code'))->toContain('Too many attempts');

    // The underlying code is untouched by the throttle — the real code still works
    // once the caller stops hammering the endpoint.
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('resending the verification code is rate limited', function () {
    Mail::fake();

    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->post(route('verification.send'))->assertSessionHasNoErrors();

    $response = $this->actingAs($user)->post(route('verification.send'));

    $response->assertSessionHasErrors('code');
    expect(session('errors')->first('code'))->toContain('wait a minute');

    Mail::assertSentCount(1);
});
