<?php

declare(strict_types=1);

/*
 * BIZ-017: converting an incomplete order charged the regular price during
 * a flash sale, and the screen showed only a subtotal while the order also
 * added a delivery charge. Conversion now uses the live checkout price, and
 * the screen previews prices, delivery and total from the same calculation.
 */

use App\Models\Admin;
use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\IncompleteOrder;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    $this->product = Product::factory()->create(['price' => 500, 'stock_quantity' => 10]);
    $sale = FlashSale::factory()->create(['starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'is_active' => true]);
    $this->flashItem = FlashSaleItem::factory()->create(['flash_sale_id' => $sale->id, 'product_id' => $this->product->id, 'sale_price' => 350, 'sold_count' => 0]);
    $this->incomplete = IncompleteOrder::factory()->create([
        'items' => [[
            'product_id' => $this->product->id, 'variant_id' => null, 'product_name' => $this->product->name,
            'sku' => $this->product->sku, 'price' => 350.0, 'quantity' => 1, 'subtotal' => 350.0, 'image' => null, 'variant_attributes' => null,
        ]],
    ]);
});

test('the conversion screen is pre-filled with the flash-sale price', function () {
    actingAs(createMotherAdmin(), 'admin')->get(route('admin.orders.incomplete.convert', $this->incomplete))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('items.0.price', 350));
});

test('converting during a flash sale charges the flash price and counts it against the sale', function () {
    actingAs(createMotherAdmin(), 'admin')->post(route('admin.orders.incomplete.placeOrder', $this->incomplete), [
        'name' => 'Karim', 'phone' => '01712345678', 'address_line1' => 'House 5', 'city' => 'Dhaka', 'state' => 'Dhaka',
        'items' => [['product_id' => $this->product->id, 'variant_id' => null, 'quantity' => 1]],
    ])->assertSessionHasNoErrors();

    $order = Order::sole();
    expect((float) $order->subtotal)->toBe(350.0)
        ->and((float) $order->items()->sole()->price)->toBe(350.0)
        ->and($order->items()->sole()->flash_sale_item_id)->toBe($this->flashItem->id)
        ->and($this->flashItem->refresh()->sold_count)->toBe(1);
});

test('the preview shows the same line prices and delivery charge the order will use', function () {
    $response = actingAs(createMotherAdmin(), 'admin')->getJson(route('admin.orders.incomplete.pricingPreview', [
        'state' => 'Dhaka', 'city' => 'Dhaka',
        'items' => [['product_id' => $this->product->id, 'variant_id' => null, 'quantity' => 2]],
    ]))->assertOk();

    expect($response->json('prices'))->toBe([350])
        ->and((float) $response->json('subtotal'))->toBe(700.0)
        ->and($response->json('shipping_cost'))->toBeGreaterThan(0);

    actingAs(createMotherAdmin(), 'admin')->post(route('admin.orders.incomplete.placeOrder', $this->incomplete), [
        'name' => 'Karim', 'phone' => '01712345678', 'address_line1' => 'House 5', 'city' => 'Dhaka', 'state' => 'Dhaka',
        'items' => [['product_id' => $this->product->id, 'variant_id' => null, 'quantity' => 2]],
    ])->assertSessionHasNoErrors();

    expect((float) Order::sole()->shipping_cost)->toBe((float) $response->json('shipping_cost'))
        ->and((float) Order::sole()->total)->toBe(700.0 + (float) $response->json('shipping_cost'));
});

test('staff who may convert incomplete orders can preview, others cannot', function () {
    $converter = Admin::create(['name' => 'Converter', 'email' => 'conv@example.com', 'password' => bcrypt('password')]);
    $converter->forceFill(['permissions' => ['incomplete_orders' => ['view', 'create']]])->save();
    $viewer = Admin::create(['name' => 'Viewer', 'email' => 'view@example.com', 'password' => bcrypt('password')]);
    $viewer->forceFill(['permissions' => ['incomplete_orders' => ['view']]])->save();
    $query = ['items' => [['product_id' => $this->product->id, 'variant_id' => null, 'quantity' => 1]]];

    actingAs($converter, 'admin')->getJson(route('admin.orders.incomplete.pricingPreview', $query))->assertOk();
    actingAs($viewer, 'admin')->getJson(route('admin.orders.incomplete.pricingPreview', $query))->assertForbidden();
});

test('the preview refuses a size or colour that belongs to a different product', function () {
    $foreignVariant = ProductVariant::factory()->create(['product_id' => Product::factory()->create()->id]);

    actingAs(createMotherAdmin(), 'admin')->getJson(route('admin.orders.incomplete.pricingPreview', [
        'items' => [['product_id' => $this->product->id, 'variant_id' => $foreignVariant->id, 'quantity' => 1]],
    ]))->assertUnprocessable();
});
