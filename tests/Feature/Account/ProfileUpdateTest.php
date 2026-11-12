<?php

declare(strict_types=1);

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

test('the profile form never changes the email address, whatever it is sent', function (bool $verified) {
    Mail::fake();
    $user = $verified
        ? User::factory()->create(['email' => 'original@example.com'])
        : User::factory()->unverified()->create(['email' => 'original@example.com']);

    $this->actingAs($user)->post(route('account.profile.update'), [
        'name' => 'New Name',
        'email' => 'someone-else@example.com',
    ])->assertSessionHasNoErrors();

    expect($user->refresh()->email)->toBe('original@example.com')
        ->and($user->name)->toBe('New Name')
        ->and(session('pending_email_change'))->toBeNull();
    Mail::assertNothingSent();
    Mail::assertNothingQueued();
})->with([true, false]);
