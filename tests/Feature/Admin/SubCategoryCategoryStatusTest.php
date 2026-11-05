<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\SubCategory;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    actingAs(createAdmin(), 'admin');
});

/*
 * A sub-category must not be (re-)created under an inactive parent — an
 * inactive category is hidden from the storefront entirely, so a
 * sub-category under it would be unreachable and misleading in admin lists.
 */
test('creating a sub-category under an inactive category is refused', function () {
    $category = Category::factory()->create(['is_active' => false]);

    $this->post(route('admin.sub-categories.store'), [
        'name' => 'New Sub', 'slug' => 'new-sub', 'category_id' => $category->id,
    ])->assertSessionHasErrors('category_id');

    expect(SubCategory::count())->toBe(0);
});

test('creating a sub-category under an active category still works', function () {
    $category = Category::factory()->create(['is_active' => true]);

    $this->post(route('admin.sub-categories.store'), [
        'name' => 'New Sub', 'slug' => 'new-sub', 'category_id' => $category->id,
    ])->assertSessionDoesntHaveErrors('category_id');

    expect(SubCategory::count())->toBe(1);
});

test('the create form only offers active categories', function () {
    $active = Category::factory()->create(['is_active' => true]);
    Category::factory()->create(['is_active' => false]);

    $this->get(route('admin.sub-categories.create'))->assertInertia(fn (Assert $page) => $page
        ->has('categories', 1)
        ->where('categories.0.id', $active->id));
});

test('moving an existing sub-category to a now-inactive category is refused', function () {
    $activeCategory = Category::factory()->create(['is_active' => true]);
    $inactiveCategory = Category::factory()->create(['is_active' => false]);
    $subCategory = SubCategory::factory()->create(['category_id' => $activeCategory->id]);

    $this->put(route('admin.sub-categories.update', $subCategory), [
        'name' => $subCategory->name, 'slug' => $subCategory->slug, 'category_id' => $inactiveCategory->id,
    ])->assertSessionHasErrors('category_id');

    expect($subCategory->refresh()->category_id)->toBe($activeCategory->id);
});

test('editing a sub-category without changing its category does not break when that category is now inactive', function () {
    $category = Category::factory()->create(['is_active' => false]);
    $subCategory = SubCategory::factory()->create(['category_id' => $category->id, 'name' => 'Old Name']);

    $this->put(route('admin.sub-categories.update', $subCategory), [
        'name' => 'Renamed', 'slug' => $subCategory->slug, 'category_id' => $category->id,
    ])->assertSessionDoesntHaveErrors('category_id');

    expect($subCategory->refresh()->name)->toBe('Renamed');
});

test('the edit form includes the sub-category\'s own inactive category alongside active ones', function () {
    $active = Category::factory()->create(['is_active' => true]);
    $ownInactive = Category::factory()->create(['is_active' => false]);
    Category::factory()->create(['is_active' => false]); // unrelated inactive category — excluded
    $subCategory = SubCategory::factory()->create(['category_id' => $ownInactive->id]);

    $this->get(route('admin.sub-categories.edit', $subCategory))->assertInertia(fn (Assert $page) => $page
        ->has('categories', 2)
        ->where('categories', fn ($categories) => collect($categories)->pluck('id')->sort()->values()->all() === collect([$active->id, $ownInactive->id])->sort()->values()->all()));
});
