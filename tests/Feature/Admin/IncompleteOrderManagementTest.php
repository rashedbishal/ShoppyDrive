<?php

declare(strict_types=1);

use App\Models\IncompleteOrder;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = createAdmin([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => bcrypt('password'),
    ]);
});

test('the incomplete orders index lists tracked abandoned checkouts', function () {
    actingAs($this->admin, 'admin');
    IncompleteOrder::factory()->count(3)->create();

    $response = $this->get(route('admin.orders.incomplete.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/orders/incomplete/index')
        ->has('incompleteOrders.data', 3)
    );
});

test('an admin can delete a single incomplete order', function () {
    actingAs($this->admin, 'admin');
    $incomplete = IncompleteOrder::factory()->create();

    $response = $this->delete(route('admin.orders.incomplete.destroy', $incomplete));

    $response->assertRedirect();
    expect(IncompleteOrder::find($incomplete->id))->toBeNull();
});

test('an admin can delete all incomplete orders at once', function () {
    actingAs($this->admin, 'admin');
    IncompleteOrder::factory()->count(4)->create();

    $response = $this->delete(route('admin.orders.incomplete.destroyAll'));

    $response->assertRedirect();
    expect(IncompleteOrder::count())->toBe(0);
});

test('an admin can convert an incomplete order into a real cash-on-delivery order', function () {
    actingAs($this->admin, 'admin');
    $product = Product::factory()->create(['price' => 450]);

    $incomplete = IncompleteOrder::factory()->create([
        'name' => 'Abandoned Customer',
        'phone' => '01712345678',
        'items' => [[
            'product_id' => $product->id,
            'variant_id' => null,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'price' => 450,
            'quantity' => 2,
            'subtotal' => 900,
            'image' => null,
            'variant_attributes' => null,
        ]],
        'subtotal' => 900,
    ]);

    $response = $this->post(route('admin.orders.incomplete.placeOrder', $incomplete), [
        'name' => 'Abandoned Customer',
        'phone' => '01712345678',
        'address_line1' => 'House 5, Road 2',
        'city' => 'Inside Dhaka City Corporation',
        'state' => 'Dhaka',
        'items' => [
            ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 2],
        ],
    ]);

    $order = Order::query()->firstOrFail();
    $response->assertRedirect(route('admin.orders.show', $order));

    expect($order->payment_method->value)->toBe('cod');
    expect($order->subtotal)->toBe(900.0);
    expect($order->shipping_address['phone'])->toBe('01712345678');
    expect($order->items()->count())->toBe(1);
    expect($order->items()->first()->quantity)->toBe(2);

    // Converting removes the incomplete-order row — it's a real order now.
    expect(IncompleteOrder::find($incomplete->id))->toBeNull();
});

test('guests cannot access any incomplete order routes', function () {
    $incomplete = IncompleteOrder::factory()->create();

    $this->get(route('admin.orders.incomplete.index'))->assertRedirect(route('admin.login'));
    $this->delete(route('admin.orders.incomplete.destroy', $incomplete))->assertRedirect(route('admin.login'));
    $this->delete(route('admin.orders.incomplete.destroyAll'))->assertRedirect(route('admin.login'));
    $this->get(route('admin.orders.incomplete.convert', $incomplete))->assertRedirect(route('admin.login'));
});
