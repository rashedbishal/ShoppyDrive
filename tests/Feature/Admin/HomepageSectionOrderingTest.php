<?php

use App\Models\HomepageSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = createAdmin([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => bcrypt('password'),
    ]);
});

test('admin can drag-reorder homepage sections and the new order persists', function () {
    actingAs($this->admin, 'admin');

    $first = HomepageSetting::create([
        'section_key' => 'categories', 'section_name' => 'Categories',
        'is_visible' => true, 'sort_order' => 0,
    ]);
    $second = HomepageSetting::create([
        'section_key' => 'brands', 'section_name' => 'Brands',
        'is_visible' => true, 'sort_order' => 1,
    ]);
    $third = HomepageSetting::create([
        'section_key' => 'new_arrivals', 'section_name' => 'New Arrivals',
        'is_visible' => true, 'sort_order' => 2,
    ]);

    // A plain JSON response, not a redirect: the admin list persists this
    // from a background fetch once reordering is confirmed, and already
    // holds the new order locally.
    $response = $this->post(route('admin.homepage.reorder'), [
        'ids' => [$third->id, $first->id, $second->id],
    ]);

    $response->assertOk();

    expect($third->refresh()->sort_order)->toBe(0);
    expect($first->refresh()->sort_order)->toBe(1);
    expect($second->refresh()->sort_order)->toBe(2);
});

test('reorder rejects ids that do not belong to an existing homepage section', function () {
    actingAs($this->admin, 'admin');

    $section = HomepageSetting::create([
        'section_key' => 'categories', 'section_name' => 'Categories',
        'is_visible' => true, 'sort_order' => 0,
    ]);

    $response = $this->post(route('admin.homepage.reorder'), [
        'ids' => [$section->id, 999999],
    ]);

    $response->assertInvalid(['ids.1']);
});

test('admin can toggle a homepage section visible from the list without opening its edit page', function () {
    actingAs($this->admin, 'admin');

    $section = HomepageSetting::create([
        'section_key' => 'categories', 'section_name' => 'Categories',
        'is_visible' => true, 'sort_order' => 0,
    ]);

    // A plain JSON response, not a redirect: the admin list flips the icon
    // optimistically from a background fetch, so a full Inertia visit back
    // to the index page would re-render the whole section list just to
    // toggle one field.
    $response = $this->patch(route('admin.homepage.toggle-visibility', $section));

    $response->assertOk();
    $response->assertJson(['is_visible' => false]);
    expect($section->refresh()->is_visible)->toBeFalse();

    $response = $this->patch(route('admin.homepage.toggle-visibility', $section));

    $response->assertOk();
    $response->assertJson(['is_visible' => true]);
    expect($section->refresh()->is_visible)->toBeTrue();
});

test('the storefront homepage exposes each section sort_order so the client can render them in that order', function () {
    HomepageSetting::create([
        'section_key' => 'categories', 'section_name' => 'Categories',
        'is_visible' => true, 'sort_order' => 5,
    ]);
    HomepageSetting::create([
        'section_key' => 'brands', 'section_name' => 'Brands',
        'is_visible' => true, 'sort_order' => 1,
    ]);

    $response = $this->get('/');

    $response->assertInertia(fn ($page) => $page
        ->where('homepage_settings.categories.sort_order', 5)
        ->where('homepage_settings.brands.sort_order', 1)
    );
});
