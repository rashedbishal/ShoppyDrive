<?php

declare(strict_types=1);

use App\Models\Cart;
use App\Models\Product;
use App\Support\CartManager;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * MED-09: CartManager::revalidatePrices() compares the stored cart-item
 * price against a freshly resolved catalog price. The two numbers can
 * legitimately arrive as different IEEE-754 doubles that represent the
 * exact same currency amount — e.g. one computed through a percentage
 * discount, the other read flat from the catalog — even though both
 * round to the same cents. The fix compares them as integer poisha
 * instead of as floats, the same convention already used by
 * ManualOrderPricing::differs().
 *
 * @return array{0: Cart, 1: Product}
 */
function cartWithSingleItem(float $productPrice, float $storedItemPrice): array
{
    $product = Product::factory()->create(['price' => $productPrice]);

    $cart = Cart::create(['session_id' => 'price-revalidation-test']);
    $cart->items()->create([
        'product_id' => $product->id,
        'variant_id' => null,
        'quantity' => 1,
        'price' => $storedItemPrice,
    ]);

    // Mutate the in-memory item's price directly, rather than re-saving it,
    // so it isn't rounded back to a clean decimal by the price column —
    // the whole point is to keep the raw float noise intact for the test.
    $cart->load('items');
    $cart->items->first()->price = $storedItemPrice;

    return [$cart, $product];
}

test('the same effective price via a different calculation path is not a false change', function () {
    // 199.99 * 0.85 lands on 169.9915..., a different raw double than the
    // flat 169.99 the catalog stores, but both represent 169.99 in cents.
    $noisyPrice = 199.99 * 0.85;
    [$cart] = cartWithSingleItem(productPrice: 169.99, storedItemPrice: $noisyPrice);

    expect(round($noisyPrice, 2))->toBe(169.99); // sanity: still the same cents
    expect($noisyPrice)->not->toBe(169.99); // sanity: genuinely a different raw float

    $changed = app(CartManager::class)->revalidatePrices($cart);

    expect($changed)->toBeFalse();
    // No real change was detected, so the row must keep its original value
    // untouched — not the catalog's clean 169.99, which would mean an
    // unnecessary write happened despite nothing having actually changed.
    expect((float) $cart->items->first()->refresh()->price)->toBe($noisyPrice);
});

test('a genuine one-poisha price change is still detected', function () {
    [$cart] = cartWithSingleItem(productPrice: 100.00, storedItemPrice: 100.01);

    $changed = app(CartManager::class)->revalidatePrices($cart);

    expect($changed)->toBeTrue();
    expect((float) $cart->items->first()->refresh()->price)->toBe(100.00);
});

test('a genuine large price change is still detected', function () {
    [$cart] = cartWithSingleItem(productPrice: 80.00, storedItemPrice: 150.00);

    $changed = app(CartManager::class)->revalidatePrices($cart);

    expect($changed)->toBeTrue();
    expect((float) $cart->items->first()->refresh()->price)->toBe(80.00);
});

test('an unchanged price leaves the cart item untouched, as before', function () {
    [$cart] = cartWithSingleItem(productPrice: 249.50, storedItemPrice: 249.50);

    $changed = app(CartManager::class)->revalidatePrices($cart);

    expect($changed)->toBeFalse();
    expect((float) $cart->items->first()->refresh()->price)->toBe(249.50);
});
