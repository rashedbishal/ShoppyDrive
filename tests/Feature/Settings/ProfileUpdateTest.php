<?php

use App\Mail\VerifyEmailCodeMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('account.profile'));

    $response->assertOk();
});

test('profile information can be updated', function () {
    // SEC-009: an unverified address (e.g. a registration typo) can still be
    // changed directly; a verified one needs a code (EmailChangeConfirmationTest).
    $user = User::factory()->unverified()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('account.profile.update'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('account.profile'));

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('SEC-003: a verification code already sent to the old email cannot verify the new one', function () {
    Mail::fake();

    $user = User::factory()->unverified()->create(['email' => 'attacker@example.com']);
    $user->sendEmailVerificationNotification();

    $capturedCode = null;
    Mail::assertSent(VerifyEmailCodeMail::class, function ($mail) use (&$capturedCode) {
        $capturedCode = $mail->code;

        return true;
    });

    // Changing the email must invalidate that still-unused code — without
    // this, submitting it below would mark "victim@example.com" verified
    // even though nothing was ever sent there.
    $this->actingAs($user)->post(route('account.profile.update'), [
        'name' => $user->name,
        'email' => 'victim@example.com',
    ])->assertSessionHasNoErrors();

    $this->actingAs($user)->post(route('verification.verify-code'), [
        'code' => $capturedCode,
    ])->assertSessionHasErrors('code');

    expect($user->refresh()->email)->toBe('victim@example.com')
        ->and($user->hasVerifiedEmail())->toBeFalse();
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('account.profile.update'), [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('account.profile'));

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete(route('account.profile.destroy'), [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertGuest();
    expect($user->fresh())->toBeNull();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('account.profile'))
        ->delete(route('account.profile.destroy'), [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect(route('account.profile'));

    expect($user->fresh())->not->toBeNull();
});

/*
 * OPS-006: the reminder email's unsubscribe link only turns cart reminders
 * off; Settings › Profile shows the setting and turns it off or back on.
 */
test('the profile page shows whether cart reminder emails are on', function () {
    $user = User::factory()->create(['abandoned_cart_reminders_enabled' => false]);

    $response = $this->actingAs($user)->get(route('account.profile'));

    $response->assertOk()->assertInertia(fn ($page) => $page->where('cartRemindersEnabled', false));
});

test('a customer can turn cart reminder emails back on after unsubscribing, and off again', function () {
    $user = User::factory()->create(['abandoned_cart_reminders_enabled' => false]);

    $this->actingAs($user)
        ->patch(route('account.profile.cart-reminders.update'), ['enabled' => true])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('account.profile'));

    expect($user->refresh()->abandoned_cart_reminders_enabled)->toBeTrue();

    $this->actingAs($user)
        ->patch(route('account.profile.cart-reminders.update'), ['enabled' => false])
        ->assertRedirect(route('account.profile'));

    expect($user->refresh()->abandoned_cart_reminders_enabled)->toBeFalse();
});

test('the cart reminder setting only changes for the signed-in customer and needs a yes/no value', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('account.profile.cart-reminders.update'), ['enabled' => 'maybe'])
        ->assertSessionHasErrors('enabled');
    $this->actingAs($user)->patch(route('account.profile.cart-reminders.update'), ['enabled' => false]);

    expect($user->refresh()->abandoned_cart_reminders_enabled)->toBeFalse()
        ->and($otherUser->refresh()->abandoned_cart_reminders_enabled)->toBeTrue();
});

test('a guest cannot change cart reminder settings', function () {
    $this->patch(route('account.profile.cart-reminders.update'), ['enabled' => false])
        ->assertRedirect(route('login'));
});
