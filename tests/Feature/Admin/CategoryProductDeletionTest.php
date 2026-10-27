<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SubCategory;

function makeHistoricalOrderItem(Product $product): OrderItem
{
    $order = Order::create([
        'order_number' => 'ORD-CATDEL-'.$product->id,
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

test('force-deleting a category is refused outright when any of its products has order history', function () {
    $category = Category::factory()->create();
    $referencedProduct = Product::factory()->create(['category_id' => $category->id]);
    $orderItem = makeHistoricalOrderItem($referencedProduct);

    expect(fn () => $category->forceDelete())->toThrow(RuntimeException::class);

    // Nothing was touched — not the category, not the product, not the order item.
    expect(Category::withTrashed()->find($category->id))->not->toBeNull();
    expect(Category::withTrashed()->find($category->id)->trashed())->toBeFalse();
    expect(Product::find($referencedProduct->id))->not->toBeNull();
    expect(OrderItem::find($orderItem->id))->not->toBeNull();
});

test('force-deleting a category with no historical products still works', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id]);

    $category->forceDelete();

    expect(Category::withTrashed()->find($category->id))->toBeNull();
    expect(Product::withTrashed()->find($product->id))->toBeNull();
});

test('force-deleting a sub-category is refused outright when any of its products has order history', function () {
    $subCategory = SubCategory::factory()->create();
    $referencedProduct = Product::factory()->create(['sub_category_id' => $subCategory->id]);
    $orderItem = makeHistoricalOrderItem($referencedProduct);

    expect(fn () => $subCategory->forceDelete())->toThrow(RuntimeException::class);

    expect(SubCategory::withTrashed()->find($subCategory->id))->not->toBeNull();
    expect(SubCategory::withTrashed()->find($subCategory->id)->trashed())->toBeFalse();
    expect(Product::find($referencedProduct->id))->not->toBeNull();
    expect(OrderItem::find($orderItem->id))->not->toBeNull();
});

test('force-deleting a category whose order-history products live under one of its sub-categories is also refused', function () {
    $category = Category::factory()->create();
    $subCategory = SubCategory::factory()->create(['category_id' => $category->id]);
    $referencedProduct = Product::factory()->create(['category_id' => $category->id, 'sub_category_id' => $subCategory->id]);
    $orderItem = makeHistoricalOrderItem($referencedProduct);

    expect(fn () => $category->forceDelete())->toThrow(RuntimeException::class);

    expect(SubCategory::withTrashed()->find($subCategory->id))->not->toBeNull();
    expect(SubCategory::withTrashed()->find($subCategory->id)->trashed())->toBeFalse();
    expect(Product::find($referencedProduct->id))->not->toBeNull();
    expect(OrderItem::find($orderItem->id))->not->toBeNull();
});

test('a plain (non-force) category delete still only soft-deletes its products', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id]);

    $category->delete();

    expect(Product::withTrashed()->find($product->id))->not->toBeNull();
    expect(Product::withTrashed()->find($product->id)->trashed())->toBeTrue();
});
