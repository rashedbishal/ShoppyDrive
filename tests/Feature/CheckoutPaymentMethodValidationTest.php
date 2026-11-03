<?php

declare(strict_types=1);

/*
 * FSA-23: `PlaceOrderRequest` used to accept `payment_method=zinipay`
 * (`in:cod,zinipay,mobile_banking`), but `CheckoutController::completeCheckout()`
 * has no branch for it — only `if (COD) {...}`, else unconditionally the
 * mobile-banking flow. ZiniPayService has no live initiation method
 * anywhere (only verifyPayment(), used by the historical-only
 * callback/webhook), so a `zinipay` submission was silently and
 * incorrectly staged as a mobile-banking PendingCheckout instead of being
 * rejected. The fix removes `zinipay` from the allowed list so it now
 * fails validation cleanly, like any other invalid value.
 */

use App\Models\Order;
use App\Models\PendingCheckout;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

function fsa23CheckoutPayload(User $user, Product $product, array $overrides = []): array
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
        'order_token' => (string) Str::uuid(),
        'items' => [
            ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1],
        ],
    ], $overrides);
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->product = Product::factory()->create([
        'price' => 500,
        'stock_quantity' => 50,
    ]);
});

test('a checkout submitted with payment_method=zinipay is rejected with a validation error', function () {
    $response = actingAs($this->user)->post(route('checkout.store'), fsa23CheckoutPayload($this->user, $this->product, [
        'payment_method' => 'zinipay',
    ]));

    $response->assertInvalid(['payment_method']);
});

test('no Order or PendingCheckout is created for a rejected zinipay checkout submission', function () {
    actingAs($this->user)->post(route('checkout.store'), fsa23CheckoutPayload($this->user, $this->product, [
        'payment_method' => 'zinipay',
    ]));

    expect(Order::count())->toBe(0);
    expect(PendingCheckout::count())->toBe(0);
});

test('COD checkout still works after removing zinipay from the allowed payment methods', function () {
    $response = actingAs($this->user)->post(route('checkout.store'), fsa23CheckoutPayload($this->user, $this->product, [
        'payment_method' => 'cod',
    ]));

    $order = Order::query()->firstOrFail();
    $response->assertRedirect(route('order.confirmation', $order->order_number));
    expect($order->payment_method->value)->toBe('cod');
});

test('mobile banking checkout still stages a PendingCheckout after removing zinipay from the allowed payment methods', function () {
    $response = actingAs($this->user)->post(route('checkout.store'), fsa23CheckoutPayload($this->user, $this->product, [
        'payment_method' => 'mobile_banking',
    ]));

    $pending = PendingCheckout::query()->where('user_id', $this->user->id)->latest('id')->firstOrFail();
    $response->assertRedirect(route('payment.mobile.show', $pending->token));
    expect(Order::count())->toBe(0);
});

test('an arbitrary invalid payment method is still rejected with a validation error', function () {
    $response = actingAs($this->user)->post(route('checkout.store'), fsa23CheckoutPayload($this->user, $this->product, [
        'payment_method' => 'bogus',
    ]));

    $response->assertInvalid(['payment_method']);
    expect(Order::count())->toBe(0);
    expect(PendingCheckout::count())->toBe(0);
});
