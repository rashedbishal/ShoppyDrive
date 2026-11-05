<?php

declare(strict_types=1);

use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\LandingPage;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * BIZ-016: LandingPageController::placeOrder() always charged the correct,
 * flash-sale-aware price (CartManager::resolvePrice()) — only the page's
 * own display (serializeProduct(), feeding LandingPage.tsx's headline
 * price and its own `price * qty` subtotal preview) showed the plain
 * price regardless of an active flash sale.
 */
test('a landing page shows its product\'s active flash-sale price, not its regular one', function () {
    $product = Product::factory()->create([
        'is_active' => true,
        'published_at' => now()->subDay(),
        'price' => 2000,
        'compare_price' => null,
        'stock_quantity' => 50,
    ]);
    $page = LandingPage::create([
        'title' => 'Promo', 'slug' => 'promo-'.uniqid(), 'product_id' => $product->id, 'is_active' => true, 'sections' => [],
    ]);

    $flashSale = FlashSale::factory()->create([
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHour(),
        'is_active' => true,
    ]);
    FlashSaleItem::factory()->create([
        'flash_sale_id' => $flashSale->id,
        'product_id' => $product->id,
        'variant_id' => null,
        'sale_price' => 1000,
    ]);

    $this->get(route('lp.show', $page->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $assert) => $assert
            ->where('product.price', 1000)
            ->where('product.compare_price', 2000)
            ->where('product.in_flash_sale', true)
            ->where('product.flash_sale_price', 1000));
});

test('a landing page with no active flash sale shows its regular price, unchanged', function () {
    $product = Product::factory()->create([
        'is_active' => true,
        'published_at' => now()->subDay(),
        'price' => 500,
        'compare_price' => 700,
        'stock_quantity' => 50,
    ]);
    $page = LandingPage::create([
        'title' => 'Promo', 'slug' => 'promo-'.uniqid(), 'product_id' => $product->id, 'is_active' => true, 'sections' => [],
    ]);

    $this->get(route('lp.show', $page->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $assert) => $assert
            ->where('product.price', 500)
            ->where('product.compare_price', 700)
            ->where('product.in_flash_sale', false)
            ->where('product.flash_sale_price', null));
});
