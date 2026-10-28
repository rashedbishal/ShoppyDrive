<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;

function createOrderWithVariantItem(?User $user, Product $product, ProductVariant $variant): Order
{
    $order = Order::create([
        'order_number' => 'ORD-VARIANT-'.uniqid(),
        'user_id' => $user?->id,
        'status' => OrderStatus::PENDING,
        'payment_status' => 'pending',
        'payment_method' => 'cod',
        'subtotal' => $variant->price,
        'shipping_cost' => 0,
        'discount' => 0,
        'tax' => 0,
        'total' => $variant->price,
        'shipping_address' => ['name' => 'Buyer', 'phone' => '01700000000', 'address_line1' => 'House 1', 'city' => 'Dhaka', 'state' => 'Dhaka', 'country' => 'Bangladesh'],
        'billing_address' => ['name' => 'Buyer', 'phone' => '01700000000', 'address_line1' => 'House 1', 'city' => 'Dhaka', 'state' => 'Dhaka', 'country' => 'Bangladesh'],
    ]);

    $order->items()->create([
        'product_id' => $product->id,
        'variant_id' => $variant->id,
        'product_name' => $product->name,
        'price' => $variant->price,
        'quantity' => 1,
        'subtotal' => $variant->price,
        'product_snapshot' => ['variant_attributes' => $variant->attributes],
    ]);

    return $order;
}

test('the account order detail page includes the ordered item\'s variant attributes', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'attributes' => ['Size' => 'XL', 'Color' => 'Red'],
    ]);

    $order = createOrderWithVariantItem($user, $product, $variant);

    $this->actingAs($user)
        ->get(route('account.orders.show', $order->order_number))
        ->assertInertia(fn ($page) => $page
            ->where('order.items.0.variant.attributes.Size', 'XL')
            ->where('order.items.0.variant.attributes.Color', 'Red')
        );
});

test('the admin order detail page includes the ordered item\'s variant attributes', function () {
    $admin = createAdmin([
        'name' => 'Admin User',
        'email' => 'admin-order-variant@example.com',
        'password' => bcrypt('password'),
    ]);
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'attributes' => ['Size' => 'XL', 'Color' => 'Red'],
    ]);

    $order = createOrderWithVariantItem(null, $product, $variant);

    $this->actingAs($admin, 'admin')
        ->get(route('admin.orders.show', $order))
        ->assertInertia(fn ($page) => $page
            ->where('order.items.0.variant.attributes.Size', 'XL')
            ->where('order.items.0.variant.attributes.Color', 'Red')
        );
});
