<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();
        $weekAgo = Carbon::now()->subDays(7);

        $todayRevenue = Order::whereDate('created_at', $today)->sum('total');
        $yesterdayRevenue = Order::whereDate('created_at', $yesterday)->sum('total');
        $todayOrders = Order::whereDate('created_at', $today)->count();
        $yesterdayOrders = Order::whereDate('created_at', $yesterday)->count();
        $totalCustomers = User::count();
        $newCustomersThisWeek = User::where('created_at', '>=', $weekAgo)->count();
        $pendingOrders = Order::where('status', 'pending')->count();
        $processingOrders = Order::where('status', 'processing')->count();
        $totalProducts = Product::where('is_active', true)->count();
        $lowStockCount = Product::where('is_active', true)
            ->whereColumn('stock_quantity', '<=', 'low_stock_threshold')
            ->count();
        $pendingReviews = Review::where('is_approved', false)->count();

        $revenueChart = Order::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COALESCE(SUM(total), 0) as revenue'),
            DB::raw('COUNT(*) as orders')
        )
            ->where('created_at', '>=', Carbon::now()->subDays(6)->startOfDay())
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $chartData = collect(range(6, 0))->map(function (int $daysAgo) use ($revenueChart): array {
            $date = Carbon::today()->subDays($daysAgo);
            $key = $date->toDateString();
            $row = $revenueChart->get($key);

            return [
                'date' => $key,
                'label' => $date->format('D'),
                'revenue' => $row ? (float) $row->revenue : 0,
                'orders' => $row ? (int) $row->orders : 0,
            ];
        })->values();

        $recentOrders = Order::with('user:id,name,email')
            ->latest()
            ->limit(8)
            ->get(['id', 'order_number', 'user_id', 'total', 'status', 'payment_status', 'created_at']);

        $lowStockProducts = Product::where('is_active', true)
            ->whereColumn('stock_quantity', '<=', 'low_stock_threshold')
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
            ],
            'chartData' => $chartData,
            'recentOrders' => $recentOrders,
            'lowStockProducts' => $lowStockProducts,
        ]);
    }
}
