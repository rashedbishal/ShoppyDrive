<?php

use App\Models\Brand;
use App\Models\HomepageSetting;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('homepage curated products and brands keep the order the admin picked them in', function () {
    $products = Product::factory()->count(3)->create(['published_at' => now()->subDay()]);
    $brands = Brand::factory()->count(3)->create();

    $productIds = [$products[2]->id, $products[0]->id, $products[1]->id];
    $brandIds = [$brands[1]->id, $brands[2]->id, $brands[0]->id];

    HomepageSetting::create([
        'section_key' => 'new_arrivals', 'section_name' => 'New Arrivals',
        'is_visible' => true, 'sort_order' => 0,
        'metadata' => ['product_ids' => $productIds],
    ]);
    HomepageSetting::create([
        'section_key' => 'best_sellers', 'section_name' => 'Best Sellers',
        'is_visible' => true, 'sort_order' => 1,
        'metadata' => ['product_ids' => $productIds],
    ]);
    HomepageSetting::create([
        'section_key' => 'brands', 'section_name' => 'Brands',
        'is_visible' => true, 'sort_order' => 2,
        'metadata' => ['brand_ids' => $brandIds],
    ]);

    $this->get('/')->assertInertia(fn ($page) => $page
        ->where('newArrivals.0.id', $productIds[0])
        ->where('newArrivals.1.id', $productIds[1])
        ->where('newArrivals.2.id', $productIds[2])
        ->where('bestSellers.0.id', $productIds[0])
        ->where('bestSellers.2.id', $productIds[2])
        ->where('brands.0.id', $brandIds[0])
        ->where('brands.1.id', $brandIds[1])
        ->where('brands.2.id', $brandIds[2])
    );
});
