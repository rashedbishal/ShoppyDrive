<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\Admin;
use App\Models\Cart;
use App\Models\LandingPage;
use App\Models\Order;
use App\Models\PendingCheckout;
use App\Models\Product;
use App\Models\ReceivedSms;
use App\Models\SmsPattern;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Tests\TestCase;

function startMobileBankingCheckout(TestCase $test, User $user, Product $product, int $quantity = 1): PendingCheckout
{
    Cart::query()->where('user_id', $user->id)->delete();

    $test->actingAs($user)->post(route('checkout.store'), [
        'name' => 'Mobile Test',
        'email' => $user->email,
        'phone' => '01700000000',
        'address_line1' => 'House 1',
        'city' => 'Dhaka',
        'state' => 'Dhaka',
        'country' => 'Bangladesh',
        'payment_method' => 'mobile_banking',
        'order_token' => (string) Str::uuid(),
        'items' => [
            ['product_id' => $product->id, 'variant_id' => null, 'quantity' => $quantity],
        ],
    ])->assertRedirect();

    return PendingCheckout::query()->where('user_id', $user->id)->latest('id')->firstOrFail();
}

test('a valid transaction id with the correct amount confirms the order and marks the SMS matched', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);
    $pending = startMobileBankingCheckout($this, $user, $product);
    $amount = (float) $pending->checkout_data['total'];

    $sms = ReceivedSms::create([
        'sender' => '01799999999', 'provider' => 'bkash', 'content' => 'TrxID OK12345',
        'received_at' => now(), 'parsed_transaction_id' => 'OK12345', 'parsed_amount' => $amount, 'is_matched' => false,
    ]);

    $this->actingAs($user)->post(route('payment.mobile.submit', $pending->token), [
        'provider' => 'bkash', 'transaction_id' => 'ok12345',
    ])->assertRedirect(route('order.confirmation', $pending->fresh()->order_number));

    $order = Order::query()->firstOrFail();
    expect($order->payment_status->value)->toBe('paid');
    expect($sms->refresh()->is_matched)->toBeTrue();
    expect($sms->matched_order_id)->toBe($order->id);
});

/*
 * FSA-07: a customer who genuinely paid within the original 2-hour window
 * but only submits the form late must not be bounced to a new checkout —
 * but the window itself (expires_at) is never changed, and an SMS that
 * truly arrived after it must still be refused.
 */
test('a late submission is accepted when the matching SMS genuinely arrived within the original window', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);
    $pending = startMobileBankingCheckout($this, $user, $product);
    $amount = (float) $pending->checkout_data['total'];
    $originalExpiry = $pending->expires_at->clone();

    // The SMS arrived with time to spare before expiry...
    ReceivedSms::create([
        'sender' => '01799999999', 'provider' => 'bkash', 'content' => 'TrxID LATE1234',
        'received_at' => $originalExpiry->clone()->subMinutes(5),
        'parsed_transaction_id' => 'LATE1234', 'parsed_amount' => $amount, 'is_matched' => false,
    ]);

    // ...but the customer only submits the form after expires_at has passed.
    $this->travelTo($originalExpiry->clone()->addMinute());

    $this->actingAs($user)->post(route('payment.mobile.submit', $pending->token), [
        'provider' => 'bkash', 'transaction_id' => 'late1234',
    ])->assertRedirect(route('order.confirmation', $pending->fresh()->order_number));

    expect(Order::query()->count())->toBe(1);
    expect(Order::query()->firstOrFail()->payment_status->value)->toBe('paid');
    expect($pending->fresh()->expires_at->equalTo($originalExpiry))->toBeTrue();
});

test('an SMS received after the original expiry is refused, not treated as proof of timely payment', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);
    $pending = startMobileBankingCheckout($this, $user, $product);
    $amount = (float) $pending->checkout_data['total'];
    $originalExpiry = $pending->expires_at->clone();

    // The SMS itself arrived after the original window closed.
    ReceivedSms::create([
        'sender' => '01799999999', 'provider' => 'bkash', 'content' => 'TrxID TOOLATE1',
        'received_at' => $originalExpiry->clone()->addMinutes(5),
        'parsed_transaction_id' => 'TOOLATE1', 'parsed_amount' => $amount, 'is_matched' => false,
    ]);
    $this->travelTo($originalExpiry->clone()->addMinutes(10));

    $this->actingAs($user)->post(route('payment.mobile.submit', $pending->token), [
        'provider' => 'bkash', 'transaction_id' => 'toolate1',
    ])->assertRedirect(route('checkout.index'))->assertSessionHas('error', 'Payment session expired. Please start a new order.');

    expect(Order::query()->count())->toBe(0);
    expect(ReceivedSms::query()->firstOrFail()->is_matched)->toBeFalse();
});

test('a genuinely expired session with no matching SMS at all is still refused exactly as before', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);
    $pending = startMobileBankingCheckout($this, $user, $product);
    $this->travelTo($pending->expires_at->clone()->addMinute());

    $this->actingAs($user)->post(route('payment.mobile.submit', $pending->token), [
        'provider' => 'bkash', 'transaction_id' => 'NEVERSENT1',
    ])->assertRedirect(route('checkout.index'))->assertSessionHas('error', 'Payment session expired. Please start a new order.');

    expect(Order::query()->count())->toBe(0);
});

test('an incorrect amount is rejected and does not mark the order paid', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);
    $pending = startMobileBankingCheckout($this, $user, $product);
    $amount = (float) $pending->checkout_data['total'];

    $sms = ReceivedSms::create([
        'sender' => '01799999999', 'provider' => 'bkash', 'content' => 'TrxID BAD0001',
        'received_at' => now(), 'parsed_transaction_id' => 'BAD0001', 'parsed_amount' => $amount - 50, 'is_matched' => false,
    ]);

    $this->actingAs($user)->post(route('payment.mobile.submit', $pending->token), [
        'provider' => 'bkash', 'transaction_id' => 'BAD0001',
    ])->assertSessionHasErrors('transaction_id');

    expect(Order::query()->count())->toBe(0);
    expect($sms->refresh()->is_matched)->toBeFalse();
});

test('a matched transaction id with no parseable amount fails closed instead of verifying payment', function () {
    // PAY-001 regression: a transaction ID alone must never be enough —
    // if the SMS amount couldn't be parsed (null), the order must not be
    // created/marked paid just because nothing contradicted the claim.
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);
    $pending = startMobileBankingCheckout($this, $user, $product);

    $sms = ReceivedSms::create([
        'sender' => '01799999999', 'provider' => 'bkash', 'content' => 'TrxID NOAMT001 (unparseable amount text)',
        'received_at' => now(), 'parsed_transaction_id' => 'NOAMT001', 'parsed_amount' => null, 'is_matched' => false,
    ]);

    $this->actingAs($user)->post(route('payment.mobile.submit', $pending->token), [
        'provider' => 'bkash', 'transaction_id' => 'NOAMT001',
    ])->assertSessionHasErrors('transaction_id');

    expect(Order::query()->count())->toBe(0);
    expect($sms->refresh()->is_matched)->toBeFalse();
});

test('a transaction id recorded for a different provider cannot be claimed under the wrong provider', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);
    $pending = startMobileBankingCheckout($this, $user, $product);
    $amount = (float) $pending->checkout_data['total'];

    $sms = ReceivedSms::create([
        'sender' => '01799999999', 'provider' => 'nagad', 'content' => 'TxnID: WRONG01',
        'received_at' => now(), 'parsed_transaction_id' => 'WRONG01', 'parsed_amount' => $amount, 'is_matched' => false,
    ]);

    $this->actingAs($user)->post(route('payment.mobile.submit', $pending->token), [
        'provider' => 'bkash', 'transaction_id' => 'WRONG01',
    ])->assertSessionHasErrors('transaction_id');

    expect(Order::query()->count())->toBe(0);
    expect($sms->refresh()->is_matched)->toBeFalse();
});

test('a transaction id already consumed by one order cannot be reused to pay for another', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);

    $pendingA = startMobileBankingCheckout($this, $userA, $product);
    $pendingB = startMobileBankingCheckout($this, $userB, $product);
    $amount = (float) $pendingA->checkout_data['total'];

    $sms = ReceivedSms::create([
        'sender' => '01799999999', 'provider' => 'bkash', 'content' => 'TrxID REUSE01',
        'received_at' => now(), 'parsed_transaction_id' => 'REUSE01', 'parsed_amount' => $amount, 'is_matched' => false,
    ]);

    $this->actingAs($userA)->post(route('payment.mobile.submit', $pendingA->token), [
        'provider' => 'bkash', 'transaction_id' => 'REUSE01',
    ])->assertRedirect();

    $this->actingAs($userB)->post(route('payment.mobile.submit', $pendingB->token), [
        'provider' => 'bkash', 'transaction_id' => 'REUSE01',
    ])->assertSessionHasErrors('transaction_id');

    expect(Order::query()->where('payment_status', 'paid')->count())->toBe(1);
    expect($sms->refresh()->matched_order_id)->not->toBeNull();
});

test('a duplicate SMS delivery for the same real transaction cannot be used to pay two separate orders', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);

    $pendingA = startMobileBankingCheckout($this, $userA, $product);
    $pendingB = startMobileBankingCheckout($this, $userB, $product);
    $amount = (float) $pendingA->checkout_data['total'];

    // The forwarder app retries delivery of the exact same SMS.
    ReceivedSms::create([
        'sender' => '01799999999', 'provider' => 'bkash', 'content' => 'TrxID DUPE001',
        'received_at' => now(), 'parsed_transaction_id' => 'DUPE001', 'parsed_amount' => $amount, 'is_matched' => false,
    ]);

    expect(fn () => ReceivedSms::create([
        'sender' => '01799999999', 'provider' => 'bkash', 'content' => 'TrxID DUPE001',
        'received_at' => now(), 'parsed_transaction_id' => 'DUPE001', 'parsed_amount' => $amount, 'is_matched' => false,
    ]))->toThrow(QueryException::class);

    expect(ReceivedSms::where('parsed_transaction_id', 'DUPE001')->count())->toBe(1);

    $this->actingAs($userA)->post(route('payment.mobile.submit', $pendingA->token), [
        'provider' => 'bkash', 'transaction_id' => 'DUPE001',
    ])->assertRedirect();

    $this->actingAs($userB)->post(route('payment.mobile.submit', $pendingB->token), [
        'provider' => 'bkash', 'transaction_id' => 'DUPE001',
    ])->assertSessionHasErrors('transaction_id');

    expect(Order::query()->where('payment_status', 'paid')->count())->toBe(1);
});

test('the SMS ingestion endpoint ignores a retried delivery of the same transaction instead of erroring', function () {
    SmsPattern::create([
        'provider' => 'bkash', 'name' => 'Bkash Money Received', 'identifier' => 'TrxID',
        'txn_id_regex' => 'TrxID\s+([A-Z0-9]+)', 'amount_regex' => 'received Tk\s+([\d,]+(?:\.\d+)?)',
        'is_active' => true, 'sort_order' => 1,
    ]);

    config(['services.sms_forwarder.token' => 'test-forwarder-token']);
    $content = 'You have received Tk 100.00 from 01867603414. Ref. Payment. TrxID RETRY99 at 01/01/2026 10:00';

    $headers = ['Authorization' => 'Bearer test-forwarder-token'];

    $this->postJson('/api/sms/receive', ['from' => '01867603414', 'content' => $content], $headers)
        ->assertOk()->assertJson(['status' => 'ok']);

    // Retried delivery of the exact same SMS — must not error and must not duplicate the row.
    $this->postJson('/api/sms/receive', ['from' => '01867603414', 'content' => $content], $headers)
        ->assertOk()->assertJson(['status' => 'ok']);

    expect(ReceivedSms::where('parsed_transaction_id', 'RETRY99')->count())->toBe(1);
});

test('an unparsed or ambiguous transaction cannot be used to mark an order paid', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);
    $pending = startMobileBankingCheckout($this, $user, $product);

    // No matching ReceivedSms row exists at all for this id.
    $this->actingAs($user)->post(route('payment.mobile.submit', $pending->token), [
        'provider' => 'bkash', 'transaction_id' => 'DOESNOTEXIST',
    ])->assertSessionHasErrors('transaction_id');

    expect(Order::query()->count())->toBe(0);
});

test('resubmitting the same confirmed transaction id is idempotent and does not duplicate the order', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);
    $pending = startMobileBankingCheckout($this, $user, $product);
    $amount = (float) $pending->checkout_data['total'];

    $sms = ReceivedSms::create([
        'sender' => '01799999999', 'provider' => 'bkash', 'content' => 'TrxID IDEMP01',
        'received_at' => now(), 'parsed_transaction_id' => 'IDEMP01', 'parsed_amount' => $amount, 'is_matched' => false,
    ]);

    $this->actingAs($user)->post(route('payment.mobile.submit', $pending->token), [
        'provider' => 'bkash', 'transaction_id' => 'IDEMP01',
    ])->assertRedirect();

    $orderNumber = $pending->fresh()->order_number;

    // Customer double-submits (double click / retried request) with the
    // token that now already has an order attached.
    $this->actingAs($user)->post(route('payment.mobile.submit', $pending->token), [
        'provider' => 'bkash', 'transaction_id' => 'IDEMP01',
    ])->assertRedirect(route('order.confirmation', $orderNumber));

    expect(Order::query()->count())->toBe(1);
    expect($sms->refresh()->is_matched)->toBeTrue();
});

test('a transaction consumed by an order that is later cancelled still cannot be reused for a new order', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);

    $pendingA = startMobileBankingCheckout($this, $userA, $product);
    $pendingB = startMobileBankingCheckout($this, $userB, $product);
    $amount = (float) $pendingA->checkout_data['total'];

    $sms = ReceivedSms::create([
        'sender' => '01799999999', 'provider' => 'bkash', 'content' => 'TrxID CANCEL01',
        'received_at' => now(), 'parsed_transaction_id' => 'CANCEL01', 'parsed_amount' => $amount, 'is_matched' => false,
    ]);

    $this->actingAs($userA)->post(route('payment.mobile.submit', $pendingA->token), [
        'provider' => 'bkash', 'transaction_id' => 'CANCEL01',
    ])->assertRedirect();

    $order = Order::query()->firstOrFail();

    // FSA-06 now correctly refuses normal admin cancellation of a paid
    // order (Admin\OrderController::cancel() refuses it, directing to "Mark
    // as Refunded" instead), so this can no longer be reached through the
    // admin cancellation endpoint. What this test actually verifies is
    // narrower than "cancellation works" — it's that a transaction id
    // already consumed by an order can never be reused for a different
    // order, regardless of that order's later status — so the cancelled
    // state is established directly against the model, independent of
    // whichever flow (FSA-06's own refund-then-close path, a courier
    // return/cancel report, etc.) a real cancelled-paid order would go
    // through.
    $order->update(['status' => OrderStatus::CANCELLED]);

    // Even though the order that consumed this transaction is cancelled,
    // the real-world payment still happened under that transaction id — it
    // must not become claimable again for an unrelated order.
    $this->actingAs($userB)->post(route('payment.mobile.submit', $pendingB->token), [
        'provider' => 'bkash', 'transaction_id' => 'CANCEL01',
    ])->assertSessionHasErrors('transaction_id');

    expect(Order::query()->count())->toBe(1);
    expect($sms->refresh()->matched_order_id)->toBe($order->id);
});

/**
 * @return array{pending: PendingCheckout, slug: string}
 */
function startLandingPageCheckout(TestCase $test, string $provider = 'bkash'): array
{
    $product = Product::factory()->create([
        'price' => 100, 'stock_quantity' => 10, 'is_active' => true, 'published_at' => now()->subDay(), 'free_delivery' => true,
    ]);
    $page = LandingPage::create([
        'title' => 'LP Test', 'slug' => 'lp-test-'.Str::random(8), 'product_id' => $product->id, 'is_active' => true, 'sections' => [],
    ]);

    $test->post(route('lp.order', $page->slug), [
        'name' => 'Guest Buyer', 'phone' => '01700000000',
        'address_line1' => 'House 1', 'city' => 'Dhaka', 'state' => 'Dhaka',
        'quantity' => 1, 'payment_method' => 'mobile_banking', 'provider' => $provider,
        'order_token' => (string) Str::uuid(),
    ])->assertRedirect(route('lp.show', $page->slug));

    $pending = PendingCheckout::query()->where('landing_page_id', $page->id)->latest('id')->firstOrFail();

    return ['pending' => $pending, 'slug' => $page->slug];
}

test('a landing-page transaction id with the correct amount confirms the order and marks the SMS matched', function () {
    [$pending, $slug] = array_values(startLandingPageCheckout($this));
    $amount = (float) $pending->checkout_data['total'];

    $sms = ReceivedSms::create([
        'sender' => '01799999999', 'provider' => 'bkash', 'content' => 'TrxID LPOK0001',
        'received_at' => now(), 'parsed_transaction_id' => 'LPOK0001', 'parsed_amount' => $amount, 'is_matched' => false,
    ]);

    $this->post(route('lp.payment.submit', [$slug, $pending->token]), [
        'transaction_id' => 'LPOK0001',
    ])->assertRedirect(route('lp.confirmation', $pending->fresh()->checkout_data['guest_token']));

    expect(Order::query()->where('payment_status', 'paid')->count())->toBe(1);
    expect($sms->refresh()->is_matched)->toBeTrue();
});

test('a landing-page transaction id with an incorrect amount is rejected and does not mark the order paid', function () {
    [$pending, $slug] = array_values(startLandingPageCheckout($this));
    $amount = (float) $pending->checkout_data['total'];

    $sms = ReceivedSms::create([
        'sender' => '01799999999', 'provider' => 'bkash', 'content' => 'TrxID LPBAD001',
        'received_at' => now(), 'parsed_transaction_id' => 'LPBAD001', 'parsed_amount' => $amount - 50, 'is_matched' => false,
    ]);

    $this->post(route('lp.payment.submit', [$slug, $pending->token]), [
        'transaction_id' => 'LPBAD001',
    ])->assertSessionHasErrors('transaction_id');

    expect(Order::query()->count())->toBe(0);
    expect($sms->refresh()->is_matched)->toBeFalse();
});

test('a landing-page transaction id with no parseable amount fails closed instead of verifying payment', function () {
    // PAY-001 regression (landing-page funnel): a matching transaction ID
    // alone must never verify a payment — if the SMS amount couldn't be
    // parsed (null), the order must not be created/marked paid just
    // because nothing contradicted the claim. Mirrors the equivalent
    // MobilePaymentController test above.
    [$pending, $slug] = array_values(startLandingPageCheckout($this));

    $sms = ReceivedSms::create([
        'sender' => '01799999999', 'provider' => 'bkash', 'content' => 'TrxID LPNOAMT1 (unparseable amount text)',
        'received_at' => now(), 'parsed_transaction_id' => 'LPNOAMT1', 'parsed_amount' => null, 'is_matched' => false,
    ]);

    $this->post(route('lp.payment.submit', [$slug, $pending->token]), [
        'transaction_id' => 'LPNOAMT1',
    ])->assertSessionHasErrors('transaction_id');

    expect(Order::query()->count())->toBe(0);
    expect($sms->refresh()->is_matched)->toBeFalse();
});

test('the landing-page guest checkout cannot reuse a transaction id already consumed by another order', function () {
    $product = Product::factory()->create([
        'price' => 100, 'stock_quantity' => 10, 'is_active' => true, 'published_at' => now()->subDay(), 'free_delivery' => true,
    ]);
    $page = LandingPage::create([
        'title' => 'LP Test', 'slug' => 'lp-test-'.Str::random(8), 'product_id' => $product->id, 'is_active' => true, 'sections' => [],
    ]);

    // Each call is a genuinely separate purchase attempt (two distinct
    // PendingCheckout rows are the point of this test), so each gets its
    // own idempotency token — reusing the same token would dedupe the
    // second call against the first (CART-01).
    $placeOrder = fn () => $this->post(route('lp.order', $page->slug), [
        'name' => 'Guest Buyer', 'phone' => '01700000000',
        'address_line1' => 'House 1', 'city' => 'Dhaka', 'state' => 'Dhaka',
        'quantity' => 1, 'payment_method' => 'mobile_banking', 'provider' => 'bkash',
        'order_token' => (string) Str::uuid(),
    ])->assertRedirect(route('lp.show', $page->slug));

    $placeOrder();
    $pendingA = PendingCheckout::query()->where('landing_page_id', $page->id)->latest('id')->firstOrFail();

    $placeOrder();
    $pendingB = PendingCheckout::query()->where('landing_page_id', $page->id)->latest('id')->firstOrFail();

    $amount = (float) $pendingA->checkout_data['total'];
    $sms = ReceivedSms::create([
        'sender' => '01799999999', 'provider' => 'bkash', 'content' => 'TrxID LPREUSE1',
        'received_at' => now(), 'parsed_transaction_id' => 'LPREUSE1', 'parsed_amount' => $amount, 'is_matched' => false,
    ]);

    $this->post(route('lp.payment.submit', [$page->slug, $pendingA->token]), [
        'transaction_id' => 'LPREUSE1',
    ])->assertRedirect(route('lp.confirmation', $pendingA->fresh()->checkout_data['guest_token']));

    $this->post(route('lp.payment.submit', [$page->slug, $pendingB->token]), [
        'transaction_id' => 'LPREUSE1',
    ])->assertSessionHasErrors('transaction_id');

    expect(Order::query()->where('payment_status', 'paid')->count())->toBe(1);
    expect($sms->refresh()->matched_order_id)->not->toBeNull();
});
