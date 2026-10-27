<?php

declare(strict_types=1);

use App\Models\Address;
use App\Models\Admin;
use App\Models\InventoryLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = Admin::create([
        'name' => 'Admin User',
        'email' => 'admin-manual-order@example.com',
        'password' => bcrypt('password'),
    ]);
});

function manualOrderPayload(array $overrides = []): array
{
    return array_merge([
        'customer_id' => null,
        'name' => 'Phone Customer',
        'phone' => '01712345678',
        'email' => 'phone-customer@example.com',
        'address_line1' => 'House 1, Road 2',
        'address_line2' => null,
        'city' => 'Inside Dhaka City Corporation',
        'state' => 'Dhaka',
        'postal_code' => '1212',
        'payment_method' => 'cod',
        'shipping_cost' => 60,
        'discount' => 0,
        'notes' => null,
    ], $overrides);
}

test('admin can create a manual order for a guest customer', function () {
    actingAs($this->admin, 'admin');

    $product = Product::factory()->create(['price' => 250, 'stock_quantity' => 10]);

    $response = $this->post(route('admin.orders.store'), manualOrderPayload([
        'items' => [
            ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 2, 'price' => 250],
        ],
    ]));

    $order = Order::query()->firstOrFail();
    $response->assertRedirect(route('admin.orders.show', $order));

    expect($order->user_id)->toBeNull();
    expect((float) $order->subtotal)->toBe(500.0);
    expect((float) $order->shipping_cost)->toBe(60.0);
    expect((float) $order->total)->toBe(560.0);
    expect($order->payment_status->value)->toBe('unpaid');
    expect($order->status->value)->toBe('pending');
    expect($order->items()->count())->toBe(1);
    expect($order->statusHistories()->latest()->first()->notes)->toContain('placed manually');

    expect($product->refresh()->stock_quantity)->toBe(8);
    expect(InventoryLog::where('product_id', $product->id)->where('reference_id', $order->id)->exists())->toBeTrue();
});

test('admin can attach a manual order to an existing customer', function () {
    actingAs($this->admin, 'admin');

    $customer = User::factory()->create();
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 5]);

    $response = $this->post(route('admin.orders.store'), manualOrderPayload([
        'customer_id' => $customer->id,
        'items' => [
            ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1, 'price' => 100],
        ],
    ]));

    $order = Order::query()->firstOrFail();
    $response->assertRedirect(route('admin.orders.show', $order));
    expect($order->user_id)->toBe($customer->id);
});

test('marking a manual order as paid via mobile banking sets payment_status to paid', function () {
    actingAs($this->admin, 'admin');

    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 5]);

    $this->post(route('admin.orders.store'), manualOrderPayload([
        'payment_method' => 'mobile_banking',
        'items' => [
            ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1, 'price' => 100],
        ],
    ]))->assertRedirect();

    $order = Order::query()->firstOrFail();
    expect($order->payment_status->value)->toBe('paid');
    expect($order->payment_method->value)->toBe('mobile_banking');
});

test('a manual order respects stock and variant selection like every other order path', function () {
    actingAs($this->admin, 'admin');

    $product = Product::factory()->create(['stock_quantity' => 0]);
    $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'stock_quantity' => 2]);

    $response = $this->post(route('admin.orders.store'), manualOrderPayload([
        'items' => [
            ['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 5, 'price' => $variant->price],
        ],
    ]));

    $response->assertSessionHasErrors('items');
    expect(Order::query()->count())->toBe(0);
    expect($variant->refresh()->stock_quantity)->toBe(2);
});

test('creating a manual order without any items is rejected', function () {
    actingAs($this->admin, 'admin');

    $response = $this->post(route('admin.orders.store'), manualOrderPayload(['items' => []]));

    $response->assertSessionHasErrors('items');
    expect(Order::query()->count())->toBe(0);
});

test('an invalid phone number is rejected', function () {
    actingAs($this->admin, 'admin');

    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 5]);

    $response = $this->post(route('admin.orders.store'), manualOrderPayload([
        'phone' => '123',
        'items' => [
            ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1, 'price' => 100],
        ],
    ]));

    $response->assertSessionHasErrors('phone');
    expect(Order::query()->count())->toBe(0);
});

test('admin can search customers by name, email or phone', function () {
    actingAs($this->admin, 'admin');

    $customer = User::factory()->create(['name' => 'Jane Caller', 'phone' => '01899998888']);
    Address::create([
        'user_id' => $customer->id,
        'name' => 'Jane Caller',
        'phone' => '01899998888',
        'address_line1' => 'House 9',
        'city' => 'Inside Dhaka City Corporation',
        'state' => 'Dhaka',
        'country' => 'Bangladesh',
        'is_default' => true,
    ]);

    $response = $this->getJson(route('admin.orders.customers.search', ['q' => 'Jane']));

    $response->assertOk();
    $response->assertJsonFragment(['id' => $customer->id, 'name' => 'Jane Caller']);
    $response->assertJsonPath('0.address.city', 'Inside Dhaka City Corporation');
});

test('guests cannot create manual orders', function () {
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 5]);

    $response = $this->post(route('admin.orders.store'), manualOrderPayload([
        'items' => [
            ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1, 'price' => 100],
        ],
    ]));

    $response->assertRedirect(route('admin.login'));
    expect(Order::query()->count())->toBe(0);
});
