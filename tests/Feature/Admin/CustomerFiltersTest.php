<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function orderFor(User $user, int $daysAgo): void
{
    $order = new Order([
        'order_number' => 'ORD-'.$user->id.'-'.$daysAgo,
        'user_id' => $user->id,
        'subtotal' => 100,
        'total' => 100,
        'shipping_address' => ['line1' => 'Test Address', 'name' => 'Customer'],
        'billing_address' => ['line1' => 'Test Address'],
    ]);
    $order->created_at = now()->subDays($daysAgo);
    $order->save();
}

beforeEach(function () {
    $this->admin = createAdmin();

    // Joined long ago, ordered recently: active.
    $this->buyer = User::factory()->create(['name' => 'Rafi Buyer', 'email' => 'rafi@example.com', 'phone' => '01712345678', 'created_at' => now()->subYear()]);
    orderFor($this->buyer, 10);

    // Joined long ago, ordered long ago: inactive.
    $this->lapsed = User::factory()->create(['name' => 'Lamia Lapsed', 'email' => 'lamia@example.com', 'created_at' => now()->subYear()]);
    orderFor($this->lapsed, 200);

    // Joined this week, no order yet: active; email never verified.
    $this->fresh = User::factory()->unverified()->create(['name' => 'Nabil Fresh', 'email' => 'nabil@example.com', 'created_at' => now()->subDays(3)]);

    // Anonymized: deleted.
    $this->gone = User::factory()->create(['name' => 'Gone User', 'email' => 'gone@example.com', 'created_at' => now()->subYear(), 'anonymized_at' => now()]);
});

function listedCustomers(array $query = []): array
{
    $names = [];

    actingAs(test()->admin, 'admin')->get(route('admin.customers.index', $query))
        ->assertInertia(function (Assert $page) use (&$names) {
            $names = collect($page->toArray()['props']['customers']['data'])->pluck('name')->sort()->values()->all();
        });

    return $names;
}

test('the default list shows every customer', function () {
    expect(listedCustomers())->toBe(['Gone User', 'Lamia Lapsed', 'Nabil Fresh', 'Rafi Buyer']);
});

test('status filters split active, inactive and deleted customers', function () {
    expect(listedCustomers(['status' => 'active']))->toBe(['Nabil Fresh', 'Rafi Buyer'])
        ->and(listedCustomers(['status' => 'inactive']))->toBe(['Lamia Lapsed'])
        ->and(listedCustomers(['status' => 'deleted']))->toBe(['Gone User']);
});

test('deleted also includes removed accounts', function () {
    $this->fresh->delete();

    expect(listedCustomers(['status' => 'deleted']))->toBe(['Gone User', 'Nabil Fresh']);
});

test('email verification and order filters narrow the list', function () {
    expect(listedCustomers(['verified' => 'no']))->toBe(['Nabil Fresh'])
        ->and(listedCustomers(['orders' => 'none']))->toBe(['Gone User', 'Nabil Fresh'])
        ->and(listedCustomers(['orders' => 'with']))->toBe(['Lamia Lapsed', 'Rafi Buyer'])
        ->and(listedCustomers(['status' => 'active', 'orders' => 'none']))->toBe(['Nabil Fresh']);
});

test('search matches name, email or phone and still respects the filters', function () {
    expect(listedCustomers(['search' => 'rafi']))->toBe(['Rafi Buyer'])
        ->and(listedCustomers(['search' => '01712345678']))->toBe(['Rafi Buyer'])
        ->and(listedCustomers(['search' => 'example.com', 'status' => 'inactive']))->toBe(['Lamia Lapsed']);
});

test('an unknown filter value is ignored', function () {
    expect(listedCustomers(['status' => 'bogus', 'verified' => 'maybe']))->toHaveCount(4);
});
