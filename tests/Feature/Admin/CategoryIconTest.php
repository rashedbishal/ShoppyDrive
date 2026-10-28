<?php

use App\Models\Category;
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

test('admin can create a category with an icon', function () {
    actingAs($this->admin, 'admin');

    $response = $this->post(route('admin.categories.store'), [
        'name' => 'Electronics',
        'slug' => 'electronics',
        'icon' => 'smartphone',
        'is_active' => true,
        'sort_order' => 0,
    ]);

    $response->assertRedirect(route('admin.categories.index'));

    expect(Category::where('slug', 'electronics')->first()->icon)->toBe('smartphone');
});

test('category icon must be a valid kebab-case icon name', function () {
    actingAs($this->admin, 'admin');

    $response = $this->post(route('admin.categories.store'), [
        'name' => 'Electronics',
        'slug' => 'electronics',
        'icon' => 'Not A Valid Icon!!',
        'is_active' => true,
        'sort_order' => 0,
    ]);

    $response->assertInvalid(['icon']);
});

test('admin can update a category icon', function () {
    actingAs($this->admin, 'admin');

    $category = Category::factory()->create(['icon' => 'shopping-bag']);

    $response = $this->put(route('admin.categories.update', $category), [
        'name' => $category->name,
        'slug' => $category->slug,
        'icon' => 'sofa',
        'is_active' => true,
        'sort_order' => 0,
    ]);

    $response->assertRedirect(route('admin.categories.index'));

    expect($category->refresh()->icon)->toBe('sofa');
});

test('homepage exposes category icon to the storefront', function () {
    Category::factory()->create([
        'name' => 'Furniture',
        'slug' => 'furniture',
        'icon' => 'sofa',
        'is_active' => true,
    ]);

    $response = $this->get('/');

    $response->assertInertia(fn ($page) => $page
        ->has('categories', 1)
        ->where('categories.0.icon', 'sofa')
    );
});
