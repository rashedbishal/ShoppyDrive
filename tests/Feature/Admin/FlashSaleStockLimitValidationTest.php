<?php

declare(strict_types=1);

use App\Models\FlashSale;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = createAdmin();
});

function flashSalePayload(array $items): array
{
    return [
        'name' => 'Weekend Flash Sale',
        'starts_at' => now()->toDateTimeString(),
        'ends_at' => now()->addDays(7)->toDateTimeString(),
        'is_active' => true,
        'items' => $items,
    ];
}

test('creating a flash sale item is rejected when stock_limit exceeds the product\'s real stock', function () {
    $product = Product::factory()->create(['stock_quantity' => 4]);

    actingAs($this->admin, 'admin');

    $response = post(route('admin.flash-sales.store'), flashSalePayload([
        ['product_id' => $product->id, 'sale_price' => 500, 'stock_limit' => 5],
    ]));

    $response->assertSessionHasErrors(['items.0.stock_limit']);
    expect(FlashSale::count())->toBe(0);
});

test('creating a flash sale item is accepted when stock_limit exactly matches the product\'s real stock', function () {
    $product = Product::factory()->create(['stock_quantity' => 4]);

    actingAs($this->admin, 'admin');

    $response = post(route('admin.flash-sales.store'), flashSalePayload([
        ['product_id' => $product->id, 'sale_price' => 500, 'stock_limit' => 4],
    ]));

    $response->assertSessionDoesntHaveErrors();
    $response->assertRedirect(route('admin.flash-sales.index'));

    $flashSale = FlashSale::first();
    expect($flashSale)->not->toBeNull();
    expect($flashSale->items()->first()->stock_limit)->toBe(4);
});

test('creating a flash sale item is accepted when stock_limit is below the product\'s real stock', function () {
    $product = Product::factory()->create(['stock_quantity' => 10]);

    actingAs($this->admin, 'admin');

    $response = post(route('admin.flash-sales.store'), flashSalePayload([
        ['product_id' => $product->id, 'sale_price' => 500, 'stock_limit' => 3],
    ]));

    $response->assertSessionDoesntHaveErrors();
    expect(FlashSale::first()->items()->first()->stock_limit)->toBe(3);
});

test('stock_limit is checked against the summed variant stock, not the denormalized column, for a variant product', function () {
    // The admin product form keeps `stock_quantity` in sync with the
    // variant sum on save, but this proves the check uses the live
    // variant sum (available_stock) rather than coincidentally relying on
    // that denormalized column already being correct.
    $product = Product::factory()->create(['stock_quantity' => 999]);
    ProductVariant::factory()->create(['product_id' => $product->id, 'stock_quantity' => 2]);
    ProductVariant::factory()->create(['product_id' => $product->id, 'stock_quantity' => 3]);

    actingAs($this->admin, 'admin');

    // Real available stock is 2 + 3 = 5, not the stale-looking 999 column.
    $rejected = post(route('admin.flash-sales.store'), flashSalePayload([
        ['product_id' => $product->id, 'sale_price' => 500, 'stock_limit' => 6],
    ]));
    $rejected->assertSessionHasErrors(['items.0.stock_limit']);
    expect(FlashSale::count())->toBe(0);

    $accepted = post(route('admin.flash-sales.store'), flashSalePayload([
        ['product_id' => $product->id, 'sale_price' => 500, 'stock_limit' => 5],
    ]));
    $accepted->assertSessionDoesntHaveErrors();
    expect(FlashSale::count())->toBe(1);
});

test('updating a flash sale item is also rejected when stock_limit exceeds the product\'s real stock', function () {
    $product = Product::factory()->create(['stock_quantity' => 4]);

    $flashSale = FlashSale::create([
        'name' => 'Weekend Flash Sale',
        'starts_at' => now(),
        'ends_at' => now()->addDays(7),
        'is_active' => true,
    ]);
    $flashSale->items()->create([
        'product_id' => $product->id,
        'sale_price' => 500,
        'stock_limit' => 4,
    ]);

    actingAs($this->admin, 'admin');

    $response = put(route('admin.flash-sales.update', $flashSale), flashSalePayload([
        ['product_id' => $product->id, 'sale_price' => 500, 'stock_limit' => 5],
    ]));

    $response->assertSessionHasErrors(['items.0.stock_limit']);
    expect($flashSale->items()->first()->stock_limit)->toBe(4);
});

test('a flash sale item referencing an out-of-stock product can never be given a stock_limit', function () {
    $product = Product::factory()->create(['stock_quantity' => 0]);

    actingAs($this->admin, 'admin');

    $response = post(route('admin.flash-sales.store'), flashSalePayload([
        ['product_id' => $product->id, 'sale_price' => 500, 'stock_limit' => 1],
    ]));

    $response->assertSessionHasErrors(['items.0.stock_limit']);
    expect(FlashSale::count())->toBe(0);
});

test('a variant-specific flash sale item is checked against that variant\'s own stock, not the product\'s total', function () {
    // The product's summed stock (10) is well above the one variant being
    // targeted (3) — proves the check uses the specific variant's own
    // stock_quantity, not the product-wide available_stock, once a
    // variant_id is given.
    $product = Product::factory()->create(['stock_quantity' => 999]);
    $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'stock_quantity' => 3]);
    ProductVariant::factory()->create(['product_id' => $product->id, 'stock_quantity' => 7]);

    actingAs($this->admin, 'admin');

    $rejected = post(route('admin.flash-sales.store'), flashSalePayload([
        ['product_id' => $product->id, 'variant_id' => $variant->id, 'sale_price' => 500, 'stock_limit' => 4],
    ]));
    $rejected->assertSessionHasErrors(['items.0.stock_limit']);
    expect(FlashSale::count())->toBe(0);

    $accepted = post(route('admin.flash-sales.store'), flashSalePayload([
        ['product_id' => $product->id, 'variant_id' => $variant->id, 'sale_price' => 500, 'stock_limit' => 3],
    ]));
    $accepted->assertSessionDoesntHaveErrors();
    expect(FlashSale::first()->items()->first()->variant_id)->toBe($variant->id);
});
