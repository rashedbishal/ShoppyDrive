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

test('a newly created category is appended after the highest existing sort order', function () {
    actingAs($this->admin, 'admin');

    Category::factory()->create(['sort_order' => 5]);
    Category::factory()->create(['sort_order' => 9]);

    $response = $this->post(route('admin.categories.store'), [
        'name' => 'New Category',
        'slug' => 'new-category',
        'is_active' => true,
    ]);

    $response->assertRedirect(route('admin.categories.index'));

    expect(Category::where('slug', 'new-category')->first()->sort_order)->toBe(10);
});

test('the first category created gets sort order zero', function () {
    actingAs($this->admin, 'admin');

    $response = $this->post(route('admin.categories.store'), [
        'name' => 'First Category',
        'slug' => 'first-category',
        'is_active' => true,
    ]);

    $response->assertRedirect(route('admin.categories.index'));

    expect(Category::where('slug', 'first-category')->first()->sort_order)->toBe(0);
});

test('admin can drag-reorder categories and the new order persists', function () {
    actingAs($this->admin, 'admin');

    $first = Category::factory()->create(['name' => 'Alpha', 'sort_order' => 0]);
    $second = Category::factory()->create(['name' => 'Beta', 'sort_order' => 1]);
    $third = Category::factory()->create(['name' => 'Gamma', 'sort_order' => 2]);

    $response = $this->post(route('admin.categories.reorder'), [
        'ids' => [$third->id, $first->id, $second->id],
    ]);

    $response->assertRedirect(route('admin.categories.index'));

    expect($third->refresh()->sort_order)->toBe(0);
    expect($first->refresh()->sort_order)->toBe(1);
    expect($second->refresh()->sort_order)->toBe(2);
});

test('reorder rejects ids that do not belong to an existing category', function () {
    actingAs($this->admin, 'admin');

    $category = Category::factory()->create();

    $response = $this->post(route('admin.categories.reorder'), [
        'ids' => [$category->id, 999999],
    ]);

    $response->assertInvalid(['ids.1']);
});

test('the category index page lists every category with no pagination', function () {
    actingAs($this->admin, 'admin');

    Category::factory()->count(25)->create();

    $response = $this->get(route('admin.categories.index'));

    $response->assertInertia(fn ($page) => $page
        ->component('admin/categories/index')
        ->has('categories', 25)
    );
});
