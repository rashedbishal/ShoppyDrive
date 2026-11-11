<?php

declare(strict_types=1);

/*
 * The customer's profile, email change, cart-reminder switch, delete account
 * and connected accounts all live under /account now. The starter-kit
 * /settings pages (which nothing linked to) only redirect there.
 */

use App\Models\User;

test('the old settings addresses send customers to the matching Account page', function (string $old, string $new) {
    $this->get($old)->assertStatus(301)->assertRedirect($new);
})->with([
    ['/settings', '/account/profile'],
    ['/settings/profile', '/account/profile'],
    ['/settings/connections', '/account/connections'],
    ['/settings/security', '/account/password'],
    ['/settings/appearance', '/account/profile'],
]);

test('the old settings form endpoints are gone', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch('/settings/profile', ['name' => 'X', 'email' => $user->email])->assertStatus(405);
    $this->actingAs($user)->put('/settings/password', [])->assertNotFound();
    $this->actingAs($user)->delete('/settings/profile', ['password' => 'password'])->assertStatus(405);
});

test('a guest is sent to the login page for the profile and connections pages', function (string $route) {
    $this->get(route($route))->assertRedirect(route('login'));
})->with(['account.profile', 'account.connections']);

test('an unverified customer can still open the profile and connected accounts pages, but not the rest of Account', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get(route('account.profile'))->assertOk();
    $this->actingAs($user)->get(route('account.connections'))->assertOk();
    $this->actingAs($user)->get(route('account.orders'))->assertRedirect(route('verification.notice'));
    $this->actingAs($user)->get(route('account.password'))->assertRedirect(route('verification.notice'));
});

test('an unverified customer can correct a mistyped email straight away, and must verify the new one', function () {
    $user = User::factory()->unverified()->create(['email' => 'typo@exmaple.com']);

    $this->actingAs($user)->post(route('account.profile.update'), [
        'name' => $user->name,
        'email' => 'right@example.com',
    ])->assertSessionHasNoErrors()->assertRedirect(route('account.profile'));

    expect($user->refresh()->email)->toBe('right@example.com')
        ->and($user->email_verified_at)->toBeNull();
});

test('deleting the account still needs a verified email', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->delete(route('account.profile.destroy'), ['password' => 'password'])
        ->assertRedirect(route('verification.notice'));

    expect(User::find($user->id))->not->toBeNull();
});

test('the profile page gives the form what the email change, reminders and delete sections need', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('account.profile'))->assertInertia(fn ($page) => $page
        ->component('account/Profile')
        ->has('mustVerifyEmail')
        ->where('pendingEmailChange', null)
        ->where('cartRemindersEnabled', true));
});
