<?php

declare(strict_types=1);

/*
 * BIZ-026: the retired ZiniPay gateway's callback and webhook were still
 * reachable, accepted any pending token (including bKash/Nagad ones),
 * never checked the amount, and could delete a pending checkout. They are
 * removed. The bKash/Nagad page's "Cancel payment" link, which shared the
 * old controller, keeps working, and old ZiniPay orders still display.
 */

use App\Models\Order;
use App\Models\PendingCheckout;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

test('the ZiniPay callback and webhook no longer exist', function () {
    expect(Route::has('payment.callback'))->toBeFalse()
        ->and(Route::has('zinipay.webhook'))->toBeFalse();

    $this->get('/api/zinipay/webhook?val_id=x&invoice_id=y')->assertNotFound();
    $this->postJson('/api/zinipay/webhook', [])->assertNotFound();
    actingAs(User::factory()->create())->get('/payment/callback/'.Str::random(40))->assertNotFound();
});

test('a bKash/Nagad pending checkout can no longer be touched through a ZiniPay URL', function () {
    $user = User::factory()->create();
    $pending = PendingCheckout::create([
        'token' => Str::random(40), 'user_id' => $user->id,
        'checkout_data' => ['total' => 2000, 'provider' => 'bkash'], 'expires_at' => now()->addMinutes(30),
    ]);

    actingAs($user)->get('/payment/callback/'.$pending->token)->assertNotFound();

    expect(PendingCheckout::whereKey($pending->id)->exists())->toBeTrue();
});

test('the payment page Cancel link still discards the customer\'s own pending checkout', function () {
    $user = User::factory()->create();
    $pending = PendingCheckout::create([
        'token' => Str::random(40), 'user_id' => $user->id,
        'checkout_data' => ['total' => 2000, 'provider' => 'bkash'], 'expires_at' => now()->addMinutes(30),
    ]);

    actingAs($user)->get(route('payment.cancel', $pending->token))->assertRedirect(route('checkout.index'));

    expect(PendingCheckout::whereKey($pending->id)->exists())->toBeFalse();
});

test('an old ZiniPay order still opens in Admin', function () {
    $order = Order::create([
        'order_number' => 'ORD-ZINI-OLD', 'status' => 'delivered', 'payment_status' => 'paid', 'payment_method' => 'zinipay',
        'subtotal' => 500, 'shipping_cost' => 60, 'discount' => 0, 'tax' => 0, 'total' => 560,
        'shipping_address' => ['name' => 'A', 'phone' => '01700000000'], 'billing_address' => [],
    ]);

    actingAs(createMotherAdmin(), 'admin')->get(route('admin.orders.show', $order))->assertOk();
});
