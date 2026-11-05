<?php

declare(strict_types=1);

use App\Enums\BundleDiscountType;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\IncompleteOrder;
use App\Models\LandingPage;
use App\Models\Order;
use App\Models\PendingCheckout;
use App\Models\Product;
use App\Models\ProductBundle;
use App\Models\ProductBundleItem;
use App\Models\ReceivedSms;
use App\Models\Setting;
use App\Models\SmsPattern;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

test('A01 a forged SMS from an unknown phone number must not pay for an order', function () {
    config(['services.sms_forwarder.token' => 'audit-token']);
    SmsPattern::create([
        'provider' => 'bkash', 'name' => 'bKash', 'identifier' => 'TrxID', 'is_active' => true,
        'txn_id_regex' => 'TrxID\s+([A-Z0-9]+)', 'amount_regex' => 'received Tk\s+([\d,]+(?:\.\d+)?)', 'sort_order' => 1,
    ]);
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 5]);

    $this->actingAs($user)->post(route('checkout.store'), auditCheckoutPayload([
        'payment_method' => 'mobile_banking',
        'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1]],
    ]))->assertRedirect();
    $pending = PendingCheckout::query()->firstOrFail();
    $total = number_format((float) $pending->checkout_data['total'], 2, '.', '');

    // The text is typed by the buyer on an ordinary phone, not sent by bKash.
    $this->withToken('audit-token')->postJson('/api/sms/receive', [
        'from' => '01811111111',
        'content' => "You have received Tk {$total} from 01811111111. TrxID FORGED123 at now",
    ])->assertOk();

    $this->actingAs($user)->post(route('payment.mobile.submit', $pending->token), [
        'provider' => 'bkash', 'transaction_id' => 'FORGED123',
    ]);

    expect(Order::query()->where('payment_status', 'paid')->count())->toBe(0);
});

test('A02 COD must not charge more than the price the customer last saw', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 5]);
    $cart = Cart::create(['user_id' => $user->id]);
    $cart->items()->create(['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1, 'price' => 100]);

    $product->update(['price' => 150]);

    // Exactly what Checkout.tsx sends: items with no price and no expected total.
    $this->actingAs($user)->post(route('checkout.store'), auditCheckoutPayload([
        'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1]],
    ]));

    expect(Order::query()->count())->toBe(0);
});

test('A03 a cart holding a complete bundle must be able to check out with the UI payload', function () {
    $user = User::factory()->create();
    $a = Product::factory()->create(['price' => 150, 'stock_quantity' => 10]);
    $b = Product::factory()->create(['price' => 50, 'stock_quantity' => 10]);
    $bundle = ProductBundle::factory()->create(['discount_type' => BundleDiscountType::FixedPrice, 'bundle_price' => 120]);
    ProductBundleItem::factory()->create(['product_bundle_id' => $bundle->id, 'product_id' => $a->id, 'quantity' => 1]);
    ProductBundleItem::factory()->create(['product_bundle_id' => $bundle->id, 'product_id' => $b->id, 'quantity' => 1]);

    // Checkout.tsx omits bundle_id, so this is what every UI submit looks like.
    $items = [
        ['product_id' => $a->id, 'variant_id' => null, 'quantity' => 1],
        ['product_id' => $b->id, 'variant_id' => null, 'quantity' => 1],
    ];

    foreach (range(1, 3) as $attempt) {
        $this->actingAs($user)->post(route('checkout.store'), auditCheckoutPayload(['items' => $items]));
    }

    expect(Order::query()->count())->toBe(1);
});

test('A04a converting an incomplete order must not sell more than is in stock', function () {
    $admin = auditAdmin();
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 5]);
    $incomplete = IncompleteOrder::factory()->create();

    $this->actingAs($admin, 'admin')->post(route('admin.orders.incomplete.placeOrder', $incomplete), [
        'name' => 'Audit', 'phone' => '01700000000', 'address_line1' => 'House 1', 'city' => 'Dhaka', 'state' => 'Dhaka',
        'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 10]],
    ]);

    expect(Order::query()->count())->toBe(0);
});

test('A04b converting then cancelling an incomplete order must leave stock unchanged', function () {
    $admin = auditAdmin();
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 5]);
    $incomplete = IncompleteOrder::factory()->create();

    $this->actingAs($admin, 'admin')->post(route('admin.orders.incomplete.placeOrder', $incomplete), [
        'name' => 'Audit', 'phone' => '01700000000', 'address_line1' => 'House 1', 'city' => 'Dhaka', 'state' => 'Dhaka',
        'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 2]],
    ])->assertRedirect();
    $order = Order::query()->firstOrFail();
    $afterConvert = (int) $product->fresh()->stock_quantity;

    $this->actingAs($admin, 'admin')->patch(route('admin.orders.cancel', $order))->assertRedirect();

    expect($afterConvert)->toBe(3)
        ->and((int) $product->fresh()->stock_quantity)->toBe(5);
});

test('A05 a customer must not be able to cancel an already paid order', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 5]);
    $order = Order::create([
        'order_number' => 'ORD-AUDIT-1', 'user_id' => $user->id, 'status' => 'pending', 'payment_status' => 'paid',
        'payment_method' => 'mobile_banking', 'subtotal' => 100, 'shipping_cost' => 0, 'discount' => 0, 'tax' => 0,
        'total' => 100, 'shipping_address' => ['name' => 'A', 'phone' => '01700000000'], 'billing_address' => [],
    ]);

    $this->actingAs($user)->patch(route('account.orders.cancel', $order->order_number));

    expect($order->fresh()->status->value)->toBe('pending');
});

test('A06 admin must not be able to cancel an order already handed to Steadfast', function () {
    $admin = auditAdmin();
    $order = Order::create([
        'order_number' => 'ORD-AUDIT-2', 'status' => 'pending', 'payment_status' => 'unpaid', 'payment_method' => 'cod',
        'subtotal' => 100, 'shipping_cost' => 0, 'discount' => 0, 'tax' => 0, 'total' => 100,
        'shipping_address' => ['name' => 'A', 'phone' => '01700000000'], 'billing_address' => [],
        'steadfast_consignment_id' => '9001',
    ]);

    $this->actingAs($admin, 'admin')->patch(route('admin.orders.cancel', $order));

    expect($order->fresh()->status->value)->toBe('pending');
});

test('A07 an admin allowed only the About page must not receive courier and OAuth secrets', function () {
    $admin = auditAdmin(['about_page' => ['view']]);
    Setting::create(['key' => 'pathao_password', 'value' => 'audit-canary', 'group' => 'pathao']);
    Setting::create(['key' => 'steadfast_webhook_secret', 'value' => 'audit-canary', 'group' => 'steadfast']);

    $response = $this->actingAs($admin, 'admin')->get(route('admin.settings.about-page'));

    $response->assertOk();
    expect(json_encode($response->viewData('page')['props']['settings'] ?? []))->not->toContain('audit-canary');
});

test('A08 order tracking must not expose IP address, browser details or staff notes', function () {
    $order = Order::create([
        'order_number' => 'ORD-AUDIT-3', 'status' => 'pending', 'payment_status' => 'unpaid', 'payment_method' => 'cod',
        'subtotal' => 100, 'shipping_cost' => 0, 'discount' => 0, 'tax' => 0, 'total' => 100,
        'shipping_address' => ['name' => 'A', 'phone' => '01700000000'], 'billing_address' => [],
    ]);
    $order->forceFill(['ip_address' => '203.0.113.9', 'user_agent' => 'AuditAgent', 'notes' => 'staff-only'])->save();

    $response = $this->get('/order/track?order_number=ORD-AUDIT-3&phone=01700000000');
    $props = json_encode($response->viewData('page')['props']['order'] ?? []);

    expect($props)->not->toContain('203.0.113.9')->not->toContain('AuditAgent')->not->toContain('staff-only');
});

test('A09 coupon validation must not reveal usage counts and limits to anonymous visitors', function () {
    $coupon = Coupon::factory()->create(['code' => 'AUDITX', 'max_uses' => 77, 'used_count' => 3]);
    $product = Product::factory()->create(['price' => 1000, 'stock_quantity' => 5]);

    $response = $this->postJson('/coupon/validate', [
        'code' => 'AUDITX', 'subtotal' => 1000,
        'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1, 'price' => 1000, 'subtotal' => 1000]],
    ])->assertOk();

    expect($response->json('coupon'))->not->toHaveKeys(['used_count', 'max_uses', 'per_user_limit']);
});

test('A10 mobile banking must refuse to take payment for an out-of-stock product', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 0]);

    $this->actingAs($user)->post(route('checkout.store'), auditCheckoutPayload([
        'payment_method' => 'mobile_banking',
        'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1]],
    ]));

    expect(PendingCheckout::query()->count())->toBe(0);
});

test('A11 a soft-deleted product in a guest cart must give a clear response, not a 404', function () {
    Setting::create(['key' => 'guest_checkout_enabled', 'value' => true, 'group' => 'general']);
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 5]);
    $product->delete();

    $response = $this->post(route('checkout.store'), auditCheckoutPayload([
        'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1]],
    ]));

    expect($response->status())->not->toBe(404);
});

test('A12 a retried checkout token must not put purchased items back in the cart', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 5]);
    $payload = auditCheckoutPayload(['items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1]]]);

    $this->actingAs($user)->post(route('checkout.store'), $payload)->assertRedirect();
    $this->actingAs($user)->post(route('checkout.store'), $payload)->assertRedirect();

    expect(Order::query()->count())->toBe(1)
        ->and(Cart::query()->where('user_id', $user->id)->withCount('items')->first()?->items_count ?? 0)->toBe(0);
});

test('A13 a per-customer coupon limit must apply when the same person checks out as a guest', function () {
    Setting::create(['key' => 'guest_checkout_enabled', 'value' => true, 'group' => 'general']);
    $coupon = Coupon::factory()->create(['code' => 'ONCE', 'type' => 'fixed', 'value' => 10, 'per_user_limit' => 1, 'max_uses' => null]);
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 50]);
    $items = [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1]];

    $this->actingAs($user)->post(route('checkout.store'), auditCheckoutPayload(['coupon_code' => 'ONCE', 'items' => $items]))->assertRedirect();
    Auth::guard('web')->logout();
    $this->post(route('checkout.store'), auditCheckoutPayload(['coupon_code' => 'ONCE', 'items' => $items]))->assertRedirect();

    expect((int) $coupon->fresh()->used_count)->toBe(1);
});

test('A14 an array in the order tracking phone field must not cause a server error', function () {
    Order::create([
        'order_number' => 'ORD-AUDIT-4', 'status' => 'pending', 'payment_status' => 'unpaid', 'payment_method' => 'cod',
        'subtotal' => 100, 'shipping_cost' => 0, 'discount' => 0, 'tax' => 0, 'total' => 100,
        'shipping_address' => ['name' => 'A', 'phone' => '01700000000'], 'billing_address' => [],
    ]);

    expect($this->get('/order/track?order_number=ORD-AUDIT-4&phone[]=x')->status())->toBeLessThan(500);
});

test('A15 a normal checkout token on a landing-page payment URL must not cause a server error', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 5]);
    $landing = LandingPage::create(['title' => 'Audit', 'slug' => 'audit-lp', 'product_id' => $product->id, 'is_active' => true]);
    $this->actingAs($user)->post(route('checkout.store'), auditCheckoutPayload([
        'payment_method' => 'mobile_banking',
        'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1]],
    ]))->assertRedirect();
    $token = PendingCheckout::query()->firstOrFail()->token;

    expect($this->get("/lp/audit-lp/payment/{$token}")->status())->toBeLessThan(500);
});

test('A16 a quantity too large for the database must be refused, not crash', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 5]);

    $response = $this->actingAs($user)->post(route('checkout.store'), auditCheckoutPayload([
        'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 3000000000]],
    ]));

    expect($response->status())->toBeLessThan(500);
});

test('A17 points held by a paid-but-late mobile checkout must not be spendable twice', function () {
    Setting::updateOrCreate(['key' => 'points_redeem_value_bdt'], ['value' => 1, 'group' => 'referral']);
    Setting::updateOrCreate(['key' => 'points_redeem_max_percent'], ['value' => 50, 'group' => 'referral']);
    $user = User::factory()->create(['points_balance' => 100]);
    $product = Product::factory()->create(['price' => 1000, 'stock_quantity' => 50]);
    $items = [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1]];

    $this->actingAs($user)->post(route('checkout.store'), auditCheckoutPayload([
        'email' => $user->email, 'payment_method' => 'mobile_banking', 'redeem_points' => true, 'points_to_redeem' => 100, 'items' => $items,
    ]))->assertRedirect();
    $pending = PendingCheckout::query()->firstOrFail();
    $expiry = $pending->expires_at->clone();

    ReceivedSms::create([
        'sender' => '01799999999', 'provider' => 'bkash', 'content' => 'TrxID LATE777', 'received_at' => $expiry->clone()->subMinutes(5),
        'parsed_transaction_id' => 'LATE777', 'parsed_amount' => (float) $pending->checkout_data['total'], 'is_matched' => false,
    ]);

    $this->travelTo($expiry->clone()->addMinute());

    // The hold has lapsed, so the same points pay for a second order.
    $this->actingAs($user)->post(route('checkout.store'), auditCheckoutPayload([
        'email' => $user->email, 'redeem_points' => true, 'points_to_redeem' => 100, 'items' => $items,
    ]))->assertRedirect();

    // The FSA-07 grace then accepts the first checkout's late submission.
    $this->actingAs($user)->post(route('payment.mobile.submit', $pending->token), ['provider' => 'bkash', 'transaction_id' => 'LATE777']);

    expect((int) $user->fresh()->points_balance)->toBeGreaterThanOrEqual(0);
});

test('A18 opening an admin product URL by hand must not be a server error', function () {
    $product = Product::factory()->create();

    expect($this->actingAs(auditAdmin(), 'admin')->get("/admin/products/{$product->id}")->status())->toBeLessThan(500);
});
