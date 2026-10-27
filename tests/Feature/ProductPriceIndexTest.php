<?php

declare(strict_types=1);

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('products.price has an index', function () {
    $indexedColumns = collect(Schema::getIndexes('products'))
        ->flatMap(fn (array $index) => $index['columns'])
        ->all();

    expect($indexedColumns)->toContain('price');
});

test('sorting the catalog by price ascending returns correctly ordered results', function () {
    foreach ([500, 100, 300, 700, 200] as $price) {
        Product::factory()->create(['price' => $price, 'is_active' => true, 'published_at' => now()->subDay()]);
    }

    $this->get(route('products.index', ['sort' => 'price_asc']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where(
            'products.data',
            fn ($data) => $data->pluck('price')->map(fn ($p) => (float) $p)->all() === [100.0, 200.0, 300.0, 500.0, 700.0]
        ));
});

test('sorting the catalog by price descending returns correctly ordered results', function () {
    foreach ([500, 100, 300, 700, 200] as $price) {
        Product::factory()->create(['price' => $price, 'is_active' => true, 'published_at' => now()->subDay()]);
    }

    $this->get(route('products.index', ['sort' => 'price_desc']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where(
            'products.data',
            fn ($data) => $data->pluck('price')->map(fn ($p) => (float) $p)->all() === [700.0, 500.0, 300.0, 200.0, 100.0]
        ));
});

test('filtering the catalog by a price range only returns products within that range', function () {
    Product::factory()->create(['price' => 50, 'is_active' => true, 'published_at' => now()->subDay()]);
    Product::factory()->create(['price' => 150, 'is_active' => true, 'published_at' => now()->subDay()]);
    Product::factory()->create(['price' => 300, 'is_active' => true, 'published_at' => now()->subDay()]);

    $this->get(route('products.index', ['min_price' => 100, 'max_price' => 200]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where(
            'products.data',
            fn ($data) => $data->pluck('price')->map(fn ($p) => (float) $p)->all() === [150.0]
        ));
});
