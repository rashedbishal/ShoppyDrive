<?php

use App\Models\LandingPage;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

test('landing page 404s instead of crashing when its product was soft-deleted', function () {
    $product = Product::factory()->create(['is_active' => true, 'published_at' => now()->subDay()]);
    $page = LandingPage::create([
        'title' => 'Promo',
        'slug' => 'promo',
        'product_id' => $product->id,
        'is_active' => true,
        'sections' => [],
    ]);
    $product->delete();

    get(route('lp.show', $page->slug))->assertNotFound();
});

test('landing page 404s instead of crashing when its product is deactivated', function () {
    $product = Product::factory()->create(['is_active' => true, 'published_at' => now()->subDay()]);
    $page = LandingPage::create([
        'title' => 'Promo',
        'slug' => 'promo',
        'product_id' => $product->id,
        'is_active' => true,
        'sections' => [],
    ]);
    $product->update(['is_active' => false]);

    get(route('lp.show', $page->slug))->assertNotFound();
});

test('landing page order placement 404s instead of crashing when its product was soft-deleted', function () {
    $product = Product::factory()->create(['is_active' => true, 'published_at' => now()->subDay()]);
    $page = LandingPage::create([
        'title' => 'Promo',
        'slug' => 'promo',
        'product_id' => $product->id,
        'is_active' => true,
        'sections' => [],
    ]);
    $product->delete();

    post(route('lp.order', $page->slug), [
        'name' => 'Test Customer',
        'phone' => '01712345678',
        'address_line1' => '123 Main St',
        'city' => 'Dhaka',
        'state' => 'Dhaka',
        'quantity' => 1,
        'payment_method' => 'cod',
    ])->assertNotFound();
});

test('live landing page with an active product loads fine', function () {
    $product = Product::factory()->create(['is_active' => true, 'published_at' => now()->subDay()]);
    $page = LandingPage::create([
        'title' => 'Promo',
        'slug' => 'promo',
        'product_id' => $product->id,
        'is_active' => true,
        'sections' => [],
    ]);

    get(route('lp.show', $page->slug))
        ->assertSuccessful()
        ->assertInertia(fn ($pageAssert) => $pageAssert
            ->component('landing/LandingPage')
            ->where('product.id', $product->id)
        );
});
