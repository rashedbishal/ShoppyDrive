<?php

use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

// --- Admin logged in → blocked from storefront pages ---

test('logged-in admin visiting the homepage is redirected to admin dashboard', function () {
    $admin = Admin::create(['name' => 'Admin', 'email' => 'admin@test.com', 'password' => bcrypt('password')]);
    actingAs($admin, 'admin');

    get(route('home'))->assertRedirect(route('admin.dashboard'));
});

test('logged-in admin visiting a customer auth page is redirected to admin dashboard', function () {
    $admin = Admin::create(['name' => 'Admin', 'email' => 'admin@test.com', 'password' => bcrypt('password')]);
    actingAs($admin, 'admin');

    get(route('login'))->assertRedirect(route('admin.dashboard'));
});

test('logged-in admin visiting a storefront page is redirected to admin dashboard', function () {
    $admin = Admin::create(['name' => 'Admin', 'email' => 'admin@test.com', 'password' => bcrypt('password')]);
    actingAs($admin, 'admin');

    get(route('products.index'))->assertRedirect(route('admin.dashboard'));
});

// --- Customer logged in → blocked from admin pages ---

test('logged-in customer visiting admin login page is redirected to home', function () {
    $user = User::factory()->create();
    actingAs($user);

    get(route('admin.login'))->assertRedirect(route('home'));
});

test('logged-in customer visiting admin dashboard is redirected to home', function () {
    $user = User::factory()->create();
    actingAs($user);

    get(route('admin.dashboard'))->assertRedirect(route('home'));
});

// --- Admin logged in → can still visit landing pages ---

test('logged-in admin can visit a landing page', function () {
    $admin = Admin::create(['name' => 'Admin', 'email' => 'admin@test.com', 'password' => bcrypt('password')]);
    actingAs($admin, 'admin');

    // /lp/* routes should NOT redirect admins
    get('/lp/any-slug')->assertStatus(404); // 404 = not found (no such page), but NOT a redirect
});

// --- Guest access is unaffected ---

test('guest can visit admin login page', function () {
    get(route('admin.login'))->assertOk();
});

test('guest can visit storefront homepage', function () {
    get(route('home'))->assertOk();
});
