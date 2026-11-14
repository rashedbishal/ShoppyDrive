<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Console\Commands\DrainQueueCommand;
use App\Console\Commands\SyncPathaoOrderStatuses;
use App\Console\Commands\SyncSteadfastOrderStatuses;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\DailyVisit;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Services\Dashboard\DashboardAnalytics;
use App\Services\RefundCalculator;
use App\Support\Dashboard\DashboardRange;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly RefundCalculator $refundCalculator,
        private readonly DashboardAnalytics $analytics,
    ) {}

    public function __invoke(Request $request): Response
    {
        // BE-09: "today" means the Dhaka calendar day, not the UTC one the
        // server clock runs on — a 3am Dhaka order is still "today" here,
        // matching the invoice date and the TrackDailyVisit day boundary.
        $dhakaToday = Carbon::now('Asia/Dhaka')->startOfDay();
        $dhakaYesterday = $dhakaToday->copy()->subDay();
        $weekAgo = Carbon::now()->subDays(7);

        $todayRevenue = $this->netRevenue($this->collectedRevenueQuery()->whereBetween('created_at', $this->utcDayRange($dhakaToday)));
        $yesterdayRevenue = $this->netRevenue($this->collectedRevenueQuery()->whereBetween('created_at', $this->utcDayRange($dhakaYesterday)));
        $todayOrders = Order::whereBetween('created_at', $this->utcDayRange($dhakaToday))->count();
        $yesterdayOrders = Order::whereBetween('created_at', $this->utcDayRange($dhakaYesterday))->count();
        $totalCustomers = User::count();
        $newCustomersThisWeek = User::where('created_at', '>=', $weekAgo)->count();
        $pendingOrders = Order::where('status', 'pending')->count();
        $processingOrders = Order::where('status', 'processing')->count();
        $totalProducts = Product::where('is_active', true)->count();
        $lowStockCount = Product::where('is_active', true)->lowStock()->count();
        $pendingReviews = Review::where('is_approved', false)->count();
        $todayVisitors = (int) DailyVisit::where('date', $dhakaToday->toDateString())->value('visitor_count');
        $yesterdayVisitors = (int) DailyVisit::where('date', $dhakaYesterday->toDateString())->value('visitor_count');

        // Grouped in PHP (not DB::raw('DATE(created_at)')) so the grouping
        // key is the Dhaka calendar day, not whatever day the DB engine
        // thinks the stored UTC timestamp falls on.
        $revenueRows = $this->collectedRevenueQuery()
            ->where('created_at', '>=', $dhakaToday->copy()->subDays(6)->utc())
            ->get(['id', 'created_at', 'total']);
        $refundedByOrder = $this->refundedAmountsFor($revenueRows->pluck('id'));
        $revenueByDate = $revenueRows->groupBy(fn (Order $order) => $order->created_at->copy()->timezone('Asia/Dhaka')->toDateString());

        $chartData = collect(range(6, 0))->map(function (int $daysAgo) use ($dhakaToday, $revenueByDate, $refundedByOrder): array {
            $date = $dhakaToday->copy()->subDays($daysAgo);
            $key = $date->toDateString();
            $rows = $revenueByDate->get($key);

            return [
                'date' => $key,
                'label' => $date->format('D'),
                'revenue' => $rows ? (float) $rows->sum(fn (Order $order) => (float) $order->total - ($refundedByOrder[$order->id] ?? 0.0)) : 0,
                'orders' => $rows ? $rows->count() : 0,
            ];
        })->values();

        $visitChart = DailyVisit::where('date', '>=', $dhakaToday->copy()->subDays(6)->toDateString())
            ->get()
            ->keyBy(fn (DailyVisit $visit) => $visit->date->toDateString());

        $visitorChartData = collect(range(6, 0))->map(function (int $daysAgo) use ($dhakaToday, $visitChart): array {
            $date = $dhakaToday->copy()->subDays($daysAgo);
            $key = $date->toDateString();
            $row = $visitChart->get($key);

            return [
                'date' => $key,
                'label' => $date->format('D'),
                'visitors' => $row ? (int) $row->visitor_count : 0,
                'page_views' => $row ? (int) $row->page_view_count : 0,
            ];
        })->values();

        $recentOrders = Order::with('user:id,name,email')
            ->latest()
            ->limit(8)
            ->get(['id', 'order_number', 'user_id', 'total', 'status', 'payment_status', 'created_at']);

        $lowStockProducts = Product::where('is_active', true)
            ->lowStock()
            ->orderBy('stock_quantity')
            ->limit(6)
            ->get(['id', 'name', 'sku', 'stock_quantity', 'low_stock_threshold']);

        return Inertia::render('admin/dashboard', [
            'stats' => [
                'today_revenue' => (float) $todayRevenue,
                'yesterday_revenue' => (float) $yesterdayRevenue,
                'today_orders' => $todayOrders,
                'yesterday_orders' => $yesterdayOrders,
                'total_customers' => $totalCustomers,
                'new_customers_this_week' => $newCustomersThisWeek,
                'pending_orders' => $pendingOrders,
                'processing_orders' => $processingOrders,
                'total_products' => $totalProducts,
                'low_stock_count' => $lowStockCount,
                'pending_reviews' => $pendingReviews,
                // Null hides the alert from admins who can't open the page.
                'failed_jobs' => Auth::guard('admin')->user()->canAccess('failed_jobs')
                    ? DB::connection(config('queue.failed.database'))->table(config('queue.failed.table'))->count()
                    : null,
                // OPS-002: a stalled cron (so the queue never drains) creates
                // no failed_jobs rows at all — nothing above would show it.
                'queue_last_drained_seconds_ago' => Auth::guard('admin')->user()->canAccess('failed_jobs')
                    ? DrainQueueCommand::lastDrainedSecondsAgo()
                    : null,
                'queue_oldest_job_seconds' => Auth::guard('admin')->user()->canAccess('failed_jobs')
                    ? DrainQueueCommand::oldestPendingJobAgeSeconds()
                    : null,
                // BE-011: every Pathao status check is failing (outage or bad
                // credentials), so courier statuses have stopped updating.
                'pathao_sync_problem' => Auth::guard('admin')->user()->canAccess('orders')
                    ? self::courierSyncProblem(SyncPathaoOrderStatuses::currentProblem())
                    : null,
                'steadfast_sync_problem' => Auth::guard('admin')->user()->canAccess('orders')
                    ? self::courierSyncProblem(SyncSteadfastOrderStatuses::currentProblem())
                    : null,
                'today_visitors' => $todayVisitors,
                'yesterday_visitors' => $yesterdayVisitors,
            ],
            'chartData' => $chartData,
            'visitorChartData' => $visitorChartData,
            'recentOrders' => $recentOrders,
            'lowStockProducts' => $lowStockProducts,
            ...$this->insights($request),
        ]);
    }

    /**
     * The 2026-11-14 dashboard additions. Each panel is sent only to admins
     * who can open the area it reports on (owner decision; the mother admin
     * sees everything) and loads just after the page, so the dashboard
     * itself opens as fast as before. The original props above are
     * unchanged and still shown to every admin.
     *
     * @return array<string, mixed>
     */
    private function insights(Request $request): array
    {
        $admin = Auth::guard('admin')->user();
        $can = fn (string $section): bool => $admin->canAccess($section);
        $range = DashboardRange::fromRequest($request);
        $orders = $can('orders');

        return [
            'range' => $range->toArray(),
            'access' => [
                'orders' => $orders,
                'products' => $can('products'),
                'customers' => $can('customers'),
                'reviews' => $can('reviews'),
                'promotions' => $can('flash_sales') || $can('bundles') || $can('coupons'),
                'edit_target' => (bool) $admin->is_mother,
            ],
            'overview' => $orders ? Inertia::defer(fn (): array => $this->analytics->overview($range, $can('customers')), 'overview') : null,
            'actionNeeded' => Inertia::defer(fn (): array => $this->analytics->actionNeeded($can), 'overview'),
            'orderHealth' => $orders ? Inertia::defer(fn (): array => $this->analytics->orderHealth($range), 'insights') : null,
            'couriers' => $orders ? Inertia::defer(fn (): array => $this->analytics->couriers($range), 'insights') : null,
            'productInsights' => $can('products')
                ? Inertia::defer(fn (): array => $this->analytics->products($range, $can('stock')), 'insights')
                : null,
            'promotions' => $can('flash_sales') || $can('bundles') || $can('coupons')
                ? Inertia::defer(fn (): array => $this->analytics->promotions($range, $can), 'insights')
                : null,
            'customerInsights' => $can('customers') && $orders
                ? Inertia::defer(fn (): array => $this->analytics->customers($range, $can), 'people')
                : null,
            'marketing' => Inertia::defer(fn (): array => $this->analytics->marketing($range, $can), 'people'),
            'reviewInsights' => $can('reviews') ? Inertia::defer(fn (): array => $this->analytics->reviews($range), 'people') : null,
            'liveFeed' => $orders ? $this->analytics->liveOrders() : null,
        ];
    }

    /** The live order feed the dashboard polls every few seconds. */
    public function live(): JsonResponse
    {
        return response()->json($this->analytics->liveOrders());
    }

    /** Mother admin only: the monthly revenue target the dashboard ring tracks. */
    public function updateTarget(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'target' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
        ], [
            'target.numeric' => 'Enter the target as a number, like 500000.',
            'target.max' => 'That target is too large.',
        ]);

        DashboardAnalytics::saveMonthlyTarget(isset($validated['target']) ? (float) $validated['target'] : null);

        return back()->with('success', isset($validated['target']) && (float) $validated['target'] > 0
            ? 'Monthly target saved.'
            : 'Monthly target removed.');
    }

    /**
     * LOW-08: revenue stats must reflect money actually collected, not every
     * order placed. Excludes unpaid orders and cancelled orders. A fully
     * refunded order is excluded too, because RefundService now flips
     * payment_status off PAID once the refundable cap is reached (BIZ-008);
     * a partially refunded order stays PAID, so netRevenue() still has to
     * subtract its ledger refunds itself.
     */
    private function collectedRevenueQuery(): Builder
    {
        return Order::where('payment_status', PaymentStatus::PAID)
            ->where('status', '!=', OrderStatus::CANCELLED);
    }

    /**
     * BIZ-008: sum(total) over-counts a partially refunded order by the
     * amount already paid back, even though its payment_status is still
     * PAID (correctly — the refund hasn't reached the cap yet).
     */
    private function netRevenue(Builder $query): float
    {
        $orders = $query->get(['id', 'total']);
        $refundedByOrder = $this->refundedAmountsFor($orders->pluck('id'));

        return (float) $orders->sum(fn (Order $order) => (float) $order->total - ($refundedByOrder[$order->id] ?? 0.0));
    }

    /**
     * @param  Collection<int, int>  $orderIds
     * @return array<int, float>
     */
    private function refundedAmountsFor(Collection $orderIds): array
    {
        return $this->refundCalculator->refundedAmountsFor($orderIds->all());
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function utcDayRange(Carbon $dhakaMidnight): array
    {
        return [$dhakaMidnight->copy()->utc(), $dhakaMidnight->copy()->addDay()->utc()->subSecond()];
    }

    /**
     * @param  array{since: int, last_failed_at: int, last_error: string}|null  $problem
     * @return array{failing_for_seconds: int, last_error: string}|null
     */
    private static function courierSyncProblem(?array $problem): ?array
    {
        return $problem === null ? null : [
            'failing_for_seconds' => max(0, now()->getTimestamp() - $problem['since']),
            'last_error' => (string) ($problem['last_error'] ?? ''),
        ];
    }
}
