<?php

declare(strict_types=1);

use App\Models\SocialAccount;
use App\Models\User;

use function Pest\Laravel\actingAs;

test('admin can view a customer\'s detail page', function () {
    actingAs(createAdmin(), 'admin');
    $user = User::factory()->create();

    $this->get(route('admin.customers.show', $user))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/customers/show')
            ->where('customer.id', $user->id)
        );
});

test('SEC-004: a customer\'s OAuth tokens never reach the admin customer page', function () {
    actingAs(createAdmin(), 'admin');
    $user = User::factory()->create();
    SocialAccount::create([
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_id' => 'google-456',
        'token' => 'canary-access-token',
        'refresh_token' => 'canary-refresh-token',
    ]);

    $response = $this->get(route('admin.customers.show', $user));

    $response->assertOk();
    expect(json_encode($response->viewData('page')['props']))->not->toContain('canary-access-token')
        ->and(json_encode($response->viewData('page')['props']))->not->toContain('canary-refresh-token');
});

/*
 * DISC-13: read-only reward points card. Balance (may be negative), the same
 * spendable figure checkout uses, and the latest 20 ledger rows, newest first.
 */

function disc13Ledger(User $user, string $type, int $points, ?int $orderId = null, ?string $note = null): App\Models\ReferralPointTransaction
{
    return App\Models\ReferralPointTransaction::create([
        'user_id' => $user->id, 'type' => $type, 'points' => $points, 'order_id' => $orderId, 'note' => $note,
    ]);
}

test('DISC-13: the customer page shows the balance, the checkout spendable figure and the latest 20 points rows, newest first', function () {
    actingAs(createAdmin(), 'admin');
    $user = User::factory()->create(['points_balance' => 100]);
    Illuminate\Support\Facades\Mail::fake();
    $address = ['name' => 'C', 'phone' => '01700000000', 'address_line1' => 'H1', 'city' => 'Dhaka', 'state' => 'Dhaka', 'country' => 'Bangladesh'];
    $order = App\Models\Order::create([
        'order_number' => 'SD-DISC13-1', 'user_id' => $user->id, 'status' => 'cancelled', 'payment_status' => 'unpaid',
        'payment_method' => 'cod', 'subtotal' => 100, 'shipping_cost' => 0, 'discount' => 0, 'tax' => 0, 'total' => 100,
        'shipping_address' => $address, 'billing_address' => $address,
    ]);
    $order->delete(); // trashed orders still show their number

    for ($i = 1; $i <= 22; $i++) {
        disc13Ledger($user, 'earn_daily_login', $i, note: "row {$i}");
    }
    $latest = disc13Ledger($user, 'refund_partial', 7, $order->id, 'Partial return.');

    // A live bKash/Nagad checkout holds 30 points, exactly as checkout sees it.
    App\Models\PendingCheckout::create([
        'token' => (string) Illuminate\Support\Str::uuid(), 'user_id' => $user->id, 'points_reserved' => 30,
        'checkout_data' => ['total' => 100], 'expires_at' => now()->addHour(),
    ]);
    $checkoutFigure = app(App\Services\LoyaltyPointsService::class)->availablePoints($user->fresh());

    $this->get(route('admin.customers.show', $user))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('points.balance', 100)
            ->where('points.spendable', $checkoutFigure)
            ->where('points.spendable', 70)
            ->has('points.history', 20)
            ->where('points.history.0.id', $latest->id)
            ->where('points.history.0.label', 'Redeemed points refunded (partial return)')
            ->where('points.history.0.points', 7)
            ->where('points.history.0.note', 'Partial return.')
            ->where('points.history.0.order_number', 'SD-DISC13-1')
            ->where('points.history.1.note', 'row 22')
            ->where('points.history.19.note', 'row 4'));
});

test('DISC-13: a negative balance is shown as-is and nothing is spendable', function () {
    actingAs(createAdmin(), 'admin');
    $user = User::factory()->create(['points_balance' => -30]);
    disc13Ledger($user, 'revoke', -50, note: 'Referral bonus reversed.');

    $this->get(route('admin.customers.show', $user))
        ->assertInertia(fn ($page) => $page
            ->where('points.balance', -30)
            ->where('points.spendable', 0)
            ->where('points.history.0.label', 'Referral reward reversed')
            ->where('points.history.0.points', -50));
});

test('DISC-13: an admin without the customers permission still gets 403 and sees no points', function () {
    actingAs(createAdmin(['permissions' => ['orders' => ['view']]]), 'admin');
    $user = User::factory()->create(['points_balance' => 55]);

    $this->get(route('admin.customers.show', $user))->assertForbidden();
});

test('DISC-13: viewing the card never changes points', function () {
    actingAs(createAdmin(), 'admin');
    $user = User::factory()->create(['points_balance' => 12]);
    disc13Ledger($user, 'earn_review', 12);

    $this->get(route('admin.customers.show', $user))->assertOk();

    expect($user->fresh()->points_balance)->toBe(12)
        ->and(App\Models\ReferralPointTransaction::where('user_id', $user->id)->count())->toBe(1);
});
