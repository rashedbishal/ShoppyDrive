<?php

declare(strict_types=1);

use App\Mail\NewOrderAlertMail;
use App\Mail\OrderPlacedMail;
use App\Models\Cart;
use App\Models\Order;
use App\Models\PendingCheckout;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    createMotherAdmin();

    $this->user = User::factory()->create();
    $this->product = Product::factory()->create([
        'price' => 500,
        'stock_quantity' => 50,
    ]);
});

function checkoutCodPayload(User $user, Product $product, string $orderToken, array $overrides = []): array
{
    return array_merge([
        'name' => 'Checkout User',
        'email' => $user->email,
        'phone' => '01700000000',
        'address_line1' => 'House 1',
        'city' => 'Dhaka',
        'state' => 'Dhaka',
        'country' => 'Bangladesh',
        'payment_method' => 'cod',
        'order_token' => $orderToken,
        'items' => [
            ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1],
        ],
    ], $overrides);
}

test('submitting the same order token twice from the main checkout creates exactly one order', function () {
    Mail::fake();
    $token = (string) Str::uuid();

    $first = actingAs($this->user)->post(route('checkout.store'), checkoutCodPayload($this->user, $this->product, $token));
    $first->assertRedirect();

    $second = actingAs($this->user)->post(route('checkout.store'), checkoutCodPayload($this->user, $this->product, $token));
    $second->assertRedirect();

    expect(Order::count())->toBe(1);

    // The retry must land on the same confirmation destination as the
    // original — not a generic error, not a fresh order.
    $order = Order::firstOrFail();
    $first->assertRedirect(route('order.confirmation', $order->order_number, false));
    $second->assertRedirect(route('order.confirmation', $order->order_number, false));
});

test('two different order tokens allow two separate legitimate purchases', function () {
    Mail::fake();

    Cart::query()->where('user_id', $this->user->id)->delete();
    $first = actingAs($this->user)->post(route('checkout.store'), checkoutCodPayload($this->user, $this->product, (string) Str::uuid()));
    $first->assertRedirect();

    Cart::query()->where('user_id', $this->user->id)->delete();
    $second = actingAs($this->user)->post(route('checkout.store'), checkoutCodPayload($this->user, $this->product, (string) Str::uuid()));
    $second->assertRedirect();

    expect(Order::count())->toBe(2);
});

test('stock is decremented only once when the same checkout token is submitted twice', function () {
    Mail::fake();
    $token = (string) Str::uuid();
    $payload = checkoutCodPayload($this->user, $this->product, $token, [
        'items' => [['product_id' => $this->product->id, 'variant_id' => null, 'quantity' => 3]],
    ]);

    actingAs($this->user)->post(route('checkout.store'), $payload);
    actingAs($this->user)->post(route('checkout.store'), $payload);

    expect($this->product->refresh()->stock_quantity)->toBe(47);
});

test('notifications are dispatched only once per logical checkout for a repeated token', function () {
    Mail::fake();
    $token = (string) Str::uuid();
    $payload = checkoutCodPayload($this->user, $this->product, $token);

    actingAs($this->user)->post(route('checkout.store'), $payload);
    actingAs($this->user)->post(route('checkout.store'), $payload);

    // Both mailables implement ShouldQueue, so Mail::fake() records them as
    // queued rather than sent — matching the assertion style already used
    // for them elsewhere (tests/Feature/OrderNotificationTest.php).
    Mail::assertQueuedTimes(OrderPlacedMail::class, 1);
    Mail::assertQueuedTimes(NewOrderAlertMail::class, 1);
});

test('a missing order token fails validation instead of silently bypassing idempotency', function () {
    $payload = checkoutCodPayload($this->user, $this->product, (string) Str::uuid());
    unset($payload['order_token']);

    actingAs($this->user)->post(route('checkout.store'), $payload)->assertInvalid(['order_token']);
    expect(Order::count())->toBe(0);
});

test('a malformed order token fails validation instead of silently bypassing idempotency', function () {
    actingAs($this->user)->post(route('checkout.store'), checkoutCodPayload($this->user, $this->product, 'not-a-uuid'))
        ->assertInvalid(['order_token']);
    expect(Order::count())->toBe(0);
});

test('a checkout request that cannot acquire the lock because another is in progress never falls through to order creation', function () {
    $token = (string) Str::uuid();

    // Hold the lock ourselves, simulating a first request that's still
    // mid-flight, and never release it — the second request must time out
    // waiting for it rather than guessing and creating a duplicate.
    $heldLock = Cache::lock("checkout-order-lock:{$token}", 30);
    expect($heldLock->get())->toBeTrue();

    $response = actingAs($this->user)->post(route('checkout.store'), checkoutCodPayload($this->user, $this->product, $token));

    $response->assertRedirect();
    $response->assertSessionHasErrors(['order_token']);
    expect(Order::count())->toBe(0);

    $heldLock->release();
})->group('slow');

test('retrying the same checkout token after the first request already completed returns the original confirmation, not a new order', function () {
    Mail::fake();
    $token = (string) Str::uuid();

    actingAs($this->user)->post(route('checkout.store'), checkoutCodPayload($this->user, $this->product, $token));
    $order = Order::firstOrFail();

    // Simulate a retry arriving well after the first request's lock was
    // released and its result cached — the lock itself is free now, so
    // this exercises the cached-result short-circuit, not lock contention.
    $retry = actingAs($this->user)->post(route('checkout.store'), checkoutCodPayload($this->user, $this->product, $token));

    $retry->assertRedirect(route('order.confirmation', $order->order_number, false));
    expect(Order::count())->toBe(1);
});

test('mobile banking duplicate submission with the same checkout token does not create a second PendingCheckout', function () {
    $token = (string) Str::uuid();
    $payload = checkoutCodPayload($this->user, $this->product, $token, ['payment_method' => 'mobile_banking']);

    $first = actingAs($this->user)->post(route('checkout.store'), $payload);
    expect(PendingCheckout::count())->toBe(1);
    $originalToken = PendingCheckout::firstOrFail()->token;
    $first->assertRedirect(route('payment.mobile.show', $originalToken));

    $second = actingAs($this->user)->post(route('checkout.store'), $payload);

    // Still exactly one staged checkout — the retry reused the existing
    // one instead of creating a duplicate.
    expect(PendingCheckout::count())->toBe(1);
    expect(PendingCheckout::firstOrFail()->token)->toBe($originalToken);
    $second->assertRedirect(route('payment.mobile.show', $originalToken));
});
