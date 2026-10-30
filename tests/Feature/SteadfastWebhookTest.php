<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

function makeSteadfastOrder(): Order
{
    $product = Product::factory()->create();

    $order = Order::create([
        'order_number' => 'ORD-WEBHOOK-1',
        'status' => 'pending',
        'payment_status' => 'unpaid',
        'payment_method' => 'cod',
        'subtotal' => 500,
        'shipping_cost' => 60,
        'discount' => 0,
        'tax' => 0,
        'total' => 560,
        'shipping_address' => ['name' => 'Rafiq Islam', 'phone' => '01712345678'],
        'billing_address' => [],
        'steadfast_consignment_id' => 'CONSIGN-ABC',
        'steadfast_status' => 'in_review',
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'sku' => $product->sku,
        'price' => 500,
        'quantity' => 1,
        'subtotal' => 500,
        'product_snapshot' => ['id' => $product->id, 'name' => $product->name],
    ]);

    return $order;
}

function configureSteadfastWebhookSecret(string $secret = 'super-secret'): void
{
    Setting::updateOrCreate(
        ['key' => 'steadfast_webhook_secret'],
        ['value' => $secret, 'group' => 'courier'],
    );
}

/** @param  array<string, mixed>  $payload */
function postSignedSteadfastWebhook(array $payload, string $secret = 'super-secret'): TestResponse
{
    $body = json_encode($payload);
    $signature = hash_hmac('sha256', $body, $secret);

    return test()->call(
        'POST',
        route('steadfast.webhook'),
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json', 'HTTP_X-Signature' => $signature],
        $body,
    );
}

it('rejects every webhook when no secret is configured, even a well-formed one, and never touches the order', function () {
    $order = makeSteadfastOrder();

    // Not signed at all — this is exactly the previously-exploitable
    // "fail open" path: no secret configured must reject, not accept.
    $response = $this->postJson(route('steadfast.webhook'), [
        'notification_type' => 'delivery_status',
        'consignment_id' => 'CONSIGN-ABC',
        'status' => 'delivered',
    ]);

    $response->assertStatus(401);
    expect($order->refresh()->steadfast_status)->toBe('in_review');
    expect($order->statusHistories()->count())->toBe(0);
});

it('rejects a request with an invalid signature when a webhook secret is configured', function () {
    $order = makeSteadfastOrder();
    configureSteadfastWebhookSecret();

    $response = $this->postJson(route('steadfast.webhook'), [
        'notification_type' => 'delivery_status',
        'consignment_id' => 'CONSIGN-ABC',
        'status' => 'delivered',
    ], ['X-Signature' => 'wrong-signature']);

    $response->assertStatus(401);
    expect($order->refresh()->steadfast_status)->toBe('in_review');
});

it('rejects a request with no signature header at all when a secret is configured', function () {
    $order = makeSteadfastOrder();
    configureSteadfastWebhookSecret();

    $response = $this->postJson(route('steadfast.webhook'), [
        'notification_type' => 'delivery_status',
        'consignment_id' => 'CONSIGN-ABC',
        'status' => 'delivered',
    ]);

    $response->assertStatus(401);
    expect($order->refresh()->steadfast_status)->toBe('in_review');
});

it('accepts a request with a valid signature when a webhook secret is configured', function () {
    $order = makeSteadfastOrder();
    configureSteadfastWebhookSecret();

    $response = postSignedSteadfastWebhook([
        'notification_type' => 'delivery_status',
        'consignment_id' => 'CONSIGN-ABC',
        'status' => 'delivered',
    ]);

    $response->assertOk();
    expect($order->refresh()->steadfast_status)->toBe('delivered');
});

it('updates the order steadfast status from a signed delivery_status webhook', function () {
    $order = makeSteadfastOrder();
    configureSteadfastWebhookSecret();

    $response = postSignedSteadfastWebhook([
        'notification_type' => 'delivery_status',
        'consignment_id' => 'CONSIGN-ABC',
        'invoice' => 'ORD-WEBHOOK-1',
        'cod_amount' => 560,
        'status' => 'delivered',
        'tracking_message' => 'Your package has been delivered successfully.',
    ]);

    $response->assertOk();
    expect($order->refresh()->steadfast_status)->toBe('delivered');
    expect($order->statusHistories()->latest()->first()->notes)->toContain('delivered');
});

it('matches the order by consignment_id when invoice is missing', function () {
    $order = makeSteadfastOrder();
    configureSteadfastWebhookSecret();

    $response = postSignedSteadfastWebhook([
        'notification_type' => 'delivery_status',
        'consignment_id' => 'CONSIGN-ABC',
        'status' => 'cancelled',
    ]);

    $response->assertOk();
    expect($order->refresh()->steadfast_status)->toBe('cancelled');
});

it('ignores non delivery_status events without erroring', function () {
    makeSteadfastOrder();
    configureSteadfastWebhookSecret();

    $response = postSignedSteadfastWebhook([
        'notification_type' => 'tracking_update',
        'consignment_id' => 'CONSIGN-ABC',
    ]);

    $response->assertOk();
});

it('rejects a malformed (non-JSON) body unless its exact bytes are correctly signed, and never crashes either way', function () {
    $order = makeSteadfastOrder();
    configureSteadfastWebhookSecret();
    $malformed = '{not valid json';

    // Wrong signature for this body — rejected, no crash, no change.
    $this->call(
        'POST', route('steadfast.webhook'), [], [], [],
        ['CONTENT_TYPE' => 'application/json', 'HTTP_X-Signature' => 'wrong'],
        $malformed,
    )->assertStatus(401);
    expect($order->refresh()->steadfast_status)->toBe('in_review');

    // Correctly signed, but still not valid JSON — Laravel's request
    // parsing just yields no fields, so there's no notification_type to
    // match; handled as a harmless no-op, not a crash.
    $signature = hash_hmac('sha256', $malformed, 'super-secret');
    $this->call(
        'POST', route('steadfast.webhook'), [], [], [],
        ['CONTENT_TYPE' => 'application/json', 'HTTP_X-Signature' => $signature],
        $malformed,
    )->assertOk();
    expect($order->refresh()->steadfast_status)->toBe('in_review');
});

it('replaying the same signed webhook does not create duplicate status history entries', function () {
    $order = makeSteadfastOrder();
    configureSteadfastWebhookSecret();

    $payload = [
        'notification_type' => 'delivery_status',
        'consignment_id' => 'CONSIGN-ABC',
        'status' => 'delivered',
    ];

    postSignedSteadfastWebhook($payload)->assertOk();
    expect($order->refresh()->steadfast_status)->toBe('delivered');
    $historyCountAfterFirst = $order->statusHistories()->count();

    // Steadfast retries the identical webhook (e.g. it didn't see our 200 in time).
    postSignedSteadfastWebhook($payload)->assertOk();

    expect($order->refresh()->steadfast_status)->toBe('delivered');
    expect($order->statusHistories()->count())->toBe($historyCountAfterFirst);
});

it('a late, validly-signed delivered_parcels webhook cannot resurrect a cancelled order through the real controller', function () {
    $order = makeSteadfastOrder();
    configureSteadfastWebhookSecret();
    $order->update(['status' => 'cancelled']);

    $response = postSignedSteadfastWebhook([
        'notification_type' => 'delivery_status',
        'consignment_id' => 'CONSIGN-ABC',
        'status' => 'delivered_parcels',
    ]);

    $response->assertOk();
    expect($order->refresh()->status->value)->toBe('cancelled');
    expect($order->payment_status->value)->toBe('unpaid');
});

it('a late, validly-signed webhook cannot regress a delivered order back to pending through the real controller', function () {
    // SHIP-001: a corrective/out-of-order Steadfast report must not demote
    // an already-delivered order — that would re-trigger stock-restore,
    // coupon-release, and loyalty-reversal for a sale that was actually
    // fulfilled, bypassing the deliberate admin-approved return flow.
    $order = makeSteadfastOrder();
    configureSteadfastWebhookSecret();
    $order->update(['status' => 'delivered', 'payment_status' => 'paid', 'delivered_at' => now()->subDay()]);

    $response = postSignedSteadfastWebhook([
        'notification_type' => 'delivery_status',
        'consignment_id' => 'CONSIGN-ABC',
        'status' => 'pending_parcels',
    ]);

    $response->assertOk();
    expect($order->refresh()->status->value)->toBe('delivered');
    expect($order->payment_status->value)->toBe('paid');
    expect($order->steadfast_status)->toBe('pending_parcels');
});

it('ignores an invoice-only webhook instead of matching the order by invoice', function () {
    $order = makeSteadfastOrder();
    configureSteadfastWebhookSecret();

    postSignedSteadfastWebhook([
        'notification_type' => 'delivery_status',
        'invoice' => 'ORD-WEBHOOK-1',
        'status' => 'cancelled',
    ])->assertOk();

    expect($order->refresh()->steadfast_status)->toBe('in_review')
        ->and($order->status->value)->toBe('pending');
    expect($order->statusHistories()->count())->toBe(0);
});

it('ignores a webhook for a consignment no order holds, even when its invoice matches an order', function () {
    $order = makeSteadfastOrder();
    configureSteadfastWebhookSecret();

    // An orphaned duplicate consignment booked for the same invoice.
    postSignedSteadfastWebhook([
        'notification_type' => 'delivery_status',
        'consignment_id' => 'CONSIGN-ORPHAN',
        'invoice' => 'ORD-WEBHOOK-1',
        'status' => 'cancelled',
    ])->assertOk();

    expect($order->refresh()->steadfast_status)->toBe('in_review')
        ->and($order->status->value)->toBe('pending');
});

it('ignores a webhook whose invoice contradicts the order holding the consignment', function () {
    $order = makeSteadfastOrder();
    configureSteadfastWebhookSecret();

    postSignedSteadfastWebhook([
        'notification_type' => 'delivery_status',
        'consignment_id' => 'CONSIGN-ABC',
        'invoice' => 'ORD-SOMEONE-ELSE',
        'status' => 'cancelled',
    ])->assertOk();

    expect($order->refresh()->steadfast_status)->toBe('in_review');
});

it('ignores a webhook whose consignment is recorded on more than one order', function () {
    // COURIER-SEND-RACE-002 made consignment IDs unique, so this data can only
    // exist where that migration could not run (its duplicate preflight
    // refused) — exactly when this defensive check still matters.
    (require database_path('migrations/2026_10_30_085859_add_unique_consignment_indexes_to_orders_table.php'))->down();

    $order = makeSteadfastOrder();
    $twin = Order::create([
        ...collect($order->getAttributes())->only(['status', 'payment_status', 'payment_method', 'subtotal', 'shipping_cost', 'discount', 'tax', 'total'])->all(),
        'order_number' => 'ORD-WEBHOOK-2',
        'shipping_address' => ['name' => 'Twin', 'phone' => '01712345678'],
        'billing_address' => [],
        'steadfast_consignment_id' => 'CONSIGN-ABC',
        'steadfast_status' => 'in_review',
    ]);
    configureSteadfastWebhookSecret();

    postSignedSteadfastWebhook([
        'notification_type' => 'delivery_status',
        'consignment_id' => 'CONSIGN-ABC',
        'status' => 'cancelled',
    ])->assertOk();

    expect($order->refresh()->steadfast_status)->toBe('in_review');
    expect($twin->refresh()->steadfast_status)->toBe('in_review');
})->skip(
    fn () => DB::connection()->getDriverName() !== 'sqlite',
    'drops the unique consignment indexes inside the test transaction — only reverted afterwards on SQLite (MySQL/MariaDB DDL commits immediately)',
);
