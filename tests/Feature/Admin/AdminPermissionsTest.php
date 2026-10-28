<?php

declare(strict_types=1);

use App\Models\Admin;
use App\Models\BlockedIp;
use App\Models\Category;
use App\Support\AdminSections;
use App\Support\SecurityRateLimits;

use function Pest\Laravel\actingAs;

// ── AdminSections::sanitize() ────────────────────────────────────────────────

test('sanitize drops unknown sections', function () {
    expect(AdminSections::sanitize(['bogus_section' => ['view']]))->toBe([]);
});

test('sanitize drops abilities a section does not support', function () {
    // "returns" only supports update (no create/delete) per AdminSections.
    expect(AdminSections::sanitize(['returns' => ['view', 'create', 'delete']]))
        ->toBe(['returns' => ['view']]);
});

test('sanitize implies view the moment any ability is granted', function () {
    expect(AdminSections::sanitize(['products' => ['create']]))
        ->toBe(['products' => ['view', 'create']]);
});

test('sanitize drops a section entirely when nothing survives', function () {
    expect(AdminSections::sanitize(['returns' => ['delete']]))->toBe([]);
});

// ── Creating/editing admins with permissions ─────────────────────────────────

test('the mother admin can create an admin with specific section permissions', function () {
    $mother = createMotherAdmin();
    actingAs($mother, 'admin');

    $this->post(route('admin.admins.store'), [
        'name' => 'Staff', 'email' => 'staff@example.com',
        'password' => 'password123', 'password_confirmation' => 'password123',
        'permissions' => ['products' => ['view', 'create'], 'orders' => ['view']],
    ])->assertRedirect(route('admin.admins.index'));

    $created = Admin::where('email', 'staff@example.com')->firstOrFail();
    expect($created->permissions)->toBe([
        'products' => ['view', 'create'],
        'orders' => ['view'],
    ]);
});

test('a non-mother admin cannot grant themselves permissions through self-edit', function () {
    $admin = Admin::create([
        'name' => 'Staff', 'email' => 'staff2@example.com', 'password' => bcrypt('password'),
    ]);
    actingAs($admin, 'admin');

    $this->put(route('admin.admins.update', $admin), [
        'name' => 'Staff', 'email' => 'staff2@example.com',
        'permissions' => ['admins' => ['view', 'create', 'update', 'delete'], 'products' => ['view', 'create', 'update', 'delete']],
    ])->assertRedirect(route('admin.admins.index'));

    expect($admin->refresh()->permissions)->toBeNull();
});

test('the mother admin can update another admins permissions', function () {
    $mother = createMotherAdmin();
    $admin = Admin::create([
        'name' => 'Staff', 'email' => 'staff3@example.com', 'password' => bcrypt('password'),
    ]);
    actingAs($mother, 'admin');

    $this->put(route('admin.admins.update', $admin), [
        'name' => 'Staff', 'email' => 'staff3@example.com',
        'permissions' => ['customers' => ['view', 'delete']],
    ])->assertRedirect(route('admin.admins.index'));

    expect($admin->refresh()->permissions)->toBe(['customers' => ['view', 'delete']]);
});

// ── Route enforcement ────────────────────────────────────────────────────────

test('a new admin with no permissions is denied every gated section but keeps the dashboard', function () {
    $admin = Admin::create([
        'name' => 'Staff', 'email' => 'blank@example.com', 'password' => bcrypt('password'),
    ]);
    actingAs($admin, 'admin');

    $this->get(route('admin.orders.index'))->assertForbidden();
    $this->get(route('admin.customers.index'))->assertForbidden();
    $this->get(route('admin.dashboard'))->assertOk();
    // Can still view (but not edit others') the admins list/own account — unaffected by this feature.
    $this->get(route('admin.admins.index'))->assertOk();
});

test('granting view lets an admin see a section but not mutate it', function () {
    $admin = Admin::create([
        'name' => 'Staff', 'email' => 'viewer@example.com', 'password' => bcrypt('password'),
        'permissions' => ['categories' => ['view']],
    ]);
    $category = Category::factory()->create();
    actingAs($admin, 'admin');

    $this->get(route('admin.categories.index'))->assertOk();
    $this->get(route('admin.categories.create'))->assertForbidden();
    $this->put(route('admin.categories.update', $category), ['name' => 'Renamed'])->assertForbidden();
    expect($category->refresh()->name)->not->toBe('Renamed');
});

test('granting update lets an admin mutate a section', function () {
    $admin = Admin::create([
        'name' => 'Staff', 'email' => 'editor@example.com', 'password' => bcrypt('password'),
        'permissions' => ['categories' => ['view', 'update']],
    ]);
    $category = Category::factory()->create(['name' => 'Original']);
    actingAs($admin, 'admin');

    $this->put(route('admin.categories.update', $category), [
        'name' => 'Renamed', 'slug' => $category->slug, 'is_active' => true,
    ])->assertSessionDoesntHaveErrors();
    expect($category->refresh()->name)->toBe('Renamed');
});

test('create and delete abilities are enforced independently', function () {
    $admin = Admin::create([
        'name' => 'Staff', 'email' => 'blocker@example.com', 'password' => bcrypt('password'),
        'permissions' => ['blocked_ips' => ['view', 'create']],
    ]);
    actingAs($admin, 'admin');

    $this->post(route('admin.blocked-ips.store'), ['ip_address' => '203.0.113.50'])
        ->assertRedirect();
    $blockedIp = BlockedIp::where('ip_address', '203.0.113.50')->firstOrFail();

    // No delete ability yet.
    $this->delete(route('admin.blocked-ips.destroy', $blockedIp))->assertForbidden();
    expect(BlockedIp::find($blockedIp->id))->not->toBeNull();

    $admin->update(['permissions' => ['blocked_ips' => ['view', 'create', 'delete']]]);

    $this->delete(route('admin.blocked-ips.destroy', $blockedIp))->assertRedirect();
    expect(BlockedIp::find($blockedIp->id))->toBeNull();
});

test('the mother admin bypasses every permission check regardless of her permissions column', function () {
    $mother = createMotherAdmin();
    expect($mother->permissions)->toBeNull();

    actingAs($mother, 'admin');

    $this->get(route('admin.orders.index'))->assertOk();
    $this->get(route('admin.customers.index'))->assertOk();
    $this->post(route('admin.blocked-ips.store'), ['ip_address' => '203.0.113.60'])->assertRedirect();
});

// ── Settings: one endpoint shared by six sections, gated by field group ─────

test('an admin without about_page update cannot change about-page settings', function () {
    $admin = Admin::create([
        'name' => 'Staff', 'email' => 'about-viewer@example.com', 'password' => bcrypt('password'),
        'permissions' => ['about_page' => ['view']],
    ]);
    actingAs($admin, 'admin');

    $this->put(route('admin.settings.update'), ['about_hero_title' => 'Hacked'])
        ->assertForbidden();
});

test('an admin with about_page update can change about-page settings without touching general settings', function () {
    $admin = Admin::create([
        'name' => 'Staff', 'email' => 'about-editor@example.com', 'password' => bcrypt('password'),
        'permissions' => ['about_page' => ['view', 'update']],
    ]);
    actingAs($admin, 'admin');

    $this->put(route('admin.settings.update'), ['about_hero_title' => 'New Title'])
        ->assertSessionDoesntHaveErrors();

    // The same admin still can't touch the general settings field group.
    $this->put(route('admin.settings.update'), ['site_name' => 'Hacked Name'])
        ->assertForbidden();
});

test('granting the settings section allows the general field group', function () {
    $admin = Admin::create([
        'name' => 'Staff', 'email' => 'settings-editor@example.com', 'password' => bcrypt('password'),
        'permissions' => ['settings' => ['view', 'update']],
    ]);
    actingAs($admin, 'admin');

    $this->put(route('admin.settings.update'), ['site_name' => 'New Name'])
        ->assertSessionDoesntHaveErrors();
});

// ── Security stays mother-only to edit no matter what ────────────────────────

test('security settings view is gated by the permission system but update always stays mother-only', function () {
    $admin = Admin::create([
        'name' => 'Staff', 'email' => 'security-viewer@example.com', 'password' => bcrypt('password'),
        'permissions' => ['security' => ['view']],
    ]);
    actingAs($admin, 'admin');

    $this->get(route('admin.settings.security.edit'))->assertOk();

    $limits = SecurityRateLimits::all();
    $payload = array_map('intval', $limits);
    $this->put(route('admin.settings.security.update'), $payload)
        ->assertSessionHas('error');
});
