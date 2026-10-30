<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\ReferralService;
use App\Support\OrderNumberGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

/**
 * DB-001: order, money, loyalty, coupon and audit history can't disappear
 * through deletion — enforced by the database (RESTRICT / SET NULL foreign
 * keys) as well as the application. Pure DML, so it runs on MySQL too.
 */
function historyOrder(array $overrides = []): Order
{
    return Order::create(array_merge([
        'order_number' => 'ORD-HIST-'.strtoupper(bin2hex(random_bytes(3))),
        'status' => OrderStatus::PROCESSING,
        'payment_status' => 'unpaid',
        'payment_method' => 'cod',
        'subtotal' => 500,
        'shipping_cost' => 60,
        'discount' => 0,
        'tax' => 0,
        'total' => 560,
        'shipping_address' => ['name' => 'Test', 'phone' => '01712345678'],
        'billing_address' => [],
    ], $overrides));
}

/** Same settings as ReferralProgramTest's enableReferralProgram(): 10% discount, 50 reward points, no delay. */
function enableReferralForHistoryTest(): void
{
    $settings = [
        'referral_enabled' => true,
        'referral_discount_type' => 'percent',
        'referral_discount_value' => 10,
        'referral_min_order_amount' => 0,
        'referral_reward_points' => 50,
        'referral_reward_delay_days' => 0,
        'referral_unlock_order_count' => 0,
        'referral_reward_cap_amount' => 0,
        'referral_reward_cap_period' => 'month',
    ];

    foreach ($settings as $key => $value) {
        Setting::updateOrCreate(['key' => $key], ['value' => $value, 'group' => 'referral']);
    }
}

/** Places one COD order for one unit of $product through the real checkout. */
function checkoutForHistoryTest(User $customer, Product $product): Order
{
    Cart::query()->where('user_id', $customer->id)->delete();

    actingAs($customer)->post(route('checkout.store'), [
        'name' => 'Checkout User',
        'phone' => '01700000000',
        'email' => $customer->email,
        'address_line1' => 'House 12, Road 7',
        'city' => 'Inside Dhaka City Corporation',
        'state' => 'Dhaka',
        'postal_code' => '1212',
        'country' => 'Bangladesh',
        'save_address' => false,
        'payment_method' => 'cod',
        'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1]],
    ]);

    return Order::where('user_id', $customer->id)->latest('id')->firstOrFail();
}

describe('database foreign keys', function () {
    test('raw SQL and model force-deletes cannot remove an order with financial records', function (Closure $addRecord) {
        $order = historyOrder();
        $addRecord($order);

        expect(fn () => DB::table('orders')->where('id', $order->id)->delete())->toThrow(QueryException::class);
        expect(fn () => $order->forceDelete())->toThrow(QueryException::class);

        expect(DB::table('orders')->where('id', $order->id)->exists())->toBeTrue();
    })->with([
        'payment' => [fn (Order $o) => DB::table('payments')->insert(['order_id' => $o->id, 'method' => 'zinipay', 'amount' => 560, 'status' => 'paid', 'created_at' => now(), 'updated_at' => now()])],
        'return request' => [fn (Order $o) => DB::table('return_requests')->insert(['order_id' => $o->id, 'user_id' => User::factory()->create()->id, 'reason' => 'x', 'status' => 'refunded', 'items' => '[]', 'created_at' => now(), 'updated_at' => now()])],
        'coupon use' => [fn (Order $o) => DB::table('coupon_uses')->insert(['coupon_id' => Coupon::factory()->create()->id, 'user_id' => User::factory()->create()->id, 'order_id' => $o->id, 'discount_amount' => 50, 'created_at' => now(), 'updated_at' => now()])],
        'consumed referral discount' => [fn (Order $o) => User::factory()->create()->forceFill(['referral_discount_used_order_id' => $o->id])->save()],
    ]);

    test('deleting a user never erases history rows they created on other customers\' orders', function () {
        $actor = User::factory()->create();
        $order = historyOrder(['user_id' => User::factory()->create()->id]);
        $historyId = DB::table('order_status_histories')->insertGetId(['order_id' => $order->id, 'status' => 'processing', 'notes' => 'Packed', 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
        $product = Product::factory()->create();
        $logId = DB::table('inventory_logs')->insertGetId(['product_id' => $product->id, 'type' => 'adjustment', 'quantity' => 1, 'created_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
        $questionId = DB::table('product_questions')->insertGetId(['product_id' => $product->id, 'user_id' => User::factory()->create()->id, 'question' => 'Size?', 'answer' => 'M', 'answered_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);

        $actor->forceDelete();

        expect(DB::table('order_status_histories')->where('id', $historyId)->value('created_by'))->toBeNull();
        expect(DB::table('inventory_logs')->where('id', $logId)->value('created_by'))->toBeNull();
        expect(DB::table('product_questions')->where('id', $questionId)->value('answered_by'))->toBeNull();
    });

    test('a customer with orders, ledger, coupon or return history cannot be hard-deleted by the database', function (Closure $addHistory) {
        $customer = User::factory()->create();
        $addHistory($customer);

        expect(fn () => DB::table('users')->where('id', $customer->id)->delete())->toThrow(QueryException::class);
        expect(DB::table('users')->where('id', $customer->id)->exists())->toBeTrue();
    })->with([
        'order' => [fn (User $u) => historyOrder(['user_id' => $u->id])],
        'loyalty ledger' => [fn (User $u) => DB::table('referral_point_transactions')->insert(['user_id' => $u->id, 'type' => 'earn_registration', 'points' => 20, 'created_at' => now(), 'updated_at' => now()])],
        'coupon use' => [fn (User $u) => DB::table('coupon_uses')->insert(['coupon_id' => Coupon::factory()->create()->id, 'user_id' => $u->id, 'order_id' => historyOrder()->id, 'discount_amount' => 10, 'created_at' => now(), 'updated_at' => now()])],
        'return request' => [fn (User $u) => DB::table('return_requests')->insert(['order_id' => historyOrder()->id, 'user_id' => $u->id, 'reason' => 'x', 'status' => 'pending', 'items' => '[]', 'created_at' => now(), 'updated_at' => now()])],
        'customers they referred' => [fn (User $u) => User::factory()->create(['referred_by_id' => $u->id])],
    ]);
});

describe('coupons', function () {
    test('deleting a coupon in the admin soft-deletes it and keeps every order that used it', function () {
        $coupon = Coupon::factory()->create(['code' => 'SAVE50']);
        $order = historyOrder(['coupon_id' => $coupon->id, 'coupon_code' => 'SAVE50', 'discount' => 50]);
        $another = Coupon::factory()->create();

        actingAs(createAdmin(), 'admin')->delete(route('admin.coupons.destroy', $coupon))->assertSessionHas('success');
        actingAs(createAdmin(), 'admin')->delete(route('admin.coupons.destroyMany'), ['ids' => [$another->id]])->assertSessionHas('success');

        expect(Coupon::find($coupon->id))->toBeNull()
            ->and(Coupon::withTrashed()->find($coupon->id)->trashed())->toBeTrue()
            ->and(Coupon::withTrashed()->find($another->id)->trashed())->toBeTrue();
        $order->refresh();
        expect($order->coupon_id)->toBe($coupon->id)
            ->and($order->coupon_code)->toBe('SAVE50')
            ->and((float) $order->discount)->toBe(50.0)
            ->and($order->coupon?->code)->toBe('SAVE50');
    });

    test('a coupon with usage history can never be permanently deleted', function () {
        $coupon = Coupon::factory()->create();
        $useId = DB::table('coupon_uses')->insertGetId(['coupon_id' => $coupon->id, 'user_id' => User::factory()->create()->id, 'order_id' => historyOrder()->id, 'discount_amount' => 10, 'created_at' => now(), 'updated_at' => now()]);

        expect(fn () => $coupon->forceDelete())->toThrow(RuntimeException::class, 'has usage history');
        expect(fn () => DB::table('coupons')->where('id', $coupon->id)->delete())->toThrow(QueryException::class);

        expect(DB::table('coupons')->where('id', $coupon->id)->exists())->toBeTrue();
        expect(DB::table('coupon_uses')->where('id', $useId)->exists())->toBeTrue();
    });

    test('even a raw coupon delete only clears the link on its orders, never the orders', function () {
        $coupon = Coupon::factory()->create(['code' => 'RAW10']);
        $order = historyOrder(['coupon_id' => $coupon->id, 'coupon_code' => 'RAW10', 'discount' => 10]);

        DB::table('coupons')->where('id', $coupon->id)->delete();

        $row = DB::table('orders')->where('id', $order->id)->first();
        expect($row)->not->toBeNull()
            ->and($row->coupon_id)->toBeNull()
            ->and($row->coupon_code)->toBe('RAW10')
            ->and((float) $row->discount)->toBe(10.0);
    });
});

describe('customer account deletion', function () {
    test('a customer with orders is anonymized instead of deleted, and their orders stay', function () {
        $customer = User::factory()->create(['name' => 'Rafiq Islam', 'phone' => '01712345678']);
        $order = historyOrder(['user_id' => $customer->id]);

        actingAs($customer)->delete(route('profile.destroy'), ['password' => 'password'])->assertRedirect(route('home'));

        $this->assertGuest();
        $customer = User::find($customer->id);
        expect($customer)->not->toBeNull()
            ->and($customer->anonymized_at)->not->toBeNull()
            ->and($customer->name)->toBe("Deleted Customer #{$customer->id}")
            ->and($customer->phone)->toBeNull();
        expect(Order::find($order->id)?->user_id)->toBe($customer->id);
        expect(DB::table('activity_log')->where('event', 'customer.self_anonymized')->where('subject_id', $customer->id)->exists())->toBeTrue();
    });

    test('a customer whose only history is a trashed order is still anonymized', function () {
        $customer = User::factory()->create();
        historyOrder(['user_id' => $customer->id, 'status' => OrderStatus::CANCELLED])->delete();

        actingAs($customer)->delete(route('profile.destroy'), ['password' => 'password'])->assertRedirect(route('home'));

        expect(User::find($customer->id)?->anonymized_at)->not->toBeNull();
    });

    test('a customer who referred others is anonymized, so the people they referred keep their referral link', function () {
        $referrer = User::factory()->create();
        $referred = User::factory()->create(['referred_by_id' => $referrer->id]);

        actingAs($referrer)->delete(route('profile.destroy'), ['password' => 'password'])->assertRedirect(route('home'));

        expect(User::find($referrer->id)?->anonymized_at)->not->toBeNull();
        expect($referred->refresh()->referred_by_id)->toBe($referrer->id);
    });

    test('a customer with no retained history is permanently deleted', function () {
        $customer = User::factory()->create();

        actingAs($customer)->delete(route('profile.destroy'), ['password' => 'password'])->assertRedirect(route('home'));

        expect(User::withTrashed()->find($customer->id))->toBeNull();
    });
});

describe('trashed orders for customers', function () {
    test('a customer still sees their trashed cancelled order, read-only', function () {
        $customer = User::factory()->create();
        $order = historyOrder(['user_id' => $customer->id, 'status' => OrderStatus::CANCELLED]);
        $order->delete();

        actingAs($customer)->get(route('account.orders'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('orders.data.0.order_number', $order->order_number));
        actingAs($customer)->get(route('account.orders.show', $order->order_number))->assertOk();

        actingAs($customer)->post(route('account.orders.return.store', $order->order_number), ['reason' => 'x', 'items' => []])->assertNotFound();
        expect($order->refresh()->trashed())->toBeTrue();
    });

    test('another customer still cannot see it', function () {
        $order = historyOrder(['user_id' => User::factory()->create()->id, 'status' => OrderStatus::CANCELLED]);
        $order->delete();

        actingAs(User::factory()->create())->get(route('account.orders.show', $order->order_number))->assertForbidden();
    });

    test('trashed orders still count toward a customer\'s referral and first-order history', function () {
        enableReferralForHistoryTest();
        $referrer = User::factory()->create();
        $customer = User::factory()->create(['referred_by_id' => $referrer->id]);
        $order = historyOrder(['user_id' => $customer->id, 'status' => OrderStatus::DELIVERED, 'used_referral_discount' => true]);
        $customer->forceFill(['referral_discount_used_order_id' => $order->id])->save();

        $order->delete();

        expect(app(ReferralService::class)->deliveredOrderCount($customer->refresh()))->toBe(1);
        expect(app(ReferralService::class)->isEligibleForDiscount($customer))->toBeFalse();
    });
});

describe('referral first-order discount with the program enabled', function () {
    test('trashing an order whose discount was released by cancellation allows exactly one more discounted order', function () {
        enableReferralForHistoryTest();
        $referred = User::factory()->create(['referred_by_id' => User::factory()->create()->id]);
        $product = Product::factory()->create(['price' => 1000, 'stock_quantity' => 10]);

        $first = checkoutForHistoryTest($referred, $product);
        expect($first->discount)->toBe(100.0)->and($first->used_referral_discount)->toBeTrue();

        $first->update(['status' => OrderStatus::CANCELLED]);
        $eligibleAfterCancel = app(ReferralService::class)->isEligibleForDiscount($referred->refresh());
        $first->delete();

        expect(app(ReferralService::class)->isEligibleForDiscount($referred->refresh()))->toBe($eligibleAfterCancel)->toBeTrue();

        $second = checkoutForHistoryTest($referred, $product);
        $third = checkoutForHistoryTest($referred, $product);

        expect($second->discount)->toBe(100.0)
            ->and($third->discount)->toBe(0.0)
            ->and($third->used_referral_discount)->toBeFalse()
            ->and($referred->refresh()->referral_discount_used_order_id)->toBe($second->id)
            ->and(Order::onlyTrashed()->find($first->id)?->used_referral_discount)->toBeTrue();
    });

    test('trashing another cancelled order never re-opens a discount that is still in use', function () {
        enableReferralForHistoryTest();
        $referrer = User::factory()->create();
        $referred = User::factory()->create(['referred_by_id' => $referrer->id]);
        $product = Product::factory()->create(['price' => 1000, 'stock_quantity' => 10]);

        $discounted = checkoutForHistoryTest($referred, $product);
        $other = historyOrder(['user_id' => $referred->id, 'status' => OrderStatus::CANCELLED]);
        $other->delete();

        $next = checkoutForHistoryTest($referred, $product);

        expect($next->discount)->toBe(0.0)
            ->and($referred->refresh()->referral_discount_used_order_id)->toBe($discounted->id)
            ->and($referred->referred_by_id)->toBe($referrer->id)
            ->and(app(ReferralService::class)->deliveredOrderCount($referred))->toBe(0);
    });

    test('a referred customer\'s trashed order changes neither the referrer\'s reward nor the referral link', function () {
        enableReferralForHistoryTest();
        $referrer = User::factory()->create(['points_balance' => 0]);
        $referred = User::factory()->create(['referred_by_id' => $referrer->id]);
        $product = Product::factory()->create(['price' => 1000, 'stock_quantity' => 10]);

        $delivered = checkoutForHistoryTest($referred, $product);
        $delivered->update(['status' => OrderStatus::DELIVERED, 'delivered_at' => now()->subDay()]);
        $trashed = historyOrder(['user_id' => $referred->id, 'status' => OrderStatus::CANCELLED]);
        $trashed->delete();

        $this->artisan('rewards:grant-due')->assertSuccessful();
        $this->artisan('rewards:grant-due')->assertSuccessful();

        expect($referrer->refresh()->points_balance)->toBe(50)
            ->and(DB::table('referral_point_transactions')->where('user_id', $referrer->id)->pluck('order_id')->all())->toBe([$delivered->id])
            ->and($referred->refresh()->referred_by_id)->toBe($referrer->id);
    });
});

describe('order numbers', function () {
    test('a number held by a trashed order is never handed out again', function () {
        $taken = 'ORD-'.date('Ymd').'-AAAAAA';
        historyOrder(['order_number' => $taken, 'status' => OrderStatus::CANCELLED])->delete();

        Str::createRandomStringsUsingSequence(['AAAAAA', 'BBBBBB']);

        try {
            expect(OrderNumberGenerator::generate())->toBe('ORD-'.date('Ymd').'-BBBBBB');
        } finally {
            Str::createRandomStringsNormally();
        }
    });
});

describe('audit trail', function () {
    test('trashing, restoring and permanently deleting an order are each logged', function () {
        $order = historyOrder(['status' => OrderStatus::CANCELLED]);

        $order->delete();
        $order->restore();
        $order->delete();
        $order->forceDelete();

        expect(DB::table('activity_log')->where('log_name', 'orders')->where('subject_id', $order->id)->whereIn('event', ['trashed', 'restored', 'force_deleted'])->pluck('event')->all())
            ->toBe(['trashed', 'restored', 'trashed', 'force_deleted']);
    });

    test('only the mother admin may clear the audit trail, and wrong passwords lock it', function () {
        actingAs(createAdmin(), 'admin')->delete(route('admin.audit-trail.destroy'), ['password' => 'password'])->assertForbidden();

        $mother = createMotherAdmin();
        actingAs($mother, 'admin');
        foreach (range(1, 5) as $attempt) {
            $this->delete(route('admin.audit-trail.destroy'), ['password' => 'wrong-'.$attempt])->assertSessionHasErrors('password');
        }

        $this->delete(route('admin.audit-trail.destroy'), ['password' => 'password'])
            ->assertSessionHasErrors(['password' => 'Too many incorrect password attempts. Try again in 15 minute(s).']);
    });
});
