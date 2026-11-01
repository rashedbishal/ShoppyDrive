<?php

declare(strict_types=1);

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = createAdmin();
});

/**
 * @param  array<string, mixed>  $overrides
 */
function dashboardOrder(array $overrides = []): Order
{
    // created_at isn't mass-assignable (not in Order::$fillable), so a
    // backdated test order needs a separate forceFill() after creation.
    $createdAt = $overrides['created_at'] ?? null;
    unset($overrides['created_at']);

    $order = Order::create(array_merge([
        'order_number' => 'ORD-DASH-'.uniqid(),
        'status' => 'pending',
        'payment_status' => 'unpaid',
        'payment_method' => 'cod',
        'subtotal' => 500,
        'shipping_cost' => 60,
        'discount' => 0,
        'tax' => 0,
        'total' => 560,
        'shipping_address' => ['name' => 'Test', 'phone' => '01700000000'],
        'billing_address' => [],
    ], $overrides));

    if ($createdAt !== null) {
        $order->forceFill(['created_at' => $createdAt])->save();
    }

    return $order;
}

test('today revenue only counts paid, non-cancelled orders', function () {
    actingAs($this->admin, 'admin');

    dashboardOrder(['total' => 560, 'payment_status' => 'paid', 'status' => 'processing']);
    dashboardOrder(['total' => 1000, 'payment_status' => 'unpaid', 'status' => 'pending']);
    dashboardOrder(['total' => 2000, 'payment_status' => 'paid', 'status' => 'cancelled']);
    dashboardOrder(['total' => 3000, 'payment_status' => 'refunded', 'status' => 'refunded']);

    $this->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('stats.today_revenue', 560));
});

test('yesterday revenue only counts paid, non-cancelled orders', function () {
    actingAs($this->admin, 'admin');

    dashboardOrder(['total' => 700, 'payment_status' => 'paid', 'status' => 'delivered', 'created_at' => now()->subDay()]);
    dashboardOrder(['total' => 5000, 'payment_status' => 'unpaid', 'status' => 'pending', 'created_at' => now()->subDay()]);
    dashboardOrder(['total' => 6000, 'payment_status' => 'paid', 'status' => 'cancelled', 'created_at' => now()->subDay()]);

    $this->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('stats.yesterday_revenue', 700));
});

test('the 7-day revenue chart also excludes unpaid, cancelled and refunded orders', function () {
    actingAs($this->admin, 'admin');

    dashboardOrder(['total' => 560, 'payment_status' => 'paid', 'status' => 'delivered']);
    dashboardOrder(['total' => 9999, 'payment_status' => 'unpaid', 'status' => 'pending']);
    dashboardOrder(['total' => 8888, 'payment_status' => 'paid', 'status' => 'cancelled']);
    dashboardOrder(['total' => 7777, 'payment_status' => 'refunded', 'status' => 'refunded']);

    $this->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('chartData.6.revenue', 560)
            ->where('chartData.6.orders', 1));
});

test('order count stats are unaffected and still count every order', function () {
    actingAs($this->admin, 'admin');

    dashboardOrder(['total' => 560, 'payment_status' => 'paid', 'status' => 'processing']);
    dashboardOrder(['total' => 1000, 'payment_status' => 'unpaid', 'status' => 'pending']);

    $this->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('stats.today_orders', 2));
});
