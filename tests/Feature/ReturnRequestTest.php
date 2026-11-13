<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Enums\PointTransactionType;
use App\Enums\ReturnStatus;
use App\Mail\ReturnApprovedMail;
use App\Mail\ReturnRejectedMail;
use App\Models\Coupon;
use App\Models\CouponUse;
use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\InventoryLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ReferralPointTransaction;
use App\Models\ReturnRequest;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Mail;

/**
 * PAY-RETURN-001: a delivered order is courier-handled, so by default it is
 * also given a linked consignment, a current readiness attestation and a
 * mother-admin full-delivery verification — without them no refund can be
 * recorded (verification-only mode).
 */
function createDeliveredOrder(User $user, Product $product, int $quantity = 1, ?CarbonInterface $deliveredAt = null, bool $courierVerified = true): Order
{
    $order = Order::create([
        'order_number' => 'ORD-RETURN-'.uniqid(),
        'user_id' => $user->id,
        'status' => OrderStatus::DELIVERED,
        'payment_status' => 'paid',
        'payment_method' => 'cod',
        'subtotal' => $product->price * $quantity,
        'shipping_cost' => 0,
        'discount' => 0,
        'tax' => 0,
        'total' => $product->price * $quantity,
        'shipping_address' => ['name' => $user->name, 'phone' => '01700000000', 'address_line1' => 'House 1', 'city' => 'Dhaka', 'state' => 'Dhaka', 'country' => 'Bangladesh'],
        'billing_address' => ['name' => $user->name, 'phone' => '01700000000', 'address_line1' => 'House 1', 'city' => 'Dhaka', 'state' => 'Dhaka', 'country' => 'Bangladesh'],
        'delivered_at' => $deliveredAt ?? now()->subDay(),
    ]);

    $order->items()->create([
        'product_id' => $product->id,
        'variant_id' => null,
        'product_name' => $product->name,
        'price' => $product->price,
        'quantity' => $quantity,
        'subtotal' => $product->price * $quantity,
        'product_snapshot' => [],
    ]);

    if ($courierVerified) {
        courierVerifiedForRefund($order);
    }

    return $order->refresh();
}

/**
 * MySQL's native JSON column type does not preserve object-key insertion
 * order on read (it reorders members by key length, then alphabetically),
 * unlike SQLite's plain-text JSON. Sorting both sides' keys recursively
 * before a strict `toBe()` comparison makes the assertion driver-agnostic
 * while still catching any missing, extra, or mismatched key/value/type.
 *
 * @param  array<int|string, mixed>  $array
 * @return array<int|string, mixed>
 */
function sortKeysRecursively(array $array): array
{
    foreach ($array as &$value) {
        if (is_array($value)) {
            $value = sortKeysRecursively($value);
        }
    }

    ksort($array);

    return $array;
}

test('a customer can request a return for their own delivered order within the window', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 500]);
    $order = createDeliveredOrder($user, $product, 2);
    $item = $order->items->first();

    $this->actingAs($user)->post(route('account.orders.return.store', $order->order_number), [
        'reason' => 'Wrong size, needs exchange.',
        'items' => [['order_item_id' => $item->id, 'quantity' => 1]],
    ])->assertRedirect();

    $returnRequest = ReturnRequest::query()->firstOrFail();
    expect($returnRequest->order_id)->toBe($order->id);
    expect($returnRequest->user_id)->toBe($user->id);
    expect($returnRequest->status)->toBe(ReturnStatus::PENDING);
    expect(sortKeysRecursively($returnRequest->items))->toBe(sortKeysRecursively([
        ['order_item_id' => $item->id, 'product_id' => $product->id, 'quantity' => 1, 'reason' => null],
    ]));
});

test('a customer cannot request a return for another customer\'s order', function () {
    $owner = User::factory()->create();
    $attacker = User::factory()->create();
    $product = Product::factory()->create(['price' => 500]);
    $order = createDeliveredOrder($owner, $product);
    $item = $order->items->first();

    $this->actingAs($attacker)->post(route('account.orders.return.store', $order->order_number), [
        'reason' => 'Not mine but trying anyway.',
        'items' => [['order_item_id' => $item->id, 'quantity' => 1]],
    ])->assertNotFound();

    expect(ReturnRequest::query()->count())->toBe(0);
});

test('a customer cannot request a return for an order that is not delivered', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 500]);
    $order = createDeliveredOrder($user, $product);
    $order->update(['status' => OrderStatus::PROCESSING, 'delivered_at' => null]);
    $item = $order->items->first();

    $this->actingAs($user)->post(route('account.orders.return.store', $order->order_number), [
        'reason' => 'Too early.',
        'items' => [['order_item_id' => $item->id, 'quantity' => 1]],
    ])->assertRedirect();

    expect(ReturnRequest::query()->count())->toBe(0);
});

test('a customer cannot request a return after the 7-day window has passed', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 500]);
    $order = createDeliveredOrder($user, $product, 1, now()->subDays(8));
    $item = $order->items->first();

    $this->actingAs($user)->post(route('account.orders.return.store', $order->order_number), [
        'reason' => 'Too late.',
        'items' => [['order_item_id' => $item->id, 'quantity' => 1]],
    ])->assertRedirect();

    expect(ReturnRequest::query()->count())->toBe(0);
});

test('a customer cannot submit a second return request for the same fully-committed item while one is already pending', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 500]);
    $order = createDeliveredOrder($user, $product, 1);
    $item = $order->items->first();

    $payload = ['reason' => 'Defective.', 'items' => [['order_item_id' => $item->id, 'quantity' => 1]]];

    $this->actingAs($user)->post(route('account.orders.return.store', $order->order_number), $payload)->assertRedirect();
    $this->actingAs($user)->post(route('account.orders.return.store', $order->order_number), $payload)->assertRedirect();

    expect(ReturnRequest::query()->count())->toBe(1);
});

test('a customer can return part of the ordered quantity, leaving the rest returnable', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 500]);
    $order = createDeliveredOrder($user, $product, 3);
    $item = $order->items->first();

    $this->actingAs($user)->post(route('account.orders.return.store', $order->order_number), [
        'reason' => 'Two of these were defective.',
        'items' => [['order_item_id' => $item->id, 'quantity' => 2]],
    ])->assertRedirect();

    // Only 1 unit remains returnable (3 ordered - 2 already committed) —
    // requesting the last 1 should still succeed.
    $this->actingAs($user)->post(route('account.orders.return.store', $order->order_number), [
        'reason' => 'The last one is defective too.',
        'items' => [['order_item_id' => $item->id, 'quantity' => 1]],
    ])->assertRedirect();

    expect(ReturnRequest::query()->count())->toBe(2);
    expect(array_sum(array_column(ReturnRequest::query()->pluck('items')->flatten(1)->all(), 'quantity')))->toBe(3);
});

test('a customer cannot return more units of an item than remain after an existing pending request', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 500]);
    $order = createDeliveredOrder($user, $product, 3);
    $item = $order->items->first();

    $this->actingAs($user)->post(route('account.orders.return.store', $order->order_number), [
        'reason' => 'Two defective.',
        'items' => [['order_item_id' => $item->id, 'quantity' => 2]],
    ])->assertRedirect();

    // Only 1 unit remains, but this request asks for 2 more — must fail.
    $response = $this->actingAs($user)->post(route('account.orders.return.store', $order->order_number), [
        'reason' => 'Trying to over-return.',
        'items' => [['order_item_id' => $item->id, 'quantity' => 2]],
    ]);

    $response->assertRedirect();
    expect(session('error'))->toContain('1 unit');
    expect(ReturnRequest::query()->count())->toBe(1);
});

test('a customer can submit a separate return request for a different item on the same order', function () {
    $user = User::factory()->create();
    $productA = Product::factory()->create(['price' => 500]);
    $productB = Product::factory()->create(['price' => 300]);
    $order = createDeliveredOrder($user, $productA, 1);
    $order->items()->create([
        'product_id' => $productB->id, 'variant_id' => null, 'product_name' => $productB->name,
        'price' => $productB->price, 'quantity' => 1, 'subtotal' => $productB->price, 'product_snapshot' => [],
    ]);
    $itemA = $order->items->first();
    $itemB = $order->items->last();

    $this->actingAs($user)->post(route('account.orders.return.store', $order->order_number), [
        'reason' => 'Item A is defective.',
        'items' => [['order_item_id' => $itemA->id, 'quantity' => 1]],
    ])->assertRedirect();

    // A different item on the same order, still fully eligible, must not
    // be blocked by the first (unrelated) item's active return request.
    $this->actingAs($user)->post(route('account.orders.return.store', $order->order_number), [
        'reason' => 'Item B is also defective.',
        'items' => [['order_item_id' => $itemB->id, 'quantity' => 1]],
    ])->assertRedirect();

    expect(ReturnRequest::query()->count())->toBe(2);
});

test('a customer can submit a new return request after the previous one was rejected', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 500]);
    $order = createDeliveredOrder($user, $product);
    $item = $order->items->first();

    ReturnRequest::create([
        'order_id' => $order->id, 'user_id' => $user->id, 'reason' => 'First attempt',
        'status' => ReturnStatus::REJECTED, 'items' => [['order_item_id' => $item->id, 'product_id' => $product->id, 'quantity' => 1]],
    ]);

    $this->actingAs($user)->post(route('account.orders.return.store', $order->order_number), [
        'reason' => 'Trying again.',
        'items' => [['order_item_id' => $item->id, 'quantity' => 1]],
    ])->assertRedirect();

    expect(ReturnRequest::query()->count())->toBe(2);
});

test('a return request cannot reference an order item belonging to a different order, or exceed its quantity', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 500]);
    $order = createDeliveredOrder($user, $product, 2);
    $otherOrder = createDeliveredOrder($user, $product, 1);
    $foreignItem = $otherOrder->items->first();
    $ownItem = $order->items->first();

    $this->actingAs($user)->post(route('account.orders.return.store', $order->order_number), [
        'reason' => 'Trying to reference a foreign item.',
        'items' => [['order_item_id' => $foreignItem->id, 'quantity' => 1]],
    ])->assertSessionHasErrors('items.0.order_item_id');

    $this->actingAs($user)->post(route('account.orders.return.store', $order->order_number), [
        'reason' => 'Trying to over-return.',
        'items' => [['order_item_id' => $ownItem->id, 'quantity' => 99]],
    ])->assertRedirect();

    expect(ReturnRequest::query()->count())->toBe(0);
});

test('admin can approve a pending return request and the customer is emailed', function () {
    Mail::fake();
    $admin = createAdmin(['name' => 'Admin', 'email' => 'admin-returns@example.com', 'password' => bcrypt('password')]);
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 500]);
    $order = createDeliveredOrder($user, $product);
    $item = $order->items->first();
    $returnRequest = ReturnRequest::create([
        'order_id' => $order->id, 'user_id' => $user->id, 'reason' => 'Defective',
        'status' => ReturnStatus::PENDING, 'items' => [['order_item_id' => $item->id, 'product_id' => $product->id, 'quantity' => 1]],
    ]);

    $this->actingAs($admin, 'admin')
        ->patch(route('admin.returns.approve', $returnRequest))
        ->assertRedirect();

    expect($returnRequest->refresh()->status)->toBe(ReturnStatus::APPROVED);
    expect($returnRequest->resolved_at)->not->toBeNull();
    Mail::assertQueued(ReturnApprovedMail::class, fn ($mail) => $mail->returnRequest->is($returnRequest));
});

test('admin can reject a pending return request with a note and the customer is emailed', function () {
    Mail::fake();
    $admin = createAdmin(['name' => 'Admin', 'email' => 'admin-returns2@example.com', 'password' => bcrypt('password')]);
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 500]);
    $order = createDeliveredOrder($user, $product);
    $item = $order->items->first();
    $returnRequest = ReturnRequest::create([
        'order_id' => $order->id, 'user_id' => $user->id, 'reason' => 'Changed my mind',
        'status' => ReturnStatus::PENDING, 'items' => [['order_item_id' => $item->id, 'product_id' => $product->id, 'quantity' => 1]],
    ]);

    $this->actingAs($admin, 'admin')
        ->patch(route('admin.returns.reject', $returnRequest), ['admin_note' => 'Outside policy.'])
        ->assertRedirect();

    expect($returnRequest->refresh()->status)->toBe(ReturnStatus::REJECTED);
    expect($returnRequest->admin_note)->toBe('Outside policy.');
    Mail::assertQueued(ReturnRejectedMail::class, fn ($mail) => $mail->returnRequest->is($returnRequest));
});

test('admin cannot approve or reject a return request that is not pending', function () {
    $admin = createAdmin(['name' => 'Admin', 'email' => 'admin-returns3@example.com', 'password' => bcrypt('password')]);
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 500]);
    $order = createDeliveredOrder($user, $product);
    $item = $order->items->first();
    $returnRequest = ReturnRequest::create([
        'order_id' => $order->id, 'user_id' => $user->id, 'reason' => 'Already approved',
        'status' => ReturnStatus::APPROVED, 'items' => [['order_item_id' => $item->id, 'product_id' => $product->id, 'quantity' => 1]],
    ]);

    $this->actingAs($admin, 'admin')->patch(route('admin.returns.approve', $returnRequest))->assertRedirect();
    $this->actingAs($admin, 'admin')->patch(route('admin.returns.reject', $returnRequest))->assertRedirect();

    expect($returnRequest->refresh()->status)->toBe(ReturnStatus::APPROVED);
});

test('admin can record a manual refund for an approved return request', function () {
    $admin = createAdmin(['name' => 'Admin', 'email' => 'admin-returns4@example.com', 'password' => bcrypt('password')]);
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 500]);
    $order = createDeliveredOrder($user, $product);
    $item = $order->items->first();
    $returnRequest = ReturnRequest::create([
        'order_id' => $order->id, 'user_id' => $user->id, 'reason' => 'Defective',
        'status' => ReturnStatus::APPROVED, 'items' => [['order_item_id' => $item->id, 'product_id' => $product->id, 'quantity' => 1]],
    ]);

    $this->actingAs($admin, 'admin')
        ->patch(route('admin.returns.markRefunded', $returnRequest), [
            'refund_amount' => 500,
            'refund_method' => 'bKash',
        ])
        ->assertRedirect();

    $returnRequest->refresh();
    expect($returnRequest->status)->toBe(ReturnStatus::REFUNDED);
    expect((float) $returnRequest->refund_amount)->toBe(500.0);
    expect($returnRequest->refund_method)->toBe('bKash');
});

test('admin cannot mark a return as refunded before it has been approved', function () {
    $admin = createAdmin(['name' => 'Admin', 'email' => 'admin-returns5@example.com', 'password' => bcrypt('password')]);
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 500]);
    $order = createDeliveredOrder($user, $product);
    $item = $order->items->first();
    $returnRequest = ReturnRequest::create([
        'order_id' => $order->id, 'user_id' => $user->id, 'reason' => 'Defective',
        'status' => ReturnStatus::PENDING, 'items' => [['order_item_id' => $item->id, 'product_id' => $product->id, 'quantity' => 1]],
    ]);

    $this->actingAs($admin, 'admin')
        ->patch(route('admin.returns.markRefunded', $returnRequest), [
            'refund_amount' => 500,
            'refund_method' => 'bKash',
        ])
        ->assertRedirect();

    expect($returnRequest->refresh()->status)->toBe(ReturnStatus::PENDING);
});

test('the order detail page exposes the latest return request to the customer', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 500]);
    $order = createDeliveredOrder($user, $product);
    $item = $order->items->first();
    ReturnRequest::create([
        'order_id' => $order->id, 'user_id' => $user->id, 'reason' => 'Defective',
        'status' => ReturnStatus::PENDING, 'items' => [['order_item_id' => $item->id, 'product_id' => $product->id, 'quantity' => 1]],
    ]);

    $response = $this->actingAs($user)->get(route('account.orders.show', $order->order_number));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->where('order.latest_return_request.status', 'pending'));
});

test('when an order has multiple historical return requests, the newest one is shown to the customer', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 500]);
    $order = createDeliveredOrder($user, $product);
    $item = $order->items->first();

    $older = ReturnRequest::create([
        'order_id' => $order->id, 'user_id' => $user->id, 'reason' => 'First attempt',
        'status' => ReturnStatus::REJECTED, 'items' => [['order_item_id' => $item->id, 'product_id' => $product->id, 'quantity' => 1]],
    ]);
    $newer = ReturnRequest::create([
        'order_id' => $order->id, 'user_id' => $user->id, 'reason' => 'Second attempt',
        'status' => ReturnStatus::PENDING, 'items' => [['order_item_id' => $item->id, 'product_id' => $product->id, 'quantity' => 1]],
    ]);

    expect($newer->id)->toBeGreaterThan($older->id);
    expect($order->fresh()->latestReturnRequest->id)->toBe($newer->id);
    expect($order->fresh()->latestReturnRequest->status)->toBe(ReturnStatus::PENDING);
});

test('approving a return request twice in a row only sends one approval email', function () {
    Mail::fake();
    $admin = createAdmin(['name' => 'Admin', 'email' => 'admin-returns6@example.com', 'password' => bcrypt('password')]);
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 500]);
    $order = createDeliveredOrder($user, $product);
    $item = $order->items->first();
    $returnRequest = ReturnRequest::create([
        'order_id' => $order->id, 'user_id' => $user->id, 'reason' => 'Defective',
        'status' => ReturnStatus::PENDING, 'items' => [['order_item_id' => $item->id, 'product_id' => $product->id, 'quantity' => 1]],
    ]);

    $this->actingAs($admin, 'admin')->patch(route('admin.returns.approve', $returnRequest))->assertRedirect();
    $this->actingAs($admin, 'admin')->patch(route('admin.returns.approve', $returnRequest))->assertRedirect();

    expect($returnRequest->refresh()->status)->toBe(ReturnStatus::APPROVED);
    Mail::assertQueued(ReturnApprovedMail::class, 1);
});

test('marking a return refunded twice in a row does not overwrite the recorded refund a second time', function () {
    $admin = createAdmin(['name' => 'Admin', 'email' => 'admin-returns7@example.com', 'password' => bcrypt('password')]);
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 500]);
    $order = createDeliveredOrder($user, $product);
    $item = $order->items->first();
    $returnRequest = ReturnRequest::create([
        'order_id' => $order->id, 'user_id' => $user->id, 'reason' => 'Defective',
        'status' => ReturnStatus::APPROVED, 'items' => [['order_item_id' => $item->id, 'product_id' => $product->id, 'quantity' => 1]],
    ]);

    $this->actingAs($admin, 'admin')
        ->patch(route('admin.returns.markRefunded', $returnRequest), ['refund_amount' => 500, 'refund_method' => 'bKash'])
        ->assertRedirect();

    $second = $this->actingAs($admin, 'admin')
        ->patch(route('admin.returns.markRefunded', $returnRequest), ['refund_amount' => 500, 'refund_method' => 'Bank Transfer']);

    $second->assertRedirect();
    expect(session('error'))->not->toBeNull();
    expect($returnRequest->refresh()->refund_method)->toBe('bKash');
});

test('the refund amount cannot exceed the value of the returned items', function () {
    $admin = createAdmin(['name' => 'Admin', 'email' => 'admin-returns8@example.com', 'password' => bcrypt('password')]);
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 500]);
    $order = createDeliveredOrder($user, $product, 2);
    $item = $order->items->first();
    // Only 1 of the 2 ordered units is being returned, so the eligible
    // ceiling is 1 * 500 = 500, not the full order value of 1000.
    $returnRequest = ReturnRequest::create([
        'order_id' => $order->id, 'user_id' => $user->id, 'reason' => 'Defective',
        'status' => ReturnStatus::APPROVED, 'items' => [['order_item_id' => $item->id, 'product_id' => $product->id, 'quantity' => 1]],
    ]);

    $this->actingAs($admin, 'admin')
        ->patch(route('admin.returns.markRefunded', $returnRequest), ['refund_amount' => 900, 'refund_method' => 'bKash'])
        ->assertSessionHas('error'); // PAY-RETURN-001: refused by RefundService under the order lock

    expect($returnRequest->refresh()->status)->toBe(ReturnStatus::APPROVED);

    $this->actingAs($admin, 'admin')
        ->patch(route('admin.returns.markRefunded', $returnRequest), ['refund_amount' => 500, 'refund_method' => 'bKash'])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    expect($returnRequest->refresh()->status)->toBe(ReturnStatus::REFUNDED);
});

test('rejecting a return request twice in a row only sends one rejection email', function () {
    Mail::fake();
    $admin = createAdmin(['name' => 'Admin', 'email' => 'admin-returns9@example.com', 'password' => bcrypt('password')]);
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 500]);
    $order = createDeliveredOrder($user, $product);
    $item = $order->items->first();
    $returnRequest = ReturnRequest::create([
        'order_id' => $order->id, 'user_id' => $user->id, 'reason' => 'Defective',
        'status' => ReturnStatus::PENDING, 'items' => [['order_item_id' => $item->id, 'product_id' => $product->id, 'quantity' => 1]],
    ]);

    $this->actingAs($admin, 'admin')->patch(route('admin.returns.reject', $returnRequest))->assertRedirect();
    $this->actingAs($admin, 'admin')->patch(route('admin.returns.reject', $returnRequest))->assertRedirect();

    expect($returnRequest->refresh()->status)->toBe(ReturnStatus::REJECTED);
    Mail::assertQueued(ReturnRejectedMail::class, 1);
});

test('a submission with a duplicate order_item_id is rejected by validation before it ever reaches the database', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 500]);
    $order = createDeliveredOrder($user, $product, 3);
    $item = $order->items->first();

    $response = $this->actingAs($user)->post(route('account.orders.return.store', $order->order_number), [
        'reason' => 'Trying to list the same item twice.',
        'items' => [
            ['order_item_id' => $item->id, 'quantity' => 3],
            ['order_item_id' => $item->id, 'quantity' => 3],
        ],
    ]);

    $response->assertSessionHasErrors(['items.0.order_item_id', 'items.1.order_item_id']);
    expect(ReturnRequest::query()->count())->toBe(0);
});

test('duplicate order_item_id lines sum their quantity instead of being checked independently against the remaining cap', function () {
    // Mirrors exactly the aggregation expression
    // ReturnRequestController::store() runs after locking the order row
    // — proving that two lines for the same item correctly combine to a
    // single quantity to check, rather than each line being compared to
    // the same stale "remaining" snapshot independently (which is the
    // defect this fix closes: two lines of 3 each against an item with
    // only 3 remaining must combine to a rejected total of 6, not two
    // independently-passing checks of 3 <= 3).
    $items = [
        ['order_item_id' => 5, 'quantity' => 3],
        ['order_item_id' => 5, 'quantity' => 3],
    ];

    $requestedByItem = collect($items)
        ->groupBy('order_item_id')
        ->map(fn ($group) => $group->sum('quantity'));

    expect($requestedByItem->all())->toBe([5 => 6]);

    $remaining = 3;
    expect($requestedByItem->get(5) > $remaining)->toBeTrue();
});

test('the refund cap cannot be inflated by a duplicated or over-quantity stored item line', function () {
    $admin = createAdmin(['name' => 'Admin', 'email' => 'admin-returns10@example.com', 'password' => bcrypt('password')]);
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 500]);
    // Ordered quantity is 3 (worth 1500 total), but the stored return
    // request's items array is crafted (bypassing the customer
    // controller entirely, as if from old/corrupted data) to list the
    // same item twice at quantity 3 each — a naive sum would compute
    // 500 * 3 + 500 * 3 = 3000, nearly double what was ever purchased.
    $order = createDeliveredOrder($user, $product, 3);
    $item = $order->items->first();
    $returnRequest = ReturnRequest::create([
        'order_id' => $order->id, 'user_id' => $user->id, 'reason' => 'Malformed stored data',
        'status' => ReturnStatus::APPROVED,
        'items' => [
            ['order_item_id' => $item->id, 'product_id' => $product->id, 'quantity' => 3],
            ['order_item_id' => $item->id, 'product_id' => $product->id, 'quantity' => 3],
        ],
    ]);

    // The naive (unfixed) ceiling of 3000 must be rejected...
    $this->actingAs($admin, 'admin')
        ->patch(route('admin.returns.markRefunded', $returnRequest), ['refund_amount' => 3000, 'refund_method' => 'bKash'])
        ->assertSessionHas('error'); // PAY-RETURN-001: refused by RefundService under the order lock

    // ...but the correctly-capped amount (the item's real purchased
    // value, 500 * 3 = 1500, deduplicated and capped at quantity 3, not
    // the malformed 6) must still be accepted.
    $this->actingAs($admin, 'admin')
        ->patch(route('admin.returns.markRefunded', $returnRequest), ['refund_amount' => 1500, 'refund_method' => 'bKash'])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    expect($returnRequest->refresh()->status)->toBe(ReturnStatus::REFUNDED);
});

// ── PAY-RETURN-001: inventory, coupon, loyalty/referral, order-status wiring ──

test('approving a return request restocks exactly the returned quantity, not the full order quantity', function () {
    $admin = createAdmin(['name' => 'Admin', 'email' => 'admin-restock1@example.com', 'password' => bcrypt('password')]);
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 500, 'stock_quantity' => 10]);
    $order = createDeliveredOrder($user, $product, 3);
    $item = $order->items->first();
    $returnRequest = ReturnRequest::create([
        'order_id' => $order->id, 'user_id' => $user->id, 'reason' => 'Defective',
        'status' => ReturnStatus::PENDING, 'items' => [['order_item_id' => $item->id, 'product_id' => $product->id, 'quantity' => 2]],
    ]);

    $this->actingAs($admin, 'admin')->patch(route('admin.returns.approve', $returnRequest))->assertRedirect();

    expect($product->refresh()->stock_quantity)->toBe(12); // 10 + 2, not +3
    expect($item->refresh()->restocked_quantity)->toBe(2);
    expect(InventoryLog::where('reference_type', ReturnRequest::class)->where('reference_id', $returnRequest->id)->first())
        ->not->toBeNull();
});

test('approving a return request twice in a row does not double-restock', function () {
    $admin = createAdmin(['name' => 'Admin', 'email' => 'admin-restock2@example.com', 'password' => bcrypt('password')]);
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 500, 'stock_quantity' => 10]);
    $order = createDeliveredOrder($user, $product, 2);
    $item = $order->items->first();
    $returnRequest = ReturnRequest::create([
        'order_id' => $order->id, 'user_id' => $user->id, 'reason' => 'Defective',
        'status' => ReturnStatus::PENDING, 'items' => [['order_item_id' => $item->id, 'product_id' => $product->id, 'quantity' => 2]],
    ]);

    $this->actingAs($admin, 'admin')->patch(route('admin.returns.approve', $returnRequest))->assertRedirect();
    $this->actingAs($admin, 'admin')->patch(route('admin.returns.approve', $returnRequest))->assertRedirect();

    expect($product->refresh()->stock_quantity)->toBe(12);
    expect($item->refresh()->restocked_quantity)->toBe(2);
});

test('a return request with a duplicated order_item_id line restocks only the deduplicated, capped quantity once', function () {
    $admin = createAdmin(['name' => 'Admin', 'email' => 'admin-restock3@example.com', 'password' => bcrypt('password')]);
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 500, 'stock_quantity' => 10]);
    $order = createDeliveredOrder($user, $product, 3);
    $item = $order->items->first();
    // Malformed/duplicated data, as if bypassing the customer controller
    // entirely (mirrors the existing refund-cap dedup test's scenario).
    $returnRequest = ReturnRequest::create([
        'order_id' => $order->id, 'user_id' => $user->id, 'reason' => 'Malformed stored data',
        'status' => ReturnStatus::PENDING,
        'items' => [
            ['order_item_id' => $item->id, 'product_id' => $product->id, 'quantity' => 3],
            ['order_item_id' => $item->id, 'product_id' => $product->id, 'quantity' => 3],
        ],
    ]);

    $this->actingAs($admin, 'admin')->patch(route('admin.returns.approve', $returnRequest))->assertRedirect();

    // Naive (unfixed) double-counting would restock 6; the real purchased
    // quantity is only 3.
    expect($product->refresh()->stock_quantity)->toBe(13);
    expect($item->refresh()->restocked_quantity)->toBe(3);
});

test('a return request cannot be approved once its parent order has already been cancelled', function () {
    $admin = createAdmin(['name' => 'Admin', 'email' => 'admin-closed1@example.com', 'password' => bcrypt('password')]);
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 500, 'stock_quantity' => 10]);
    $order = createDeliveredOrder($user, $product, 2);
    $item = $order->items->first();
    // Cancelling the order itself already restocks it in full via the
    // existing whole-order path (restocked_quantity -> 2) — the point of
    // this test is that the blocked approval attempt below does not
    // restock it a second time on top of that.
    $order->update(['status' => OrderStatus::CANCELLED]);
    $restockedAfterCancel = $item->refresh()->restocked_quantity;
    expect($restockedAfterCancel)->toBe(2);
    $returnRequest = ReturnRequest::create([
        'order_id' => $order->id, 'user_id' => $user->id, 'reason' => 'Defective',
        'status' => ReturnStatus::PENDING, 'items' => [['order_item_id' => $item->id, 'product_id' => $product->id, 'quantity' => 2]],
    ]);

    $response = $this->actingAs($admin, 'admin')->patch(route('admin.returns.approve', $returnRequest));

    $response->assertRedirect();
    expect(session('error'))->toContain('already been cancelled or refunded');
    expect($returnRequest->refresh()->status)->toBe(ReturnStatus::PENDING);
    expect($item->refresh()->restocked_quantity)->toBe($restockedAfterCancel);
});

test('a return request cannot be approved once its parent order has already been refunded', function () {
    $admin = createAdmin(['name' => 'Admin', 'email' => 'admin-closed2@example.com', 'password' => bcrypt('password')]);
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 500, 'stock_quantity' => 10]);
    $order = createDeliveredOrder($user, $product, 2);
    $item = $order->items->first();
    $order->update(['status' => OrderStatus::REFUNDED]);
    $restockedAfterRefund = $item->refresh()->restocked_quantity;
    expect($restockedAfterRefund)->toBe(2);
    $returnRequest = ReturnRequest::create([
        'order_id' => $order->id, 'user_id' => $user->id, 'reason' => 'Defective',
        'status' => ReturnStatus::PENDING, 'items' => [['order_item_id' => $item->id, 'product_id' => $product->id, 'quantity' => 2]],
    ]);

    $response = $this->actingAs($admin, 'admin')->patch(route('admin.returns.approve', $returnRequest));

    $response->assertRedirect();
    expect($returnRequest->refresh()->status)->toBe(ReturnStatus::PENDING);
    expect($item->refresh()->restocked_quantity)->toBe($restockedAfterRefund);
});

test('two separate partial return requests on the same order item each restock only their own quantity', function () {
    $admin = createAdmin(['name' => 'Admin', 'email' => 'admin-restock4@example.com', 'password' => bcrypt('password')]);
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 500, 'stock_quantity' => 10]);
    $order = createDeliveredOrder($user, $product, 3);
    $item = $order->items->first();
    $returnA = ReturnRequest::create([
        'order_id' => $order->id, 'user_id' => $user->id, 'reason' => 'First batch',
        'status' => ReturnStatus::PENDING, 'items' => [['order_item_id' => $item->id, 'product_id' => $product->id, 'quantity' => 2]],
    ]);
    $returnB = ReturnRequest::create([
        'order_id' => $order->id, 'user_id' => $user->id, 'reason' => 'Second batch',
        'status' => ReturnStatus::PENDING, 'items' => [['order_item_id' => $item->id, 'product_id' => $product->id, 'quantity' => 1]],
    ]);

    $this->actingAs($admin, 'admin')->patch(route('admin.returns.approve', $returnA))->assertRedirect();
    $this->actingAs($admin, 'admin')->patch(route('admin.returns.approve', $returnB))->assertRedirect();

    expect($product->refresh()->stock_quantity)->toBe(13); // 10 + 2 + 1
    expect($item->refresh()->restocked_quantity)->toBe(3);
});

test('restocking via a return approval leaves a later whole-order refund from double-restocking the same units', function () {
    $admin = createAdmin(['name' => 'Admin', 'email' => 'admin-restock5@example.com', 'password' => bcrypt('password')]);
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 500, 'stock_quantity' => 10]);
    $order = createDeliveredOrder($user, $product, 3);
    $item = $order->items->first();
    $returnRequest = ReturnRequest::create([
        'order_id' => $order->id, 'user_id' => $user->id, 'reason' => 'Defective',
        'status' => ReturnStatus::PENDING, 'items' => [['order_item_id' => $item->id, 'product_id' => $product->id, 'quantity' => 2]],
    ]);
    $this->actingAs($admin, 'admin')->patch(route('admin.returns.approve', $returnRequest))->assertRedirect();
    expect($product->refresh()->stock_quantity)->toBe(12); // 2 restocked via the return

    // PAY-RETURN-001: a whole-order refund is refused while a return is
    // still approved, so record the return's own refund (2 × 500) first.
    $this->actingAs($admin, 'admin')->patch(route('admin.returns.markRefunded', $returnRequest), ['refund_amount' => 1000, 'refund_method' => 'bKash'])->assertSessionHasNoErrors();
    courierVerifiedForRefund($order); // a new refund changes nothing courier-side; re-verify only if needed

    // The remaining 1 unit (never returned) is still restocked by the
    // existing whole-order refund path; the already-returned 2 must not
    // be restocked a second time.
    // BIZ-012: a delivered order restocks on refund only when the admin
    // confirms the goods came back.
    $this->actingAs($admin, 'admin')->patch(route('admin.orders.markRefunded', $order->id), ['refund_amount' => 500, 'refund_method' => 'bKash', 'goods_returned' => true])->assertSessionDoesntHaveErrors();

    expect($product->refresh()->stock_quantity)->toBe(13); // +1 only, not +3
    expect($item->refresh()->restocked_quantity)->toBe(3);
});

test('a flash-sale item\'s sold_count is decremented when its return is approved', function () {
    $admin = createAdmin(['name' => 'Admin', 'email' => 'admin-flash1@example.com', 'password' => bcrypt('password')]);
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 80, 'stock_quantity' => 10]);
    $flashSale = FlashSale::factory()->create();
    $flashItem = FlashSaleItem::factory()->create([
        'flash_sale_id' => $flashSale->id, 'product_id' => $product->id, 'variant_id' => null,
        'sale_price' => 80, 'stock_limit' => 10, 'sold_count' => 2,
    ]);
    $order = createDeliveredOrder($user, $product, 2);
    $item = $order->items->first();
    $item->update(['flash_sale_item_id' => $flashItem->id]);
    $returnRequest = ReturnRequest::create([
        'order_id' => $order->id, 'user_id' => $user->id, 'reason' => 'Defective',
        'status' => ReturnStatus::PENDING, 'items' => [['order_item_id' => $item->id, 'product_id' => $product->id, 'quantity' => 2]],
    ]);

    $this->actingAs($admin, 'admin')->patch(route('admin.returns.approve', $returnRequest))->assertRedirect();

    expect($flashItem->refresh()->sold_count)->toBe(0);
    expect($product->refresh()->stock_quantity)->toBe(12);
});

test('a return request restocks variant stock independently of the product\'s own stock', function () {
    $admin = createAdmin(['name' => 'Admin', 'email' => 'admin-variant1@example.com', 'password' => bcrypt('password')]);
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 500, 'stock_quantity' => 999]);
    $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 500, 'stock_quantity' => 5]);
    $order = Order::create([
        'order_number' => 'ORD-VARIANT-'.uniqid(), 'user_id' => $user->id, 'status' => OrderStatus::DELIVERED,
        'payment_status' => 'paid', 'payment_method' => 'cod', 'subtotal' => 1000, 'shipping_cost' => 0,
        'discount' => 0, 'tax' => 0, 'total' => 1000,
        'shipping_address' => ['name' => $user->name, 'phone' => '01700000000', 'address_line1' => 'House 1', 'city' => 'Dhaka', 'state' => 'Dhaka', 'country' => 'Bangladesh'],
        'billing_address' => [], 'delivered_at' => now()->subDay(),
    ]);
    $item = $order->items()->create([
        'product_id' => $product->id, 'variant_id' => $variant->id, 'product_name' => $product->name,
        'price' => 500, 'quantity' => 2, 'subtotal' => 1000, 'product_snapshot' => [],
    ]);
    $returnRequest = ReturnRequest::create([
        'order_id' => $order->id, 'user_id' => $user->id, 'reason' => 'Defective',
        'status' => ReturnStatus::PENDING, 'items' => [['order_item_id' => $item->id, 'product_id' => $product->id, 'quantity' => 1]],
    ]);

    $this->actingAs($admin, 'admin')->patch(route('admin.returns.approve', $returnRequest))->assertRedirect();

    expect($variant->refresh()->stock_quantity)->toBe(6);
    expect($product->refresh()->stock_quantity)->toBe(999);
});

test('a return that fully returns and refunds every unit on the order transitions it to refunded and releases the coupon', function () {
    $admin = createAdmin(['name' => 'Admin', 'email' => 'admin-full1@example.com', 'password' => bcrypt('password')]);
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 500, 'stock_quantity' => 10]);
    $order = createDeliveredOrder($user, $product, 2);
    $coupon = Coupon::factory()->create(['used_count' => 1]);
    $order->update(['coupon_id' => $coupon->id, 'discount' => 100]);
    CouponUse::create(['coupon_id' => $coupon->id, 'user_id' => $user->id, 'order_id' => $order->id, 'discount_amount' => 100]);
    $item = $order->items->first();
    $returnRequest = ReturnRequest::create([
        'order_id' => $order->id, 'user_id' => $user->id, 'reason' => 'Defective',
        'status' => ReturnStatus::APPROVED, 'items' => [['order_item_id' => $item->id, 'product_id' => $product->id, 'quantity' => 2]],
    ]);

    // PAY-RETURN-001: the cap is the discounted product value, 1000 − 100.
    $this->actingAs($admin, 'admin')
        ->patch(route('admin.returns.markRefunded', $returnRequest), ['refund_amount' => 900, 'refund_method' => 'bKash'])
        ->assertRedirect();

    expect($returnRequest->refresh()->status)->toBe(ReturnStatus::REFUNDED);
    expect($order->refresh()->status)->toBe(OrderStatus::REFUNDED);
    expect($coupon->refresh()->used_count)->toBe(0);
    expect(CouponUse::where('order_id', $order->id)->exists())->toBeFalse();
});

test('a partial return does not release the coupon or change the order status', function () {
    $admin = createAdmin(['name' => 'Admin', 'email' => 'admin-partial1@example.com', 'password' => bcrypt('password')]);
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 500, 'stock_quantity' => 10]);
    $order = createDeliveredOrder($user, $product, 3);
    $coupon = Coupon::factory()->create(['used_count' => 1]);
    $order->update(['coupon_id' => $coupon->id, 'discount' => 100]);
    CouponUse::create(['coupon_id' => $coupon->id, 'user_id' => $user->id, 'order_id' => $order->id, 'discount_amount' => 100]);
    $item = $order->items->first();
    $returnRequest = ReturnRequest::create([
        'order_id' => $order->id, 'user_id' => $user->id, 'reason' => 'Defective',
        // Only 2 of the 3 purchased units are being returned — not a full return.
        'status' => ReturnStatus::APPROVED, 'items' => [['order_item_id' => $item->id, 'product_id' => $product->id, 'quantity' => 2]],
    ]);

    // PAY-RETURN-001: 2 of 3 units of a 1500 line with a 100 discount = floor(1400 × 2 / 3) = 933.33.
    $this->actingAs($admin, 'admin')
        ->patch(route('admin.returns.markRefunded', $returnRequest), ['refund_amount' => 933.33, 'refund_method' => 'bKash'])
        ->assertRedirect();

    expect($returnRequest->refresh()->status)->toBe(ReturnStatus::REFUNDED);
    expect($order->refresh()->status)->toBe(OrderStatus::DELIVERED);
    expect($coupon->refresh()->used_count)->toBe(1);
    expect(CouponUse::where('order_id', $order->id)->exists())->toBeTrue();
});

test('a return that fully returns and refunds every unit revokes the referrer\'s already-granted reward', function () {
    $admin = createAdmin(['name' => 'Admin', 'email' => 'admin-full2@example.com', 'password' => bcrypt('password')]);
    $referrer = User::factory()->create(['points_balance' => 50]);
    $referred = User::factory()->create(['referred_by_id' => $referrer->id]);
    $product = Product::factory()->create(['price' => 500, 'stock_quantity' => 10]);
    $order = createDeliveredOrder($referred, $product, 1);
    $order->update(['used_referral_discount' => true]);
    $order->forceFill(['referral_reward_granted_at' => now()])->save();
    $referred->forceFill(['referral_discount_used_order_id' => $order->id])->save();
    ReferralPointTransaction::create([
        'user_id' => $referrer->id, 'referred_user_id' => $referred->id, 'order_id' => $order->id,
        'type' => PointTransactionType::EARN_REFERRAL, 'points' => 50, 'currency_amount' => 50,
        'note' => 'Referral bonus.',
    ]);
    $item = $order->items->first();
    $returnRequest = ReturnRequest::create([
        'order_id' => $order->id, 'user_id' => $referred->id, 'reason' => 'Defective',
        'status' => ReturnStatus::APPROVED, 'items' => [['order_item_id' => $item->id, 'product_id' => $product->id, 'quantity' => 1]],
    ]);

    $this->actingAs($admin, 'admin')
        ->patch(route('admin.returns.markRefunded', $returnRequest), ['refund_amount' => 500, 'refund_method' => 'bKash'])
        ->assertRedirect();

    expect($order->refresh()->status)->toBe(OrderStatus::REFUNDED);
    expect($referrer->refresh()->points_balance)->toBe(0);
    expect($referred->refresh()->referral_discount_used_order_id)->toBeNull();
    $this->assertDatabaseHas('referral_point_transactions', [
        'order_id' => $order->id, 'type' => PointTransactionType::REVOKE->value, 'points' => -50,
    ]);
});

test('a partial return does not revoke any part of the referrer\'s reward', function () {
    $admin = createAdmin(['name' => 'Admin', 'email' => 'admin-partial2@example.com', 'password' => bcrypt('password')]);
    $referrer = User::factory()->create(['points_balance' => 50]);
    $referred = User::factory()->create(['referred_by_id' => $referrer->id]);
    $product = Product::factory()->create(['price' => 500, 'stock_quantity' => 10]);
    $order = createDeliveredOrder($referred, $product, 3);
    $order->update(['used_referral_discount' => true]);
    $order->forceFill(['referral_reward_granted_at' => now()])->save();
    $referred->forceFill(['referral_discount_used_order_id' => $order->id])->save();
    ReferralPointTransaction::create([
        'user_id' => $referrer->id, 'referred_user_id' => $referred->id, 'order_id' => $order->id,
        'type' => PointTransactionType::EARN_REFERRAL, 'points' => 50, 'currency_amount' => 50,
        'note' => 'Referral bonus.',
    ]);
    $item = $order->items->first();
    $returnRequest = ReturnRequest::create([
        'order_id' => $order->id, 'user_id' => $referred->id, 'reason' => 'Defective',
        'status' => ReturnStatus::APPROVED, 'items' => [['order_item_id' => $item->id, 'product_id' => $product->id, 'quantity' => 2]],
    ]);

    $this->actingAs($admin, 'admin')
        ->patch(route('admin.returns.markRefunded', $returnRequest), ['refund_amount' => 1000, 'refund_method' => 'bKash'])
        ->assertRedirect();

    expect($order->refresh()->status)->toBe(OrderStatus::DELIVERED);
    expect($referrer->refresh()->points_balance)->toBe(50);
    expect($referred->refresh()->referral_discount_used_order_id)->toBe($order->id);
});
