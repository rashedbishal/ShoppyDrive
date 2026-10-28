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

    $response = $this->post(route('admin.homepage.reorder'), [
        'ids' => [$third->id, $first->id, $second->id],
    ]);

    $response->assertRedirect(route('admin.homepage.index'));

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
