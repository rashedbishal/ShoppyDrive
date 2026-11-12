<?php

declare(strict_types=1);

use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\Product;
use App\Models\ProductVariant;

/*
 * DISC-14: the product JSON-LD offer must match what the storefront sells:
 * live stock (variants' own stock for variant products; the product column can
 * be stale) and the price actually charged (active flash price, else variant
 * price, else product price). Values are read from the decoded JSON-LD.
 */

function disc14Offers(Product $product): array
{
    config(['inertia.ssr.enabled' => false]);

    $html = test()->get(route('product.show', $product->slug))->assertOk()->getContent();
    expect(preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $match))->toBe(1);

    return json_decode($match[1], true, flags: JSON_THROW_ON_ERROR)['offers'];
}

function disc14Product(array $attributes = []): Product
{
    return Product::factory()->create(array_merge([
        'price' => 1000, 'stock_quantity' => 5, 'is_active' => true, 'published_at' => now()->subDay(),
    ], $attributes));
}

function disc14Sale(Product $product, float $salePrice, ?ProductVariant $variant = null, bool $active = true): FlashSaleItem
{
    $sale = FlashSale::create([
        'name' => 'Sale', 'is_active' => true,
        'starts_at' => $active ? now()->subHour() : now()->subDays(3),
        'ends_at' => $active ? now()->addHour() : now()->subDay(),
    ]);

    return FlashSaleItem::create([
        'flash_sale_id' => $sale->id, 'product_id' => $product->id, 'variant_id' => $variant?->id,
        'sale_price' => $salePrice, 'stock_limit' => null,
    ]);
}

test('a variant product whose variants are all sold out is OutOfStock even though its stored stock column is stale', function () {
    $product = disc14Product(['stock_quantity' => 5]); // stale sum from an old admin save
    ProductVariant::factory()->create(['product_id' => $product->id, 'stock_quantity' => 0, 'price' => 1100]);
    ProductVariant::factory()->create(['product_id' => $product->id, 'stock_quantity' => 0, 'price' => 1200]);

    expect(disc14Offers($product)['availability'])->toBe('https://schema.org/OutOfStock');
});

test('a variant product with some stock is InStock with an AggregateOffer over the variant prices', function () {
    $product = disc14Product(['stock_quantity' => 0, 'price' => 1000]);
    ProductVariant::factory()->create(['product_id' => $product->id, 'stock_quantity' => 0, 'price' => 1500]);
    ProductVariant::factory()->create(['product_id' => $product->id, 'stock_quantity' => 3, 'price' => 1200]);
    ProductVariant::factory()->create(['product_id' => $product->id, 'stock_quantity' => 2, 'price' => null]); // falls back to product price

    expect(disc14Offers($product))->toBe([
        '@type' => 'AggregateOffer',
        'priceCurrency' => 'BDT',
        'lowPrice' => '1000.00',
        'highPrice' => '1500.00',
        'offerCount' => 3,
        'availability' => 'https://schema.org/InStock',
    ]);
});

test('an active flash sale on one variant lowers that variant, an expired one is ignored, a whole-product sale covers every variant', function () {
    $product = disc14Product(['price' => 1000]);
    $cheap = ProductVariant::factory()->create(['product_id' => $product->id, 'stock_quantity' => 4, 'price' => 1200]);
    $dear = ProductVariant::factory()->create(['product_id' => $product->id, 'stock_quantity' => 4, 'price' => 1400]);
    disc14Sale($product, 800, $cheap);
    disc14Sale($product, 100, $dear, active: false); // expired: must not count

    $offers = disc14Offers($product);
    expect($offers['lowPrice'])->toBe('800.00')->and($offers['highPrice'])->toBe('1400.00');

    disc14Sale($product, 900); // whole-product sale: applies to the variant without its own sale
    $offers = disc14Offers($product);
    expect($offers['lowPrice'])->toBe('800.00')->and($offers['highPrice'])->toBe('900.00');
});

test('a simple product on an active flash sale shows the sale price; an expired sale shows the regular price', function () {
    $product = disc14Product(['price' => 1000, 'stock_quantity' => 2]);
    disc14Sale($product, 750, active: false);

    expect(disc14Offers($product)['price'])->toBe((string) $product->fresh()->price); // unchanged previous format
    expect((float) disc14Offers($product)['price'])->toBe(1000.0);

    $sale = disc14Sale($product, 750);

    expect(disc14Offers($product))->toBe([
        '@type' => 'Offer',
        'priceCurrency' => 'BDT',
        'price' => (string) $sale->fresh()->sale_price,
        'availability' => 'https://schema.org/InStock',
    ])->and((float) disc14Offers($product)['price'])->toBe(750.0);
});

test('a simple product without a sale keeps exactly the previous Offer, including OutOfStock at zero stock', function () {
    $inStock = disc14Product(['price' => 1500, 'stock_quantity' => 3]);
    $soldOut = disc14Product(['price' => 1500, 'stock_quantity' => 0]);

    expect(disc14Offers($inStock))->toBe([
        '@type' => 'Offer', 'priceCurrency' => 'BDT', 'price' => (string) $inStock->price, 'availability' => 'https://schema.org/InStock',
    ])->and(disc14Offers($soldOut)['availability'])->toBe('https://schema.org/OutOfStock');
});
