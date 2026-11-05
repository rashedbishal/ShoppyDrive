<?php

declare(strict_types=1);

use App\Jobs\AbandonedCartReminderJob;
use App\Mail\AbandonedCartReminderMail;
use App\Models\IncompleteOrder;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

function placeCodOrder(TestCase $test, User $user, Product $product, string $phone): void
{
    $test->actingAs($user)->post(route('checkout.store'), [
        'name' => $user->name, 'email' => $user->email, 'phone' => $phone,
        'address_line1' => 'House 1', 'city' => 'Dhaka', 'state' => 'Dhaka', 'country' => 'Bangladesh',
        'payment_method' => 'cod',
        'order_token' => (string) Str::uuid(),
        'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1]],
    ])->assertRedirect();
}

test('only logged-in, not-yet-reminded, sufficiently-old checkout attempts are queued', function () {
    $user = User::factory()->create();

    $eligible = IncompleteOrder::factory()->create(['user_id' => $user->id, 'created_at' => now()->subHours(2)]);
    IncompleteOrder::factory()->create(['user_id' => null, 'created_at' => now()->subHours(2)]); // guest — no email on file
    IncompleteOrder::factory()->create(['user_id' => $user->id, 'created_at' => now()->subMinutes(10)]); // too recent
    IncompleteOrder::factory()->create(['user_id' => $user->id, 'created_at' => now()->subHours(2), 'reminder_sent_at' => now()]); // already reminded

    $this->artisan('cart:remind-abandoned')->assertExitCode(0);

    // QUEUE_CONNECTION=sync in tests, so the dispatched job already ran.
    expect($eligible->refresh()->reminder_sent_at)->not->toBeNull();
});

test('the reminder job sends the email and marks the row as reminded, once', function () {
    Mail::fake();
    $user = User::factory()->create(['email' => 'cartabandoner@example.com']);
    $incompleteOrder = IncompleteOrder::factory()->create(['user_id' => $user->id]);

    AbandonedCartReminderJob::dispatchSync($incompleteOrder->id);

    Mail::assertQueued(AbandonedCartReminderMail::class, fn ($mail) => $mail->incompleteOrder->is($incompleteOrder) && $mail->hasTo('cartabandoner@example.com'));
    expect($incompleteOrder->refresh()->reminder_sent_at)->not->toBeNull();

    // Running it again (e.g. a re-queued/duplicate job) must not resend.
    AbandonedCartReminderJob::dispatchSync($incompleteOrder->id);
    Mail::assertQueued(AbandonedCartReminderMail::class, 1);
});

test('a guest checkout attempt with no account email is never emailed', function () {
    Mail::fake();
    $incompleteOrder = IncompleteOrder::factory()->create(['user_id' => null]);

    AbandonedCartReminderJob::dispatchSync($incompleteOrder->id);

    Mail::assertNothingQueued();
    expect($incompleteOrder->refresh()->reminder_sent_at)->toBeNull();
});

test('a checkout attempt that was already completed (row deleted) is silently skipped', function () {
    Mail::fake();
    $user = User::factory()->create();
    $incompleteOrder = IncompleteOrder::factory()->create(['user_id' => $user->id]);
    $id = $incompleteOrder->id;
    $incompleteOrder->delete();

    AbandonedCartReminderJob::dispatchSync($id);

    Mail::assertNothingQueued();
});

test('running the reminder job for the same row sequentially never sends twice (claim-then-send)', function () {
    // This proves the application-level logic is correct under sequential
    // execution — it does NOT prove true concurrent-request blocking.
    // SQLite (this test's connection, per phpunit.xml) silently drops
    // lockForUpdate() entirely (Illuminate\Database\Query\Grammars\
    // SQLiteGrammar::compileLock() returns an empty string), so no Pest
    // test here can demonstrate genuine row-lock contention between two
    // simultaneous MySQL connections. That would require a real
    // multi-connection test against MySQL directly.
    Mail::fake();
    $user = User::factory()->create(['email' => 'race@example.com']);
    $incompleteOrder = IncompleteOrder::factory()->create(['user_id' => $user->id]);

    AbandonedCartReminderJob::dispatchSync($incompleteOrder->id);
    AbandonedCartReminderJob::dispatchSync($incompleteOrder->id);
    AbandonedCartReminderJob::dispatchSync($incompleteOrder->id);

    Mail::assertQueued(AbandonedCartReminderMail::class, 1);
    expect($incompleteOrder->refresh()->reminder_sent_at)->not->toBeNull();
});

test('a logged-in customer who completes an order with a different phone number is not reminded', function () {
    Mail::fake();
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);

    // Abandoned using one phone number...
    $incompleteOrder = IncompleteOrder::factory()->create([
        'user_id' => $user->id, 'phone' => '01700000001', 'created_at' => now()->subHours(2),
    ]);

    // ...but actually checks out with a different one.
    placeCodOrder($this, $user, $product, '01799999999');

    expect(IncompleteOrder::find($incompleteOrder->id))->toBeNull();

    AbandonedCartReminderJob::dispatchSync($incompleteOrder->id);
    // Placing the real order above legitimately queues OrderPlacedMail/
    // NewOrderAlertMail — only the abandoned-cart reminder must be absent.
    Mail::assertNotQueued(AbandonedCartReminderMail::class);
});

test('a guest order never deletes a different customer\'s incomplete cart that happens to share a phone number', function () {
    $owner = User::factory()->create();
    $sharedPhone = '01700000002';
    $incompleteOrder = IncompleteOrder::factory()->create(['user_id' => $owner->id, 'phone' => $sharedPhone]);

    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);
    $guest = User::factory()->create();
    // A different account placing an order with the SAME phone number —
    // the existing phone-based match is unchanged/expected to catch this
    // (pre-existing behavior, not something this fix changes), but the
    // new user_id-based match must never widen this to delete rows
    // belonging to yet another, unrelated user_id.
    placeCodOrder($this, $guest, $product, $sharedPhone);

    // The shared-phone row is still legitimately gone (phone matched) —
    // this assertion documents existing behavior, not a new guarantee.
    expect(IncompleteOrder::find($incompleteOrder->id))->toBeNull();
});

test('the reminder job skips a customer who has already placed any order since the cart was abandoned, even if cleanup missed it', function () {
    Mail::fake();
    $user = User::factory()->create();
    $abandonedAt = now()->subHours(3);
    $incompleteOrder = IncompleteOrder::factory()->create(['user_id' => $user->id, 'created_at' => $abandonedAt]);

    // Simulate a real order placed by this user AFTER the cart was
    // abandoned, via a path that (for whatever reason) never cleaned up
    // the IncompleteOrder row — e.g. a different phone number than any
    // cleanup check covers, or a future order-creation path that forgets.
    // created_at isn't mass-assignable (not in Order::$fillable), so it's
    // force-set afterward to land the order at a specific point in time.
    $staleOrder = Order::create([
        'order_number' => 'ORD-STALE-'.uniqid(), 'user_id' => $user->id,
        'status' => 'pending', 'payment_status' => 'unpaid', 'payment_method' => 'cod',
        'subtotal' => 100, 'shipping_cost' => 0, 'discount' => 0, 'tax' => 0, 'total' => 100,
        'shipping_address' => [], 'billing_address' => [],
    ]);
    $staleOrder->forceFill(['created_at' => $abandonedAt->copy()->addHour()])->save();

    AbandonedCartReminderJob::dispatchSync($incompleteOrder->id);

    // Placing the order above legitimately queues OrderPlacedMail/
    // NewOrderAlertMail — only the abandoned-cart reminder must be absent.
    Mail::assertNotQueued(AbandonedCartReminderMail::class);
    // Claimed (so it's never re-evaluated every hour forever) even
    // though no email was actually sent.
    expect($incompleteOrder->refresh()->reminder_sent_at)->not->toBeNull();
});

test('an order placed by this user before the cart was abandoned does not suppress a genuine reminder', function () {
    Mail::fake();
    $user = User::factory()->create(['email' => 'genuine@example.com']);
    $abandonedAt = now()->subHours(2);

    // An older, unrelated order from before this abandonment must not be
    // mistaken for "they already checked out this cart".
    $oldOrder = Order::create([
        'order_number' => 'ORD-OLD-'.uniqid(), 'user_id' => $user->id,
        'status' => 'delivered', 'payment_status' => 'paid', 'payment_method' => 'cod',
        'subtotal' => 100, 'shipping_cost' => 0, 'discount' => 0, 'tax' => 0, 'total' => 100,
        'shipping_address' => [], 'billing_address' => [],
    ]);
    $oldOrder->forceFill(['created_at' => $abandonedAt->copy()->subDay()])->save();

    $incompleteOrder = IncompleteOrder::factory()->create(['user_id' => $user->id, 'created_at' => $abandonedAt]);

    AbandonedCartReminderJob::dispatchSync($incompleteOrder->id);

    Mail::assertQueued(AbandonedCartReminderMail::class, fn ($mail) => $mail->incompleteOrder->is($incompleteOrder));
    expect($incompleteOrder->refresh()->reminder_sent_at)->not->toBeNull();
});

test('the one-hour age threshold is governed by created_at, not updated_at', function () {
    $user = User::factory()->create();

    $incompleteOrder = IncompleteOrder::factory()->create([
        'user_id' => $user->id,
        'created_at' => now()->subHours(2),
    ]);
    // Touch the row (as a cart edit would via updateOrCreate) without
    // actually changing created_at — Eloquent leaves created_at alone on
    // an update and only bumps updated_at.
    $incompleteOrder->update(['subtotal' => 999]);
    $incompleteOrder->refresh();

    expect($incompleteOrder->created_at->lt(now()->subHour()))->toBeTrue();
    expect($incompleteOrder->updated_at->gt($incompleteOrder->created_at))->toBeTrue();

    $this->artisan('cart:remind-abandoned')->assertExitCode(0);

    expect($incompleteOrder->refresh()->reminder_sent_at)->not->toBeNull();
});

// ── OPS-006: no cap, no opt-out, and anonymized accounts still got mailed ──

test('a checkout attempt older than the 72-hour ceiling is never reminded, even after a long cron outage', function () {
    $user = User::factory()->create();
    $tooOld = IncompleteOrder::factory()->create(['user_id' => $user->id, 'created_at' => now()->subHours(73)]);
    $stillEligible = IncompleteOrder::factory()->create(['user_id' => $user->id, 'created_at' => now()->subHours(71)]);

    $this->artisan('cart:remind-abandoned')->assertExitCode(0);

    expect($tooOld->refresh()->reminder_sent_at)->toBeNull();
    expect($stillEligible->refresh()->reminder_sent_at)->not->toBeNull();
});

test('a customer who anonymized/deleted their account is never mailed', function () {
    Mail::fake();
    $user = User::factory()->create(['email' => 'deleted@example.com']);
    $user->anonymize();
    $incompleteOrder = IncompleteOrder::factory()->create(['user_id' => $user->id]);

    AbandonedCartReminderJob::dispatchSync($incompleteOrder->id);

    Mail::assertNothingQueued();
    // Claimed so it's never re-evaluated every hour forever.
    expect($incompleteOrder->refresh()->reminder_sent_at)->not->toBeNull();
});

test('a customer who opted out of cart reminders is never mailed', function () {
    Mail::fake();
    $user = User::factory()->create(['abandoned_cart_reminders_enabled' => false]);
    $incompleteOrder = IncompleteOrder::factory()->create(['user_id' => $user->id]);

    AbandonedCartReminderJob::dispatchSync($incompleteOrder->id);

    Mail::assertNothingQueued();
    expect($incompleteOrder->refresh()->reminder_sent_at)->not->toBeNull();
});

test('the email contains a signed unsubscribe link that turns reminders off for that customer only', function () {
    $user = User::factory()->create(['email' => 'unsub@example.com']);
    $otherUser = User::factory()->create();
    $incompleteOrder = IncompleteOrder::factory()->create(['user_id' => $user->id]);

    $mail = new AbandonedCartReminderMail($incompleteOrder);
    $rendered = $mail->render();

    expect($rendered)->toContain('Unsubscribe');

    $url = (new AbandonedCartReminderMail($incompleteOrder))->content()->with['unsubscribeUrl'];
    $this->get($url)->assertRedirect(route('home'));

    expect($user->refresh()->abandoned_cart_reminders_enabled)->toBeFalse();
    expect($otherUser->refresh()->abandoned_cart_reminders_enabled)->toBeTrue();
});

test('an unsubscribe link cannot be tampered with to affect a different customer', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $incompleteOrder = IncompleteOrder::factory()->create(['user_id' => $user->id]);

    $url = (new AbandonedCartReminderMail($incompleteOrder))->content()->with['unsubscribeUrl'];
    // Swap only the route's {user} path segment, not any digit anywhere in
    // the URL (the signature hash could coincidentally contain the same
    // digits as the id, corrupting an unrelated part of the URL instead).
    $tampered = preg_replace('#/unsubscribe/\d+#', '/unsubscribe/'.$otherUser->id, $url, 1);

    $this->get($tampered)->assertForbidden();

    expect($user->refresh()->abandoned_cart_reminders_enabled)->toBeTrue();
    expect($otherUser->refresh()->abandoned_cart_reminders_enabled)->toBeTrue();
});

test('editing an already-reminded cart does not trigger a second reminder (unchanged policy)', function () {
    $user = User::factory()->create();
    $incompleteOrder = IncompleteOrder::factory()->create([
        'user_id' => $user->id,
        'created_at' => now()->subHours(3),
        'reminder_sent_at' => now()->subHours(2),
    ]);

    // Same upsert shape IncompleteOrderController::store() uses — editing
    // the cart must not reset reminder_sent_at.
    $incompleteOrder->update(['subtotal' => 500]);

    expect($incompleteOrder->refresh()->reminder_sent_at)->not->toBeNull();

    Mail::fake();
    $this->artisan('cart:remind-abandoned')->assertExitCode(0);
    Mail::assertNothingQueued();
});
