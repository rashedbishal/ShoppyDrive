<?php

declare(strict_types=1);

use App\Models\Admin;

test('a correct password logs the admin in normally, under the throttle', function () {
    $admin = Admin::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => bcrypt('correct-password')]);

    $this->post(route('admin.login.submit'), ['email' => $admin->email, 'password' => 'correct-password'])
        ->assertRedirect(route('admin.dashboard'));

    expect(auth('admin')->check())->toBeTrue();
});

test('the mother/super-admin is throttled exactly like any other admin', function () {
    $admin = createMotherAdmin(['name' => 'Owner', 'email' => 'owner@example.com', 'password' => bcrypt('correct-password')]);

    for ($i = 0; $i < 5; $i++) {
        $this->post(route('admin.login.submit'), ['email' => $admin->email, 'password' => 'wrong'])
            ->assertSessionHasErrors('email');
    }

    // The 6th attempt, even with the CORRECT password, is throttled before
    // credentials are even checked.
    $response = $this->post(route('admin.login.submit'), ['email' => $admin->email, 'password' => 'correct-password']);

    $response->assertStatus(429);
    expect(auth('admin')->check())->toBeFalse();
});

test('throttling is keyed per email+IP, not globally — a different admin account is unaffected', function () {
    $victim = Admin::create(['name' => 'Victim', 'email' => 'victim@example.com', 'password' => bcrypt('correct-password')]);
    $other = Admin::create(['name' => 'Other', 'email' => 'other@example.com', 'password' => bcrypt('correct-password')]);

    for ($i = 0; $i < 6; $i++) {
        $this->post(route('admin.login.submit'), ['email' => $victim->email, 'password' => 'wrong']);
    }

    // The attacker's attempts against $victim are now throttled...
    $this->post(route('admin.login.submit'), ['email' => $victim->email, 'password' => 'correct-password'])
        ->assertStatus(429);

    // ...but a different admin account, from the same IP, is untouched.
    $this->post(route('admin.login.submit'), ['email' => $other->email, 'password' => 'correct-password'])
        ->assertRedirect(route('admin.dashboard'));
});

test('a throttled admin can log in again once the one-minute window passes', function () {
    $admin = Admin::create(['name' => 'Admin', 'email' => 'admin2@example.com', 'password' => bcrypt('correct-password')]);

    for ($i = 0; $i < 6; $i++) {
        $this->post(route('admin.login.submit'), ['email' => $admin->email, 'password' => 'wrong']);
    }

    $this->post(route('admin.login.submit'), ['email' => $admin->email, 'password' => 'correct-password'])
        ->assertStatus(429);

    $this->travel(61)->seconds();

    $this->post(route('admin.login.submit'), ['email' => $admin->email, 'password' => 'correct-password'])
        ->assertRedirect(route('admin.dashboard'));
});
