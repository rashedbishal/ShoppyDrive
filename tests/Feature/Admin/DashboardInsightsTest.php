<?php

declare(strict_types=1);

use App\Models\DailyVisit;
use App\Models\Order;
use App\Models\OrderRefund;
use App\Models\Product;
use App\Models\Review;
use App\Models\SearchLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\Dashboard\DashboardAnalytics;
use App\Support\Dashboard\DashboardRange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * Owner decisions 2026-11-14: the dashboard redesign adds range-based
 * panels, each sent only to admins who can open that area (the mother sees
 * everything); the original cards are unchanged; no cost figures; heavy
 * panels cached 5 minutes; a mother-only monthly target.
 */

/**
 * @param  array<string, mixed>  $overrides
 */
function insightsOrder(array $overrides = []): Order
{
    $createdAt = $overrides['created_at'] ?? null;
    unset($overrides['created_at']);

    $order = Order::create(array_merge([
        'order_number' => 'ORD-INS-'.uniqid(),
        'status' => 'pending',
        'payment_status' => 'unpaid',
        'payment_method' => 'cod',
        'subtotal' => 500,
        'shipping_cost' => 60,
        'discount' => 0,
        'tax' => 0,
        'total' => 560,
        'shipping_address' => ['name' => 'Test', 'phone' => '01700000000', 'state' => 'Dhaka'],
        'billing_address' => [],
    ], $overrides));

    if ($createdAt !== null) {
        $order->forceFill(['created_at' => $createdAt])->save();
    }

    return $order;
}

/**
 * @param  array<string, string>  $query
 */
function insightsRange(array $query = []): DashboardRange
{
    return DashboardRange::fromRequest(Request::create('/admin/dashboard', 'GET', $query));
}

afterEach(fn () => Carbon::setTestNow());

test('revenue KPIs use collected money net of refunds, compared with the previous period', function () {
    insightsOrder(['total' => 1000, 'payment_status' => 'paid', 'status' => 'delivered', 'created_at' => now()->subDays(2)]);
    $partlyRefunded = insightsOrder(['total' => 500, 'payment_status' => 'paid', 'status' => 'delivered', 'created_at' => now()->subDays(3)]);
    OrderRefund::factory()->create(['order_id' => $partlyRefunded->id, 'amount' => 100]);
    insightsOrder(['total' => 9000, 'payment_status' => 'unpaid', 'status' => 'pending', 'created_at' => now()->subDays(1)]);
    insightsOrder(['total' => 7000, 'payment_status' => 'paid', 'status' => 'cancelled', 'created_at' => now()->subDays(1)]);
    // Previous 7-day period.
    insightsOrder(['total' => 300, 'payment_status' => 'paid', 'status' => 'delivered', 'created_at' => now()->subDays(10)]);
    DailyVisit::create(['date' => now('Asia/Dhaka')->subDay()->toDateString(), 'visitor_count' => 40, 'page_view_count' => 90]);

    $overview = app(DashboardAnalytics::class)->overview(insightsRange(['range' => '7d']), true);

    expect($overview['kpis']['revenue'])->toBe(['value' => 1400.0, 'previous' => 300.0])
        ->and($overview['kpis']['orders'])->toBe(['value' => 4, 'previous' => 1])
        ->and($overview['kpis']['average_order_value']['value'])->toBe(700.0)
        ->and($overview['kpis']['visitors']['value'])->toBe(40)
        ->and($overview['kpis']['conversion_rate']['value'])->toBe(10.0)
        ->and($overview['trend'])->toHaveCount(7)
        ->and(collect($overview['trend'])->sum('revenue'))->toEqual(1400)
        ->and(collect($overview['trend'])->sum('previous_revenue'))->toEqual(300);
});

test('the mother admin gets every panel, and each one loads without errors', function () {
    $product = Product::factory()->create(['name' => 'Hoodie']);
    $customer = User::factory()->create();
    $order = insightsOrder(['user_id' => $customer->id, 'total' => 800, 'payment_status' => 'paid', 'status' => 'delivered']);
    // Order::booted() stamps the visitor's traffic cookie on create.
    $order->forceFill(['traffic_source' => 'facebook'])->save();
    $order->items()->create(['product_id' => $product->id, 'product_name' => 'Hoodie', 'price' => 800, 'quantity' => 1, 'subtotal' => 800, 'product_snapshot' => []]);
    Review::create(['product_id' => $product->id, 'user_id' => $customer->id, 'rating' => 4, 'body' => 'Nice', 'is_approved' => true]);
    SearchLog::record('hoodie', 3);
    SearchLog::record('hoodie', 3);
    SearchLog::record('raincoat', 0);

    actingAs(createMotherAdmin(), 'admin');

    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/dashboard')
            ->where('access.edit_target', true)
            ->where('liveFeed.orders.0.order_number', $order->order_number)
            ->missing('overview')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('overview.kpis.revenue.value', 800)
                ->where('actionNeeded.pending_orders', 0)
                ->where('orderHealth.statuses.0.status', 'delivered')
                ->has('couriers', 2)
                ->where('productInsights.top_products.0.name', 'Hoodie')
                ->has('promotions')
                ->where('customerInsights.top_customers.0.spent', 800)
                ->where('customerInsights.districts.0.district', 'Dhaka')
                ->where('marketing.traffic_sources.0.source', 'facebook')
                ->where('marketing.top_searches.0.term', 'hoodie')
                ->where('marketing.no_result_searches.0.term', 'raincoat')
                ->where('reviewInsights.average', 4)));
});

test('an admin without Orders access gets no sales panels, and the original cards are unchanged', function () {
    actingAs(createAdmin(['permissions' => ['products' => ['view']]]), 'admin');
    insightsOrder(['total' => 560, 'payment_status' => 'paid', 'status' => 'processing']);

    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.today_revenue', 560)
            ->where('overview', null)
            ->where('orderHealth', null)
            ->where('couriers', null)
            ->where('customerInsights', null)
            ->where('reviewInsights', null)
            ->where('liveFeed', null)
            ->where('access.orders', false)
            ->where('access.edit_target', false)
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('actionNeeded.pending_orders', null)
                ->where('actionNeeded.out_of_stock', 0)
                ->where('marketing.traffic_sources', null)
                ->where('marketing.heatmap', null)
                ->has('marketing.devices')
                ->has('productInsights.top_products')));

    $this->getJson(route('admin.dashboard.live'))->assertForbidden();
});

test('the date range accepts presets and safe custom ranges only', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-14 10:00', 'Asia/Dhaka'));

    expect(insightsRange()->toArray())->toMatchArray(['preset' => '30d', 'from' => '2026-10-16', 'to' => '2026-11-14'])
        ->and(insightsRange(['range' => 'today'])->bucketUnit())->toBe('hour')
        ->and(insightsRange(['range' => 'month'])->toArray()['from'])->toBe('2026-11-01')
        ->and(insightsRange(['range' => '7d'])->previous()->toArray())->toMatchArray(['from' => '2026-11-01', 'to' => '2026-11-07'])
        // Reversed dates are swapped; the future is cut at today.
        ->and(insightsRange(['range' => 'custom', 'from' => '2026-12-31', 'to' => '2026-11-01'])->toArray())
        ->toMatchArray(['from' => '2026-11-01', 'to' => '2026-11-14'])
        // Over a year is shortened to one.
        ->and(insightsRange(['range' => 'custom', 'from' => '2020-01-01', 'to' => '2026-11-14'])->days())->toBe(DashboardRange::MAX_DAYS)
        ->and(insightsRange(['range' => 'custom', 'from' => '2020-01-01', 'to' => '2026-11-14'])->bucketUnit())->toBe('week')
        // Junk falls back to the default.
        ->and(insightsRange(['range' => 'custom', 'from' => '2026-02-30', 'to' => 'x'])->preset)->toBe('30d')
        ->and(insightsRange(['range' => 'forever'])->preset)->toBe('30d');
});

test('the live feed returns the newest orders to admins with Orders access', function () {
    insightsOrder(['order_number' => 'ORD-OLDER']);
    insightsOrder(['order_number' => 'ORD-NEWEST', 'shipping_address' => ['name' => 'Rahim', 'state' => 'Sylhet']]);
    actingAs(createAdmin(), 'admin');

    $this->getJson(route('admin.dashboard.live'))
        ->assertOk()
        ->assertJsonPath('orders.0.order_number', 'ORD-NEWEST')
        ->assertJsonPath('orders.0.customer', 'Rahim')
        ->assertJsonPath('orders.0.district', 'Sylhet')
        ->assertJsonPath('today_orders', 2);
});

test('only the mother admin can set the monthly target', function () {
    actingAs(createAdmin(), 'admin');
    $this->put(route('admin.dashboard.target'), ['target' => 500000])->assertForbidden();
    expect(DashboardAnalytics::monthlyTarget())->toBeNull();

    actingAs(createMotherAdmin(), 'admin');
    $this->put(route('admin.dashboard.target'), ['target' => 500000])->assertSessionHasNoErrors();
    expect(DashboardAnalytics::monthlyTarget())->toBe(500000.0);

    $this->put(route('admin.dashboard.target'), ['target' => 'lots'])->assertSessionHasErrors('target');
    expect(DashboardAnalytics::monthlyTarget())->toBe(500000.0);

    $this->put(route('admin.dashboard.target'), ['target' => null])->assertSessionHasNoErrors();
    expect(Setting::where('key', DashboardAnalytics::TARGET_KEY)->exists())->toBeFalse();
});

test('a typed search is counted once per search, and refinements, prefetches and bots are not', function () {
    Product::factory()->create(['name' => 'Blue Hoodie']);

    $this->get('/search?q=Hoodie')->assertOk();
    $this->get('/search?q=hoodie')->assertOk();
    $this->get('/search?q=hoodie&sort=price_asc')->assertOk();
    $this->get('/search?q=hoodie&page=2')->assertOk();
    $this->get('/search?q=hoodie', ['Purpose' => 'prefetch'])->assertOk();
    $this->get('/search?q=hoodie', ['User-Agent' => 'Googlebot/2.1'])->assertOk();
    $this->get('/search?q=zzzznothing', ['User-Agent' => 'Mozilla/5.0'])->assertOk();

    expect(SearchLog::where('term', 'hoodie')->value('searches'))->toBe(2)
        ->and(SearchLog::where('term', 'zzzznothing')->value('results_count'))->toBe(0)
        ->and(SearchLog::count())->toBe(2);
});

test('a new visitor is counted by device type', function (string $userAgent, string $column) {
    $this->withHeader('User-Agent', $userAgent)->get('/')->assertOk();

    $visit = DailyVisit::first();

    expect($visit->visitor_count)->toBe(1)
        ->and($visit->{$column})->toBe(1)
        ->and($visit->mobile_visitors + $visit->tablet_visitors + $visit->desktop_visitors)->toBe(1);
})->with([
    'android phone' => ['Mozilla/5.0 (Linux; Android 14; SM-A146) AppleWebKit/537.36 Chrome/120 Mobile Safari/537.36', 'mobile_visitors'],
    'iphone' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile/15E148', 'mobile_visitors'],
    'android tablet' => ['Mozilla/5.0 (Linux; Android 13; SM-X200) AppleWebKit/537.36 Chrome/120 Safari/537.36', 'tablet_visitors'],
    'ipad' => ['Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) Mobile/15E148', 'tablet_visitors'],
    'desktop' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120 Safari/537.36', 'desktop_visitors'],
]);
