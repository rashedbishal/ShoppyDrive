<?php

declare(strict_types=1);

use App\Http\Middleware\AuthenticateAdminSession;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\actingAs;

test('SEC-005: a session stays valid across ordinary requests for the same admin', function () {
    $admin = createAdmin();
    actingAs($admin, 'admin');

    $this->get(route('admin.dashboard'))->assertOk();
    // Second request re-checks the now-stored fingerprint against the
    // (unchanged) password — must still pass, not false-positive.
    $this->get(route('admin.dashboard'))->assertOk();
});

test('SEC-005: a session is signed out the moment that admin\'s password changes elsewhere', function () {
    $admin = createAdmin();
    actingAs($admin, 'admin');

    // Establishes this session's fingerprint against the current password.
    $this->get(route('admin.dashboard'))->assertOk();

    // Simulates the password changing on another device/session entirely —
    // not through this session, and not through AdminController::update()
    // (which would otherwise resync *this* session's own fingerprint if it
    // were the one making the change).
    $admin->forceFill(['password' => Hash::make('a-brand-new-password')])->save();

    $response = $this->get(route('admin.dashboard'));

    $response->assertRedirect(route('admin.login'));
    $response->assertSessionHas('error');
    $this->assertGuest('admin');
});

test('SEC-005: changing your own password resyncs this session\'s own fingerprint, instead of also signing it out', function () {
    // actingAs() pins one fixed in-memory Admin instance as the resolved
    // user for every request in this test, including the one after the
    // PUT below — a real browser's session re-resolves the admin from the
    // database on every request, so it would see the new password hash
    // immediately. actingAs() would not, making a real follow-up request
    // here fail for a reason that isn't a bug (a stale in-memory object),
    // not the thing this test exists to verify. Checking that the session
    // itself was resynced to the *new* hash — the actual, controller-side
    // behavior in question — avoids that gap entirely.
    $admin = createAdmin();
    actingAs($admin, 'admin');
    $this->get(route('admin.dashboard'))->assertOk();

    $this->put(route('admin.admins.update', $admin), [
        'name' => $admin->name,
        'email' => $admin->email,
        'current_password' => 'password', // createAdmin()'s default
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
    ])->assertRedirect(route('admin.admins.index'));

    $stored = session(AuthenticateAdminSession::SESSION_KEY);
    expect($stored['id'])->toBe($admin->id);
    expect(Hash::check('a-brand-new-password', $stored['hash']))->toBeTrue();
});

test('SEC-005: switching which admin a session authenticates as is treated as a new identity, not a stolen one', function () {
    // Mirrors exactly what actingAs() does between two calls — and the one
    // real path Auth::login() doesn't itself guard against: logging in as
    // a different account on a browser that was already authenticated,
    // without an explicit logout first.
    $first = createAdmin();
    actingAs($first, 'admin');
    $this->get(route('admin.dashboard'))->assertOk();

    $second = createAdmin();
    actingAs($second, 'admin');

    $this->get(route('admin.dashboard'))->assertOk();
    $this->assertAuthenticatedAs($second, 'admin');
});

test('the session fingerprint is keyed by admin id, not just the password hash', function () {
    $admin = createAdmin(['password' => Hash::make('shared-password')]);
    actingAs($admin, 'admin');
    $this->get(route('admin.dashboard'));

    $other = createAdmin(['password' => Hash::make('shared-password')]);
    actingAs($other, 'admin');

    // Same hash as $admin's, but a different admin id — must not be
    // mistaken for the same account's unchanged password.
    $this->get(route('admin.dashboard'))->assertOk();
    $this->assertAuthenticatedAs($other, 'admin');
});
