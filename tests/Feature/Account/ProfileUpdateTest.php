<?php

declare(strict_types=1);

use App\Mail\EmailChangeCodeMail;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

test('a customer can update their name and phone', function () {
    $user = User::factory()->create(['name' => 'Old Name', 'phone' => '01700000000']);

    $response = $this->actingAs($user)->post(route('account.profile.update'), [
        'name' => 'New Name',
        'email' => $user->email,
        'phone' => '01812345678',
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $user->refresh();
    expect($user->name)->toBe('New Name');
    expect($user->phone)->toBe('01812345678');
});

test('updating the profile fails validation when the name is missing', function () {
    $user = User::factory()->create(['name' => 'Old Name']);

    $response = $this->actingAs($user)->post(route('account.profile.update'), [
        'name' => '',
        'email' => $user->email,
    ]);

    $response->assertSessionHasErrors('name');
    expect($user->refresh()->name)->toBe('Old Name');
});

test('a verified customer\'s different email is not written until the code sent to the current address is entered', function () {
    $user = User::factory()->create(['email' => 'original@example.com']);

    $response = $this->actingAs($user)->post(route('account.profile.update'), [
        'name' => $user->name,
        'email' => 'someone-else@example.com',
    ]);

    $response->assertSessionHasNoErrors();
    expect($user->refresh()->email)->toBe('original@example.com');
});

test('a customer can upload a new avatar, which replaces and deletes the old file', function () {
    Storage::fake('public');
    $user = User::factory()->create(['avatar' => '/storage/avatars/old-avatar.jpg']);
    Storage::disk('public')->put('avatars/old-avatar.jpg', 'old-contents');

    $response = $this->actingAs($user)->post(route('account.profile.update'), [
        'name' => $user->name,
        'email' => $user->email,
        'avatar' => UploadedFile::fake()->image('new-avatar.jpg'),
    ]);

    $response->assertSessionHasNoErrors();

    $user->refresh();
    expect($user->avatar)->not->toBeNull();
    expect($user->avatar)->not->toBe('/storage/avatars/old-avatar.jpg');
    expect(str_starts_with($user->avatar, '/storage/avatars/'))->toBeTrue();
    Storage::disk('public')->assertMissing('avatars/old-avatar.jpg');
});

test('a customer can remove their avatar, deleting the stored file', function () {
    Storage::fake('public');
    $user = User::factory()->create(['avatar' => '/storage/avatars/to-remove.jpg']);
    Storage::disk('public')->put('avatars/to-remove.jpg', 'contents');

    $response = $this->actingAs($user)->post(route('account.profile.update'), [
        'name' => $user->name,
        'email' => $user->email,
        'remove_avatar' => true,
    ]);

    $response->assertSessionHasNoErrors();

    expect($user->refresh()->avatar)->toBeNull();
    Storage::disk('public')->assertMissing('avatars/to-remove.jpg');
});

test('uploading a non-image avatar is rejected', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('account.profile.update'), [
        'name' => $user->name,
        'email' => $user->email,
        'avatar' => UploadedFile::fake()->create('not-an-image.txt', 10, 'text/plain'),
    ]);

    $response->assertSessionHasErrors('avatar');
    expect($user->refresh()->avatar)->toBeNull();
});

/** Connects the account to Google or Facebook, as social login does. */
function connectSocialLogin(User $user, string $provider = 'google'): void
{
    SocialAccount::create(['user_id' => $user->id, 'provider' => $provider, 'provider_id' => 'social-'.$user->id]);
}

test('an account connected to Google or Facebook cannot change its email', function (bool $verified, string $provider) {
    Mail::fake();
    $user = $verified
        ? User::factory()->create(['email' => 'original@example.com'])
        : User::factory()->unverified()->create(['email' => 'original@example.com']);
    connectSocialLogin($user, $provider);

    $this->actingAs($user)->post(route('account.profile.update'), [
        'name' => 'New Name',
        'email' => 'someone-else@example.com',
    ])->assertSessionHasErrors(['email' => "Your email comes from your Google or Facebook sign-in, so it can't be changed here."]);

    expect($user->refresh()->email)->toBe('original@example.com')
        ->and(session('pending_email_change'))->toBeNull();
    Mail::assertNothingSent();
    Mail::assertNothingQueued();
})->with([
    'verified, Google' => [true, 'google'],
    'unverified, Google' => [false, 'google'],
    'verified, Facebook' => [true, 'facebook'],
]);

test('a connected account can still save the rest of its profile', function (?string $email) {
    $user = User::factory()->create(['email' => 'original@example.com']);
    connectSocialLogin($user);

    $this->actingAs($user)->post(route('account.profile.update'), array_filter([
        'name' => 'New Name',
        'email' => $email,
    ], fn ($value) => $value !== null))->assertSessionHasNoErrors();

    expect($user->refresh()->name)->toBe('New Name')
        ->and($user->email)->toBe('original@example.com');
})->with([
    'sending its current email' => ['original@example.com'],
    'the same email in other capitals' => ['Original@Example.com'],
    'no email at all' => [null],
]);

test('an account without social login can change its email with a code', function () {
    Mail::fake();
    $user = User::factory()->create(['email' => 'original@example.com']);

    $this->actingAs($user)->post(route('account.profile.update'), [
        'name' => $user->name,
        'email' => 'new@example.com',
    ])->assertSessionHasNoErrors();

    // Not changed yet: it waits for the code sent to the current address.
    expect($user->refresh()->email)->toBe('original@example.com')
        ->and(session('pending_email_change.email'))->toBe('new@example.com');
    Mail::assertSent(EmailChangeCodeMail::class);
});

test('connecting Google while an email change is pending stops the change', function () {
    Mail::fake();
    $user = User::factory()->create(['email' => 'original@example.com']);

    $this->actingAs($user)->post(route('account.profile.update'), ['name' => $user->name, 'email' => 'new@example.com']);
    $code = null;
    Mail::assertSent(EmailChangeCodeMail::class, function (EmailChangeCodeMail $mail) use (&$code): bool {
        $code = $mail->code;

        return true;
    });

    connectSocialLogin($user);

    $this->post(route('account.profile.email.confirm'), ['code' => $code])
        ->assertSessionHasErrors('code');

    expect($user->refresh()->email)->toBe('original@example.com')
        ->and(session('pending_email_change'))->toBeNull();
});

test('the profile page tells the form whether the email is locked', function () {
    $free = User::factory()->create();
    $connected = User::factory()->create();
    connectSocialLogin($connected, 'facebook');

    $this->actingAs($free)->get(route('account.profile'))
        ->assertInertia(fn ($page) => $page->where('emailLocked', false));
    $this->actingAs($connected)->get(route('account.profile'))
        ->assertInertia(fn ($page) => $page->where('emailLocked', true));
});
