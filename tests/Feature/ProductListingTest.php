<?php

declare(strict_types=1);

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('renders the product listing page', function (): void {
    $this->get(route('products.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('catalog/ProductListing')
            ->where('title', 'All Products')
            ->where('category', null)
            ->where('listingUrl', route('products.index'))
        );
});

it('renders a category listing page', function (): void {
    $category = Category::query()->create([
        'name' => 'Electronics',
        'slug' => 'electronics',
        'is_active' => true,
        'sort_order' => 0,
    ]);

    $this->get(route('category.show', $category->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('catalog/ProductListing')
            ->where('title', 'Electronics')
            ->where('category.id', $category->id)
            ->where('listingUrl', route('category.show', $category->slug))
        );
});
