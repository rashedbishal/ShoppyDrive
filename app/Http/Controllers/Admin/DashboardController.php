<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\DailyVisit;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Services\RefundCalculator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly RefundCalculator $refundCalculator) {}

    public function __invoke(): Response
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
                'today_visitors' => $todayVisitors,
                'yesterday_visitors' => $yesterdayVisitors,
            ],
            'chartData' => $chartData,
            'visitorChartData' => $visitorChartData,
            'recentOrders' => $recentOrders,
            'lowStockProducts' => $lowStockProducts,
        ]);
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
}
