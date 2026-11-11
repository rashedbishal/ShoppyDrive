<?php

declare(strict_types=1);

use App\Models\LandingPage;
use App\Models\Order;
use App\Models\PendingCheckout;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

/*
 * Landing pages for products with variants (one row per variant, each with
 * its own quantity), for inactive products, with a per-page order limit, and
 * stock reduced like any other order.
 */
beforeEach(function () {
    Mail::fake();
    createMotherAdmin();

    $this->product = Product::factory()->create(['is_active' => true, 'published_at' => now()->subDay(), 'price' => 500, 'stock_quantity' => 0]);
    $this->red = ProductVariant::factory()->create(['product_id' => $this->product->id, 'price' => 600, 'stock_quantity' => 10, 'attributes' => ['Color' => 'Red', 'Size' => 'M']]);
    $this->blue = ProductVariant::factory()->create(['product_id' => $this->product->id, 'price' => 700, 'stock_quantity' => 5, 'attributes' => ['Color' => 'Blue', 'Size' => 'L']]);

    $this->page = LandingPage::create(['title' => 'Promo', 'slug' => 'promo-'.uniqid(), 'product_id' => $this->product->id, 'is_active' => true, 'sections' => []]);
});

function lpPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Test Customer', 'phone' => '01712345678', 'email' => 'c@example.com',
        'address_line1' => '123 Main St', 'city' => 'Dhaka', 'state' => 'Dhaka',
        'payment_method' => 'cod', 'order_token' => (string) Str::uuid(),
    ], $overrides);
}

test('the page for a variant product is shown with every variant, price, image and stock', function () {
    get(route('lp.show', $this->page->slug))->assertOk()->assertInertia(fn ($p) => $p
        ->where('page.max_order_quantity', 100)
        ->has('product.variants', 2)
        ->where('product.variants.0.price', 600)
        ->where('product.variants.1.stock_quantity', 5)
        ->where('product.variants.0.attributes.Color', 'Red'));
});

test('a page for an inactive product is still shown and orderable', function () {
    $plain = Product::factory()->create(['is_active' => false, 'price' => 300, 'stock_quantity' => 20]);
    $page = LandingPage::create(['title' => 'Off', 'slug' => 'off-'.uniqid(), 'product_id' => $plain->id, 'is_active' => true, 'sections' => []]);

    get(route('lp.show', $page->slug))->assertOk();
    post(route('lp.order', $page->slug), lpPayload(['quantity' => 2]))->assertSessionHasNoErrors();

    expect(Order::query()->sole()->total)->toBeGreaterThan(0)->and($plain->refresh()->stock_quantity)->toBe(18);
});

test('a deleted product makes the page 404', function () {
    $this->product->delete();

    get(route('lp.show', $this->page->slug))->assertNotFound();
    post(route('lp.order', $this->page->slug), lpPayload(['items' => [['variant_id' => $this->red->id, 'quantity' => 1]]]))->assertNotFound();
});

test('several variants in one order become one line each, priced and stocked per variant', function () {
    post(route('lp.order', $this->page->slug), lpPayload(['items' => [
        ['variant_id' => $this->blue->id, 'quantity' => 1],
        ['variant_id' => $this->red->id, 'quantity' => 2],
    ]]))->assertSessionHasNoErrors();

    $order = Order::query()->with('items')->sole();
    expect($order->items)->toHaveCount(2)
        ->and((float) $order->subtotal)->toBe(1900.0) // 2 x 600 + 1 x 700
        ->and($order->items->firstWhere('variant_id', $this->red->id)->quantity)->toBe(2)
        ->and($order->items->firstWhere('variant_id', $this->blue->id)->product_snapshot['variant_attributes']['Color'])->toBe('Blue')
        ->and($this->red->refresh()->stock_quantity)->toBe(8)
        ->and($this->blue->refresh()->stock_quantity)->toBe(4)
        ->and($order->landing_page_id)->toBe($this->page->id);
});

test('if one variant is out of stock nothing is ordered and no stock changes', function () {
    post(route('lp.order', $this->page->slug), lpPayload(['items' => [
        ['variant_id' => $this->red->id, 'quantity' => 3],
        ['variant_id' => $this->blue->id, 'quantity' => 6], // only 5 left
    ]]))->assertSessionHasErrors('quantity');

    expect(Order::query()->count())->toBe(0)->and($this->red->refresh()->stock_quantity)->toBe(10)->and($this->blue->refresh()->stock_quantity)->toBe(5);
});

test('a variant product cannot be ordered without choosing variants', function () {
    post(route('lp.order', $this->page->slug), lpPayload(['quantity' => 1]))->assertSessionHasErrors('items');

    expect(Order::query()->count())->toBe(0);
});

test('invalid lines are refused: another product\'s variant, duplicates, zero quantity', function (array $items) {
    $other = ProductVariant::factory()->create(['stock_quantity' => 10]);
    $items = array_map(fn ($i) => $i['variant_id'] === 'other' ? [...$i, 'variant_id' => $other->id] : $i, $items);
    $items = array_map(fn ($i) => $i['variant_id'] === 'red' ? [...$i, 'variant_id' => $this->red->id] : $i, $items);

    post(route('lp.order', $this->page->slug), lpPayload(['items' => $items]))->assertSessionHasErrors();

    expect(Order::query()->count())->toBe(0);
})->with([
    'another product\'s variant' => [[['variant_id' => 'other', 'quantity' => 1]]],
    'the same variant twice' => [[['variant_id' => 'red', 'quantity' => 1], ['variant_id' => 'red', 'quantity' => 1]]],
    'zero quantity' => [[['variant_id' => 'red', 'quantity' => 0]]],
]);

test('the page\'s own limit caps the total units across variants', function () {
    $this->page->update(['max_order_quantity' => 4]);

    post(route('lp.order', $this->page->slug), lpPayload(['items' => [
        ['variant_id' => $this->red->id, 'quantity' => 3],
        ['variant_id' => $this->blue->id, 'quantity' => 2],
    ]]))->assertSessionHasErrors('items');
    expect(Order::query()->count())->toBe(0);

    post(route('lp.order', $this->page->slug), lpPayload(['items' => [
        ['variant_id' => $this->red->id, 'quantity' => 2],
        ['variant_id' => $this->blue->id, 'quantity' => 2],
    ]]))->assertSessionHasNoErrors();
    expect(Order::query()->count())->toBe(1);
});

test('the limit also applies to a product without variants', function () {
    $plain = Product::factory()->create(['is_active' => true, 'stock_quantity' => 50]);
    $page = LandingPage::create(['title' => 'P', 'slug' => 'p-'.uniqid(), 'product_id' => $plain->id, 'is_active' => true, 'max_order_quantity' => 3, 'sections' => []]);

    post(route('lp.order', $page->slug), lpPayload(['quantity' => 4]))->assertSessionHasErrors('quantity');
    post(route('lp.order', $page->slug), lpPayload(['quantity' => 3]))->assertSessionHasNoErrors();
    expect($plain->refresh()->stock_quantity)->toBe(47);
});

test('mobile banking checks every variant line first and stages all of them, stock only changes after payment', function () {
    post(route('lp.order', $this->page->slug), lpPayload(['payment_method' => 'mobile_banking', 'provider' => 'bkash', 'items' => [
        ['variant_id' => $this->red->id, 'quantity' => 2],
        ['variant_id' => $this->blue->id, 'quantity' => 1],
    ]]))->assertSessionHasNoErrors();

    $pending = PendingCheckout::query()->sole();
    expect($pending->checkout_data['items'])->toHaveCount(2)
        ->and((float) $pending->checkout_data['subtotal'])->toBe(1900.0)
        ->and($this->red->refresh()->stock_quantity)->toBe(10);

    post(route('lp.order', $this->page->slug), lpPayload(['payment_method' => 'mobile_banking', 'provider' => 'bkash', 'items' => [
        ['variant_id' => $this->blue->id, 'quantity' => 9],
    ]]))->assertSessionHasErrors('quantity');
    expect(PendingCheckout::query()->count())->toBe(1);
});

test('the confirmation page lists each variant line with its choice', function () {
    post(route('lp.order', $this->page->slug), lpPayload(['items' => [['variant_id' => $this->red->id, 'quantity' => 1]]]));
    $order = Order::query()->sole();

    get(route('lp.confirmation', $order->guest_token))->assertInertia(fn ($p) => $p->where('order.items.0.variant_label', 'Color: Red, Size: M'));
});

test('submitting the same order twice with variants still creates one order and takes stock once', function () {
    $payload = lpPayload(['items' => [['variant_id' => $this->red->id, 'quantity' => 2]]]);

    post(route('lp.order', $this->page->slug), $payload);
    post(route('lp.order', $this->page->slug), $payload);

    expect(Order::query()->count())->toBe(1)->and($this->red->refresh()->stock_quantity)->toBe(8);
});
