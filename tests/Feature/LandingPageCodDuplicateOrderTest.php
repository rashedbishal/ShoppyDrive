<?php

declare(strict_types=1);

use App\Mail\NewOrderAlertMail;
use App\Mail\OrderPlacedMail;
use App\Models\LandingPage;
use App\Models\Order;
use App\Models\PendingCheckout;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

use function Pest\Laravel\post;

uses(RefreshDatabase::class);

beforeEach(function () {
    createMotherAdmin();

    $this->product = Product::factory()->create([
        'is_active' => true,
        'published_at' => now()->subDay(),
        'price' => 500,
        'stock_quantity' => 50,
    ]);

    $this->page = LandingPage::create([
        'title' => 'Promo',
        'slug' => 'promo-'.uniqid(),
        'product_id' => $this->product->id,
        'is_active' => true,
        'sections' => [],
    ]);
});

function codOrderPayload(string $orderToken, array $overrides = []): array
{
    return array_merge([
        'name' => 'Test Customer',
        'phone' => '01712345678',
        'email' => 'customer@example.com',
        'address_line1' => '123 Main St',
        'city' => 'Dhaka',
        'state' => 'Dhaka',
        'quantity' => 1,
        'payment_method' => 'cod',
        'order_token' => $orderToken,
    ], $overrides);
}

test('submitting the same order token twice creates exactly one order', function () {
    Mail::fake();
    $token = (string) Str::uuid();

    $first = post(route('lp.order', $this->page->slug), codOrderPayload($token));
    $first->assertRedirect();

    $second = post(route('lp.order', $this->page->slug), codOrderPayload($token));
    $second->assertRedirect();

    expect(Order::count())->toBe(1);

    // The retry must land on the same confirmation destination as the
    // original — not a generic error, not a fresh order.
    $order = Order::firstOrFail();
    $first->assertRedirect(route('lp.confirmation', $order->guest_token));
    $second->assertRedirect(route('lp.confirmation', $order->guest_token));
});

test('two different order tokens allow two separate legitimate purchases', function () {
    Mail::fake();

    $first = post(route('lp.order', $this->page->slug), codOrderPayload((string) Str::uuid()));
    $first->assertRedirect();

    $second = post(route('lp.order', $this->page->slug), codOrderPayload((string) Str::uuid()));
    $second->assertRedirect();

    expect(Order::count())->toBe(2);
});

test('stock is decremented only once when the same token is submitted twice', function () {
    Mail::fake();
    $token = (string) Str::uuid();

    post(route('lp.order', $this->page->slug), codOrderPayload($token, ['quantity' => 3]));
    post(route('lp.order', $this->page->slug), codOrderPayload($token, ['quantity' => 3]));

    expect($this->product->refresh()->stock_quantity)->toBe(47);
});

test('notifications are dispatched only once per logical purchase for a repeated token', function () {
    Mail::fake();
    $token = (string) Str::uuid();

    post(route('lp.order', $this->page->slug), codOrderPayload($token));
    post(route('lp.order', $this->page->slug), codOrderPayload($token));

    // Both mailables implement ShouldQueue, so Mail::fake() records them as
    // queued rather than sent — matching the assertion style already used
    // for them elsewhere (tests/Feature/OrderNotificationTest.php).
    Mail::assertQueuedTimes(OrderPlacedMail::class, 1);
    Mail::assertQueuedTimes(NewOrderAlertMail::class, 1);
});

test('a missing order token fails validation instead of silently bypassing idempotency', function () {
    $payload = codOrderPayload((string) Str::uuid());
    unset($payload['order_token']);

    post(route('lp.order', $this->page->slug), $payload)->assertInvalid(['order_token']);
    expect(Order::count())->toBe(0);
});

test('a malformed order token fails validation instead of silently bypassing idempotency', function () {
    post(route('lp.order', $this->page->slug), codOrderPayload('not-a-uuid'))->assertInvalid(['order_token']);
    expect(Order::count())->toBe(0);
});

test('a request that cannot acquire the lock because another is in progress never falls through to order creation', function () {
    $token = (string) Str::uuid();

    // Hold the lock ourselves, simulating a first request that's still
    // mid-flight, and never release it — the second request must time out
    // waiting for it rather than guessing and creating a duplicate.
    $heldLock = Cache::lock("lp-order-lock:{$token}", 30);
    expect($heldLock->get())->toBeTrue();

    $response = post(route('lp.order', $this->page->slug), codOrderPayload($token));

    $response->assertRedirect();
    $response->assertSessionHasErrors(['order_token']);
    expect(Order::count())->toBe(0);

    $heldLock->release();
})->group('slow');

test('retrying the same token after the first request already completed returns the original confirmation, not a new order', function () {
    Mail::fake();
    $token = (string) Str::uuid();

    $first = post(route('lp.order', $this->page->slug), codOrderPayload($token));
    $order = Order::firstOrFail();

    // Simulate a retry arriving well after the first request's lock was
    // released and its result cached — the lock itself is free now, so
    // this exercises the cached-result short-circuit, not lock contention.
    $retry = post(route('lp.order', $this->page->slug), codOrderPayload($token));

    $retry->assertRedirect(route('lp.confirmation', $order->guest_token));
    expect(Order::count())->toBe(1);
});

test('mobile banking duplicate submission with the same token does not create a second PendingCheckout', function () {
    $token = (string) Str::uuid();
    $payload = codOrderPayload($token, ['payment_method' => 'mobile_banking', 'provider' => 'bkash']);
    unset($payload['payment_method']);
    $payload['payment_method'] = 'mobile_banking';

    $first = post(route('lp.order', $this->page->slug), $payload);
    $first->assertRedirect(route('lp.show', $this->page->slug));
    expect(PendingCheckout::count())->toBe(1);
    $originalToken = PendingCheckout::firstOrFail()->token;

    $second = post(route('lp.order', $this->page->slug), $payload);
    $second->assertRedirect(route('lp.show', $this->page->slug));

    // Still exactly one staged checkout — the retry reused the existing
    // one instead of creating a duplicate.
    expect(PendingCheckout::count())->toBe(1);
    expect(PendingCheckout::firstOrFail()->token)->toBe($originalToken);
    $second->assertSessionHas('lp_pending_payment.token', $originalToken);
});
