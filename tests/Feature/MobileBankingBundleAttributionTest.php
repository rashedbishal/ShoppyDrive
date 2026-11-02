<?php

use App\Enums\BundleDiscountType;
use App\Models\Order;
use App\Models\PendingCheckout;
use App\Models\Product;
use App\Models\ProductBundle;
use App\Models\ProductBundleItem;
use App\Models\ReceivedSms;
use App\Models\User;
use Illuminate\Support\Str;

/*
 * FSA-12: a mobile-banking order keeps the bundle attribution checkout
 * staged for it, exactly like a COD order. The staged tag is the one
 * CartManager::revalidatePrices() left after BundleService::reconcile(), so
 * a client-submitted bundle id the cart doesn't actually satisfy never
 * reaches the order.
 */

function fixedPriceBundle(float $price, Product ...$components): ProductBundle
{
    $bundle = ProductBundle::factory()->create(['discount_type' => BundleDiscountType::FixedPrice, 'bundle_price' => $price]);

    foreach ($components as $component) {
        ProductBundleItem::factory()->create(['product_bundle_id' => $bundle->id, 'product_id' => $component->id, 'quantity' => 1]);
    }

    return $bundle;
}

/**
 * Stages a mobile-banking checkout for $items and pays it with a matching
 * SMS, returning the created order.
 *
 * @param  array<int, array<string, mixed>>  $items
 */
function payStagedMobileBankingOrder(User $user, array $items): Order
{
    test()->actingAs($user)->post(route('checkout.store'), [
        'name' => 'Bundle Buyer', 'email' => $user->email, 'phone' => '01700000000',
        'address_line1' => 'House 1', 'city' => 'Dhaka', 'state' => 'Dhaka', 'country' => 'Bangladesh',
        'payment_method' => 'mobile_banking',
        'order_token' => (string) Str::uuid(),
        'items' => $items,
    ])->assertRedirect();

    $pending = PendingCheckout::query()->where('user_id', $user->id)->sole();
    $transactionId = 'BNDL'.Str::upper(Str::random(6));
    ReceivedSms::create([
        'sender' => 'bKash', 'provider' => 'bkash', 'content' => "TrxID {$transactionId}",
        'received_at' => now(), 'parsed_transaction_id' => $transactionId,
        'parsed_amount' => (float) $pending->checkout_data['total'], 'is_matched' => false,
    ]);

    test()->actingAs($user)
        ->post(route('payment.mobile.submit', $pending->token), ['provider' => 'bkash', 'transaction_id' => $transactionId])
        ->assertRedirect(route('order.confirmation', $pending->fresh()->order_number));

    return Order::with('items')->where('user_id', $user->id)->sole();
}

test('a paid mobile-banking order keeps its bundle attribution and ordinary items stay unbundled', function () {
    $user = User::factory()->create();
    $a = Product::factory()->create(['price' => 150, 'stock_quantity' => 10]);
    $b = Product::factory()->create(['price' => 50, 'stock_quantity' => 10]);
    $plain = Product::factory()->create(['price' => 80, 'stock_quantity' => 10]);
    $bundle = fixedPriceBundle(120, $a, $b);

    $order = payStagedMobileBankingOrder($user, [
        ['product_id' => $a->id, 'variant_id' => null, 'quantity' => 1, 'bundle_id' => $bundle->id, 'bundle_unit_quantity' => 1],
        ['product_id' => $b->id, 'variant_id' => null, 'quantity' => 1, 'bundle_id' => $bundle->id, 'bundle_unit_quantity' => 1],
        ['product_id' => $plain->id, 'variant_id' => null, 'quantity' => 1],
    ]);

    $byProduct = $order->items->keyBy('product_id');
    expect($order->subtotal)->toBe(200.0)
        ->and($byProduct[$a->id]->bundle_id)->toBe($bundle->id)
        ->and($byProduct[$a->id]->price)->toBe(90.0)
        ->and($byProduct[$b->id]->bundle_id)->toBe($bundle->id)
        ->and($byProduct[$b->id]->price)->toBe(30.0)
        ->and($byProduct[$plain->id]->bundle_id)->toBeNull();
});

test('items from two different bundles keep their own attribution', function () {
    $user = User::factory()->create();
    [$a, $b, $c, $d] = Product::factory()->count(4)->create(['price' => 100, 'stock_quantity' => 10])->all();
    $first = fixedPriceBundle(150, $a, $b);
    $second = fixedPriceBundle(160, $c, $d);

    $order = payStagedMobileBankingOrder($user, [
        ['product_id' => $a->id, 'variant_id' => null, 'quantity' => 1, 'bundle_id' => $first->id, 'bundle_unit_quantity' => 1],
        ['product_id' => $b->id, 'variant_id' => null, 'quantity' => 1, 'bundle_id' => $first->id, 'bundle_unit_quantity' => 1],
        ['product_id' => $c->id, 'variant_id' => null, 'quantity' => 1, 'bundle_id' => $second->id, 'bundle_unit_quantity' => 1],
        ['product_id' => $d->id, 'variant_id' => null, 'quantity' => 1, 'bundle_id' => $second->id, 'bundle_unit_quantity' => 1],
    ]);

    expect($order->items->pluck('bundle_id', 'product_id')->all())->toEqual([
        $a->id => $first->id,
        $b->id => $first->id,
        $c->id => $second->id,
        $d->id => $second->id,
    ])->and($order->subtotal)->toBe(310.0);
});

test('an unrelated bundle id submitted with a product never reaches the order', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 200, 'stock_quantity' => 10]);
    $unrelated = fixedPriceBundle(50, ...Product::factory()->count(2)->create(['price' => 100])->all());

    $order = payStagedMobileBankingOrder($user, [
        ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1, 'bundle_id' => $unrelated->id, 'bundle_unit_quantity' => 1],
    ]);

    expect($order->items->sole()->bundle_id)->toBeNull()
        ->and($order->items->sole()->price)->toBe(200.0);
});
