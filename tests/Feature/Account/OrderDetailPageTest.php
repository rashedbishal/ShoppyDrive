<?php

declare(strict_types=1);

/*
 * DISC-49: the customer order details page — compact cards, items link to
 * their product page while it is public, and variant options show next to
 * the SKU (also on the invoice), falling back to the live variant when the
 * order saved none.
 */

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Mail::fake();
    $this->user = User::factory()->create();
});

function disc49Order(User $user, array $items): Order
{
    $order = Order::create([
        'order_number' => 'ORD-DISC49-'.uniqid(), 'user_id' => $user->id, 'status' => 'processing',
        'payment_status' => 'unpaid', 'payment_method' => 'cod', 'subtotal' => 100, 'shipping_cost' => 60,
        'discount' => 0, 'tax' => 0, 'total' => 160,
        'shipping_address' => ['name' => 'Buyer', 'phone' => '01712345678', 'address_line1' => 'House 1', 'city' => 'Dhaka', 'state' => 'Dhaka'],
        'billing_address' => [],
    ]);

    foreach ($items as $item) {
        OrderItem::create(array_merge([
            'order_id' => $order->id, 'product_name' => 'Line', 'sku' => 'SKU-1', 'price' => 100, 'quantity' => 1,
            'subtotal' => 100, 'product_snapshot' => [],
        ], $item));
    }

    return $order;
}

test('items link to their product page only while that page is public', function () {
    $visible = Product::factory()->create();
    $hidden = Product::factory()->create(['is_active' => false]);
    $order = disc49Order($this->user, [['product_id' => $visible->id], ['product_id' => $hidden->id]]);

    actingAs($this->user)->get(route('account.orders.show', $order->order_number))
        ->assertInertia(fn ($page) => $page
            ->where("productLinks.{$visible->id}", route('product.show', $visible->slug))
            ->missing("productLinks.{$hidden->id}"));
});

test('an empty saved options list falls back to the live variant, for the label and the invoice', function () {
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'attributes' => ['Size' => 'XL'], 'sku' => 'VAR-XL']);
    $order = disc49Order($this->user, [[
        'product_id' => $product->id, 'variant_id' => $variant->id, 'sku' => 'VAR-XL',
        'product_snapshot' => ['variant_attributes' => []],
    ]]);

    $item = $order->items()->firstOrFail();
    expect($item->variantLabel())->toBe('Size: XL');

    $html = view('invoices.order', ['order' => $order->load(['items.product', 'items.variant', 'user'])])->render();
    expect($html)->toContain('Size: XL')->toContain('SKU: VAR-XL');
});

test('saved options still win over the live variant', function () {
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'attributes' => ['Size' => 'XL']]);
    $order = disc49Order($this->user, [[
        'product_id' => $product->id, 'variant_id' => $variant->id,
        'product_snapshot' => ['variant_attributes' => ['Size' => 'M']],
    ]]);

    expect($order->items()->firstOrFail()->variantLabel())->toBe('Size: M');
});

test('the page links image and name, shows option chips beside the SKU, and uses the real payment method', function () {
    // Source-level check (this project has no JavaScript test runner).
    $page = (string) preg_replace('/\s+/', ' ', (string) file_get_contents(resource_path('js/pages/account/OrderDetail.tsx')));

    expect($page)->toContain('productLinks[item.product_id]')
        ->toContain('aria-label={`View ${item.product_name}`}')
        ->toContain('options.map(')
        ->toContain('([name, value]) =>')
        ->toContain('SKU: {item.sku')
        ->toContain('<OrderTimeline histories={order.status_histories} compact />')
        ->not->toContain('TXN-98234723')
        ->not->toContain('sslcommerz');
});
