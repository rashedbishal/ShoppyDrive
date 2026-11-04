<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\SubCategory;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    actingAs(createAdmin(), 'admin');
    $this->category = Category::factory()->create();
});

test('the subcategories table lists the newest created first, regardless of sort order', function () {
    $oldest = SubCategory::factory()->create(['category_id' => $this->category->id, 'sort_order' => 1, 'created_at' => now()->subDays(3)]);
    $newest = SubCategory::factory()->create(['category_id' => $this->category->id, 'sort_order' => 9, 'created_at' => now()]);
    $middle = SubCategory::factory()->create(['category_id' => $this->category->id, 'sort_order' => 5, 'created_at' => now()->subDay()]);

    get(route('admin.sub-categories.index'))->assertInertia(fn (Assert $page) => $page
        ->where('subCategories.data.0.id', $newest->id)
        ->where('subCategories.data.1.id', $middle->id)
        ->where('subCategories.data.2.id', $oldest->id));
});

test('subcategories created at the same moment fall back to sort order then name', function () {
    $this->freezeTime();

    $second = SubCategory::factory()->create(['category_id' => $this->category->id, 'sort_order' => 2, 'name' => 'Alpha']);
    $third = SubCategory::factory()->create(['category_id' => $this->category->id, 'sort_order' => 2, 'name' => 'Beta']);
    $first = SubCategory::factory()->create(['category_id' => $this->category->id, 'sort_order' => 1, 'name' => 'Zeta']);

    get(route('admin.sub-categories.index'))->assertInertia(fn (Assert $page) => $page
        ->where('subCategories.data.0.id', $first->id)
        ->where('subCategories.data.1.id', $second->id)
        ->where('subCategories.data.2.id', $third->id));
});
