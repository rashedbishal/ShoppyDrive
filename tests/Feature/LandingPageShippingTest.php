<?php

declare(strict_types=1);

use App\Models\HomepageSetting;
use App\Models\LandingPage;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

use function Pest\Laravel\post;

uses(RefreshDatabase::class);

/**
 * SHIP-DUP-001: LandingPageController::calculateShipping() checked
 * `free_delivery` but never the ordered quantity, so a product with
 * `free_delivery_min_qty` shipped free here regardless of how many units
 * were actually ordered — unlike CheckoutController, which correctly
 * requires the quantity to meet that minimum.
 */
function landingPageOrderPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Test Customer',
        'phone' => '01712345678',
        'email' => 'customer@example.com',
        'address_line1' => '123 Main St',
        'city' => 'Inside Dhaka City Corporation',
        'state' => 'Dhaka',
        'quantity' => 1,
        'payment_method' => 'cod',
        'order_token' => (string) Str::uuid(),
    ], $overrides);
}

test('a landing-page product with no minimum quantity keeps its existing free-delivery behavior', function () {
    $product = Product::factory()->create(['is_active' => true, 'published_at' => now()->subDay(), 'price' => 500, 'stock_quantity' => 50, 'free_delivery' => true, 'free_delivery_min_qty' => null]);
    $page = LandingPage::create(['title' => 'Promo', 'slug' => 'promo-'.uniqid(), 'product_id' => $product->id, 'is_active' => true, 'sections' => []]);

    post(route('lp.order', $page->slug), landingPageOrderPayload(['quantity' => 1]))->assertRedirect();

    expect(Order::query()->firstOrFail()->shipping_cost)->toBe(0.0);
});

test('ordering below the free-delivery minimum quantity on a landing page charges standard shipping', function () {
    $product = Product::factory()->create(['is_active' => true, 'published_at' => now()->subDay(), 'price' => 500, 'stock_quantity' => 50, 'free_delivery' => true, 'free_delivery_min_qty' => 3]);
    $page = LandingPage::create(['title' => 'Promo', 'slug' => 'promo-'.uniqid(), 'product_id' => $product->id, 'is_active' => true, 'sections' => []]);

    post(route('lp.order', $page->slug), landingPageOrderPayload(['quantity' => 2]))->assertRedirect();

    expect(Order::query()->firstOrFail()->shipping_cost)->toBe(60.0);
});

test('ordering exactly the free-delivery minimum quantity on a landing page charges no shipping', function () {
    $product = Product::factory()->create(['is_active' => true, 'published_at' => now()->subDay(), 'price' => 500, 'stock_quantity' => 50, 'free_delivery' => true, 'free_delivery_min_qty' => 3]);
    $page = LandingPage::create(['title' => 'Promo', 'slug' => 'promo-'.uniqid(), 'product_id' => $product->id, 'is_active' => true, 'sections' => []]);

    post(route('lp.order', $page->slug), landingPageOrderPayload(['quantity' => 3]))->assertRedirect();

    expect(Order::query()->firstOrFail()->shipping_cost)->toBe(0.0);
});

test('ordering above the free-delivery minimum quantity on a landing page charges no shipping', function () {
    $product = Product::factory()->create(['is_active' => true, 'published_at' => now()->subDay(), 'price' => 500, 'stock_quantity' => 50, 'free_delivery' => true, 'free_delivery_min_qty' => 3]);
    $page = LandingPage::create(['title' => 'Promo', 'slug' => 'promo-'.uniqid(), 'product_id' => $product->id, 'is_active' => true, 'sections' => []]);

    post(route('lp.order', $page->slug), landingPageOrderPayload(['quantity' => 5]))->assertRedirect();

    expect(Order::query()->firstOrFail()->shipping_cost)->toBe(0.0);
});

test('a landing-page order above the sitewide free-delivery threshold charges no shipping regardless of quantity rules', function () {
    HomepageSetting::updateOrCreate(['section_key' => 'announcement_bar'], ['section_name' => 'Bar', 'metadata' => ['free_delivery_amount' => 1000]]);
    $product = Product::factory()->create(['is_active' => true, 'published_at' => now()->subDay(), 'price' => 500, 'stock_quantity' => 50, 'free_delivery' => true, 'free_delivery_min_qty' => 3]);
    $page = LandingPage::create(['title' => 'Promo', 'slug' => 'promo-'.uniqid(), 'product_id' => $product->id, 'is_active' => true, 'sections' => []]);

    // Quantity 2 is below free_delivery_min_qty (3), so the per-product rule
    // alone would charge shipping — but 2 x 500 = 1000 meets the sitewide
    // threshold, which must still waive it regardless of the quantity rule.
    post(route('lp.order', $page->slug), landingPageOrderPayload(['quantity' => 2]))->assertRedirect();

    expect(Order::query()->firstOrFail()->shipping_cost)->toBe(0.0);
});
