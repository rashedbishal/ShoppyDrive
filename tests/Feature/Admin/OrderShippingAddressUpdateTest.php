<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\OrderItem;
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

    $product = Product::factory()->create();

    $this->order = Order::create([
        'order_number' => 'ORD-ADDR-1',
        'status' => 'pending',
        'payment_status' => 'unpaid',
        'payment_method' => 'cod',
        'subtotal' => 500,
        'shipping_cost' => 60,
        'discount' => 0,
        'tax' => 0,
        'total' => 560,
        'shipping_address' => [
            'email' => 'customer@example.com',
            'name' => 'Old Name',
            'phone' => '01700000000',
            'address_line1' => 'Old address',
            'city' => 'Dhaka',
            'state' => 'Dhaka',
            'country' => 'Bangladesh',
        ],
        'billing_address' => [],
    ]);

    OrderItem::create([
        'order_id' => $this->order->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'sku' => $product->sku,
        'price' => 500,
        'quantity' => 1,
        'subtotal' => 500,
        'product_snapshot' => ['id' => $product->id, 'name' => $product->name],
    ]);
});

test('admin can update the shipping address and phone, leaving other fields like email untouched', function () {
    actingAs($this->admin, 'admin');

    $response = $this->patch(route('admin.orders.updateShippingAddress', $this->order->id), [
        'phone' => '01812345678',
        'address_line1' => 'New address line',
        'address_line2' => 'Apt 4',
        'city' => 'Chattogram',
        'state' => 'Chattogram',
        'postal_code' => '4000',
        'country' => 'Bangladesh',
    ]);

    $response->assertRedirect();

    $address = $this->order->refresh()->shipping_address;
    expect($address['phone'])->toBe('01812345678');
    expect($address['address_line1'])->toBe('New address line');
    expect($address['city'])->toBe('Chattogram');
    // Fields outside the edit form (set at checkout) survive the merge.
    expect($address['email'])->toBe('customer@example.com');
});

test('the recipient name cannot be changed through this endpoint, even if submitted', function () {
    actingAs($this->admin, 'admin');

    $response = $this->patch(route('admin.orders.updateShippingAddress', $this->order->id), [
        'name' => 'Attempted New Name',
        'phone' => '01812345678',
        'address_line1' => 'New address line',
        'city' => 'Chattogram',
        'state' => 'Chattogram',
        'country' => 'Bangladesh',
    ]);

    $response->assertRedirect();
    expect($this->order->refresh()->shipping_address['name'])->toBe('Old Name');
});

test('an invalid phone number is rejected and nothing is saved', function () {
    actingAs($this->admin, 'admin');

    $response = $this->patch(route('admin.orders.updateShippingAddress', $this->order->id), [
        'phone' => '123',
        'address_line1' => 'New address line',
        'city' => 'Chattogram',
        'state' => 'Chattogram',
        'country' => 'Bangladesh',
    ]);

    $response->assertInvalid(['phone']);
    expect($this->order->refresh()->shipping_address['address_line1'])->toBe('Old address');
});

test('order items, status and pricing are never touched by the shipping-address endpoint', function () {
    actingAs($this->admin, 'admin');

    $this->patch(route('admin.orders.updateShippingAddress', $this->order->id), [
        'phone' => '01812345678',
        'address_line1' => 'New address line',
        'city' => 'Chattogram',
        'state' => 'Chattogram',
        'country' => 'Bangladesh',
    ]);

    $fresh = $this->order->refresh();
    expect($fresh->status->value)->toBe('pending');
    expect((float) $fresh->total)->toBe(560.0);
    expect($fresh->items)->toHaveCount(1);
});
