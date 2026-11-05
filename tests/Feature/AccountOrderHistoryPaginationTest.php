<?php

declare(strict_types=1);

/*
 * FE-001: My Orders shows 10 orders per page; older ones must be reachable
 * on later pages (the page links were an empty placeholder), and a search
 * must stay applied while paging.
 */

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function historyPageOrder(User $user, string $orderNumber, int $minutesAgo): Order
{
    $address = ['name' => $user->name, 'phone' => '01700000000', 'address_line1' => 'House 1', 'city' => 'Dhaka', 'state' => 'Dhaka', 'country' => 'Bangladesh'];

    $order = Order::create([
        'order_number' => $orderNumber, 'user_id' => $user->id, 'status' => 'pending', 'payment_status' => 'unpaid',
        'payment_method' => 'cod', 'subtotal' => 100, 'shipping_cost' => 0, 'discount' => 0, 'tax' => 0, 'total' => 100,
        'shipping_address' => $address, 'billing_address' => $address,
    ]);
    $order->forceFill(['created_at' => now()->subMinutes($minutesAgo)])->saveQuietly();

    return $order;
}

beforeEach(function () {
    Mail::fake();
    $this->customer = User::factory()->create();

    foreach (range(1, 12) as $n) {
        historyPageOrder($this->customer, sprintf('ORD-PAGE-%02d', $n), 100 - $n);
    }
});

test('the first page shows the 10 newest orders and says there is a second page', function () {
    actingAs($this->customer)->get(route('account.orders'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('orders.data', 10)
            ->where('orders.current_page', 1)
            ->where('orders.last_page', 2)
            ->where('orders.data.0.order_number', 'ORD-PAGE-12')
        );
});

test('the oldest orders are reachable on page 2', function () {
    actingAs($this->customer)->get(route('account.orders', ['page' => 2]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('orders.data', 2)
            ->where('orders.current_page', 2)
            ->where('orders.data.1.order_number', 'ORD-PAGE-01')
        );
});

test('a search stays applied in the page links', function () {
    actingAs($this->customer)->get(route('account.orders', ['search' => 'ORD-PAGE']))
        ->assertInertia(fn ($page) => $page
            ->where('orders.last_page', 2)
            ->where('orders.next_page_url', fn (string $url) => str_contains($url, 'search=ORD-PAGE') && str_contains($url, 'page=2'))
        );
});
