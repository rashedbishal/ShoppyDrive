<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Http\Controllers\Admin\OrderController;
use App\Models\Coupon;
use App\Models\CouponUse;
use App\Models\InventoryLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;

use function Pest\Laravel\actingAs;

function orderForCancelGuardTest(array $attributes = []): Order
{
    return Order::create(array_merge([
        'order_number' => 'ORD-CANCELGUARD-'.uniqid(),
        'status' => OrderStatus::PENDING,
        'payment_status' => 'unpaid',
        'payment_method' => 'cod',
        'subtotal' => 500,
        'shipping_cost' => 60,
        'discount' => 0,
        'tax' => 0,
        'total' => 560,
        'shipping_address' => ['name' => 'Rafiq Islam', 'phone' => '01712345678'],
        'billing_address' => [],
    ], $attributes));
}

test('admin cannot cancel a delivered order', function () {
    $admin = createAdmin(['name' => 'Admin', 'email' => 'cancel-guard-1@example.com', 'password' => bcrypt('password')]);
    $product = Product::factory()->create(['stock_quantity' => 5]);
    $order = orderForCancelGuardTest(['status' => OrderStatus::DELIVERED, 'payment_status' => 'paid']);
    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'price' => 500,
        'quantity' => 1,
        'subtotal' => 500,
        'product_snapshot' => ['id' => $product->id, 'name' => $product->name],
    ]);

    actingAs($admin, 'admin');

    $response = $this->patch(route('admin.orders.cancel', $order), ['reason' => 'Should be refused']);

    $response->assertRedirect();
    $response->assertSessionHas('error');

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::DELIVERED);
    expect($order->stock_restored_at)->toBeNull();
    expect($product->refresh()->stock_quantity)->toBe(5);
    expect(InventoryLog::where('product_id', $product->id)->where('type', 'release')->exists())->toBeFalse();
});

test('admin cannot cancel an already refunded order', function () {
    $admin = createAdmin(['name' => 'Admin', 'email' => 'cancel-guard-2@example.com', 'password' => bcrypt('password')]);
    $order = orderForCancelGuardTest(['status' => OrderStatus::REFUNDED]);

    actingAs($admin, 'admin');

    $response = $this->patch(route('admin.orders.cancel', $order), ['reason' => 'Should be refused']);

    $response->assertRedirect();
    expect($order->refresh()->status)->toBe(OrderStatus::REFUNDED);
});

test('admin cannot cancel an already cancelled order', function () {
    $admin = createAdmin(['name' => 'Admin', 'email' => 'cancel-guard-3@example.com', 'password' => bcrypt('password')]);
    $order = orderForCancelGuardTest(['status' => OrderStatus::CANCELLED]);

    actingAs($admin, 'admin');

    $response = $this->patch(route('admin.orders.cancel', $order), ['reason' => 'Should be refused']);

    $response->assertRedirect();
    expect($order->refresh()->status)->toBe(OrderStatus::CANCELLED);
});

test('rejected cancellation leaves released coupon usage untouched', function () {
    $admin = createAdmin(['name' => 'Admin', 'email' => 'cancel-guard-4@example.com', 'password' => bcrypt('password')]);
    $customer = User::factory()->create();
    $coupon = Coupon::factory()->create(['type' => 'fixed', 'value' => 10, 'used_count' => 1]);
    $order = orderForCancelGuardTest(['status' => OrderStatus::DELIVERED, 'user_id' => $customer->id, 'coupon_id' => $coupon->id]);
    CouponUse::create(['coupon_id' => $coupon->id, 'user_id' => $customer->id, 'order_id' => $order->id, 'discount_amount' => 10]);

    actingAs($admin, 'admin');
    $this->patch(route('admin.orders.cancel', $order), ['reason' => 'Should be refused']);

    expect($coupon->refresh()->used_count)->toBe(1);
    expect(CouponUse::where('order_id', $order->id)->exists())->toBeTrue();
    expect($order->refresh()->coupon_released_at)->toBeNull();
});

test('admin can still cancel a pending order', function () {
    $admin = createAdmin(['name' => 'Admin', 'email' => 'cancel-guard-5@example.com', 'password' => bcrypt('password')]);
    $order = orderForCancelGuardTest(['status' => OrderStatus::PENDING]);

    actingAs($admin, 'admin');

    $response = $this->patch(route('admin.orders.cancel', $order), ['reason' => 'Customer changed their mind']);

    $response->assertRedirect();
    $response->assertSessionHas('success');
    expect($order->refresh()->status)->toBe(OrderStatus::CANCELLED);
});

// FSA-11 replaced "admin can still cancel a shipped order": the parcel is
// with the courier, so only the courier's cancel/return report cancels it.
test('admin cannot cancel a shipped order', function () {
    $admin = createAdmin(['name' => 'Admin', 'email' => 'cancel-guard-6@example.com', 'password' => bcrypt('password')]);
    $order = orderForCancelGuardTest(['status' => OrderStatus::SHIPPED]);

    actingAs($admin, 'admin');

    $response = $this->patch(route('admin.orders.cancel', $order), ['reason' => 'Lost in transit']);

    $response->assertRedirect();
    $response->assertSessionHas('error', OrderController::SHIPPED_CANCEL_MESSAGE);
    expect($order->refresh()->status)->toBe(OrderStatus::SHIPPED);
});
