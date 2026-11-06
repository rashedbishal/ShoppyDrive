<?php

declare(strict_types=1);

use App\Models\FlashSale;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = createAdmin();
});

function flashSaleVariantPayload(array $items): array
{
    return [
        'name' => 'Weekend Flash Sale',
        'starts_at' => now()->toDateTimeString(),
        'ends_at' => now()->addDays(7)->toDateTimeString(),
        'is_active' => true,
        'items' => $items,
    ];
}

test('the flash sale product search endpoint returns each product\'s variants with their own stock', function () {
    $product = Product::factory()->create(['name' => 'Classic Tee', 'stock_quantity' => 50, 'is_active' => true]);
    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'sku' => 'TEE-RED-M',
        'stock_quantity' => 12,
        'attributes' => ['Size' => 'M', 'Color' => 'Red'],
    ]);

    actingAs($this->admin, 'admin');

    $response = get(route('admin.flash-sales.products.search', ['q' => 'Classic']));

    $response->assertSuccessful();
    $response->assertJsonFragment(['id' => $product->id, 'name' => 'Classic Tee']);
    $response->assertJsonFragment([
        'id' => $variant->id,
        'sku' => 'TEE-RED-M',
        'stock_quantity' => 12,
        'attributes' => ['Size' => 'M', 'Color' => 'Red'],
    ]);
});

test('the flash sale product search endpoint requires admin authentication', function () {
    $response = get(route('admin.flash-sales.products.search', ['q' => 'anything']));

    $response->assertRedirect(route('admin.login'));
});

test('a variant_id that does not belong to the item\'s product_id is rejected', function () {
    $product = Product::factory()->create();
    $otherProduct = Product::factory()->create();
    $foreignVariant = ProductVariant::factory()->create(['product_id' => $otherProduct->id, 'stock_quantity' => 10]);

    actingAs($this->admin, 'admin');

    $response = post(route('admin.flash-sales.store'), flashSaleVariantPayload([
        ['product_id' => $product->id, 'variant_id' => $foreignVariant->id, 'sale_price' => 500, 'stock_limit' => 1],
    ]));

    $response->assertSessionHasErrors(['items.0.variant_id']);
    expect(FlashSale::count())->toBe(0);
});

test('the same product/variant combination cannot be added twice in one submission', function () {
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'stock_quantity' => 10]);

    actingAs($this->admin, 'admin');

    $response = post(route('admin.flash-sales.store'), flashSaleVariantPayload([
        ['product_id' => $product->id, 'variant_id' => $variant->id, 'sale_price' => 500, 'stock_limit' => 1],
        ['product_id' => $product->id, 'variant_id' => $variant->id, 'sale_price' => 450, 'stock_limit' => 2],
    ]));

    $response->assertSessionHasErrors(['items.1.product_id']);
    expect(FlashSale::count())->toBe(0);
});

test('the same product can have both a whole-product item and a specific-variant item in one submission', function () {
    $product = Product::factory()->create(['price' => 100000, 'stock_quantity' => 999]);
    $variant = ProductVariant::factory()->create(['price' => 100000, 'product_id' => $product->id, 'stock_quantity' => 5]);
    ProductVariant::factory()->create(['price' => 100000, 'product_id' => $product->id, 'stock_quantity' => 5]);

    actingAs($this->admin, 'admin');

    $response = post(route('admin.flash-sales.store'), flashSaleVariantPayload([
        ['product_id' => $product->id, 'variant_id' => null, 'sale_price' => 900, 'stock_limit' => 10],
        ['product_id' => $product->id, 'variant_id' => $variant->id, 'sale_price' => 700, 'stock_limit' => 5],
    ]));

    $response->assertSessionDoesntHaveErrors();

    $items = FlashSale::first()->items;
    expect($items)->toHaveCount(2);
    expect($items->firstWhere('variant_id', null)->sale_price)->toBe(900.0);
    expect($items->firstWhere('variant_id', $variant->id)->sale_price)->toBe(700.0);
});

test('store persists variant_id, and update can change it', function () {
    $product = Product::factory()->create(['price' => 100000]);
    $variant = ProductVariant::factory()->create(['price' => 100000, 'product_id' => $product->id, 'stock_quantity' => 10]);
    $otherVariant = ProductVariant::factory()->create(['price' => 100000, 'product_id' => $product->id, 'stock_quantity' => 10]);

    actingAs($this->admin, 'admin');

    post(route('admin.flash-sales.store'), flashSaleVariantPayload([
        ['product_id' => $product->id, 'variant_id' => $variant->id, 'sale_price' => 500, 'stock_limit' => 5],
    ]))->assertSessionDoesntHaveErrors();

    $flashSale = FlashSale::first();
    expect($flashSale->items->first()->variant_id)->toBe($variant->id);

    $this->put(route('admin.flash-sales.update', $flashSale), flashSaleVariantPayload([
        ['product_id' => $product->id, 'variant_id' => $otherVariant->id, 'sale_price' => 450, 'stock_limit' => 5],
    ]))->assertSessionDoesntHaveErrors();

    expect($flashSale->items()->first()->variant_id)->toBe($otherVariant->id);
});
