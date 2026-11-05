<?php

declare(strict_types=1);

/*
 * BIZ-011: a COD order costs nothing to place, so one phone number may hold
 * at most PendingCodLimit::MAX_UNITS_PER_PRODUCT units of the same product
 * in COD orders no courier has picked up yet, and a hidden honeypot field
 * refuses orders from bots that fill every input.
 */

use App\Models\Cart;
use App\Models\LandingPage;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\PendingCodLimit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    $this->product = Product::factory()->create(['price' => 100, 'stock_quantity' => 50]);
});

function pendingCodCheckoutPayload(Product $product, int $quantity, array $overrides = []): array
{
    return array_merge([
        'name' => 'Checkout User',
        'email' => 'buyer@example.com',
        'phone' => '01711111111',
        'address_line1' => 'House 12, Road 7',
        'city' => 'Dhaka',
        'state' => 'Dhaka',
        'country' => 'Bangladesh',
        'save_address' => false,
        'payment_method' => 'cod',
        'order_token' => (string) Str::uuid(),
        'items' => [
            ['product_id' => $product->id, 'variant_id' => null, 'quantity' => $quantity, 'price' => (float) $product->price],
        ],
    ], $overrides);
}

function placePendingCodCheckout(Product $product, int $quantity, array $overrides = []): TestResponse
{
    $user = User::factory()->create();
    Cart::query()->where('user_id', $user->id)->delete();

    return test()->actingAs($user)->post(route('checkout.store'), pendingCodCheckoutPayload($product, $quantity, $overrides));
}

test('the limit is 10 units', function () {
    expect(PendingCodLimit::MAX_UNITS_PER_PRODUCT)->toBe(10);
});

test('a phone can hold up to the limit, and the next unit is refused without touching stock', function () {
    placePendingCodCheckout($this->product, 6)->assertRedirect();
    placePendingCodCheckout($this->product, 4)->assertRedirect();
    expect(Order::count())->toBe(2)
        ->and($this->product->refresh()->stock_quantity)->toBe(40);

    placePendingCodCheckout($this->product, 1)
        ->assertSessionHasErrors('items')
        ->assertSessionHas('error', fn (string $message) => str_contains($message, 'at most 10')
            && str_contains($message, 'You already have 10'));

    expect(Order::count())->toBe(2)
        ->and($this->product->refresh()->stock_quantity)->toBe(40);
});

test('a single order above the limit is refused', function () {
    placePendingCodCheckout($this->product, 11)->assertSessionHasErrors('items');

    expect(Order::count())->toBe(0)
        ->and($this->product->refresh()->stock_quantity)->toBe(50);
});

test('the limit is per phone number and per product', function () {
    $otherProduct = Product::factory()->create(['price' => 100, 'stock_quantity' => 50]);
    placePendingCodCheckout($this->product, 10)->assertRedirect();

    placePendingCodCheckout($this->product, 10, ['phone' => '01822222222'])->assertRedirect();
    placePendingCodCheckout($otherProduct, 10)->assertRedirect();

    expect(Order::count())->toBe(3);
});

test('orders that are with a courier, finished or cancelled no longer count', function (array $state) {
    placePendingCodCheckout($this->product, 10)->assertRedirect();
    Order::query()->firstOrFail()->forceFill($state)->saveQuietly();

    placePendingCodCheckout($this->product, 10)->assertRedirect();

    expect(Order::count())->toBe(2);
})->with([
    'handed to Steadfast' => [['status' => 'processing', 'steadfast_consignment_id' => 'SF-1']],
    'handed to Pathao' => [['status' => 'processing', 'pathao_consignment_id' => 'PT-1']],
    'shipped' => [['status' => 'shipped']],
    'delivered' => [['status' => 'delivered']],
    'cancelled' => [['status' => 'cancelled']],
]);

test('a processing order not yet with a courier still counts', function () {
    placePendingCodCheckout($this->product, 10)->assertRedirect();
    Order::query()->firstOrFail()->forceFill(['status' => 'processing'])->saveQuietly();

    placePendingCodCheckout($this->product, 1)->assertSessionHasErrors('items');

    expect(Order::count())->toBe(1);
});

test('the landing page COD order counts toward and respects the same limit', function () {
    $page = LandingPage::create([
        'title' => 'Promo',
        'slug' => 'promo-'.uniqid(),
        'product_id' => $this->product->id,
        'is_active' => true,
        'sections' => [],
    ]);
    $landingPayload = fn (int $quantity) => [
        'name' => 'Test Customer',
        'phone' => '01711111111',
        'address_line1' => '123 Main St',
        'city' => 'Dhaka',
        'state' => 'Dhaka',
        'quantity' => $quantity,
        'payment_method' => 'cod',
        'order_token' => (string) Str::uuid(),
    ];

    placePendingCodCheckout($this->product, 8)->assertRedirect();
    $this->post(route('lp.order', $page->slug), $landingPayload(3))->assertSessionHasErrors('quantity');
    $this->post(route('lp.order', $page->slug), $landingPayload(2))->assertRedirect();

    expect(Order::count())->toBe(2)
        ->and($this->product->refresh()->stock_quantity)->toBe(40);
});

test('a filled-in honeypot refuses the checkout order', function () {
    placePendingCodCheckout($this->product, 1, ['sd_confirm_ref' => 'http://spam.example'])
        ->assertSessionHasErrors('sd_confirm_ref');

    expect(Order::count())->toBe(0);
});

test('an empty honeypot, as real browsers send it, is accepted', function () {
    placePendingCodCheckout($this->product, 1, ['sd_confirm_ref' => ''])->assertRedirect();

    expect(Order::count())->toBe(1);
});

test('a filled-in honeypot refuses the landing page order', function () {
    $page = LandingPage::create([
        'title' => 'Promo',
        'slug' => 'promo-'.uniqid(),
        'product_id' => $this->product->id,
        'is_active' => true,
        'sections' => [],
    ]);

    $this->post(route('lp.order', $page->slug), [
        'name' => 'Bot',
        'phone' => '01711111111',
        'address_line1' => '123 Main St',
        'city' => 'Dhaka',
        'state' => 'Dhaka',
        'quantity' => 1,
        'payment_method' => 'cod',
        'order_token' => (string) Str::uuid(),
        'sd_confirm_ref' => 'anything',
    ])->assertSessionHasErrors('sd_confirm_ref');

    expect(Order::count())->toBe(0);
});
