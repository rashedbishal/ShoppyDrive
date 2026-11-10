<?php

declare(strict_types=1);

use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\LandingPage;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/*
 * PERF-001: the product page, landing page and AI assistant resolved each
 * variant's flash sale with its own findActiveFor() call — two queries per
 * variant, so a 30-variant product on sale took 91 queries. They now share
 * one batched findActiveForVariants(), which must give each variant exactly
 * the item findActiveFor() would.
 */

test('the batched lookup picks the same item findActiveFor() does for every variant', function () {
    $product = Product::factory()->create();
    [$variantA, $variantB, $variantC, $variantD] = ProductVariant::factory()->count(4)->create(['product_id' => $product->id])->all();

    $campaign = FlashSale::factory()->create(['starts_at' => now()->subHours(2), 'ends_at' => now()->addDay()]);
    $laterCampaign = FlashSale::factory()->create(['starts_at' => now()->subHour(), 'ends_at' => now()->addDay()]);
    $earlierCampaign = FlashSale::factory()->create(['starts_at' => now()->subHours(3), 'ends_at' => now()->addDay()]);
    $inactiveCampaign = FlashSale::factory()->create(['is_active' => false]);
    $expiredCampaign = FlashSale::factory()->create(['starts_at' => now()->subDays(2), 'ends_at' => now()->subDay()]);

    $productWide = FlashSaleItem::factory()->create(['flash_sale_id' => $campaign->id, 'product_id' => $product->id, 'sale_price' => 800]);
    FlashSaleItem::factory()->create(['flash_sale_id' => $laterCampaign->id, 'product_id' => $product->id, 'sale_price' => 700, 'stock_limit' => 5, 'sold_count' => 5]);
    $variantAItem = FlashSaleItem::factory()->create(['flash_sale_id' => $campaign->id, 'product_id' => $product->id, 'variant_id' => $variantA->id, 'sale_price' => 650]);
    FlashSaleItem::factory()->create(['flash_sale_id' => $campaign->id, 'product_id' => $product->id, 'variant_id' => $variantB->id, 'sale_price' => 900, 'stock_limit' => 3, 'sold_count' => 3]);
    FlashSaleItem::factory()->create(['flash_sale_id' => $inactiveCampaign->id, 'product_id' => $product->id, 'variant_id' => $variantC->id, 'sale_price' => 100]);
    FlashSaleItem::factory()->create(['flash_sale_id' => $expiredCampaign->id, 'product_id' => $product->id, 'variant_id' => $variantD->id, 'sale_price' => 50]);
    FlashSaleItem::factory()->create(['flash_sale_id' => $campaign->id, 'product_id' => $product->id, 'variant_id' => $variantD->id, 'sale_price' => 600]);
    $variantDItem = FlashSaleItem::factory()->create(['flash_sale_id' => $earlierCampaign->id, 'product_id' => $product->id, 'variant_id' => $variantD->id, 'sale_price' => 600]);
    FlashSaleItem::factory()->create(['flash_sale_id' => $campaign->id, 'sale_price' => 10]);

    $result = FlashSaleItem::findActiveForVariants($product->id, [$variantA->id, $variantB->id, $variantC->id, $variantD->id]);

    expect($result['product']?->id)->toBe($productWide->id)
        ->and($result['variants'][$variantA->id]?->id)->toBe($variantAItem->id)
        ->and($result['variants'][$variantB->id]?->id)->toBe($productWide->id)
        ->and($result['variants'][$variantC->id]?->id)->toBe($productWide->id)
        ->and($result['variants'][$variantD->id]?->id)->toBe($variantDItem->id);

    expect($result['product']?->id)->toBe(FlashSaleItem::findActiveFor($product->id, null)?->id);
    foreach ([$variantA, $variantB, $variantC, $variantD] as $variant) {
        expect($result['variants'][$variant->id]?->id)->toBe(FlashSaleItem::findActiveFor($product->id, $variant->id)?->id);
    }
});

test('the batched lookup reports nothing on sale for a product with no active flash-sale item', function () {
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
    FlashSaleItem::factory()->create(['flash_sale_id' => FlashSale::factory()->create(['is_active' => false])->id, 'product_id' => $product->id]);

    $result = FlashSaleItem::findActiveForVariants($product->id, [$variant->id]);

    expect($result)->toBe(['product' => null, 'variants' => [$variant->id => null]]);
});

/**
 * Callers warm the page once first: the first request of a test also fills
 * the layout cache and records the daily visit, unrelated to variants.
 *
 * @return int the queries the given request ran
 */
function queriesFor(callable $request): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $request();
    DB::disableQueryLog();

    return count(DB::getQueryLog());
}

function productOnFlashSaleWithVariants(int $variantCount): Product
{
    $product = Product::factory()->create(['is_active' => true, 'published_at' => now()->subDay()]);
    ProductVariant::factory()->count($variantCount)->create(['product_id' => $product->id]);
    FlashSaleItem::factory()->create(['flash_sale_id' => FlashSale::factory(), 'product_id' => $product->id, 'sale_price' => 100]);

    return $product;
}

test('the product page runs the same number of queries for 1 variant as for 30', function () {
    $oneVariant = productOnFlashSaleWithVariants(1);
    $thirtyVariants = productOnFlashSaleWithVariants(30);
    $this->get(route('product.show', $oneVariant->slug))->assertOk();

    $oneVariantQueries = queriesFor(fn () => $this->get(route('product.show', $oneVariant->slug))->assertOk());
    $thirtyVariantQueries = queriesFor(fn () => $this->get(route('product.show', $thirtyVariants->slug))->assertOk());

    expect($thirtyVariantQueries)->toBe($oneVariantQueries);
});

test('a landing page runs the same number of queries for 1 variant as for 30', function () {
    $oneVariant = LandingPage::create(['title' => 'One', 'slug' => 'one-variant', 'product_id' => productOnFlashSaleWithVariants(1)->id, 'is_active' => true, 'sections' => []]);
    $thirtyVariants = LandingPage::create(['title' => 'Thirty', 'slug' => 'thirty-variants', 'product_id' => productOnFlashSaleWithVariants(30)->id, 'is_active' => true, 'sections' => []]);
    $this->get(route('lp.show', $oneVariant->slug))->assertOk();

    $oneVariantQueries = queriesFor(fn () => $this->get(route('lp.show', $oneVariant->slug))->assertOk());
    $thirtyVariantQueries = queriesFor(fn () => $this->get(route('lp.show', $thirtyVariants->slug))->assertOk());

    expect($thirtyVariantQueries)->toBe($oneVariantQueries);
});
