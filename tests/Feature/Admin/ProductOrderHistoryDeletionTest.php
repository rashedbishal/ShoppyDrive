<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = createAdmin([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => bcrypt('password'),
    ]);
});

function orderItemForDeletionTest(Product $product): OrderItem
{
    $order = Order::create([
        'order_number' => 'ORD-PRODDEL-'.$product->id,
        'status' => 'delivered',
        'payment_status' => 'paid',
        'payment_method' => 'cod',
        'subtotal' => 100,
        'shipping_cost' => 0,
        'discount' => 0,
        'tax' => 0,
        'total' => 100,
        'shipping_address' => [],
        'billing_address' => [],
    ]);

    return $order->items()->create([
        'product_id' => $product->id,
        'variant_id' => null,
        'product_name' => $product->name,
        'price' => 100,
        'quantity' => 1,
        'subtotal' => 100,
        'product_snapshot' => ['id' => $product->id, 'name' => $product->name],
    ]);
}

function orderItemForVariantDeletionTest(Product $product, ProductVariant $variant): OrderItem
{
    $order = Order::create([
        'order_number' => 'ORD-VARDEL-'.$variant->id,
        'status' => 'delivered',
        'payment_status' => 'paid',
        'payment_method' => 'cod',
        'subtotal' => 100,
        'shipping_cost' => 0,
        'discount' => 0,
        'tax' => 0,
        'total' => 100,
        'shipping_address' => [],
        'billing_address' => [],
    ]);

    return $order->items()->create([
        'product_id' => $product->id,
        'variant_id' => $variant->id,
        'product_name' => $product->name,
        'sku' => $variant->sku,
        'price' => 100,
        'quantity' => 1,
        'subtotal' => 100,
        'product_snapshot' => ['id' => $product->id, 'name' => $product->name, 'variant_id' => $variant->id],
    ]);
}

test('admin cannot delete a product that has order history', function () {
    $product = Product::factory()->create();
    $orderItem = orderItemForDeletionTest($product);

    actingAs($this->admin, 'admin');

    $response = $this->delete(route('admin.products.destroy', $product->id));

    $response->assertRedirect();
    expect(Product::find($product->id))->not->toBeNull();
    expect(OrderItem::find($orderItem->id))->not->toBeNull();
});

test('admin can permanently delete a product with no order history', function () {
    $product = Product::factory()->create();

    actingAs($this->admin, 'admin');

    $response = $this->delete(route('admin.products.destroy', $product->id));

    $response->assertRedirect(route('admin.products.index'));
    expect(Product::withTrashed()->find($product->id))->toBeNull();
});

test('force-deleting a product with order history is refused outright', function () {
    $product = Product::factory()->create();
    $orderItem = orderItemForDeletionTest($product);

    expect(fn () => $product->forceDelete())->toThrow(RuntimeException::class);

    expect(Product::find($product->id))->not->toBeNull();
    expect(OrderItem::find($orderItem->id))->not->toBeNull();
});

test('force-deleting a product with no order history still works', function () {
    $product = Product::factory()->create();

    $product->forceDelete();

    expect(Product::withTrashed()->find($product->id))->toBeNull();
});

test('a raw delete of a product no longer cascades away its order items', function () {
    $product = Product::factory()->create();
    $orderItem = orderItemForDeletionTest($product);

    // Bypasses Eloquent model events entirely, to prove the FK itself (not
    // just the application-level guards above) now preserves order history
    // instead of cascading, per the migration that changed
    // order_items.product_id from cascadeOnDelete() to nullOnDelete().
    DB::table('products')->where('id', $product->id)->delete();

    $survivingItem = OrderItem::find($orderItem->id);
    expect($survivingItem)->not->toBeNull();
    expect($survivingItem->product_id)->toBeNull();
    expect($survivingItem->product_name)->toBe($product->name);
});

test('a raw delete of a product variant no longer cascades away its order items', function () {
    // DB-004: order_items.variant_id used to cascadeOnDelete(), so a
    // hard-deleted variant silently destroyed the order_items row that
    // referenced it — the same financial-history-loss bug the product_id
    // fix addressed, just on the sibling column. Bypasses Eloquent model
    // events entirely (same technique as the product_id test above) to
    // prove the FK itself, not an application-level guard, now preserves
    // the line item.
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
    $orderItem = orderItemForVariantDeletionTest($product, $variant);

    DB::table('product_variants')->where('id', $variant->id)->delete();

    $survivingItem = OrderItem::find($orderItem->id);
    expect($survivingItem)->not->toBeNull();
    expect($survivingItem->variant_id)->toBeNull();
    expect($survivingItem->product_id)->toBe($product->id);
    expect($survivingItem->product_snapshot)->not->toBeNull();
    expect($survivingItem->product_snapshot['variant_id'])->toBe($variant->id);
});

test('a hard-deleted product cascading to its variants no longer cascades away its order items', function () {
    // Covers the realistic end-to-end chain: products.id -> (cascade)
    // product_variants -> (used to cascade, now nullOnDelete) order_items.
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
    $orderItem = orderItemForVariantDeletionTest($product, $variant);

    DB::table('products')->where('id', $product->id)->delete();

    expect(ProductVariant::find($variant->id))->toBeNull();

    $survivingItem = OrderItem::find($orderItem->id);
    expect($survivingItem)->not->toBeNull();
    expect($survivingItem->variant_id)->toBeNull();
    expect($survivingItem->product_id)->toBeNull();
    expect($survivingItem->product_name)->toBe($product->name);
});
