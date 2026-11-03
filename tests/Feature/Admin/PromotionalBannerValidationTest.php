<?php

use App\Models\PromotionalBanner;
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

test('creating a promotional banner rejects fields past their character limits', function () {
    actingAs($this->admin, 'admin');

    $response = $this->post(route('admin.promotional-banners.store'), [
        'title' => str_repeat('a', 81),
        'description' => str_repeat('a', 151),
        'badge' => str_repeat('a', 41),
        'is_active' => '1',
        'sort_order' => '1',
    ]);

    $response->assertInvalid(['title', 'description', 'badge']);
    expect(PromotionalBanner::count())->toBe(0);
});

test('creating a promotional banner accepts fields within their character limits', function () {
    actingAs($this->admin, 'admin');

    $response = $this->post(route('admin.promotional-banners.store'), [
        'title' => str_repeat('a', 80),
        'description' => str_repeat('a', 150),
        'badge' => str_repeat('a', 40),
        'is_active' => '1',
        'sort_order' => '1',
    ]);

    $response->assertRedirect(route('admin.promotional-banners.index'));
    $response->assertSessionDoesntHaveErrors();
    expect(PromotionalBanner::count())->toBe(1);
});

test('clearing a promotional banner image on update replaces it with the placeholder', function () {
    actingAs($this->admin, 'admin');

    $banner = PromotionalBanner::create([
        'title' => 'Sale',
        'image' => '/storage/promotional-banners/original.jpg',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    // Mirrors what the browser sends when the admin clicks the media upload's
    // remove button without picking a replacement: no file, and the field
    // posts as an empty string (Inertia serializes a null form value that way).
    $response = $this->post(route('admin.promotional-banners.update', $banner), [
        '_method' => 'PUT',
        'title' => 'Sale',
        'image' => '',
        'is_active' => '1',
        'sort_order' => '1',
    ]);

    $response->assertRedirect(route('admin.promotional-banners.index'));
    $response->assertSessionDoesntHaveErrors();
    expect($banner->refresh()->image)->toBe('/placeholder.svg');
});

test('leaving a promotional banner image untouched on update keeps the existing image', function () {
    actingAs($this->admin, 'admin');

    $banner = PromotionalBanner::create([
        'title' => 'Sale',
        'image' => '/storage/promotional-banners/original.jpg',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $response = $this->post(route('admin.promotional-banners.update', $banner), [
        '_method' => 'PUT',
        'title' => 'Sale',
        'image' => $banner->image,
        'is_active' => '1',
        'sort_order' => '1',
    ]);

    $response->assertRedirect(route('admin.promotional-banners.index'));
    $response->assertSessionDoesntHaveErrors();
    expect($banner->refresh()->image)->toBe('/storage/promotional-banners/original.jpg');
});
