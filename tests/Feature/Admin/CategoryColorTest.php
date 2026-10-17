<?php

use App\Models\Admin;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = Admin::create([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => bcrypt('password'),
    ]);
});

test('admin can create a category with a named color', function () {
    actingAs($this->admin, 'admin');

    $response = $this->post(route('admin.categories.store'), [
        'name' => 'Electronics',
        'slug' => 'electronics',
        'color' => 'forestgreen',
        'is_active' => true,
        'sort_order' => 0,
    ]);

    $response->assertRedirect(route('admin.categories.index'));

    expect(Category::where('slug', 'electronics')->first()->color)->toBe('forestgreen');
});

test('admin can create a category with a custom hex color', function () {
    actingAs($this->admin, 'admin');

    $response = $this->post(route('admin.categories.store'), [
        'name' => 'Electronics',
        'slug' => 'electronics',
        'color' => '#3B82F6',
        'is_active' => true,
        'sort_order' => 0,
    ]);

    $response->assertRedirect(route('admin.categories.index'));

    expect(Category::where('slug', 'electronics')->first()->color)->toBe('#3B82F6');
});

test('category color must be a named color or a hex code', function () {
    actingAs($this->admin, 'admin');

    $response = $this->post(route('admin.categories.store'), [
        'name' => 'Electronics',
        'slug' => 'electronics',
        'color' => 'not-a-color',
        'is_active' => true,
        'sort_order' => 0,
    ]);

    $response->assertInvalid(['color']);
});

test('an empty color submission is stored as automatic (null)', function () {
    actingAs($this->admin, 'admin');

    $response = $this->post(route('admin.categories.store'), [
        'name' => 'Electronics',
        'slug' => 'electronics',
        'color' => '',
        'is_active' => true,
        'sort_order' => 0,
    ]);

    $response->assertRedirect(route('admin.categories.index'));

    expect(Category::where('slug', 'electronics')->first()->color)->toBeNull();
});

test('admin can update a category color', function () {
    actingAs($this->admin, 'admin');

    $category = Category::factory()->create(['color' => 'skyblue']);

    $response = $this->put(route('admin.categories.update', $category), [
        'name' => $category->name,
        'slug' => $category->slug,
        'color' => '#ff00aa',
        'is_active' => true,
        'sort_order' => 0,
    ]);

    $response->assertRedirect(route('admin.categories.index'));

    expect($category->refresh()->color)->toBe('#ff00aa');
});

test('homepage exposes category color to the storefront', function () {
    Category::factory()->create([
        'name' => 'Furniture',
        'slug' => 'furniture',
        'color' => 'rebeccapurple',
        'is_active' => true,
    ]);

    $response = $this->get('/');

    $response->assertInertia(fn ($page) => $page
        ->has('categories', 1)
        ->where('categories.0.color', 'rebeccapurple')
    );
});
