<?php

use App\Models\Brand;
use App\Models\HomepageSetting;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * BIZ-033: HomeController's featured/new_arrivals/best_sellers queries
 * omitted the is_approved filter other product listings already apply, so
 * an unapproved or rejected review changed the rating shown on the most-
 * viewed page immediately — this test covers all three sections.
 */
test('an unapproved or rejected review does not change the rating shown on any home page section', function () {
    $product = Product::factory()->create(['is_active' => true, 'is_featured' => true, 'published_at' => now()->subDay()]);
    // Rejecting a review just sets is_approved back to false (Admin\ReviewController::reject()) — the same state as a still-pending one.
    Review::create(['product_id' => $product->id, 'user_id' => User::factory()->create()->id, 'rating' => 1, 'body' => 'x', 'is_approved' => false]);
    Review::create(['product_id' => $product->id, 'user_id' => User::factory()->create()->id, 'rating' => 1, 'body' => 'x', 'is_approved' => false]);
    Review::create(['product_id' => $product->id, 'user_id' => User::factory()->create()->id, 'rating' => 5, 'body' => 'x', 'is_approved' => true]);

    HomepageSetting::create([
        'section_key' => 'new_arrivals', 'section_name' => 'New Arrivals',
        'is_visible' => true, 'sort_order' => 0,
        'metadata' => ['product_ids' => [$product->id]],
    ]);
    HomepageSetting::create([
        'section_key' => 'best_sellers', 'section_name' => 'Best Sellers',
        'is_visible' => true, 'sort_order' => 1,
        'metadata' => ['product_ids' => [$product->id]],
    ]);

    $this->get('/')->assertInertia(fn ($page) => $page
        ->where('featuredProducts.0.reviews_count', 1)
        ->where('featuredProducts.0.average_rating', 5)
        ->where('newArrivals.0.reviews_count', 1)
        ->where('newArrivals.0.average_rating', 5)
        ->where('bestSellers.0.reviews_count', 1)
        ->where('bestSellers.0.average_rating', 5)
    );
});

test('homepage curated products and brands order newest-first regardless of the admin\'s pick order (DISPLAY-ORDER-001)', function () {
    // Which products/brands appear is still exactly the admin's picked
    // list; superseded by DisplayOrderSettings is only the *order* they
    // display in — "Descending" (the default) is newest first by
    // published_at/created_at, not the array position below.
    $products = [
        Product::factory()->create(['published_at' => now()->subDays(3)]),
        Product::factory()->create(['published_at' => now()->subDays(2)]),
        Product::factory()->create(['published_at' => now()->subDay()]),
    ];
    $brands = [
        Brand::factory()->create(['created_at' => now()->subDays(3)]),
        Brand::factory()->create(['created_at' => now()->subDays(2)]),
        Brand::factory()->create(['created_at' => now()->subDay()]),
    ];

    $productIds = [$products[0]->id, $products[2]->id, $products[1]->id];
    $brandIds = [$brands[1]->id, $brands[0]->id, $brands[2]->id];

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
        ->where('newArrivals.0.id', $products[2]->id)
        ->where('newArrivals.1.id', $products[1]->id)
        ->where('newArrivals.2.id', $products[0]->id)
        ->where('bestSellers.0.id', $products[2]->id)
        ->where('bestSellers.2.id', $products[0]->id)
        ->where('brands.0.id', $brands[2]->id)
        ->where('brands.1.id', $brands[1]->id)
        ->where('brands.2.id', $brands[0]->id)
    );
});
