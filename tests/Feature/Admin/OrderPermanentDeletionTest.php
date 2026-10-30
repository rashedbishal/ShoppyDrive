<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\Admin;
use App\Models\Order;
use App\Models\OrderDeletionAudit;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;

/**
 * DB-001 (L1, L3, L7): permanent order deletion is mother-admin only, takes
 * two separate password-verified requests plus the typed order number, is
 * refused for any order with financial or business history, is throttled,
 * and every attempt is recorded in order_deletion_audits.
 */
const MOTHER_PASSWORD = 'Mother-Secret-Pass-91!';

function deletableOrder(array $overrides = []): Order
{
    $order = Order::create(array_merge([
        'order_number' => 'ORD-DEL-'.strtoupper(bin2hex(random_bytes(3))),
        'status' => OrderStatus::CANCELLED,
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

    $product = Product::factory()->create();
    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'sku' => $product->sku,
        'price' => 250,
        'quantity' => 2,
        'subtotal' => 500,
        'product_snapshot' => ['id' => $product->id, 'name' => $product->name],
    ]);

    $order->delete();

    return $order;
}

function confirmStep(Order $order, string $password = MOTHER_PASSWORD)
{
    return test()->post(route('admin.orders.permanentDelete.confirm', $order->id), ['password' => $password]);
}

function deleteStep(Order $order, string $password = MOTHER_PASSWORD, ?string $orderNumber = null)
{
    return test()->delete(route('admin.orders.forceDelete', $order->id), [
        'password' => $password,
        'order_number' => $orderNumber ?? $order->order_number,
    ]);
}

function lastAudit(): OrderDeletionAudit
{
    return OrderDeletionAudit::latest('id')->firstOrFail();
}

beforeEach(function () {
    $this->mother = createMotherAdmin(['password' => bcrypt(MOTHER_PASSWORD)]);
});

describe('who may permanently delete', function () {
    test('a non-mother admin with every ordinary permission gets 403 on both steps and nothing is recorded', function () {
        $order = deletableOrder();
        actingAs(createAdmin(['password' => bcrypt(MOTHER_PASSWORD)]), 'admin');

        confirmStep($order)->assertForbidden();
        deleteStep($order)->assertForbidden();

        expect(Order::withTrashed()->find($order->id))->not->toBeNull();
        expect(OrderDeletionAudit::count())->toBe(0);
    });

    test('an ordinary admin can still soft-delete a cancelled order', function () {
        $order = Order::create(['order_number' => 'ORD-SOFT-1', 'status' => OrderStatus::CANCELLED, 'payment_status' => 'unpaid', 'payment_method' => 'cod', 'subtotal' => 1, 'shipping_cost' => 0, 'discount' => 0, 'tax' => 0, 'total' => 1, 'shipping_address' => ['name' => 'x', 'phone' => '01712345678'], 'billing_address' => []]);
        actingAs(createAdmin(), 'admin');

        $this->delete(route('admin.orders.destroy', $order))->assertSessionHas('success');

        expect($order->refresh()->trashed())->toBeTrue();
    });

    test('the trashed-orders page offers permanent deletion and its blockers to the mother admin only', function () {
        $eligible = deletableOrder();
        $paid = deletableOrder(['payment_status' => 'paid']);

        actingAs($this->mother, 'admin')->get(route('admin.orders.trashed'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('canPermanentlyDelete', true)
                ->where('orders.data', function ($orders) use ($eligible, $paid): bool {
                    $blockers = collect($orders)->mapWithKeys(fn ($order) => [$order['order_number'] => array_column($order['deletion_blockers'], 'code')]);

                    return $blockers[$eligible->order_number] === [] && $blockers[$paid->order_number] === ['payment_status'];
                }));

        actingAs(createAdmin(), 'admin')->get(route('admin.orders.trashed'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('canPermanentlyDelete', false)
                ->where('pendingPermanentDeletion', null)
                ->missing('orders.data.0.deletion_blockers'));
    });
});

describe('the two password steps', function () {
    test('both correct passwords and the typed order number permanently delete the order with an audit snapshot', function () {
        $order = deletableOrder();
        actingAs($this->mother, 'admin');

        confirmStep($order)->assertSessionHas('success');
        expect(lastAudit()->outcome)->toBe('confirmed');

        deleteStep($order)->assertRedirect(route('admin.orders.trashed'))->assertSessionHas('success');

        expect(Order::withTrashed()->find($order->id))->toBeNull();
        expect(OrderItem::where('order_id', $order->id)->exists())->toBeFalse();

        $audit = lastAudit();
        expect($audit->only(['step', 'outcome', 'order_id', 'order_number', 'order_status', 'admin_id', 'admin_name', 'item_count']))->toBe([
            'step' => 'delete', 'outcome' => 'deleted', 'order_id' => $order->id, 'order_number' => $order->order_number,
            'order_status' => 'cancelled', 'admin_id' => $this->mother->id, 'admin_name' => $this->mother->name, 'item_count' => 2,
        ]);
        expect((float) $audit->total)->toBe(560.0)
            ->and($audit->order_created_at)->not->toBeNull()
            ->and($audit->order_deleted_at)->not->toBeNull()
            ->and($audit->ip_address)->toBe('127.0.0.1');
    });

    test('a wrong first password creates no confirmation and is audited', function () {
        $order = deletableOrder();
        actingAs($this->mother, 'admin');

        confirmStep($order, 'not-the-password')->assertSessionHasErrors(['password' => 'The password is incorrect.']);
        expect(lastAudit()->only(['step', 'outcome']))->toBe(['step' => 'confirm', 'outcome' => 'wrong_password']);

        deleteStep($order)->assertSessionHasErrors('confirmation');
        expect(Order::withTrashed()->find($order->id))->not->toBeNull();
    });

    test('a wrong second password is refused, audited, and spends the confirmation', function () {
        $order = deletableOrder();
        actingAs($this->mother, 'admin');
        confirmStep($order);

        deleteStep($order, 'not-the-password')->assertSessionHasErrors('password');
        expect(lastAudit()->only(['step', 'outcome']))->toBe(['step' => 'delete', 'outcome' => 'wrong_password']);

        // The marker is gone: the right password now still needs step 1 again.
        deleteStep($order)->assertSessionHasErrors('confirmation');
        expect(Order::withTrashed()->find($order->id))->not->toBeNull();
    });

    test('the second step without a first step is refused', function () {
        $order = deletableOrder();
        actingAs($this->mother, 'admin');

        deleteStep($order)->assertSessionHasErrors('confirmation');

        expect(lastAudit()->outcome)->toBe('invalid_confirmation')
            ->and(lastAudit()->refusal_reasons[0]['code'])->toBe('confirmation_missing');
        expect(Order::withTrashed()->find($order->id))->not->toBeNull();
    });

    test('an expired confirmation is refused', function () {
        $order = deletableOrder();
        actingAs($this->mother, 'admin');
        confirmStep($order);

        $this->travel(6)->minutes();
        deleteStep($order)->assertSessionHasErrors('confirmation');

        expect(lastAudit()->refusal_reasons[0]['code'])->toBe('confirmation_expired');
        expect(Order::withTrashed()->find($order->id))->not->toBeNull();
    });

    test('a confirmation for one order cannot delete another', function () {
        $confirmed = deletableOrder();
        $other = deletableOrder();
        actingAs($this->mother, 'admin');
        confirmStep($confirmed);

        deleteStep($other)->assertSessionHasErrors('confirmation');

        expect(lastAudit()->refusal_reasons[0]['code'])->toBe('confirmation_other_order');
        expect(Order::withTrashed()->find($other->id))->not->toBeNull();
        // …and that attempt spent the marker for the confirmed order too.
        deleteStep($confirmed)->assertSessionHasErrors('confirmation');
        expect(Order::withTrashed()->find($confirmed->id))->not->toBeNull();
    });

    test('a confirmation made by one mother admin cannot be used by another', function () {
        $order = deletableOrder();
        $secondMother = Admin::create(['name' => 'Second Mother', 'email' => 'second-mother@example.com', 'password' => bcrypt(MOTHER_PASSWORD)]);
        $secondMother->forceFill(['is_mother' => true])->save();

        actingAs($this->mother, 'admin');
        confirmStep($order);
        actingAs($secondMother, 'admin');

        deleteStep($order)->assertSessionHasErrors('confirmation');

        expect(lastAudit()->refusal_reasons[0]['code'])->toBe('confirmation_other_admin');
        expect(Order::withTrashed()->find($order->id))->not->toBeNull();
    });

    test('a mistyped order number is refused and audited', function () {
        $order = deletableOrder();
        actingAs($this->mother, 'admin');
        confirmStep($order);

        deleteStep($order, MOTHER_PASSWORD, strtolower($order->order_number))->assertSessionHasErrors('order_number');

        expect(lastAudit()->outcome)->toBe('order_number_mismatch');
        expect(Order::withTrashed()->find($order->id))->not->toBeNull();
    });

    test('5 wrong passwords lock both steps for 15 minutes, even with the right password', function () {
        $order = deletableOrder();
        actingAs($this->mother, 'admin');

        foreach (range(1, 4) as $attempt) {
            confirmStep($order, 'wrong-'.$attempt)->assertSessionHasErrors(['password' => 'The password is incorrect.']);
        }
        confirmStep($order)->assertSessionHas('success');
        deleteStep($order, 'wrong-5')->assertSessionHasErrors('password');

        confirmStep($order)->assertSessionHasErrors(['password' => 'Too many incorrect password attempts. Try again in 15 minute(s).']);
        deleteStep($order)->assertSessionHasErrors(['password' => 'Too many incorrect password attempts. Try again in 15 minute(s).']);

        expect(OrderDeletionAudit::where('outcome', 'rate_limited')->pluck('step')->all())->toBe(['confirm', 'delete']);
        expect(Order::withTrashed()->find($order->id))->not->toBeNull();

        $this->travel(16)->minutes();
        confirmStep($order)->assertSessionHas('success');
    });

    test('correct passwords are not counted, so legitimate deletions are not throttled', function () {
        $orders = collect(range(1, 4))->map(fn () => deletableOrder());
        actingAs($this->mother, 'admin');

        $orders->each(function (Order $order): void {
            confirmStep($order)->assertSessionHas('success');
            deleteStep($order)->assertSessionHas('success');
        });

        expect(OrderDeletionAudit::where('outcome', 'deleted')->count())->toBe(4);
    });

    test('the wrong-password lock is shared with clearing the audit trail', function () {
        $order = deletableOrder();
        actingAs($this->mother, 'admin');

        foreach (range(1, 5) as $attempt) {
            $this->delete(route('admin.audit-trail.destroy'), ['password' => 'wrong-'.$attempt])->assertSessionHasErrors('password');
        }

        confirmStep($order)->assertSessionHasErrors(['password' => 'Too many incorrect password attempts. Try again in 15 minute(s).']);
    });

    test('passwords never reach the audit records, the activity log, the session or the log', function () {
        $order = deletableOrder();
        Log::spy();
        actingAs($this->mother, 'admin');

        confirmStep($order, 'Wrong-Attempt-Pass-77!');
        confirmStep($order);
        deleteStep($order, 'Wrong-Second-Pass-55!');
        confirmStep($order);
        deleteStep($order);

        $stored = json_encode([
            DB::table('order_deletion_audits')->get(),
            DB::table('activity_log')->get(),
            session()->all(),
        ]);
        foreach ([MOTHER_PASSWORD, 'Wrong-Attempt-Pass-77!', 'Wrong-Second-Pass-55!'] as $password) {
            expect($stored)->not->toContain($password);
        }
        Log::shouldNotHaveReceived('info', fn (...$args) => str_contains(json_encode($args), 'Pass-'));
        Log::shouldNotHaveReceived('warning', fn (...$args) => str_contains(json_encode($args), 'Pass-'));
        Log::shouldNotHaveReceived('error', fn (...$args) => str_contains(json_encode($args), 'Pass-'));
    });
});

describe('orders that may never be permanently deleted', function () {
    test('a non-trashed or non-cancelled order is refused even with correct passwords', function (Closure $makeOrder, string $code) {
        $order = $makeOrder();
        actingAs($this->mother, 'admin');

        confirmStep($order)->assertSessionHasErrors('order');

        expect(lastAudit()->outcome)->toBe('refused_ineligible')
            ->and(array_column(lastAudit()->refusal_reasons, 'code'))->toContain($code);
        expect(Order::withTrashed()->find($order->id))->not->toBeNull();
    })->with([
        'not in the trash' => [fn () => tap(deletableOrder())->restore(), 'not_trashed'],
        'pending' => [fn () => deletableOrder(['status' => OrderStatus::PENDING]), 'not_cancelled'],
        'processing' => [fn () => deletableOrder(['status' => OrderStatus::PROCESSING]), 'not_cancelled'],
        'shipped' => [fn () => deletableOrder(['status' => OrderStatus::SHIPPED]), 'not_cancelled'],
        'delivered' => [fn () => deletableOrder(['status' => OrderStatus::DELIVERED]), 'not_cancelled'],
        'refunded' => [fn () => deletableOrder(['status' => OrderStatus::REFUNDED]), 'not_cancelled'],
    ]);

    test('each kind of financial or business history independently blocks deletion', function (Closure $addHistory, string $code) {
        $order = deletableOrder();
        $addHistory($order);
        actingAs($this->mother, 'admin');

        confirmStep($order)->assertSessionHasErrors('order');

        expect(lastAudit()->outcome)->toBe('refused_ineligible')
            ->and(array_column(lastAudit()->refusal_reasons, 'code'))->toBe([$code]);
        expect(Order::withTrashed()->find($order->id))->not->toBeNull();
    })->with([
        'payment record' => [fn (Order $o) => DB::table('payments')->insert(['order_id' => $o->id, 'method' => 'zinipay', 'amount' => 560, 'status' => 'failed', 'created_at' => now(), 'updated_at' => now()]), 'payment_record'],
        'paid' => [fn (Order $o) => DB::table('orders')->where('id', $o->id)->update(['payment_status' => 'paid']), 'payment_status'],
        'refunded payment' => [fn (Order $o) => DB::table('orders')->where('id', $o->id)->update(['payment_status' => 'refunded']), 'payment_status'],
        'return request' => [fn (Order $o) => DB::table('return_requests')->insert(['order_id' => $o->id, 'user_id' => User::factory()->create()->id, 'reason' => 'x', 'status' => 'pending', 'items' => '[]', 'created_at' => now(), 'updated_at' => now()]), 'return_request'],
        'loyalty ledger' => [fn (Order $o) => DB::table('referral_point_transactions')->insert(['user_id' => User::factory()->create()->id, 'order_id' => $o->id, 'type' => 'earn_order', 'points' => 5, 'created_at' => now(), 'updated_at' => now()]), 'loyalty_ledger'],
        'points redeemed' => [fn (Order $o) => DB::table('orders')->where('id', $o->id)->update(['points_redeemed' => 10]), 'points_redeemed'],
        'points refunded' => [fn (Order $o) => DB::table('orders')->where('id', $o->id)->update(['points_refunded_at' => now()]), 'points_settled'],
        'points granted' => [fn (Order $o) => DB::table('orders')->where('id', $o->id)->update(['loyalty_points_granted_at' => now()]), 'points_settled'],
        'referral reward granted' => [fn (Order $o) => DB::table('orders')->where('id', $o->id)->update(['referral_reward_granted_at' => now()]), 'points_settled'],
        'referral discount used' => [fn (Order $o) => DB::table('orders')->where('id', $o->id)->update(['used_referral_discount' => true]), 'referral_discount'],
        'referral discount on a customer' => [fn (Order $o) => User::factory()->create()->forceFill(['referral_discount_used_order_id' => $o->id])->save(), 'referral_discount'],
        'coupon use' => [fn (Order $o) => DB::table('coupon_uses')->insert(['coupon_id' => DB::table('coupons')->insertGetId(['code' => 'C'.$o->id, 'name' => 'c', 'type' => 'fixed', 'value' => 1, 'created_at' => now(), 'updated_at' => now()]), 'user_id' => User::factory()->create()->id, 'order_id' => $o->id, 'discount_amount' => 0, 'created_at' => now(), 'updated_at' => now()]), 'coupon'],
        'coupon code kept on the order' => [fn (Order $o) => DB::table('orders')->where('id', $o->id)->update(['coupon_code' => 'OLD10']), 'coupon'],
        'discount' => [fn (Order $o) => DB::table('orders')->where('id', $o->id)->update(['discount' => 50]), 'discount'],
        'delivered at' => [fn (Order $o) => DB::table('orders')->where('id', $o->id)->update(['delivered_at' => now()]), 'fulfilled'],
        'courier consignment' => [fn (Order $o) => DB::table('orders')->where('id', $o->id)->update(['steadfast_consignment_id' => 'SF-'.$o->id]), 'courier_history'],
        'courier submission' => [fn (Order $o) => DB::table('courier_submissions')->insert(['order_id' => $o->id, 'courier' => 'pathao', 'state' => 'failed', 'reference' => $o->order_number, 'created_at' => now(), 'updated_at' => now()]), 'courier_history'],
        'matched payment SMS' => [fn (Order $o) => DB::table('received_sms')->insert(['sender' => 'bKash', 'provider' => 'bkash', 'content' => 'x', 'received_at' => now(), 'is_matched' => true, 'matched_order_id' => $o->id, 'created_at' => now(), 'updated_at' => now()]), 'payment_sms'],
        'inventory movement' => [fn (Order $o) => DB::table('inventory_logs')->insert(['product_id' => Product::factory()->create()->id, 'type' => 'out', 'quantity' => 1, 'reference_type' => Order::class, 'reference_id' => $o->id, 'created_at' => now(), 'updated_at' => now()]), 'inventory_history'],
        'review' => [fn (Order $o) => DB::table('reviews')->insert(['user_id' => User::factory()->create()->id, 'product_id' => Product::factory()->create()->id, 'order_id' => $o->id, 'rating' => 5, 'body' => 'Great.', 'created_at' => now(), 'updated_at' => now()]), 'reviews'],
    ]);

    test('a cancelled order whose stock was taken at checkout and fully restored can be deleted, keeping the inventory history', function () {
        $product = Product::factory()->create(['stock_quantity' => 10]);
        $order = Order::create(['order_number' => 'ORD-STOCK-OK', 'status' => OrderStatus::PROCESSING, 'payment_status' => 'unpaid', 'payment_method' => 'cod', 'subtotal' => 500, 'shipping_cost' => 60, 'discount' => 0, 'tax' => 0, 'total' => 560, 'shipping_address' => ['name' => 'x', 'phone' => '01712345678'], 'billing_address' => []]);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'product_name' => $product->name, 'sku' => $product->sku, 'price' => 250, 'quantity' => 2, 'subtotal' => 500, 'product_snapshot' => []]);
        app(InventoryService::class)->decrementForOrderItem($order, $product->id, null, 2, 250.0);
        $order->update(['status' => OrderStatus::CANCELLED]);
        $order->delete();
        expect($product->refresh()->stock_quantity)->toBe(10);

        actingAs($this->mother, 'admin');
        confirmStep($order)->assertSessionHas('success');
        deleteStep($order)->assertSessionHas('success');

        expect(Order::withTrashed()->find($order->id))->toBeNull();
        expect(DB::table('inventory_logs')->where('reference_type', Order::class)->where('reference_id', $order->id)->orderBy('id')->pluck('type')->all())->toBe(['out', 'release']);
        expect(array_map(fn (array $m) => [$m['type'], $m['quantity'], $m['product_id']], lastAudit()->inventory_movements))
            ->toBe([['out', 2, $product->id], ['release', 2, $product->id]]);
    });

    test('stock history that does not provably balance blocks deletion', function (Closure $breakBalance) {
        $product = Product::factory()->create(['stock_quantity' => 10]);
        $order = Order::create(['order_number' => 'ORD-STOCK-'.strtoupper(bin2hex(random_bytes(2))), 'status' => OrderStatus::PROCESSING, 'payment_status' => 'unpaid', 'payment_method' => 'cod', 'subtotal' => 500, 'shipping_cost' => 60, 'discount' => 0, 'tax' => 0, 'total' => 560, 'shipping_address' => ['name' => 'x', 'phone' => '01712345678'], 'billing_address' => []]);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'product_name' => $product->name, 'sku' => $product->sku, 'price' => 250, 'quantity' => 2, 'subtotal' => 500, 'product_snapshot' => []]);
        app(InventoryService::class)->decrementForOrderItem($order, $product->id, null, 2, 250.0);
        $order->update(['status' => OrderStatus::CANCELLED]);
        $order->delete();
        $breakBalance($order, $product);

        actingAs($this->mother, 'admin');
        confirmStep($order)->assertSessionHasErrors('order');

        expect(array_column(lastAudit()->refusal_reasons, 'code'))->toBe(['inventory_history']);
        expect(Order::withTrashed()->find($order->id))->not->toBeNull();
    })->with([
        'restock not recorded on the order' => [fn (Order $o) => DB::table('orders')->where('id', $o->id)->update(['stock_restored_at' => null])],
        'a line not fully restocked' => [fn (Order $o) => DB::table('order_items')->where('order_id', $o->id)->update(['restocked_quantity' => 1])],
        'restock quantity short' => [fn (Order $o) => DB::table('inventory_logs')->where('reference_id', $o->id)->where('type', 'release')->update(['quantity' => 1])],
        'an extra manual adjustment' => [fn (Order $o, Product $p) => DB::table('inventory_logs')->insert(['product_id' => $p->id, 'type' => 'adjustment', 'quantity' => 1, 'reference_type' => Order::class, 'reference_id' => $o->id, 'created_at' => now(), 'updated_at' => now()])],
        'restocked onto a different product' => [fn (Order $o) => DB::table('inventory_logs')->where('reference_id', $o->id)->where('type', 'release')->update(['product_id' => Product::factory()->create()->id])],
    ]);

    test('history that appears between the two steps still blocks deletion', function () {
        $order = deletableOrder();
        actingAs($this->mother, 'admin');
        confirmStep($order)->assertSessionHas('success');

        DB::table('orders')->where('id', $order->id)->update(['payment_status' => 'paid']);
        deleteStep($order)->assertSessionHasErrors('order');

        expect(lastAudit()->only(['step', 'outcome']))->toBe(['step' => 'delete', 'outcome' => 'refused_ineligible']);
        expect(Order::withTrashed()->find($order->id))->not->toBeNull();
    });
});

describe('the audit record', function () {
    test('survives the order, its customer and clearing the activity log', function () {
        $customer = User::factory()->create();
        $order = deletableOrder(['user_id' => $customer->id]);
        actingAs($this->mother, 'admin');
        confirmStep($order);
        deleteStep($order);
        $customer->forceDelete();

        $this->delete(route('admin.audit-trail.destroy'), ['password' => MOTHER_PASSWORD])->assertSessionHas('success');

        expect(DB::table('activity_log')->where('event', '!=', 'cleared')->count())->toBe(0);
        $audit = OrderDeletionAudit::where('outcome', 'deleted')->sole();
        expect($audit->order_number)->toBe($order->order_number)
            ->and($audit->customer_id)->toBe($customer->id);
    });
});
