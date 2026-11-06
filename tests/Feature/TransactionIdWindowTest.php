<?php

declare(strict_types=1);

/*
 * BIZ-025: someone who learned another customer's unused bKash/Nagad
 * Transaction ID (e.g. from a shared screenshot) could use it to pay for
 * their own same-amount order, and the error messages revealed other
 * payments' amounts and whether an ID had been used. A TrxID now only
 * counts if our server received its SMS after this checkout started
 * (owner: strict), and the errors no longer reveal those details.
 */

use App\Models\Cart;
use App\Models\LandingPage;
use App\Models\Order;
use App\Models\PendingCheckout;
use App\Models\Product;
use App\Models\ReceivedSms;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

const TRX_GENERIC = "This Transaction ID can't be used for this order. If you paid for this order, please contact support with your Transaction ID.";

beforeEach(fn () => Mail::fake());

function stageBkashCheckout(User $user): PendingCheckout
{
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);
    Cart::query()->where('user_id', $user->id)->delete();
    test()->actingAs($user)->post(route('checkout.store'), [
        'name' => 'Buyer', 'email' => $user->email, 'phone' => '01700000000', 'address_line1' => 'House 1',
        'city' => 'Dhaka', 'state' => 'Dhaka', 'country' => 'Bangladesh', 'payment_method' => 'mobile_banking',
        'order_token' => (string) Str::uuid(),
        'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1, 'price' => 100]],
    ])->assertRedirect();

    return PendingCheckout::where('user_id', $user->id)->latest('id')->firstOrFail();
}

/** An SMS our server received at $serverReceivedAt, stamped by the phone at $phoneTime. */
function bkashSms(string $trxId, float $amount, DateTimeInterface $serverReceivedAt, ?DateTimeInterface $phoneTime = null, bool $matched = false): ReceivedSms
{
    $sms = ReceivedSms::create([
        'sender' => 'bKash', 'provider' => 'bkash', 'content' => "TrxID {$trxId}", 'received_at' => $phoneTime ?? $serverReceivedAt,
        'parsed_transaction_id' => $trxId, 'parsed_amount' => $amount, 'is_matched' => $matched,
    ]);
    $sms->forceFill(['created_at' => $serverReceivedAt])->saveQuietly();

    return $sms;
}

test('a TrxID whose SMS reached us before this checkout started is refused, generically', function () {
    $old = bkashSms('KARIM123', 160, now()->subHour());
    $user = User::factory()->create();
    $pending = stageBkashCheckout($user);

    $this->actingAs($user)->post(route('payment.mobile.submit', $pending->token), ['provider' => 'bkash', 'transaction_id' => 'KARIM123'])
        ->assertSessionHasErrors(['transaction_id' => TRX_GENERIC]);

    expect(Order::count())->toBe(0)
        ->and($old->refresh()->is_matched)->toBeFalse()
        ->and(PendingCheckout::whereKey($pending->id)->exists())->toBeTrue();
});

test('a payment received after the checkout started is accepted even if the forwarder phone clock is behind', function () {
    $user = User::factory()->create();
    $pending = stageBkashCheckout($user);
    $amount = (float) $pending->checkout_data['total'];
    // Phone clock 10 minutes slow: its timestamp is before the checkout, our server's is after.
    bkashSms('REAL0001', $amount, now()->addMinute(), phoneTime: now()->subMinutes(10));

    $this->actingAs($user)->post(route('payment.mobile.submit', $pending->token), ['provider' => 'bkash', 'transaction_id' => 'REAL0001'])
        ->assertSessionHasNoErrors();

    expect(Order::where('payment_status', 'paid')->count())->toBe(1);
});

test('an already used TrxID gets the same generic answer, revealing nothing', function () {
    $user = User::factory()->create();
    $pending = stageBkashCheckout($user);
    bkashSms('USED0001', (float) $pending->checkout_data['total'], now()->addMinute(), matched: true);

    $this->actingAs($user)->post(route('payment.mobile.submit', $pending->token), ['provider' => 'bkash', 'transaction_id' => 'USED0001'])
        ->assertSessionHasErrors(['transaction_id' => TRX_GENERIC]);
});

test('an amount mismatch never reveals the other payment\'s amount', function () {
    $user = User::factory()->create();
    $pending = stageBkashCheckout($user);
    bkashSms('AMT00001', 1234, now()->addMinute());

    $response = $this->actingAs($user)->post(route('payment.mobile.submit', $pending->token), ['provider' => 'bkash', 'transaction_id' => 'AMT00001']);

    $response->assertSessionHasErrors('transaction_id');
    expect(session('errors')->first('transaction_id'))->not->toContain('1234')
        ->toContain('does not match your order total');
});

test('the landing page refuses a TrxID received before its checkout started', function () {
    bkashSms('LPOLD001', 100, now()->subHour());
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10, 'free_delivery' => true]);
    $page = LandingPage::create(['title' => 'LP', 'slug' => 'lp-window', 'product_id' => $product->id, 'is_active' => true, 'sections' => []]);
    $this->post(route('lp.order', $page->slug), [
        'name' => 'Guest', 'phone' => '01700000000', 'address_line1' => 'House 1', 'city' => 'Dhaka', 'state' => 'Dhaka',
        'quantity' => 1, 'payment_method' => 'mobile_banking', 'provider' => 'bkash', 'order_token' => (string) Str::uuid(),
    ]);
    $pending = PendingCheckout::where('landing_page_id', $page->id)->sole();

    $this->post(route('lp.payment.submit', ['lp-window', $pending->token]), ['transaction_id' => 'LPOLD001'])
        ->assertSessionHasErrors(['transaction_id' => TRX_GENERIC]);

    expect(Order::count())->toBe(0);
});
