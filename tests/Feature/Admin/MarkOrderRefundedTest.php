<?php

declare(strict_types=1);

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderRefund;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = createAdmin([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => bcrypt('password'),
    ]);

    $product = Product::factory()->create();

    $this->order = Order::create([
        'order_number' => 'ORD-REFUND-1',
        'status' => 'delivered',
        'payment_status' => 'paid',
        'payment_method' => 'cod',
        'subtotal' => 500,
        'shipping_cost' => 60,
        'discount' => 0,
        'tax' => 0,
        'total' => 560,
        'shipping_address' => ['name' => 'Test', 'phone' => '01700000000'],
        'billing_address' => [],
        'delivered_at' => now()->subDay(),
    ]);

    OrderItem::create([
        'order_id' => $this->order->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'sku' => $product->sku,
        'price' => 500,
        'quantity' => 1,
        'subtotal' => 500,
        'product_snapshot' => ['id' => $product->id, 'name' => $product->name],
    ]);

    // PAY-RETURN-001: a delivered (courier-handled) order needs readiness + a verified consignment.
    courierVerifiedForRefund($this->order);
});

test('admin can mark a paid order as refunded', function () {
    actingAs($this->admin, 'admin');

    // PAY-RETURN-001: exactly the remaining refundable amount — 500 of product; the 60 shipping is kept.
    $response = $this->patch(route('admin.orders.markRefunded', $this->order->id), ['refund_amount' => 500, 'refund_method' => 'bKash']);

    $response->assertRedirect()->assertSessionHas('success');
    expect($this->order->refresh()->status->value)->toBe('refunded')
        // BIZ-008: the 500 refund equals the full refundable cap (product
        // value, shipping excluded), so payment is fully refunded too.
        ->and($this->order->payment_status)->toBe(PaymentStatus::REFUNDED)
        ->and($this->order->statusHistories()->latest('id')->first()->notes)->toContain('refunded')->toContain('500.00')->toContain('bKash');

    $refund = OrderRefund::sole();
    expect((float) $refund->amount)->toBe(500.0)
        ->and($refund->method)->toBe('bKash')
        ->and($refund->source)->toBe(OrderRefund::SOURCE_ORDER)
        ->and($refund->admin_id)->toBe($this->admin->id)
        ->and($refund->previous_order_status)->toBe('delivered')
        ->and($refund->resulting_order_status)->toBe('refunded');
});

test('a whole-order refund requires an amount and a method', function () {
    actingAs($this->admin, 'admin');

    $this->patch(route('admin.orders.markRefunded', $this->order->id))->assertSessionHasErrors(['refund_amount', 'refund_method']);

    expect($this->order->refresh()->status->value)->toBe('delivered')->and(OrderRefund::count())->toBe(0);
});

test('a whole-order refund for anything but exactly the remaining amount is refused', function (float $amount) {
    actingAs($this->admin, 'admin');

    $this->patch(route('admin.orders.markRefunded', $this->order->id), ['refund_amount' => $amount, 'refund_method' => 'bKash'])
        ->assertSessionHas('error');

    expect($this->order->refresh()->status->value)->toBe('delivered')->and(OrderRefund::count())->toBe(0);
})->with([
    'shipping included' => [560],
    'more than paid' => [600],
    'stale, less' => [499.99],
]);

test('an unpaid order cannot be marked financially refunded', function () {
    actingAs($this->admin, 'admin');
    $this->order->update(['payment_status' => 'unpaid']);

    $this->patch(route('admin.orders.markRefunded', $this->order->id), ['refund_amount' => 500, 'refund_method' => 'bKash'])
        ->assertSessionHas('error');

    expect($this->order->refresh()->status->value)->toBe('delivered')->and(OrderRefund::count())->toBe(0);
});

test('a delivered order without courier verification cannot be refunded', function () {
    actingAs($this->admin, 'admin');
    $this->order->deliveryVerifications()->toBase()->delete(); // test-only: drop the helper's verification

    $this->patch(route('admin.orders.markRefunded', $this->order->id), ['refund_amount' => 500, 'refund_method' => 'bKash'])
        ->assertSessionHas('error');

    expect($this->order->refresh()->status->value)->toBe('delivered')->and(OrderRefund::count())->toBe(0);
});

test('marking an already refunded order as refunded is rejected', function () {
    actingAs($this->admin, 'admin');
    $this->order->update(['status' => 'refunded']);

    $response = $this->patch(route('admin.orders.markRefunded', $this->order->id), ['refund_amount' => 500, 'refund_method' => 'bKash']);

    $response->assertRedirect();
    $response->assertSessionHas('error');
});
