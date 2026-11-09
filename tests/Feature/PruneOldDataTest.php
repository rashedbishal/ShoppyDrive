<?php

declare(strict_types=1);

/*
 * DB-002: owner-approved retention. Each period is tested just inside and
 * just outside its limit, and orders, payments and logged-in customers'
 * carts must never be touched.
 */

use App\Console\Commands\PruneOldDataCommand as Prune;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\IncompleteOrder;
use App\Models\Order;
use App\Models\PendingCheckout;
use App\Models\Product;
use App\Models\ReceivedSms;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

function agedSms(bool $matched, int $daysOld, ?int $matchedOrderId = null): ReceivedSms
{
    $sms = ReceivedSms::create([
        'sender' => 'bKash', 'provider' => 'bkash', 'content' => 'TrxID '.Str::random(8), 'received_at' => now(),
        'parsed_transaction_id' => Str::random(10), 'parsed_amount' => 100, 'is_matched' => $matched, 'matched_order_id' => $matchedOrderId,
    ]);
    $sms->forceFill(['created_at' => now()->subDays($daysOld)])->saveQuietly();

    return $sms;
}

function agedCart(?int $userId, int $daysOld): Cart
{
    $cart = Cart::create(['user_id' => $userId, 'session_id' => $userId ? null : Str::random(20)]);
    CartItem::create(['cart_id' => $cart->id, 'product_id' => Product::factory()->create()->id, 'variant_id' => null, 'quantity' => 1, 'price' => 100]);
    $cart->forceFill(['updated_at' => now()->subDays($daysOld)])->saveQuietly();

    return $cart;
}

test('unfinished checkouts are removed after 60 days of no activity', function () {
    $old = IncompleteOrder::factory()->create();
    $old->forceFill(['updated_at' => now()->subDays(Prune::INCOMPLETE_ORDER_DAYS + 1)])->saveQuietly();
    $recent = IncompleteOrder::factory()->create();
    $recent->forceFill(['updated_at' => now()->subDays(Prune::INCOMPLETE_ORDER_DAYS - 1)])->saveQuietly();

    $this->artisan('data:prune')->assertSuccessful();

    expect(IncompleteOrder::whereKey($old->id)->exists())->toBeFalse()
        ->and(IncompleteOrder::whereKey($recent->id)->exists())->toBeTrue();
});

test('old guest carts go with their items, but logged-in carts are never touched', function () {
    $oldGuest = agedCart(null, Prune::GUEST_CART_DAYS + 1);
    $recentGuest = agedCart(null, Prune::GUEST_CART_DAYS - 1);
    $customer = User::factory()->create();
    Cart::where('user_id', $customer->id)->delete();
    $customerCart = agedCart($customer->id, 400);

    $this->artisan('data:prune')->assertSuccessful();

    expect(Cart::whereKey($oldGuest->id)->exists())->toBeFalse()
        ->and(CartItem::where('cart_id', $oldGuest->id)->exists())->toBeFalse()
        ->and(Cart::whereKey($recentGuest->id)->exists())->toBeTrue()
        ->and(Cart::whereKey($customerCart->id)->exists())->toBeTrue();
});

test('a pending checkout survives its guest cart being removed', function () {
    $cart = agedCart(null, Prune::GUEST_CART_DAYS + 1);
    $pending = PendingCheckout::create(['token' => Str::random(40), 'cart_id' => $cart->id, 'checkout_data' => ['total' => 100], 'expires_at' => now()->subDays(40)]);

    $this->artisan('data:prune')->assertSuccessful();

    expect($pending->refresh()->cart_id)->toBeNull();
});

test('unmatched SMS go after 90 days and matched ones after a year', function () {
    $oldUnmatched = agedSms(false, Prune::UNMATCHED_SMS_DAYS + 1);
    $recentUnmatched = agedSms(false, Prune::UNMATCHED_SMS_DAYS - 1);
    $order = Order::create([
        'order_number' => 'ORD-PRUNE-1', 'status' => 'delivered', 'payment_status' => 'paid', 'payment_method' => 'mobile_banking',
        'subtotal' => 100, 'shipping_cost' => 0, 'discount' => 0, 'tax' => 0, 'total' => 100, 'shipping_address' => [], 'billing_address' => [],
    ]);
    $evidence = agedSms(true, Prune::UNMATCHED_SMS_DAYS + 30, $order->id);
    $oldMatched = agedSms(true, Prune::MATCHED_SMS_DAYS + 1, $order->id);

    $this->artisan('data:prune')->assertSuccessful();

    expect(ReceivedSms::whereKey($oldUnmatched->id)->exists())->toBeFalse()
        ->and(ReceivedSms::whereKey($recentUnmatched->id)->exists())->toBeTrue()
        ->and(ReceivedSms::whereKey($evidence->id)->exists())->toBeTrue()
        ->and(ReceivedSms::whereKey($oldMatched->id)->exists())->toBeFalse()
        ->and(Order::whereKey($order->id)->exists())->toBeTrue();
});

test('more rows than one batch are all removed', function () {
    $old = now()->subDays(200);
    ReceivedSms::insert(array_map(fn (int $n) => [
        'sender' => 'bKash', 'provider' => 'bkash', 'content' => "SMS {$n}", 'received_at' => $old,
        'parsed_transaction_id' => "BULK{$n}", 'parsed_amount' => 100, 'is_matched' => false, 'created_at' => $old, 'updated_at' => $old,
    ], range(1, 501)));

    $this->artisan('data:prune')->assertSuccessful();

    expect(ReceivedSms::count())->toBe(0);
});

test('the audit trail clean-up really deletes year-old entries and keeps newer ones', function () {
    $old = activity()->log('old entry');
    $old->forceFill(['created_at' => now()->subDays(366)])->save();
    $recent = activity()->log('recent entry');

    $this->artisan('activitylog:clean --force')->assertSuccessful();

    expect(Activity::whereKey($old->id)->exists())->toBeFalse()
        ->and(Activity::whereKey($recent->id)->exists())->toBeTrue();
});

test('the failed job and batch clean-ups run', function () {
    $this->artisan('queue:prune-failed --hours=720')->assertSuccessful();
    $this->artisan('queue:prune-batches --hours=720 --unfinished=720 --cancelled=720')->assertSuccessful();
});

test('every clean-up is scheduled, with the options that make it actually run', function () {
    $commands = collect(app(Schedule::class)->events())->pluck('command')->implode("\n");

    expect($commands)->toContain('data:prune')
        ->toContain('cache:prune-expired')
        ->toContain('queue:prune-failed --hours=720')
        ->toContain('queue:prune-batches --hours=720 --unfinished=720 --cancelled=720')
        ->toContain('activitylog:clean --force');
    expect(config('activitylog.delete_records_older_than_days'))->toBe(365);
});
