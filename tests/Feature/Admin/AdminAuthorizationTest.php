<?php

declare(strict_types=1);

use App\Models\Admin;

use function Pest\Laravel\actingAs;

function regularAdmin(array $attributes = []): Admin
{
    return Admin::create(array_merge([
        'name' => 'Staff Admin',
        'email' => 'staff-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
    ], $attributes));
}

// ── Creating admins ──────────────────────────────────────────────────────────

test('a regular admin cannot create another admin account', function () {
    $regular = regularAdmin();
    actingAs($regular, 'admin');

    $response = $this->post(route('admin.admins.store'), [
        'name' => 'New Guy', 'email' => 'newguy@example.com',
        'password' => 'password123', 'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(403);
    expect(Admin::where('email', 'newguy@example.com')->exists())->toBeFalse();
});

test('a regular admin cannot even view the create-admin page', function () {
    $regular = regularAdmin();
    actingAs($regular, 'admin');

    $this->get(route('admin.admins.create'))->assertStatus(403);
});

test('the mother admin can create a new admin account', function () {
    $mother = createMotherAdmin();
    actingAs($mother, 'admin');

    $response = $this->post(route('admin.admins.store'), [
        'name' => 'New Guy', 'email' => 'newguy@example.com',
        'password' => 'password123', 'password_confirmation' => 'password123',
    ]);

    $response->assertRedirect(route('admin.admins.index'));
    $created = Admin::where('email', 'newguy@example.com')->firstOrFail();
    expect($created->is_mother)->toBeFalse();
});

test('a client cannot smuggle is_mother into the create request to self-promote the new account', function () {
    $mother = createMotherAdmin();
    actingAs($mother, 'admin');

    $this->post(route('admin.admins.store'), [
        'name' => 'Sneaky', 'email' => 'sneaky@example.com',
        'password' => 'password123', 'password_confirmation' => 'password123',
        'is_mother' => true,
    ]);

    $created = Admin::where('email', 'sneaky@example.com')->firstOrFail();
    expect($created->is_mother)->toBeFalse();
});

// ── Editing admins ───────────────────────────────────────────────────────────

test('a regular admin cannot edit another regular admins account', function () {
    $regular = regularAdmin();
    $other = regularAdmin(['name' => 'Other Staff', 'email' => 'other@example.com']);
    actingAs($regular, 'admin');

    $response = $this->put(route('admin.admins.update', $other), [
        'name' => 'Hijacked', 'email' => 'other@example.com',
    ]);

    $response->assertStatus(403);
    expect($other->refresh()->name)->toBe('Other Staff');
});

test('a regular admin cannot view another admins edit page', function () {
    $regular = regularAdmin();
    $other = regularAdmin(['email' => 'other2@example.com']);
    actingAs($regular, 'admin');

    $this->get(route('admin.admins.edit', $other))->assertStatus(403);
});

test('a regular admin can edit their own account', function () {
    $regular = regularAdmin(['email' => 'self@example.com']);
    actingAs($regular, 'admin');

    $response = $this->put(route('admin.admins.update', $regular), [
        'name' => 'Updated Me', 'email' => 'self@example.com',
    ]);

    $response->assertRedirect(route('admin.admins.index'));
    expect($regular->refresh()->name)->toBe('Updated Me');
});

test('a client cannot smuggle is_mother into their own self-edit request to self-promote', function () {
    $regular = regularAdmin(['email' => 'selfpromote@example.com']);
    actingAs($regular, 'admin');

    $this->put(route('admin.admins.update', $regular), [
        'name' => $regular->name, 'email' => 'selfpromote@example.com',
        'is_mother' => true,
    ]);

    expect($regular->refresh()->is_mother)->toBeFalse();
});

test('a regular admin cannot edit the mother admins account', function () {
    $regular = regularAdmin();
    $mother = createMotherAdmin(['email' => 'mother@example.com']);
    actingAs($regular, 'admin');

    $response = $this->put(route('admin.admins.update', $mother), [
        'name' => 'Hijacked Mother', 'email' => 'mother@example.com',
    ]);

    $response->assertStatus(403);
    expect($mother->refresh()->name)->not->toBe('Hijacked Mother');
});

test('the mother admin can edit another regular admins account', function () {
    $mother = createMotherAdmin();
    $regular = regularAdmin(['email' => 'managed@example.com']);
    actingAs($mother, 'admin');

    $response = $this->put(route('admin.admins.update', $regular), [
        'name' => 'Renamed By Mother', 'email' => 'managed@example.com',
    ]);

    $response->assertRedirect(route('admin.admins.index'));
    expect($regular->refresh()->name)->toBe('Renamed By Mother');
});

test('the mother admin can edit her own account', function () {
    $mother = createMotherAdmin(['email' => 'mother-self@example.com']);
    actingAs($mother, 'admin');

    $response = $this->put(route('admin.admins.update', $mother), [
        'name' => 'Mother Renamed', 'email' => 'mother-self@example.com',
    ]);

    $response->assertRedirect(route('admin.admins.index'));
    expect($mother->refresh()->name)->toBe('Mother Renamed');
});

// ── Deleting admins ──────────────────────────────────────────────────────────

test('a regular admin cannot delete another regular admins account', function () {
    $regular = regularAdmin();
    $other = regularAdmin(['email' => 'todelete@example.com']);
    actingAs($regular, 'admin');

    $response = $this->delete(route('admin.admins.destroy', $other));

    $response->assertRedirect();
    $response->assertSessionHas('error');
    expect(Admin::find($other->id))->not->toBeNull();
});

test('the mother admin can delete another regular admins account', function () {
    $mother = createMotherAdmin();
    $other = regularAdmin(['email' => 'todelete2@example.com']);
    actingAs($mother, 'admin');

    $this->delete(route('admin.admins.destroy', $other))
        ->assertRedirect(route('admin.admins.index'));

    expect(Admin::find($other->id))->toBeNull();
});

test('nobody, including the mother admin, can delete the mother admin account', function () {
    $mother = createMotherAdmin();
    $other = regularAdmin();

    actingAs($other, 'admin');
    $this->delete(route('admin.admins.destroy', $mother))->assertSessionHas('error');
    expect(Admin::find($mother->id))->not->toBeNull();
});

test('an admin cannot delete their own account', function () {
    $mother = createMotherAdmin();
    actingAs($mother, 'admin');

    $this->delete(route('admin.admins.destroy', $mother))->assertSessionHas('error');
    expect(Admin::find($mother->id))->not->toBeNull();
});

// ── Sanity: normal operations still work ─────────────────────────────────────

test('the mother admin can still view the admins index', function () {
    actingAs(createMotherAdmin(), 'admin');
    $this->get(route('admin.admins.index'))->assertOk();
});

test('a regular admin can still view the admins index', function () {
    // A fresh request cycle per admin — SEC-005's AuthenticateAdminSession
    // fingerprints the session to one specific admin's password hash;
    // switching which admin actingAs() authenticates as mid-test (sharing
    // one session) isn't something a real login/logout cycle would ever
    // do, since logout always invalidates the session first.
    actingAs(regularAdmin(), 'admin');
    $this->get(route('admin.admins.index'))->assertOk();
});
