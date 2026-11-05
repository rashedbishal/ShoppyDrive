<?php

declare(strict_types=1);

/*
 * BIZ-012: refunding a whole shipped or delivered order used to put every
 * unit back in stock whether or not the goods came back. The admin now
 * confirms it; unconfirmed, stock stays as it is.
 */

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderDeliveryVerification;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    $this->product = Product::factory()->create(['price' => 500, 'stock_quantity' => 8]);
});

/** A paid order for 2 units whose stock was already taken (8 left of 10). */
function restockTestOrder(Product $product, OrderStatus $status): Order
{
    $user = User::factory()->create();
    $address = ['name' => $user->name, 'phone' => '01700000000', 'address_line1' => 'House 1', 'city' => 'Dhaka', 'state' => 'Dhaka', 'country' => 'Bangladesh'];

    $order = Order::create([
        'order_number' => 'ORD-RESTOCK-'.uniqid(),
        'user_id' => $user->id,
        'status' => $status,
        'payment_status' => 'paid',
        'payment_method' => 'mobile_banking',
        'subtotal' => 1000,
        'shipping_cost' => 0,
        'discount' => 0,
        'tax' => 0,
        'total' => 1000,
        'shipping_address' => $address,
        'billing_address' => $address,
        'delivered_at' => $status === OrderStatus::DELIVERED ? now()->subDay() : null,
    ]);
    $order->items()->create([
        'product_id' => $product->id, 'variant_id' => null, 'product_name' => $product->name,
        'price' => 500, 'quantity' => 2, 'subtotal' => 1000, 'product_snapshot' => [],
    ]);

    // Shipped and delivered orders need courier verification before any
    // refund (PAY-RETURN-001), unrelated to restocking: delivered as
    // delivered, an in-transit order as not delivered.
    if ($status === OrderStatus::DELIVERED) {
        courierVerifiedForRefund($order);
    } elseif ($status === OrderStatus::SHIPPED) {
        makeCourierReadinessCurrent();
        linkTestConsignment($order);
        verifyConsignmentForTest($order, OrderDeliveryVerification::OUTCOME_NOT_DELIVERED);
    }

    return $order->refresh();
}

function refundWholeOrder(Order $order, array $extra = []): void
{
    actingAs(createMotherAdmin(), 'admin')
        ->patch(route('admin.orders.markRefunded', $order), [...wholeOrderRefundPayload($order), ...$extra])
        ->assertSessionHas('success');
}

test('a delivered order refunded without confirming the goods came back leaves stock unchanged', function () {
    $order = restockTestOrder($this->product, OrderStatus::DELIVERED);

    refundWholeOrder($order);

    expect($order->refresh()->status)->toBe(OrderStatus::REFUNDED)
        ->and($order->restock_skipped_at)->not->toBeNull()
        ->and($order->stock_restored_at)->toBeNull()
        ->and($this->product->refresh()->stock_quantity)->toBe(8)
        ->and($order->statusHistories()->latest('id')->value('notes'))->toContain('Stock not restored');
});

test('a delivered order refunded with the goods confirmed returned is restocked', function () {
    $order = restockTestOrder($this->product, OrderStatus::DELIVERED);

    refundWholeOrder($order, ['goods_returned' => true]);

    expect($order->refresh()->restock_skipped_at)->toBeNull()
        ->and($order->stock_restored_at)->not->toBeNull()
        ->and($this->product->refresh()->stock_quantity)->toBe(10)
        ->and($order->statusHistories()->latest('id')->value('notes'))->toContain('Goods confirmed returned');
});

test('a shipped order refunded without confirmation leaves stock unchanged too', function () {
    $order = restockTestOrder($this->product, OrderStatus::SHIPPED);

    refundWholeOrder($order);

    expect($order->refresh()->restock_skipped_at)->not->toBeNull()
        ->and($this->product->refresh()->stock_quantity)->toBe(8);
});

test('an order that never shipped is still restocked on refund, with no question asked', function (OrderStatus $status) {
    $order = restockTestOrder($this->product, $status);

    refundWholeOrder($order);

    expect($order->refresh()->restock_skipped_at)->toBeNull()
        ->and($this->product->refresh()->stock_quantity)->toBe(10);
})->with([OrderStatus::PENDING, OrderStatus::PROCESSING]);

test('the skip marker cannot be set through mass assignment', function () {
    expect((new Order)->isFillable('restock_skipped_at'))->toBeFalse();
});
