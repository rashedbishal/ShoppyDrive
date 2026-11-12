<?php

declare(strict_types=1);

use App\Mail\PaidOrderOutOfStockAlertMail;
use App\Models\Cart;
use App\Models\LandingPage;
use App\Models\Order;
use App\Models\PendingCheckout;
use App\Models\Product;
use App\Models\ReceivedSms;
use App\Models\User;
use App\Support\PaidOrderStockFailure;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/*
 * DISC-19: a bKash/Nagad payment verified by SMS, but an item sold out before
 * the order could be created. No order, no stock change, the SMS stays
 * unmatched, one persistent record per SMS on the pending checkout, one alert
 * per record, and a label on that SMS in Admin › Received SMS.
 */

function disc19CartCheckout(TestCase $test, User $user, Product $product): PendingCheckout
{
    Cart::query()->where('user_id', $user->id)->delete();

    $test->actingAs($user)->post(route('checkout.store'), [
        'name' => 'Paid Customer', 'email' => $user->email, 'phone' => '01700000000',
        'address_line1' => 'House 1', 'city' => 'Dhaka', 'state' => 'Dhaka', 'country' => 'Bangladesh',
        'payment_method' => 'mobile_banking', 'order_token' => (string) Str::uuid(),
        'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1]],
    ])->assertRedirect();

    return PendingCheckout::query()->where('user_id', $user->id)->latest('id')->firstOrFail();
}

function disc19LandingCheckout(TestCase $test, Product $product): array
{
    $page = LandingPage::create([
        'title' => 'LP', 'slug' => 'lp-disc19-'.Str::random(8), 'product_id' => $product->id, 'is_active' => true, 'sections' => [],
    ]);

    $test->post(route('lp.order', $page->slug), [
        'name' => 'Guest Buyer', 'phone' => '01700000000', 'address_line1' => 'House 1', 'city' => 'Dhaka', 'state' => 'Dhaka',
        'quantity' => 1, 'payment_method' => 'mobile_banking', 'provider' => 'bkash', 'order_token' => (string) Str::uuid(),
    ])->assertRedirect(route('lp.show', $page->slug));

    return [PendingCheckout::query()->where('landing_page_id', $page->id)->latest('id')->firstOrFail(), $page->slug];
}

function disc19Sms(string $transactionId, float $amount): ReceivedSms
{
    return ReceivedSms::create([
        'sender' => 'bKash', 'provider' => 'bkash', 'content' => "TrxID {$transactionId}", 'received_at' => now(),
        'parsed_transaction_id' => $transactionId, 'parsed_amount' => $amount, 'is_matched' => false,
    ]);
}

function disc19Product(): Product
{
    return Product::factory()->create([
        'name' => 'Steel Water Bottle', 'price' => 100, 'stock_quantity' => 5,
        'is_active' => true, 'published_at' => now()->subDay(), 'free_delivery' => true,
    ]);
}

function disc19Failures(PendingCheckout $pending): array
{
    return $pending->fresh()->checkout_data[PaidOrderStockFailure::DATA_KEY] ?? [];
}

beforeEach(function () {
    createMotherAdmin(['email' => 'owner@example.com']);
});

test('cart checkout: a paid TrxID for a sold-out item creates no order, records the failure, alerts once and tells the customer', function () {
    Mail::fake();
    $user = User::factory()->create();
    $product = disc19Product();
    $pending = disc19CartCheckout($this, $user, $product);
    $amount = (float) $pending->checkout_data['total'];
    $product->update(['stock_quantity' => 0]);
    $sms = disc19Sms('PAIDOOS1', $amount);

    $this->actingAs($user)->post(route('payment.mobile.submit', $pending->token), ['provider' => 'bkash', 'transaction_id' => 'paidoos1'])
        ->assertSessionHasErrors(['transaction_id' => 'We received your payment of ৳'.number_format($amount, 2).' (TrxID: PAIDOOS1), but unfortunately, Steel Water Bottle became unavailable before your order could be confirmed. Our team has been notified and will contact you shortly to arrange the next steps. We apologize for the inconvenience.']);

    expect(Order::query()->count())->toBe(0)
        ->and($sms->fresh()->is_matched)->toBeFalse()
        ->and($product->fresh()->stock_quantity)->toBe(0)
        ->and($pending->fresh()->order_number)->toBeNull();

    $failures = disc19Failures($pending);
    expect($failures)->toHaveCount(1)
        ->and($failures[0]['sms_id'])->toBe($sms->id)
        ->and($failures[0]['transaction_id'])->toBe('PAIDOOS1')
        ->and($failures[0]['unavailable_item'])->toBe('Steel Water Bottle');

    Mail::assertQueued(PaidOrderOutOfStockAlertMail::class, 1);
    Mail::assertQueued(PaidOrderOutOfStockAlertMail::class, fn (PaidOrderOutOfStockAlertMail $mail) => $mail->hasTo('owner@example.com')
        && $mail->transactionId === 'PAIDOOS1'
        && $mail->unavailableItem === 'Steel Water Bottle');
});

test('resubmitting the same SMS records and alerts only once, but a different verified SMS gets its own record and alert', function () {
    Mail::fake();
    $user = User::factory()->create();
    $product = disc19Product();
    $pending = disc19CartCheckout($this, $user, $product);
    $amount = (float) $pending->checkout_data['total'];
    $product->update(['stock_quantity' => 0]);
    $first = disc19Sms('PAIDOOS2', $amount);

    foreach ([1, 2] as $attempt) {
        $this->actingAs($user)->post(route('payment.mobile.submit', $pending->token), ['provider' => 'bkash', 'transaction_id' => 'PAIDOOS2'])
            ->assertSessionHasErrors('transaction_id');
    }

    expect(disc19Failures($pending))->toHaveCount(1);
    Mail::assertQueued(PaidOrderOutOfStockAlertMail::class, 1);

    $second = disc19Sms('PAIDOOS3', $amount);
    $this->actingAs($user)->post(route('payment.mobile.submit', $pending->token), ['provider' => 'bkash', 'transaction_id' => 'PAIDOOS3'])
        ->assertSessionHasErrors('transaction_id');

    expect(collect(disc19Failures($pending))->pluck('sms_id')->all())->toBe([$first->id, $second->id]);
    Mail::assertQueued(PaidOrderOutOfStockAlertMail::class, 2);
});

test('the SMS stays usable: once stock returns, resubmitting it completes the order as before', function () {
    Mail::fake();
    $user = User::factory()->create();
    $product = disc19Product();
    $pending = disc19CartCheckout($this, $user, $product);
    $product->update(['stock_quantity' => 0]);
    $sms = disc19Sms('PAIDOOS4', (float) $pending->checkout_data['total']);

    $this->actingAs($user)->post(route('payment.mobile.submit', $pending->token), ['provider' => 'bkash', 'transaction_id' => 'PAIDOOS4']);

    $product->update(['stock_quantity' => 3]);

    $this->actingAs($user)->post(route('payment.mobile.submit', $pending->token), ['provider' => 'bkash', 'transaction_id' => 'PAIDOOS4'])
        ->assertRedirect(route('order.confirmation', $pending->fresh()->order_number));

    expect(Order::query()->count())->toBe(1)
        ->and($sms->fresh()->is_matched)->toBeTrue()
        ->and($product->fresh()->stock_quantity)->toBe(2);
});

test('landing-page checkout: the same failure is recorded, alerted once and shown to the customer', function () {
    Mail::fake();
    $product = disc19Product();
    [$pending, $slug] = disc19LandingCheckout($this, $product);
    $amount = (float) $pending->checkout_data['total'];
    $product->update(['stock_quantity' => 0]);
    $sms = disc19Sms('LPOOS001', $amount);

    foreach ([1, 2] as $attempt) {
        $this->post(route('lp.payment.submit', [$slug, $pending->token]), ['transaction_id' => 'LPOOS001'])
            ->assertRedirect(route('lp.show', $slug))
            ->assertSessionHasErrors(['transaction_id' => PaidOrderStockFailure::customerMessage($amount, 'LPOOS001', 'Steel Water Bottle')]);
    }

    expect(Order::query()->count())->toBe(0)
        ->and($sms->fresh()->is_matched)->toBeFalse()
        ->and(disc19Failures($pending))->toHaveCount(1);
    Mail::assertQueued(PaidOrderOutOfStockAlertMail::class, fn (PaidOrderOutOfStockAlertMail $mail) => $mail->landingPage);
    Mail::assertQueued(PaidOrderOutOfStockAlertMail::class, 1);
});

test('if the alert email cannot be queued, the failure is still recorded and the customer still gets the message', function () {
    $user = User::factory()->create();
    $product = disc19Product();
    $pending = disc19CartCheckout($this, $user, $product);
    $amount = (float) $pending->checkout_data['total'];
    $product->update(['stock_quantity' => 0]);
    $sms = disc19Sms('PAIDOOS5', $amount);

    // Only queuing the DISC-19 alert fails; the order emails sent inside the
    // (rolled back) order transaction behave normally.
    Mail::shouldReceive('to')->andReturn(new class
    {
        public function send(mixed $mailable): void {}

        public function queue(mixed $mailable): void
        {
            if ($mailable instanceof PaidOrderOutOfStockAlertMail) {
                throw new RuntimeException('mail transport down');
            }
        }
    });

    $this->actingAs($user)->post(route('payment.mobile.submit', $pending->token), ['provider' => 'bkash', 'transaction_id' => 'PAIDOOS5'])
        ->assertSessionHasErrors(['transaction_id' => PaidOrderStockFailure::customerMessage($amount, 'PAIDOOS5', 'Steel Water Bottle')]);

    expect(disc19Failures($pending))->toHaveCount(1)
        ->and($sms->fresh()->is_matched)->toBeFalse();
});

test('Received SMS shows the label only on the affected unmatched SMS, and not once it is matched or deleted', function () {
    // Guest landing-page flow, so no customer session is redirected away
    // from /admin (redirect-customer-from-admin).
    Mail::fake();
    $product = disc19Product();
    [$pending, $slug] = disc19LandingCheckout($this, $product);
    $product->update(['stock_quantity' => 0]);
    $affected = disc19Sms('PAIDOOS6', (float) $pending->checkout_data['total']);
    $unrelated = disc19Sms('OTHER001', 999);

    $this->post(route('lp.payment.submit', [$slug, $pending->token]), ['transaction_id' => 'PAIDOOS6']);

    $this->actingAs(createAdmin(), 'admin');

    $this->get(route('admin.received-sms.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where("stockFailures.{$affected->id}.unavailable_item", 'Steel Water Bottle')
            ->where("stockFailures.{$affected->id}.customer_phone", '01700000000')
            ->where("stockFailures.{$affected->id}.order_number", $pending->checkout_data['order_number'])
            ->missing("stockFailures.{$unrelated->id}"));

    $affected->update(['is_matched' => true]);

    $this->get(route('admin.received-sms.index'))
        ->assertInertia(fn (Assert $page) => $page->missing("stockFailures.{$affected->id}"));

    $affected->update(['is_matched' => false]);
    $affected->delete();

    $this->get(route('admin.received-sms.index'))
        ->assertInertia(fn (Assert $page) => $page->missing("stockFailures.{$affected->id}"));
});

test('a successful payment records nothing and sends no alert', function () {
    Mail::fake();
    $user = User::factory()->create();
    $product = disc19Product();
    $pending = disc19CartCheckout($this, $user, $product);
    disc19Sms('PAIDOK01', (float) $pending->checkout_data['total']);

    $this->actingAs($user)->post(route('payment.mobile.submit', $pending->token), ['provider' => 'bkash', 'transaction_id' => 'PAIDOK01'])
        ->assertRedirect(route('order.confirmation', $pending->fresh()->order_number));

    expect(Order::query()->count())->toBe(1)
        ->and(disc19Failures($pending))->toBe([]);
    Mail::assertNotQueued(PaidOrderOutOfStockAlertMail::class);
});
