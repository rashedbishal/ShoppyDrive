<?php

declare(strict_types=1);

use App\Console\Commands\DrainQueueCommand;
use App\Models\Order;
use App\Models\OrderRefund;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
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

test('BIZ-008: a partially refunded order\'s revenue is reduced by the amount already refunded', function () {
    actingAs($this->admin, 'admin');

    $order = dashboardOrder(['total' => 1000, 'payment_status' => 'paid', 'status' => 'delivered']);
    OrderRefund::create([
        'order_id' => $order->id,
        'source' => 'return',
        'amount' => 300,
        'method' => 'bKash',
        'previous_order_status' => 'delivered',
        'resulting_order_status' => 'delivered',
        'previous_payment_status' => 'paid',
        'resulting_payment_status' => 'paid',
    ]);

    $this->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.today_revenue', 700)
            ->where('chartData.6.revenue', 700));
});

test('BE-009: an order placed in the Dhaka early morning still counts as today, not the UTC day it falls in', function () {
    // 2026-11-05 04:00 UTC = 2026-11-05 10:00 Asia/Dhaka: unambiguously "today" in Dhaka.
    Carbon::setTestNow('2026-11-05 04:00:00');
    actingAs($this->admin, 'admin');

    // 2026-11-04 21:30 UTC = 2026-11-05 03:30 Asia/Dhaka: Dhaka's early morning
    // "today", but the UTC calendar day before — the bug this guards against.
    dashboardOrder(['total' => 560, 'payment_status' => 'paid', 'status' => 'processing', 'created_at' => '2026-11-04 21:30:00']);

    $this->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.today_revenue', 560)
            ->where('stats.today_orders', 1)
            ->where('stats.yesterday_revenue', 0)
            ->where('stats.yesterday_orders', 0));

    Carbon::setTestNow();
});

test('OPS-002: the dashboard reports when the queue was last drained and the oldest pending job\'s age', function () {
    actingAs($this->admin, 'admin');

    Cache::forever(DrainQueueCommand::LAST_RUN_CACHE_KEY, now()->subMinutes(3)->getTimestamp());

    $this->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.queue_last_drained_seconds_ago', fn ($seconds) => $seconds >= 180)
            ->where('stats.queue_oldest_job_seconds', null));
});

test('OPS-002: the queue heartbeat survives the real file cache store, and a legacy Carbon entry no longer crashes the dashboard', function () {
    // The in-memory test store never serializes; production's file store
    // has cache.serializable_classes disabled, so a cached Carbon object
    // read back as __PHP_Incomplete_Class and 500'd the admin dashboard.
    $directory = sys_get_temp_dir().'/queue-heartbeat-test-'.uniqid();
    config(['cache.default' => 'file', 'cache.stores.file.path' => $directory, 'queue.default' => 'database']);
    Cache::purge('file');
    Process::fake();
    actingAs($this->admin, 'admin');

    try {
        // The value the previous release wrote to production's cache.
        Cache::forever(DrainQueueCommand::LAST_RUN_CACHE_KEY, now());

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('stats.queue_last_drained_seconds_ago', null));

        Artisan::call('app:drain-queue');

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('stats.queue_last_drained_seconds_ago', fn ($seconds) => is_int($seconds) && $seconds >= 0 && $seconds < 60));
    } finally {
        File::deleteDirectory($directory);
    }
});
