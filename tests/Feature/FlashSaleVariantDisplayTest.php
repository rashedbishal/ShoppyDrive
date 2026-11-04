<?php

declare(strict_types=1);

use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

function visibleFlashProduct(array $attributes = []): Product
{
    return Product::factory()->create(array_merge([
        'is_active' => true,
        'published_at' => now()->subDay(),
    ], $attributes));
}

test('a variant targeted by its own flash-sale item shows that price on the product page, independent of its siblings', function () {
    $product = visibleFlashProduct(['price' => 1000]);
    $onSale = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 1000, 'stock_quantity' => 10]);
    $notOnSale = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 1000, 'stock_quantity' => 10]);

    $flashSale = FlashSale::factory()->create(['starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'is_active' => true]);
    FlashSaleItem::factory()->create([
        'flash_sale_id' => $flashSale->id,
        'product_id' => $product->id,
        'variant_id' => $onSale->id,
        'sale_price' => 650,
    ]);

    $response = get(route('product.show', $product->slug));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('product.in_flash_sale', false)
        ->where('product.flash_sale_price', null)
        ->where('product.variants.0.id', $onSale->id)
        ->where('product.variants.0.in_flash_sale', true)
        ->where('product.variants.0.flash_sale_price', 650)
        ->where('product.variants.1.id', $notOnSale->id)
        ->where('product.variants.1.in_flash_sale', false)
        ->where('product.variants.1.flash_sale_price', null)
        ->etc()
    );
});

test('a whole-product flash-sale item applies to every variant that has no more specific item of its own', function () {
    $product = visibleFlashProduct(['price' => 1000]);
    $variantA = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 1000, 'stock_quantity' => 10]);
    $variantB = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 1000, 'stock_quantity' => 10]);

    $flashSale = FlashSale::factory()->create(['starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'is_active' => true]);
    FlashSaleItem::factory()->create([
        'flash_sale_id' => $flashSale->id,
        'product_id' => $product->id,
        'variant_id' => null,
        'sale_price' => 800,
    ]);

    $response = get(route('product.show', $product->slug));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('product.in_flash_sale', true)
        ->where('product.flash_sale_price', 800)
        ->where('product.variants.0.id', $variantA->id)
        ->where('product.variants.0.in_flash_sale', true)
        ->where('product.variants.0.flash_sale_price', 800)
        ->where('product.variants.1.id', $variantB->id)
        ->where('product.variants.1.in_flash_sale', true)
        ->where('product.variants.1.flash_sale_price', 800)
        ->etc()
    );
});

test('a variant-specific flash-sale item overrides a whole-product one for that one variant only', function () {
    $product = visibleFlashProduct(['price' => 1000]);
    $cheaperVariant = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 1000, 'stock_quantity' => 10]);
    $otherVariant = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 1000, 'stock_quantity' => 10]);

    $flashSale = FlashSale::factory()->create(['starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'is_active' => true]);
    FlashSaleItem::factory()->create([
        'flash_sale_id' => $flashSale->id,
        'product_id' => $product->id,
        'variant_id' => null,
        'sale_price' => 800,
    ]);
    FlashSaleItem::factory()->create([
        'flash_sale_id' => $flashSale->id,
        'product_id' => $product->id,
        'variant_id' => $cheaperVariant->id,
        'sale_price' => 600,
    ]);

    $response = get(route('product.show', $product->slug));

    $response->assertInertia(fn (Assert $page) => $page
        ->where('product.variants.0.id', $cheaperVariant->id)
        ->where('product.variants.0.flash_sale_price', 600)
        ->where('product.variants.1.id', $otherVariant->id)
        ->where('product.variants.1.flash_sale_price', 800)
        ->etc()
    );
});

test('the homepage flash-sale carousel shows one card per product, priced at its lowest active item', function () {
    $product = Product::factory()->create(['is_active' => true, 'published_at' => now()->subDay(), 'price' => 1000]);
    $variantA = ProductVariant::factory()->create(['product_id' => $product->id, 'stock_quantity' => 10]);
    $variantB = ProductVariant::factory()->create(['product_id' => $product->id, 'stock_quantity' => 10]);

    $flashSale = FlashSale::factory()->create(['starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'is_active' => true]);
    FlashSaleItem::factory()->create([
        'flash_sale_id' => $flashSale->id,
        'product_id' => $product->id,
        'variant_id' => $variantA->id,
        'sale_price' => 900,
        'sold_count' => 2,
    ]);
    FlashSaleItem::factory()->create([
        'flash_sale_id' => $flashSale->id,
        'product_id' => $product->id,
        'variant_id' => $variantB->id,
        'sale_price' => 700,
        'sold_count' => 3,
    ]);

    $response = get(route('home'));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('activeFlashSale.items', 1)
        ->where('activeFlashSale.items.0.product_id', $product->id)
        ->where('activeFlashSale.items.0.sale_price', 700)
        ->where('activeFlashSale.items.0.sold_count', 5)
        ->where('activeFlashSale.items.0.product.flash_sale_price', 700)
        ->etc()
    );
});
