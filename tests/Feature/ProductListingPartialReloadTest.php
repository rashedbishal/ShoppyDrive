<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('a full visit includes every filter-sidebar prop', function (): void {
    $this->get(route('products.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('catalog/ProductListing')
            ->has('categories')
            ->has('brands')
            ->has('attributes')
            ->has('maxPrice')
            ->has('products')
            ->has('filters')
        );
});

test('a same-context filter change (partial reload scoped to products/filters) never resolves or returns the sidebar lookup props', function (): void {
    // Mirrors ProductListing.tsx's `submitFilters()`: a same-URL filter
    // change is sent as `only: ['products', 'filters']`. categories/brands/
    // attributes/maxPrice are wrapped in closures specifically so this skips
    // querying and returning them entirely, not just trims them from the
    // response.
    $this->get(route('products.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('categories')
            ->has('brands')
            ->has('attributes')
            ->has('maxPrice')
            ->reloadOnly(['products', 'filters'], fn (Assert $reload) => $reload
                ->missing('categories')
                ->missing('brands')
                ->missing('attributes')
                ->missing('maxPrice')
                ->has('products')
                ->has('filters')
            )
        );
});
