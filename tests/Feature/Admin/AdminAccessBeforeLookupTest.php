<?php

declare(strict_types=1);

/*
 * Admin access is checked BEFORE a record is looked up. Otherwise a visitor
 * (or a staff member without that section) could tell whether an order,
 * customer or product ID exists by comparing a 404 ("no such record") with a
 * redirect / 403 ("exists, but you may not see it").
 */

use App\Models\Admin;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

function probeOrder(): Order
{
    return Order::create([
        'order_number' => 'ORD-PROBE-'.uniqid(), 'user_id' => User::factory()->create()->id, 'status' => 'pending',
        'payment_status' => 'unpaid', 'payment_method' => 'cod', 'subtotal' => 100, 'shipping_cost' => 0,
        'discount' => 0, 'tax' => 0, 'total' => 100, 'shipping_address' => ['name' => 'X'], 'billing_address' => [],
    ]);
}

test('a visitor who is not logged in gets the same answer for a record that exists and one that does not', function (string $routeName, callable $existingId) {
    $existing = get(route($routeName, $existingId()));
    $missing = get(route($routeName, 987654));

    $existing->assertRedirect(route('admin.login'));
    $missing->assertRedirect(route('admin.login'));
})->with([
    'order' => ['admin.orders.show', fn () => probeOrder()->id],
    'customer' => ['admin.customers.show', fn () => User::factory()->create()->id],
]);

test('a visitor cannot probe a product edit page either', function () {
    $product = Product::factory()->create();

    get(route('admin.products.edit', $product->id))->assertRedirect(route('admin.login'));
    get(route('admin.products.edit', 987654))->assertRedirect(route('admin.login'));
});

test('a logged-in admin without that section gets 403 whether or not the record exists', function () {
    $staff = Admin::create(['name' => 'No Orders', 'email' => 'noorders@example.com', 'password' => 'password', 'permissions' => ['products' => ['view']]]);
    $order = probeOrder();

    actingAs($staff, 'admin')->get(route('admin.orders.show', $order->id))->assertForbidden();
    actingAs($staff, 'admin')->get(route('admin.orders.show', 987654))->assertForbidden();
});

test('an admin who may see the section still gets 404 for a record that does not exist, and the page for one that does', function () {
    $admin = createAdmin(['email' => 'full-access@example.com']);
    $order = probeOrder();

    actingAs($admin, 'admin')->get(route('admin.orders.show', $order->id))->assertOk();
    actingAs($admin, 'admin')->get(route('admin.orders.show', 987654))->assertNotFound();
});

test('a customer logged in on the storefront gets no hint about admin records either', function () {
    $customer = User::factory()->create();
    $order = probeOrder();

    $existing = actingAs($customer)->get(route('admin.orders.show', $order->id));
    $missing = actingAs($customer)->get(route('admin.orders.show', 987654));

    expect($existing->status())->toBe($missing->status());
});
