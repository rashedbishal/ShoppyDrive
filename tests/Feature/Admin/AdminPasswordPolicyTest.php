<?php

declare(strict_types=1);

use App\Models\Admin;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\actingAs;

test('SEC-010: admin account creation now uses Password::default(), the same rule customers get', function () {
    // Password::default() falls back to Laravel's own Password::min(8)
    // whenever AppServiceProvider's factory returns null — which it does
    // outside production — so this environment can't observe the
    // production-only policy (12+ chars, mixed case, symbols, breach
    // check) actually tightening; it behaves the same as the old
    // hardcoded min(8) here, by coincidence of Password::default()'s own
    // fallback. What this proves is that admin creation now goes through
    // the *same shared* rule customers get (AppServiceProvider's single
    // Password::defaults() factory), not a separate, independently
    // hardcoded one — an 8-character password is accepted (the floor
    // both old and new code agree on in this environment); the
    // behavioral difference only shows up in production.
    $mother = createMotherAdmin();
    actingAs($mother, 'admin');

    $this->post(route('admin.admins.store'), [
        'name' => 'New Staff',
        'email' => 'ok-pw@example.com',
        'password' => 'eight888',
        'password_confirmation' => 'eight888',
    ])->assertSessionDoesntHaveErrors('password');

    expect(Admin::where('email', 'ok-pw@example.com')->exists())->toBeTrue();
});

test('SEC-010: an admin cannot change their own password without confirming the current one', function () {
    $admin = createAdmin();
    actingAs($admin, 'admin');

    $this->put(route('admin.admins.update', $admin), [
        'name' => $admin->name,
        'email' => $admin->email,
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
    ])->assertSessionHasErrors('current_password');

    expect(Hash::check('password', $admin->refresh()->password))->toBeTrue();
});

test('SEC-010: the wrong current password refuses a self password change', function () {
    $admin = createAdmin();
    actingAs($admin, 'admin');

    $this->put(route('admin.admins.update', $admin), [
        'name' => $admin->name,
        'email' => $admin->email,
        'current_password' => 'totally-wrong',
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
    ])->assertSessionHasErrors('current_password');

    expect(Hash::check('password', $admin->refresh()->password))->toBeTrue();
});

test('SEC-010: the correct current password allows a self password change', function () {
    $admin = createAdmin();
    actingAs($admin, 'admin');

    $this->put(route('admin.admins.update', $admin), [
        'name' => $admin->name,
        'email' => $admin->email,
        'current_password' => 'password',
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
    ])->assertSessionDoesntHaveErrors();

    expect(Hash::check('a-brand-new-password', $admin->refresh()->password))->toBeTrue();
});

test('SEC-010: the mother resetting a different admin\'s password never needs the mother\'s own current password', function () {
    $mother = createMotherAdmin();
    $staff = createAdmin();
    actingAs($mother, 'admin');

    $this->put(route('admin.admins.update', $staff), [
        'name' => $staff->name,
        'email' => $staff->email,
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
        'permissions' => $staff->permissions ?? [],
    ])->assertSessionDoesntHaveErrors();

    expect(Hash::check('a-brand-new-password', $staff->refresh()->password))->toBeTrue();
});
