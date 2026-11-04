<?php

declare(strict_types=1);

use App\Exceptions\InsufficientStockException;
use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

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

test('a flash sale has no quantity of its own, even if one is submitted', function () {
    $product = Product::factory()->create(['stock_quantity' => 4]);

    actingAs($this->admin, 'admin');

    post(route('admin.flash-sales.store'), flashSalePayload([
        ['product_id' => $product->id, 'sale_price' => 500, 'stock_limit' => 2],
    ]))->assertSessionHasNoErrors()->assertRedirect(route('admin.flash-sales.index'));

    expect(FlashSale::first()->items()->first()->stock_limit)->toBeNull();
});

test('a sold out product can stay in a flash sale so the sale can still be saved', function () {
    $product = Product::factory()->create(['stock_quantity' => 0]);
    $variantProduct = Product::factory()->create(['stock_quantity' => 0]);
    $variant = ProductVariant::factory()->create(['product_id' => $variantProduct->id, 'stock_quantity' => 0]);
    $flashSale = FlashSale::factory()->create();
    $flashSale->items()->create(['product_id' => $product->id, 'sale_price' => 500]);

    actingAs($this->admin, 'admin');

    put(route('admin.flash-sales.update', $flashSale), flashSalePayload([
        ['product_id' => $product->id, 'sale_price' => 450],
        ['product_id' => $variantProduct->id, 'variant_id' => $variant->id, 'sale_price' => 300],
    ]))->assertSessionHasNoErrors();

    expect($flashSale->items()->count())->toBe(2);
});

test('flash sale pricing runs until main stock runs out, and never past it', function () {
    $product = Product::factory()->create(['stock_quantity' => 3]);
    $flashSale = FlashSale::factory()->create([
        'is_active' => true,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHour(),
    ]);
    $item = $flashSale->items()->create(['product_id' => $product->id, 'sale_price' => 500]);
    $order = new Order(['order_number' => 'ORD-TEST']);
    $order->id = 1;
    $inventory = app(InventoryService::class);

    expect($inventory->decrementForOrderItem($order, $product->id, null, 3, 500))->toBe($item->id)
        ->and($item->fresh()->sold_count)->toBe(3)
        ->and($product->fresh()->stock_quantity)->toBe(0);

    expect(fn () => $inventory->decrementForOrderItem($order, $product->id, null, 1, 500))
        ->toThrow(InsufficientStockException::class);
});

test('the edit form shows the live stock of a variant product', function () {
    $product = Product::factory()->create(['stock_quantity' => 100]);
    ProductVariant::factory()->create(['product_id' => $product->id, 'stock_quantity' => 3]);
    $flashSale = FlashSale::factory()->create();
    $flashSale->items()->create(['product_id' => $product->id, 'sale_price' => 500]);

    actingAs($this->admin, 'admin');

    $this->get(route('admin.flash-sales.edit', $flashSale))->assertInertia(fn (Assert $page) => $page
        ->where('flashSale.items.0.product.available_stock', 3));
});

test('the migration clears saved quantities and keeps sold counts', function () {
    $item = FlashSaleItem::factory()->create(['stock_limit' => 12, 'sold_count' => 7]);

    $migration = require glob(database_path('migrations/*_clear_flash_sale_item_stock_limits.php'))[0];
    $migration->up();

    expect($item->fresh()->stock_limit)->toBeNull()
        ->and($item->fresh()->sold_count)->toBe(7);
});
