<?php

declare(strict_types=1);

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\CouponUse;
use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * DB-003: a 2026-11-07 production check found zero existing duplicates in
 * any of these five groups, so the constraints were added directly. SQLite
 * can't prove a database-level constraint rejects a second row, so these
 * only run on real MySQL/MariaDB, mirroring the existing *MysqlTest files.
 */
function skipWithoutRealMysql(): bool
{
    return DB::connection()->getDriverName() !== 'mysql';
}

function duplicateTestOrder(): Order
{
    $address = ['name' => 'Test', 'phone' => '01700000000', 'address_line1' => 'House 1', 'city' => 'Dhaka', 'state' => 'Dhaka', 'country' => 'Bangladesh'];

    return Order::create([
        'order_number' => 'ORD-DUP-'.Str::random(10), 'status' => 'pending', 'payment_status' => 'unpaid',
        'payment_method' => 'cod', 'subtotal' => 100, 'shipping_cost' => 0, 'discount' => 0, 'tax' => 0, 'total' => 100,
        'shipping_address' => $address, 'billing_address' => $address,
    ]);
}

test('a second payment cannot reuse a transaction id', function () {
    $order = duplicateTestOrder();

    Payment::create(['order_id' => $order->id, 'method' => 'mobile_banking', 'amount' => 100, 'status' => 'paid', 'transaction_id' => 'TRX-DUP-1']);

    expect(fn () => Payment::create(['order_id' => $order->id, 'method' => 'mobile_banking', 'amount' => 100, 'status' => 'paid', 'transaction_id' => 'TRX-DUP-1']))
        ->toThrow(UniqueConstraintViolationException::class);
})->skip(fn () => skipWithoutRealMysql(), 'requires real MySQL');

test('two payments with no transaction id are both allowed', function () {
    $order = duplicateTestOrder();

    Payment::create(['order_id' => $order->id, 'method' => 'cod', 'amount' => 100, 'status' => 'paid', 'transaction_id' => null]);

    expect(Payment::create(['order_id' => $order->id, 'method' => 'cod', 'amount' => 100, 'status' => 'paid', 'transaction_id' => null])->exists)->toBeTrue();
})->skip(fn () => skipWithoutRealMysql(), 'requires real MySQL');

test('a coupon cannot be recorded used twice on the same order', function () {
    $coupon = Coupon::factory()->create();
    $order = duplicateTestOrder();
    $user = User::factory()->create();

    CouponUse::create(['coupon_id' => $coupon->id, 'user_id' => $user->id, 'order_id' => $order->id, 'discount_amount' => 10]);

    expect(fn () => CouponUse::create(['coupon_id' => $coupon->id, 'user_id' => $user->id, 'order_id' => $order->id, 'discount_amount' => 10]))
        ->toThrow(UniqueConstraintViolationException::class);
})->skip(fn () => skipWithoutRealMysql(), 'requires real MySQL');

test('a logged-in user cannot have two carts', function () {
    $user = User::factory()->create();

    Cart::create(['user_id' => $user->id, 'session_id' => 'session-a']);

    expect(fn () => Cart::create(['user_id' => $user->id, 'session_id' => 'session-b']))
        ->toThrow(UniqueConstraintViolationException::class);
})->skip(fn () => skipWithoutRealMysql(), 'requires real MySQL');

test('guests can each have their own cart with no user id', function () {
    Cart::create(['user_id' => null, 'session_id' => 'session-a']);

    expect(Cart::create(['user_id' => null, 'session_id' => 'session-b'])->exists)->toBeTrue();
})->skip(fn () => skipWithoutRealMysql(), 'requires real MySQL');

test('a plain product cannot appear twice in the same cart', function () {
    $cart = Cart::create(['user_id' => null, 'session_id' => 'session-plain']);
    $product = Product::factory()->create();

    CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'variant_id' => null, 'bundle_id' => null, 'quantity' => 1, 'price' => 100]);

    expect(fn () => CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'variant_id' => null, 'bundle_id' => null, 'quantity' => 1, 'price' => 100]))
        ->toThrow(UniqueConstraintViolationException::class);
})->skip(fn () => skipWithoutRealMysql(), 'requires real MySQL');

test('the same variant cannot appear twice in the same cart', function () {
    $cart = Cart::create(['user_id' => null, 'session_id' => 'session-variant']);
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

    CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'variant_id' => $variant->id, 'bundle_id' => null, 'quantity' => 1, 'price' => 100]);

    expect(fn () => CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'variant_id' => $variant->id, 'bundle_id' => null, 'quantity' => 1, 'price' => 100]))
        ->toThrow(UniqueConstraintViolationException::class);
})->skip(fn () => skipWithoutRealMysql(), 'requires real MySQL');

test('the same variant can be on flash sale only once', function () {
    $sale = FlashSale::factory()->create();
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

    FlashSaleItem::factory()->create(['flash_sale_id' => $sale->id, 'product_id' => $product->id, 'variant_id' => $variant->id]);

    expect(fn () => FlashSaleItem::factory()->create(['flash_sale_id' => $sale->id, 'product_id' => $product->id, 'variant_id' => $variant->id]))
        ->toThrow(UniqueConstraintViolationException::class);
})->skip(fn () => skipWithoutRealMysql(), 'requires real MySQL');

test('the same product without a variant can be on flash sale only once', function () {
    $sale = FlashSale::factory()->create();
    $product = Product::factory()->create();

    FlashSaleItem::factory()->create(['flash_sale_id' => $sale->id, 'product_id' => $product->id, 'variant_id' => null]);

    expect(fn () => FlashSaleItem::factory()->create(['flash_sale_id' => $sale->id, 'product_id' => $product->id, 'variant_id' => null]))
        ->toThrow(UniqueConstraintViolationException::class);
})->skip(fn () => skipWithoutRealMysql(), 'requires real MySQL');
