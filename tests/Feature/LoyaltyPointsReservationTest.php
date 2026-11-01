<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Enums\PointTransactionType;
use App\Models\Cart;
use App\Models\Order;
use App\Models\PendingCheckout;
use App\Models\Product;
use App\Models\ReceivedSms;
use App\Models\ReferralPointTransaction;
use App\Models\Setting;
use App\Models\User;
use App\Services\LoyaltyPointsService;
use App\Services\OrderService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;

/**
 * CRIT-01: an unpaid mobile-banking checkout has already promised its
 * customer a discount, and they pay that discounted amount before the order
 * exists. The points behind it are therefore held from the moment the pending
 * checkout is created, so no other checkout can spend them.
 */
beforeEach(function () {
    Setting::updateOrCreate(['key' => 'points_redeem_value_bdt'], ['value' => 1, 'group' => 'referral']);
    Setting::updateOrCreate(['key' => 'points_redeem_max_percent'], ['value' => 50, 'group' => 'referral']);
});

/**
 * @param  array<string, mixed>  $overrides
 */
function reservationCheckoutPayload(Product $product, array $overrides = []): array
{
    return array_merge([
        'name' => 'Reservation Test',
        'phone' => '01700000000',
        'address_line1' => 'House 12, Road 7',
        'city' => 'Inside Dhaka City Corporation',
        'state' => 'Dhaka',
        'postal_code' => '1212',
        'country' => 'Bangladesh',
        'save_address' => false,
        'payment_method' => 'mobile_banking',
        'order_token' => (string) Str::uuid(),
        'redeem_points' => true,
        'points_to_redeem' => 100,
        'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1]],
    ], $overrides);
}

function startPointsCheckout(User $user, Product $product, int $points = 100): PendingCheckout
{
    Cart::query()->where('user_id', $user->id)->delete();

    actingAs($user)->post(route('checkout.store'), reservationCheckoutPayload($product, [
        'email' => $user->email,
        'points_to_redeem' => $points,
    ]))->assertRedirect();

    return PendingCheckout::query()->where('user_id', $user->id)->latest('id')->firstOrFail();
}

function submitPointsPayment(User $user, PendingCheckout $pending, string $transactionId): void
{
    ReceivedSms::firstOrCreate(['parsed_transaction_id' => $transactionId, 'provider' => 'bkash'], [
        'sender' => '01799999999',
        'content' => "TrxID {$transactionId}",
        'received_at' => now(),
        'parsed_amount' => (float) $pending->checkout_data['total'],
        'is_matched' => false,
    ]);

    actingAs($user)->post(route('payment.mobile.submit', $pending->token), [
        'provider' => 'bkash',
        'transaction_id' => $transactionId,
    ]);
}

function placeCodWithPoints(User $user, Product $product, int $points = 100): void
{
    Cart::query()->where('user_id', $user->id)->delete();

    actingAs($user)->post(route('checkout.store'), reservationCheckoutPayload($product, [
        'email' => $user->email,
        'payment_method' => 'cod',
        'points_to_redeem' => $points,
    ]))->assertRedirect();
}

test('a second pending checkout cannot reserve points the first one already holds', function () {
    $user = User::factory()->create(['points_balance' => 100]);
    $product = Product::factory()->create(['price' => 1000, 'stock_quantity' => 10]);

    $first = startPointsCheckout($user, $product);
    $second = startPointsCheckout($user, $product);

    expect($first->points_reserved)->toBe(100);
    expect((float) $first->checkout_data['discount'])->toBe(100.0);
    expect($second->points_reserved)->toBeNull();
    expect((int) $second->checkout_data['points_redeemed'])->toBe(0);
    expect((float) $second->checkout_data['discount'])->toBe(0.0);
    expect((float) $second->checkout_data['total'])->toBe((float) $first->checkout_data['total'] + 100);
});

test('a second pending checkout can only reserve whatever points are left', function () {
    $user = User::factory()->create(['points_balance' => 100]);
    $product = Product::factory()->create(['price' => 1000, 'stock_quantity' => 10]);

    startPointsCheckout($user, $product, 60);
    $second = startPointsCheckout($user, $product, 100);

    expect($second->points_reserved)->toBe(40);
    expect((float) $second->checkout_data['discount'])->toBe(40.0);
});

test('paying two overlapping pending checkouts debits only the points that were reserved', function () {
    $user = User::factory()->create(['points_balance' => 100]);
    $product = Product::factory()->create(['price' => 1000, 'stock_quantity' => 10]);

    $first = startPointsCheckout($user, $product);
    $second = startPointsCheckout($user, $product);

    submitPointsPayment($user, $first, 'OVERLAP001');
    submitPointsPayment($user, $second, 'OVERLAP002');

    expect(Order::query()->where('user_id', $user->id)->count())->toBe(2);
    expect($user->refresh()->points_balance)->toBe(0);
    expect((int) ReferralPointTransaction::query()->where('user_id', $user->id)->sum('points'))->toBe(-100);
});

test('cash on delivery cannot spend points held by a pending checkout', function () {
    $user = User::factory()->create(['points_balance' => 100]);
    $product = Product::factory()->create(['price' => 1000, 'stock_quantity' => 10]);

    $pending = startPointsCheckout($user, $product);
    placeCodWithPoints($user, $product);

    $codOrder = Order::query()->where('user_id', $user->id)->firstOrFail();
    expect($codOrder->points_redeemed)->toBeNull();
    expect((float) $codOrder->discount)->toBe(0.0);
    expect($user->refresh()->points_balance)->toBe(100);

    submitPointsPayment($user, $pending, 'CODHOLD001');

    expect($user->refresh()->points_balance)->toBe(0);
});

test('cash on delivery can still spend points that no pending checkout holds', function () {
    $user = User::factory()->create(['points_balance' => 100]);
    $product = Product::factory()->create(['price' => 1000, 'stock_quantity' => 10]);

    startPointsCheckout($user, $product, 60);
    placeCodWithPoints($user, $product);

    expect(Order::query()->where('user_id', $user->id)->firstOrFail()->points_redeemed)->toBe(40);
    expect($user->refresh()->points_balance)->toBe(60);
});

test('an expired reservation stops holding points without any cleanup', function () {
    $user = User::factory()->create(['points_balance' => 100]);
    $product = Product::factory()->create(['price' => 1000, 'stock_quantity' => 10]);

    $expired = startPointsCheckout($user, $product);
    $this->travelTo($expired->expires_at->copy()->addSecond());

    placeCodWithPoints($user, $product);

    expect(Order::query()->where('user_id', $user->id)->firstOrFail()->points_redeemed)->toBe(100);
    expect($user->refresh()->points_balance)->toBe(0);
    expect(PendingCheckout::query()->whereKey($expired->id)->exists())->toBeTrue();
});

test('an expired pending checkout cannot be paid, so its points cannot be spent twice', function () {
    $user = User::factory()->create(['points_balance' => 100]);
    $product = Product::factory()->create(['price' => 1000, 'stock_quantity' => 10]);

    $expired = startPointsCheckout($user, $product);
    $this->travelTo($expired->expires_at->copy()->addSecond());
    placeCodWithPoints($user, $product);

    submitPointsPayment($user, $expired, 'EXPIRED001');

    expect($expired->fresh()->order_number)->toBeNull();
    expect(Order::query()->where('user_id', $user->id)->count())->toBe(1);
    expect($user->refresh()->points_balance)->toBe(0);
});

test('a reservation still holds points during its final second', function () {
    $user = User::factory()->create(['points_balance' => 100]);
    $product = Product::factory()->create(['price' => 1000, 'stock_quantity' => 10]);

    $pending = startPointsCheckout($user, $product);
    $this->travelTo($pending->expires_at);

    expect(app(LoyaltyPointsService::class)->availablePoints($user->refresh()))->toBe(0);
});

test('a converted pending checkout no longer reserves on top of its real debit', function () {
    $user = User::factory()->create(['points_balance' => 100]);
    $product = Product::factory()->create(['price' => 1000, 'stock_quantity' => 10]);

    $pending = startPointsCheckout($user, $product, 60);
    submitPointsPayment($user, $pending, 'CONVERT001');

    expect($pending->fresh()->order_number)->not->toBeNull();
    expect(app(LoyaltyPointsService::class)->availablePoints($user->refresh()))->toBe(40);
});

test('a pending checkout that fails payment verification keeps holding its points', function () {
    $user = User::factory()->create(['points_balance' => 100]);
    $product = Product::factory()->create(['price' => 1000, 'stock_quantity' => 10]);

    $pending = startPointsCheckout($user, $product);

    actingAs($user)->post(route('payment.mobile.submit', $pending->token), [
        'provider' => 'bkash',
        'transaction_id' => 'UNKNOWN001',
    ]);

    expect($user->refresh()->points_balance)->toBe(100);
    expect(app(LoyaltyPointsService::class)->availablePoints($user))->toBe(0);
});

test('cancelling a pending checkout releases its points', function () {
    $user = User::factory()->create(['points_balance' => 100]);
    $product = Product::factory()->create(['price' => 1000, 'stock_quantity' => 10]);

    $pending = startPointsCheckout($user, $product);

    actingAs($user)->get(route('payment.cancel', $pending->token))->assertRedirect(route('checkout.index'));

    expect(app(LoyaltyPointsService::class)->availablePoints($user->refresh()))->toBe(100);
});

test('another customer cannot cancel a pending checkout to release its points', function () {
    $owner = User::factory()->create(['points_balance' => 100]);
    $product = Product::factory()->create(['price' => 1000, 'stock_quantity' => 10]);

    $pending = startPointsCheckout($owner, $product);

    actingAs(User::factory()->create())->get(route('payment.cancel', $pending->token));

    expect(app(LoyaltyPointsService::class)->availablePoints($owner->refresh()))->toBe(0);
});

test('a duplicate payment submission debits the reserved points only once', function () {
    $user = User::factory()->create(['points_balance' => 100]);
    $product = Product::factory()->create(['price' => 1000, 'stock_quantity' => 10]);

    $pending = startPointsCheckout($user, $product);

    submitPointsPayment($user, $pending, 'DUPLICATE01');
    submitPointsPayment($user, $pending, 'DUPLICATE01');

    expect(Order::query()->where('user_id', $user->id)->count())->toBe(1);
    expect($user->refresh()->points_balance)->toBe(0);
    expect(ReferralPointTransaction::query()->where('type', PointTransactionType::REDEEM)->count())->toBe(1);
});

test('resubmitting the same checkout token does not reserve the points again', function () {
    $user = User::factory()->create(['points_balance' => 100]);
    $product = Product::factory()->create(['price' => 1000, 'stock_quantity' => 10]);
    $payload = reservationCheckoutPayload($product, ['email' => $user->email]);

    actingAs($user)->post(route('checkout.store'), $payload)->assertRedirect();
    actingAs($user)->post(route('checkout.store'), $payload)->assertRedirect();

    expect(PendingCheckout::query()->where('user_id', $user->id)->count())->toBe(1);
    expect((int) PendingCheckout::query()->where('user_id', $user->id)->sum('points_reserved'))->toBe(100);
});

test('a ZiniPay confirmation debits the reservation once across callback and webhook', function () {
    $user = User::factory()->create(['points_balance' => 100]);
    $product = Product::factory()->create(['price' => 1000, 'stock_quantity' => 10]);

    $pending = startPointsCheckout($user, $product);
    $orders = app(OrderService::class);

    $orders->createFromPending($pending, ['transaction_id' => 'ZINI001']);
    $orders->createFromPending($pending, ['transaction_id' => 'ZINI001']);

    expect(Order::query()->where('user_id', $user->id)->count())->toBe(1);
    expect($user->refresh()->points_balance)->toBe(0);
    expect(app(LoyaltyPointsService::class)->availablePoints($user))->toBe(0);
});

test('an expired ZiniPay pending checkout is refused without debiting points', function () {
    $user = User::factory()->create(['points_balance' => 100]);
    $product = Product::factory()->create(['price' => 1000, 'stock_quantity' => 10]);

    $pending = startPointsCheckout($user, $product);
    $this->travelTo($pending->expires_at->copy()->addSecond());

    expect(fn () => app(OrderService::class)->createFromPending($pending, ['transaction_id' => 'ZINI002']))
        ->toThrow(RuntimeException::class);

    expect($user->refresh()->points_balance)->toBe(100);
});

test('refunding a reserved order restores the debited points exactly once', function () {
    $user = User::factory()->create(['points_balance' => 100]);
    $product = Product::factory()->create(['price' => 1000, 'stock_quantity' => 10]);

    $pending = startPointsCheckout($user, $product, 60);
    submitPointsPayment($user, $pending, 'REFUND0001');

    $order = Order::query()->where('order_number', $pending->fresh()->order_number)->firstOrFail();
    $order->update(['status' => OrderStatus::CANCELLED]);
    $order->update(['status' => OrderStatus::REFUNDED]);

    expect($user->refresh()->points_balance)->toBe(100);
    expect(app(LoyaltyPointsService::class)->availablePoints($user))->toBe(100);
});

test('the checkout page offers only points no pending checkout holds', function () {
    $user = User::factory()->create(['points_balance' => 100]);
    $product = Product::factory()->create(['price' => 1000, 'stock_quantity' => 10]);

    startPointsCheckout($user, $product, 60);

    actingAs($user)
        ->get(route('checkout.index', ['buy_now_product' => $product->id]))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('pointsBalance', 40));
});

test('the payment page tells only a points-holding checkout that it can be cancelled', function (int $balance, bool $holdsPoints) {
    $user = User::factory()->create(['points_balance' => $balance]);
    $product = Product::factory()->create(['price' => 1000, 'stock_quantity' => 10]);

    $pending = startPointsCheckout($user, $product, 60);

    actingAs($user)
        ->get(route('payment.mobile.show', $pending->token))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('holdsPoints', $holdsPoints));
})->with([
    'holding points' => [60, true],
    'no points' => [0, false],
]);

test('the migration reserves points only for pending checkouts that can still be paid', function () {
    $user = User::factory()->create();
    $migration = require database_path('migrations/2026_10_31_193742_add_points_reserved_to_pending_checkouts_table.php');
    $migration->down();

    $insert = function (array $attributes) use ($user): int {
        return DB::table('pending_checkouts')->insertGetId(array_merge([
            'token' => (string) Str::uuid(),
            'user_id' => $user->id,
            'checkout_data' => json_encode(['points_redeemed' => 75]),
            'expires_at' => now()->addHour(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));
    };

    $active = $insert([]);
    $noPoints = $insert(['checkout_data' => json_encode(['total' => 100])]);
    $expired = $insert(['expires_at' => now()->subMinute()]);
    $converted = $insert(['order_number' => 'ORD-CONVERTED']);
    $guest = $insert(['user_id' => null]);

    $migration->up();

    expect(DB::table('pending_checkouts')->pluck('points_reserved', 'id')->all())->toBe([
        $active => 75,
        $noPoints => null,
        $expired => null,
        $converted => null,
        $guest => null,
    ]);
});
