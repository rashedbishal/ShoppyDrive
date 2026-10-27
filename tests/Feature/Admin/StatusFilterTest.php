<?php

use App\Models\Admin;
use App\Models\Brand;
use App\Models\SubCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = Admin::create([
        'name' => 'Staff Admin',
        'email' => 'staff@example.com',
        'password' => bcrypt('password'),
    ]);
});

test('admin can filter brands by active status', function () {
    $active = Brand::factory()->create(['is_active' => true]);
    Brand::factory()->create(['is_active' => false]);

    actingAs($this->admin, 'admin')
        ->get(route('admin.brands.index', ['status' => 'active']))
        ->assertInertia(fn ($page) => $page
            ->component('admin/brands/index')
            ->where('brands.data.0.id', $active->id)
            ->has('brands.data', 1)
        );
});

test('admin can filter brands by inactive status', function () {
    Brand::factory()->create(['is_active' => true]);
    $inactive = Brand::factory()->create(['is_active' => false]);

    actingAs($this->admin, 'admin')
        ->get(route('admin.brands.index', ['status' => 'inactive']))
        ->assertInertia(fn ($page) => $page
            ->component('admin/brands/index')
            ->where('brands.data.0.id', $inactive->id)
            ->has('brands.data', 1)
        );
});

test('admin can filter sub-categories by active status', function () {
    $active = SubCategory::factory()->create(['is_active' => true]);
    SubCategory::factory()->create(['is_active' => false]);

    actingAs($this->admin, 'admin')
        ->get(route('admin.sub-categories.index', ['status' => 'active']))
        ->assertInertia(fn ($page) => $page
            ->component('admin/sub-categories/index')
            ->where('subCategories.data.0.id', $active->id)
            ->has('subCategories.data', 1)
        );
});

test('admin can filter sub-categories by inactive status', function () {
    SubCategory::factory()->create(['is_active' => true]);
    $inactive = SubCategory::factory()->create(['is_active' => false]);

    actingAs($this->admin, 'admin')
        ->get(route('admin.sub-categories.index', ['status' => 'inactive']))
        ->assertInertia(fn ($page) => $page
            ->component('admin/sub-categories/index')
            ->where('subCategories.data.0.id', $inactive->id)
            ->has('subCategories.data', 1)
        );
});
