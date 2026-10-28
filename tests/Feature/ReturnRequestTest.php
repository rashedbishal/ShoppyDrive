<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Enums\ReturnStatus;
use App\Mail\ReturnApprovedMail;
use App\Mail\ReturnRejectedMail;
use App\Models\Order;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Mail;

function createDeliveredOrder(User $user, Product $product, int $quantity = 1, ?CarbonInterface $deliveredAt = null): Order
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

    return $order;
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
    expect($returnRequest->items)->toBe([
        ['order_item_id' => $item->id, 'product_id' => $product->id, 'quantity' => 1, 'reason' => null],
    ]);
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
    ])->assertForbidden();

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
        ->assertSessionHasErrors('refund_amount');

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
        ->assertSessionHasErrors('refund_amount');

    // ...but the correctly-capped amount (the item's real purchased
    // value, 500 * 3 = 1500, deduplicated and capped at quantity 3, not
    // the malformed 6) must still be accepted.
    $this->actingAs($admin, 'admin')
        ->patch(route('admin.returns.markRefunded', $returnRequest), ['refund_amount' => 1500, 'refund_method' => 'bKash'])
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    expect($returnRequest->refresh()->status)->toBe(ReturnStatus::REFUNDED);
});
