<?php

declare(strict_types=1);

use App\Models\Admin;
use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\HomepageSetting;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductBundle;
use App\Models\ProductVariant;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function pricedOrderPayload(Product $product, array $overrides = []): array
{
    return array_merge([
        'name' => 'Phone Customer',
        'phone' => '01712345678',
        'address_line1' => 'House 1, Road 2',
        'city' => 'Inside Dhaka City Corporation',
        'state' => 'Dhaka',
        'payment_method' => 'cod',
        'shipping_cost' => 60,
        'discount' => 0,
        'items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 2, 'price' => 250]],
    ], $overrides);
}

function pricingActivity(): ?Activity
{
    return Activity::query()->where('event', 'pricing_override')->latest('id')->first();
}

beforeEach(function () {
    $this->pricingAdmin = Admin::create([
        'name' => 'Order Staff',
        'email' => 'order-staff@example.com',
        'password' => 'password',
        'permissions' => ['orders' => ['view', 'create']],
    ]);
    $this->product = Product::factory()->create(['price' => 250, 'stock_quantity' => 10]);
    actingAs($this->pricingAdmin, 'admin');
});

test('the calculated pricing needs no reason and writes no override entry', function () {
    $this->post(route('admin.orders.store'), pricedOrderPayload($this->product))->assertSessionHasNoErrors()->assertRedirect();

    expect(Order::query()->count())->toBe(1);
    expect(pricingActivity())->toBeNull();
});

test('a custom item price with a reason is accepted and recorded', function () {
    $this->post(route('admin.orders.store'), pricedOrderPayload($this->product, [
        'items' => [['product_id' => $this->product->id, 'quantity' => 2, 'price' => 200, 'price_override_reason' => 'Agreed by phone']],
    ]))->assertSessionHasNoErrors();

    $order = Order::query()->firstOrFail();
    $activity = pricingActivity();

    expect((float) $order->subtotal)->toBe(400.0);
    expect($activity->causer->is($this->pricingAdmin))->toBeTrue();
    expect($activity->subject->is($order))->toBeTrue();
    expect($activity->created_at)->not->toBeNull();
    expect($activity->properties['order_number'])->toBe($order->order_number);
    expect($activity->properties['items'][0])->toMatchArray([
        'product_id' => $this->product->id,
        'variant_id' => null,
        'calculated_price' => 250,
        'final_price' => 200,
        'reason' => 'Agreed by phone',
    ]);
});

test('a custom item price without a reason is rejected', function (mixed $reason) {
    $this->post(route('admin.orders.store'), pricedOrderPayload($this->product, [
        'items' => [['product_id' => $this->product->id, 'quantity' => 2, 'price' => 200, 'price_override_reason' => $reason]],
    ]))->assertSessionHasErrors('items.0.price_override_reason');

    expect(Order::query()->count())->toBe(0);
    expect(pricingActivity())->toBeNull();
    expect($this->product->refresh()->stock_quantity)->toBe(10);
})->with(['missing' => [null], 'blank' => ['   '], 'too short' => ['no']]);

test('a client cannot claim its own catalog price or override flag', function () {
    $this->post(route('admin.orders.store'), pricedOrderPayload($this->product, [
        'catalog_price' => 1,
        'calculated_price' => 1,
        'price_overridden' => false,
        'items' => [['product_id' => $this->product->id, 'quantity' => 2, 'price' => 1, 'catalog_price' => 1, 'calculated_price' => 1, 'price_overridden' => false]],
    ]))->assertSessionHasErrors('items.0.price_override_reason');

    expect(Order::query()->count())->toBe(0);
});

test('a malformed price is rejected', function (mixed $price) {
    $this->post(route('admin.orders.store'), pricedOrderPayload($this->product, [
        'items' => [['product_id' => $this->product->id, 'quantity' => 2, 'price' => $price, 'price_override_reason' => 'Valid reason']],
    ]))->assertSessionHasErrors('items.0.price');

    expect(Order::query()->count())->toBe(0);
})->with(['negative' => [-1], 'text' => ['free'], 'sub-poisha' => [249.999], 'too large' => [100000000]]);

test('a variant is compared against its own price, not the product price', function () {
    $product = Product::factory()->create(['price' => 100]);
    $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 180, 'stock_quantity' => 5]);

    $this->post(route('admin.orders.store'), pricedOrderPayload($product, [
        'items' => [['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 1, 'price' => 100]],
    ]))->assertSessionHasErrors('items.0.price_override_reason');

    $this->post(route('admin.orders.store'), pricedOrderPayload($product, [
        'items' => [['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 1, 'price' => 180]],
    ]))->assertSessionHasNoErrors();

    expect(pricingActivity())->toBeNull();
});

test('a variant belonging to another product is rejected', function () {
    $otherVariant = ProductVariant::factory()->create(['price' => 5, 'stock_quantity' => 5]);

    $this->post(route('admin.orders.store'), pricedOrderPayload($this->product, [
        'items' => [['product_id' => $this->product->id, 'variant_id' => $otherVariant->id, 'quantity' => 1, 'price' => 5]],
    ]))->assertSessionHasErrors('items.0.variant_id');

    expect(Order::query()->count())->toBe(0);
});

test('a bundle component is priced standalone, whatever bundle a client names', function () {
    $bundle = ProductBundle::factory()->create();
    $bundle->items()->create(['product_id' => $this->product->id, 'variant_id' => null, 'quantity' => 1]);

    $this->post(route('admin.orders.store'), pricedOrderPayload($this->product, [
        'items' => [['product_id' => $this->product->id, 'bundle_id' => $bundle->id, 'quantity' => 2, 'price' => 1]],
    ]))->assertSessionHasErrors('items.0.price_override_reason');

    $this->post(route('admin.orders.store'), pricedOrderPayload($this->product, [
        'items' => [['product_id' => $this->product->id, 'bundle_id' => $bundle->id, 'quantity' => 2, 'price' => 250]],
    ]))->assertSessionHasNoErrors();
});

test('an active flash sale price is the calculated price', function () {
    $sale = FlashSale::create(['name' => 'Sale', 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'is_active' => true]);
    FlashSaleItem::create(['flash_sale_id' => $sale->id, 'product_id' => $this->product->id, 'sale_price' => 150, 'stock_limit' => 10, 'sold_count' => 0]);

    $this->post(route('admin.orders.store'), pricedOrderPayload($this->product, [
        'items' => [['product_id' => $this->product->id, 'quantity' => 2, 'price' => 150]],
    ]))->assertSessionHasNoErrors();

    expect(pricingActivity())->toBeNull();
});

test('a discount needs a reason, and is recorded with its calculated value', function () {
    $payload = pricedOrderPayload($this->product, ['discount' => 50]);

    $this->post(route('admin.orders.store'), $payload)->assertSessionHasErrors('discount_override_reason');
    expect(Order::query()->count())->toBe(0);

    $this->post(route('admin.orders.store'), [...$payload, 'discount_override_reason' => 'Loyal customer'])->assertSessionHasNoErrors();

    expect(pricingActivity()->properties['discount'])->toEqual(['calculated' => 0, 'final' => 50, 'reason' => 'Loyal customer']);
    expect(pricingActivity()->description)->toContain('discount');
});

test('changing shipping needs a reason, and is recorded with its calculated value', function () {
    $payload = pricedOrderPayload($this->product, ['shipping_cost' => 0]);

    $this->post(route('admin.orders.store'), $payload)->assertSessionHasErrors('shipping_override_reason');
    expect(Order::query()->count())->toBe(0);

    $this->post(route('admin.orders.store'), [...$payload, 'shipping_override_reason' => 'Customer collects'])->assertSessionHasNoErrors();

    expect(pricingActivity()->properties['shipping'])->toEqual(['calculated' => 60, 'final' => 0, 'reason' => 'Customer collects']);
});

test('the shipping rules decide the calculated shipping', function (Closure $arrange, array $overrides) {
    $arrange($this->product);

    $this->post(route('admin.orders.store'), pricedOrderPayload($this->product, $overrides))->assertSessionHasNoErrors();

    expect(pricingActivity())->toBeNull();
})->with([
    'outside Dhaka rate' => [fn () => null, ['city' => 'Savar', 'shipping_cost' => 120]],
    'free delivery product' => [fn (Product $product) => $product->update(['free_delivery' => true]), ['shipping_cost' => 0]],
    'free delivery threshold' => [fn () => HomepageSetting::updateOrCreate(['section_key' => 'announcement_bar'], ['section_name' => 'Announcement Bar', 'metadata' => ['free_delivery_amount' => 400]]), ['shipping_cost' => 0]],
]);

test('a reason on an unchanged value is not recorded as an override', function () {
    $this->post(route('admin.orders.store'), pricedOrderPayload($this->product, [
        'shipping_override_reason' => 'Nothing changed',
    ]))->assertSessionHasNoErrors();

    expect(pricingActivity())->toBeNull();
});

test('an order that fails on stock leaves no override entry', function () {
    $this->product->update(['stock_quantity' => 1]);

    $this->post(route('admin.orders.store'), pricedOrderPayload($this->product, [
        'items' => [['product_id' => $this->product->id, 'quantity' => 2, 'price' => 200, 'price_override_reason' => 'Agreed by phone']],
    ]))->assertSessionHasErrors('items');

    expect(Order::query()->count())->toBe(0);
    expect(pricingActivity())->toBeNull();
});

test('the pricing preview returns server prices and needs orders.create', function () {
    $query = ['state' => 'Dhaka', 'city' => 'Inside Dhaka City Corporation', 'items' => [['product_id' => $this->product->id, 'variant_id' => null, 'quantity' => 2]]];

    $this->getJson(route('admin.orders.pricingPreview', $query))
        ->assertOk()
        ->assertJson(['prices' => [250], 'subtotal' => 500, 'shipping_cost' => 60, 'discount' => 0]);

    actingAs(Admin::create(['name' => 'No Access', 'email' => 'no-access@example.com', 'password' => 'password', 'permissions' => ['orders' => ['view']]]), 'admin');

    $this->getJson(route('admin.orders.pricingPreview', $query))->assertForbidden();
    $this->post(route('admin.orders.store'), pricedOrderPayload($this->product))->assertForbidden();
});
