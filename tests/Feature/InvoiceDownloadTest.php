<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    Mail::fake();
});

/**
 * @param  array<string, mixed>  $attributes
 */
function invoiceOrder(User $user, string $status, string $paymentMethod = 'cod', array $attributes = []): Order
{
    $order = Order::create(array_merge([
        'order_number' => 'ORD-INVOICE-'.uniqid(),
        'user_id' => $user->id,
        'status' => $status,
        'payment_status' => 'paid',
        'payment_method' => $paymentMethod,
        'subtotal' => 500,
        'shipping_cost' => 60,
        'discount' => 0,
        'tax' => 0,
        'total' => 560,
        'shipping_address' => ['name' => 'Invoice Buyer', 'phone' => '01700000000', 'address_line1' => 'House 1', 'city' => 'Dhaka', 'state' => 'Dhaka'],
        'billing_address' => ['name' => 'Invoice Buyer', 'phone' => '01700000000', 'address_line1' => 'House 1', 'city' => 'Dhaka', 'state' => 'Dhaka'],
    ], $attributes));

    $product = Product::factory()->create();

    OrderItem::create([
        'order_id' => $order->id, 'product_id' => $product->id, 'product_name' => 'Invoice Shirt', 'sku' => 'INV-SKU-1',
        'price' => 500, 'quantity' => 1, 'subtotal' => 500, 'product_snapshot' => [],
    ]);

    return $order;
}

function assertPdfDownload(TestResponse $response, Order $order): void
{
    $response->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('application/pdf');
    expect($response->headers->get('Content-Disposition'))->toContain('attachment')->toContain("invoice-{$order->order_number}.pdf");
    expect($response->getContent())->toStartWith('%PDF-');
}

test('a delivered cash on delivery order downloads a real PDF', function () {
    $user = User::factory()->create();
    $order = invoiceOrder($user, 'delivered');

    assertPdfDownload(actingAs($user)->get(route('account.orders.invoice', $order)), $order);
});

test('an undelivered cash on delivery order has no invoice yet', function (string $status) {
    $user = User::factory()->create();

    actingAs($user)->get(route('account.orders.invoice', invoiceOrder($user, $status)))->assertForbidden();
})->with(['pending', 'processing', 'shipped']);

test('a prepaid order has an invoice before delivery', function (string $status, string $paymentMethod) {
    $user = User::factory()->create();
    $order = invoiceOrder($user, $status, $paymentMethod);

    assertPdfDownload(actingAs($user)->get(route('account.orders.invoice', $order)), $order);
})->with([
    ['pending', 'mobile_banking'],
    ['processing', 'mobile_banking'],
    ['shipped', 'mobile_banking'],
    ['delivered', 'mobile_banking'],
    ['processing', 'zinipay'],
]);

test('a cancelled or refunded order has no invoice, for any payment method', function (string $status, string $paymentMethod) {
    $user = User::factory()->create();

    actingAs($user)->get(route('account.orders.invoice', invoiceOrder($user, $status, $paymentMethod)))->assertForbidden();
})->with([
    ['cancelled', 'cod'],
    ['refunded', 'cod'],
    ['cancelled', 'mobile_banking'],
    ['refunded', 'mobile_banking'],
    ['cancelled', 'zinipay'],
    ['refunded', 'zinipay'],
]);

test('a customer cannot download another customer\'s invoice', function () {
    $order = invoiceOrder(User::factory()->create(), 'delivered');

    actingAs(User::factory()->create())->get(route('account.orders.invoice', $order))->assertForbidden();
});

test('a guest order cannot be downloaded through a customer account', function () {
    $order = invoiceOrder(User::factory()->create(), 'delivered', attributes: ['user_id' => null]);

    actingAs(User::factory()->create())->get(route('account.orders.invoice', $order))->assertForbidden();
});

test('a guest is sent to log in', function () {
    get(route('account.orders.invoice', invoiceOrder(User::factory()->create(), 'delivered')))->assertRedirect(route('login'));
});

test('an unverified customer is sent to verify their email', function () {
    $user = User::factory()->unverified()->create();

    actingAs($user)->get(route('account.orders.invoice', invoiceOrder($user, 'delivered')))->assertRedirect(route('verification.notice'));
});

test('an unknown order number is not found', function () {
    actingAs(User::factory()->create())->get(route('account.orders.invoice', 'ORD-NOPE'))->assertNotFound();
});

test('a trashed order has no invoice route', function () {
    $user = User::factory()->create();
    $order = invoiceOrder($user, 'cancelled');
    $order->delete();

    actingAs($user)->get(route('account.orders.invoice', $order->order_number))->assertNotFound();
});

test('the invoice shows the order and only this order', function () {
    $user = User::factory()->create(['name' => 'Account Name']);
    $order = invoiceOrder($user, 'delivered', attributes: ['discount' => 50, 'coupon_code' => 'SAVE50', 'total' => 510]);
    $other = invoiceOrder(User::factory()->create(), 'delivered', attributes: ['shipping_address' => ['name' => 'Someone Else']]);

    $html = view('invoices.order', ['order' => $order->load(['items.product', 'items.variant', 'user'])])->render();

    expect($html)->toContain($order->order_number)->toContain('Invoice Shirt')->toContain('INV-SKU-1')->toContain('Invoice Buyer')
        ->toContain('SAVE50')->toContain('510.00')->toContain('60.00');
    expect($html)->not->toContain($other->order_number)->not->toContain('Someone Else');
});

test('an invoice still renders when the variant and product have gone', function () {
    $user = User::factory()->create();
    $order = invoiceOrder($user, 'delivered');
    $item = $order->items()->firstOrFail();
    $variant = ProductVariant::factory()->create(['product_id' => $item->product_id, 'attributes' => ['Color' => 'Red']]);
    $item->update(['variant_id' => $variant->id]);

    assertPdfDownload(actingAs($user)->get(route('account.orders.invoice', $order)), $order);

    $variant->delete();
    Product::whereKey($item->product_id)->delete();

    assertPdfDownload(actingAs($user)->get(route('account.orders.invoice', $order)), $order);
});

test('a variant line shows its attributes on the invoice', function () {
    $user = User::factory()->create();
    $order = invoiceOrder($user, 'delivered');
    $item = $order->items()->firstOrFail();
    $item->update(['variant_id' => ProductVariant::factory()->create(['product_id' => $item->product_id, 'attributes' => ['Color' => 'Crimson'], 'sku' => 'VAR-CRIMSON'])->id]);

    $html = view('invoices.order', ['order' => $order->load(['items.product', 'items.variant', 'user'])])->render();

    expect($html)->toContain('Crimson');
});

test('a guest landing page order downloads by its private token', function () {
    $order = invoiceOrder(User::factory()->create(), 'delivered', attributes: ['user_id' => null, 'guest_token' => 'private-guest-token']);

    assertPdfDownload(get(route('lp.invoice', 'private-guest-token')), $order);
    get(route('lp.invoice', 'wrong-token'))->assertNotFound();
});

// LP-INVOICE-STATUS-001: the guest landing-page invoice route previously had
// no cancelled/refunded/undelivered-COD restriction at all, unlike the
// account invoice route above — these mirror that exact coverage for the
// landing-page route.

test('a cancelled or refunded guest landing-page order has no invoice, for any payment method', function (string $status, string $paymentMethod) {
    $order = invoiceOrder(User::factory()->create(), $status, $paymentMethod, ['user_id' => null, 'guest_token' => 'lp-blocked-token-'.$status.'-'.$paymentMethod]);

    get(route('lp.invoice', $order->guest_token))->assertForbidden();
})->with([
    ['cancelled', 'cod'],
    ['refunded', 'cod'],
    ['cancelled', 'mobile_banking'],
    ['refunded', 'mobile_banking'],
]);

test('an undelivered guest landing-page cash on delivery order has no invoice yet', function (string $status) {
    $order = invoiceOrder(User::factory()->create(), $status, 'cod', ['user_id' => null, 'guest_token' => 'lp-undelivered-cod-'.$status]);

    get(route('lp.invoice', $order->guest_token))->assertForbidden();
})->with(['pending', 'processing', 'shipped']);

test('a prepaid guest landing-page order has an invoice before delivery', function (string $status) {
    $order = invoiceOrder(User::factory()->create(), $status, 'mobile_banking', ['user_id' => null, 'guest_token' => 'lp-prepaid-'.$status]);

    assertPdfDownload(get(route('lp.invoice', $order->guest_token)), $order);
})->with(['pending', 'processing', 'shipped', 'delivered']);

test('BE-009: the invoice date is the Dhaka calendar day, not the UTC day the order was stored on', function (string $createdAtUtc, string $expectedDate) {
    $order = invoiceOrder(User::factory()->create(), 'delivered');
    $order->forceFill(['created_at' => $createdAtUtc])->saveQuietly();
    $order->load(['items.product', 'items.variant', 'user']);

    $html = view('invoices.order', ['order' => $order])->render();

    expect($html)->toContain($expectedDate);
})->with([
    '03:00 Dhaka on 5 Nov is 21:00 UTC on 4 Nov (the reported case)' => ['2026-11-04 21:00:00', '05 Nov 2026'],
    '23:59 Dhaka is still the same Dhaka day' => ['2026-11-05 17:59:00', '05 Nov 2026'],
    'midnight in Dhaka starts the next day' => ['2026-11-05 18:00:00', '06 Nov 2026'],
]);
