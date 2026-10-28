<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\SubCategory;
use Inertia\Testing\AssertableInertia as Assert;

test('a category page uses the category\'s own colour for its banner', function () {
    $category = Category::factory()->create(['is_active' => true, 'color' => 'forestgreen']);

    $this->get(route('category.show', $category->slug))
        ->assertInertia(fn (Assert $page) => $page->where('bannerColor', 'forestgreen'));
});

test('a sub-category page uses its parent category\'s colour', function () {
    $category = Category::factory()->create(['is_active' => true, 'color' => '#4b0082']);
    $subCategory = SubCategory::factory()->create(['category_id' => $category->id, 'is_active' => true]);

    $this->get(route('category.show', $subCategory->slug))
        ->assertInertia(fn (Assert $page) => $page->where('bannerColor', '#4b0082'));
});

test('without a category colour the banner falls back to the theme colour', function () {
    $category = Category::factory()->create(['is_active' => true, 'color' => null]);

    $this->get(route('category.show', $category->slug))
        ->assertInertia(fn (Assert $page) => $page->where('bannerColor', null));

    $this->get(route('products.index'))
        ->assertInertia(fn (Assert $page) => $page->where('bannerColor', null));
});
