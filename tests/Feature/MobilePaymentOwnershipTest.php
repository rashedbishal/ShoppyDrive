<?php

declare(strict_types=1);

/*
 * FSA-22: MobilePaymentController::show()/submit() used to compare
 * auth()->id() against PendingCheckout.user_id with a strict `!==`. For a
 * guest-staged checkout (user_id null), any later auth-state change in that
 * same browser — most commonly, the guest logging in before confirming the
 * transaction ID — made that comparison fail and locked the guest out of
 * their own pending payment with a 403, even though the unguessable token
 * is already this app's access boundary for guest-originated checkouts
 * (same pattern as `guest_token` for order confirmation).
 *
 * The fix only skips ownership enforcement when the checkout was staged as
 * a guest (user_id === null); a checkout staged by a specific logged-in
 * user still enforces that exact ownership, in both directions (a
 * different user, or having since logged out).
 */

use App\Models\Cart;
use App\Models\PendingCheckout;
use App\Models\Product;
use App\Models\ReceivedSms;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;

function fsa22EnableGuestCheckout(): void
{
    Setting::updateOrCreate(['key' => 'guest_checkout_enabled'], ['value' => true, 'group' => 'general']);
}

function fsa22StartGuestMobileBankingCheckout(TestCase $test, Product $product): PendingCheckout
{
    fsa22EnableGuestCheckout();

    $test->post(route('checkout.store'), [
        'name' => 'Guest Buyer',
        'phone' => '01700000000',
        'address_line1' => 'House 1',
        'city' => 'Dhaka',
        'state' => 'Dhaka',
        'country' => 'Bangladesh',
        'payment_method' => 'mobile_banking',
        'provider' => 'bkash',
        'order_token' => (string) Str::uuid(),
        'items' => [
            ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1],
        ],
    ])->assertRedirect();

    return PendingCheckout::query()->whereNull('user_id')->latest('id')->firstOrFail();
}

function fsa22StartOwnedMobileBankingCheckout(TestCase $test, User $user, Product $product): PendingCheckout
{
    // Guest checkout must be enabled so the later logged-out scenario's
    // unauthenticated request reaches the controller's own ownership
    // check at all — otherwise EnsureCheckoutAccess redirects it to
    // login before abort_if() ever runs, which would test the wrong
    // layer entirely.
    fsa22EnableGuestCheckout();

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
        'provider' => 'bkash',
        'order_token' => (string) Str::uuid(),
        'items' => [
            ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1],
        ],
    ])->assertRedirect();

    return PendingCheckout::query()->where('user_id', $user->id)->latest('id')->firstOrFail();
}

// Scenario 1 & 2: guest stages, then logs in, before confirming payment.

test('a guest-originated pending checkout is still viewable after the guest logs in', function () {
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);
    $pending = fsa22StartGuestMobileBankingCheckout($this, $product);

    $laterUser = User::factory()->create();

    $response = $this->actingAs($laterUser)->get(route('payment.mobile.show', $pending->token));

    $response->assertOk();
});

test('a guest-originated pending checkout can still be confirmed after the guest logs in', function () {
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);
    $pending = fsa22StartGuestMobileBankingCheckout($this, $product);
    $amount = (float) $pending->checkout_data['total'];

    $sms = ReceivedSms::create([
        'sender' => '01799999999', 'provider' => 'bkash', 'content' => 'TrxID FSA22OK1',
        'received_at' => now(), 'parsed_transaction_id' => 'FSA22OK1', 'parsed_amount' => $amount, 'is_matched' => false,
    ]);

    $laterUser = User::factory()->create();

    $response = $this->actingAs($laterUser)->post(route('payment.mobile.submit', $pending->token), [
        'provider' => 'bkash', 'transaction_id' => 'fsa22ok1',
    ]);

    $response->assertRedirect(route('order.confirmation', $pending->fresh()->order_number));
    expect($sms->refresh()->is_matched)->toBeTrue();
});

// Scenario 3: guest-originated pending checkout accessible with no auth at
// all (baseline, must remain unchanged).

test('a guest-originated pending checkout is still viewable with no auth at all', function () {
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);
    $pending = fsa22StartGuestMobileBankingCheckout($this, $product);

    $response = $this->get(route('payment.mobile.show', $pending->token));

    $response->assertOk();
});

// Scenario 4: a different logged-in user must still be refused.

test('a different logged-in user cannot view another logged-in user\'s pending checkout', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);
    $pending = fsa22StartOwnedMobileBankingCheckout($this, $owner, $product);

    $response = $this->actingAs($intruder)->get(route('payment.mobile.show', $pending->token));

    $response->assertForbidden();
});

test('a different logged-in user cannot submit a transaction id for another logged-in user\'s pending checkout', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);
    $pending = fsa22StartOwnedMobileBankingCheckout($this, $owner, $product);

    $response = $this->actingAs($intruder)->post(route('payment.mobile.submit', $pending->token), [
        'provider' => 'bkash', 'transaction_id' => 'INTRUDE1',
    ]);

    $response->assertForbidden();
});

// Scenario 5: logging out of a logged-in-originated checkout must still 403.

test('an unauthenticated request cannot view a logged-in user\'s pending checkout after logging out', function () {
    $owner = User::factory()->create();
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);
    $pending = fsa22StartOwnedMobileBankingCheckout($this, $owner, $product);

    $this->post(route('logout'));

    $response = $this->get(route('payment.mobile.show', $pending->token));

    $response->assertForbidden();
});

test('an unauthenticated request cannot submit a transaction id for a logged-in user\'s pending checkout after logging out', function () {
    $owner = User::factory()->create();
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);
    $pending = fsa22StartOwnedMobileBankingCheckout($this, $owner, $product);

    $this->post(route('logout'));

    $response = $this->post(route('payment.mobile.submit', $pending->token), [
        'provider' => 'bkash', 'transaction_id' => 'LOGGEDOUT',
    ]);

    $response->assertForbidden();
});

// The owner themselves (never logging out or in) must remain unaffected.

test('the original logged-in owner can still view and confirm their own pending checkout', function () {
    $owner = User::factory()->create();
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);
    $pending = fsa22StartOwnedMobileBankingCheckout($this, $owner, $product);

    $this->actingAs($owner)->get(route('payment.mobile.show', $pending->token))->assertOk();

    $amount = (float) $pending->checkout_data['total'];
    $sms = ReceivedSms::create([
        'sender' => '01799999999', 'provider' => 'bkash', 'content' => 'TrxID FSA22OWN',
        'received_at' => now(), 'parsed_transaction_id' => 'FSA22OWN', 'parsed_amount' => $amount, 'is_matched' => false,
    ]);

    $response = $this->actingAs($owner)->post(route('payment.mobile.submit', $pending->token), [
        'provider' => 'bkash', 'transaction_id' => 'fsa22own',
    ]);

    $response->assertRedirect(route('order.confirmation', $pending->fresh()->order_number));
    expect($sms->refresh()->is_matched)->toBeTrue();
});
