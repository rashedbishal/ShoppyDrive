<?php

declare(strict_types=1);

use App\Models\Admin;
use Illuminate\Support\Facades\Log;

test('SEC-010: a failed admin login is logged, with no password in it', function () {
    Log::spy();
    $admin = createAdmin(['email' => 'logged-failure@example.com', 'password' => bcrypt('correct-password')]);

    $this->post(route('admin.login.submit'), ['email' => $admin->email, 'password' => 'super-secret-guess']);

    Log::shouldHaveReceived('warning')->once()->withArgs(function (string $message, array $context) {
        return str_contains($message, 'Failed login attempt')
            && $context['email'] === 'logged-failure@example.com'
            && ! str_contains(json_encode($context), 'super-secret-guess');
    });
});

test('a correct password logs the admin in normally, under the throttle', function () {
    $admin = createAdmin(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => bcrypt('correct-password')]);

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
    $victim = createAdmin(['name' => 'Victim', 'email' => 'victim@example.com', 'password' => bcrypt('correct-password')]);
    $other = createAdmin(['name' => 'Other', 'email' => 'other@example.com', 'password' => bcrypt('correct-password')]);

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

test('SEC-010: one admin account is throttled even when wrong attempts are spread across many different IPs', function () {
    $admin = createAdmin(['name' => 'Admin', 'email' => 'spread-attack@example.com', 'password' => bcrypt('correct-password')]);

    // Each IP only tries once — individually, neither the per-IP (20/min)
    // nor the per-email+IP (5/min) limit would ever trip. Only the
    // per-email, any-IP limit catches a slow attack spread this thin.
    for ($i = 0; $i < 10; $i++) {
        $this->withServerVariables(['REMOTE_ADDR' => "203.0.113.{$i}"])
            ->post(route('admin.login.submit'), ['email' => $admin->email, 'password' => 'wrong']);
    }

    $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.99'])
        ->post(route('admin.login.submit'), ['email' => $admin->email, 'password' => 'correct-password']);

    $response->assertStatus(429);
    expect(auth('admin')->check())->toBeFalse();
});

test('a throttled admin can log in again once the one-minute window passes', function () {
    $admin = createAdmin(['name' => 'Admin', 'email' => 'admin2@example.com', 'password' => bcrypt('correct-password')]);

    for ($i = 0; $i < 6; $i++) {
        $this->post(route('admin.login.submit'), ['email' => $admin->email, 'password' => 'wrong']);
    }

    $this->post(route('admin.login.submit'), ['email' => $admin->email, 'password' => 'correct-password'])
        ->assertStatus(429);

    $this->travel(61)->seconds();

    $this->post(route('admin.login.submit'), ['email' => $admin->email, 'password' => 'correct-password'])
        ->assertRedirect(route('admin.dashboard'));
});
