<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Enums\CourierSubmissionState;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PointTransactionType;
use App\Enums\ReturnStatus;
use App\Models\CouponUse;
use App\Models\CourierSubmission;
use App\Models\DailyVisit;
use App\Models\FlashSale;
use App\Models\IncompleteOrder;
use App\Models\InventoryLog;
use App\Models\NewsletterSubscriber;
use App\Models\Order;
use App\Models\Product;
use App\Models\ReferralPointTransaction;
use App\Models\ReturnRequest;
use App\Models\Review;
use App\Models\SearchLog;
use App\Models\Setting;
use App\Models\User;
use App\Models\Wishlist;
use App\Services\RefundCalculator;
use App\Support\Dashboard\DashboardRange;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Every number on the admin dashboard beyond the original cards. Read-only:
 * nothing here writes to the database, except the monthly target setter.
 *
 * Revenue follows the same rule as the original dashboard cards (LOW-08,
 * BIZ-008): money actually collected — paid, not cancelled — minus the
 * order_refunds ledger. "Orders" counts every order placed, like the
 * original "Today's Orders" card. No cost-price figures, by owner decision.
 *
 * Heavy panels are cached for CACHE_SECONDS per range (owner decision
 * 2026-11-14); "needs action" and the live feed are always current.
 */
class DashboardAnalytics
{
    public const CACHE_SECONDS = 300;

    public const TARGET_KEY = 'dashboard_monthly_target';

    /** Products with no sale in this many days count as slow movers. */
    public const SLOW_MOVER_DAYS = 60;

    /** @var array<string, Collection<int, object>> */
    private array $ordersMemo = [];

    public function __construct(private readonly RefundCalculator $refundCalculator) {}

    // ── Overview: KPIs, trend, today so far, monthly target ────────────────

    /**
     * @return array<string, mixed>
     */
    public function overview(DashboardRange $range, bool $includeCustomers): array
    {
        $current = $this->ordersIn($range);
        $previous = $this->ordersIn($range->previous());
        $visits = $this->visitsIn($range);
        $previousVisits = $this->visitsIn($range->previous());

        $revenue = $this->netRevenue($current);
        $previousRevenue = $this->netRevenue($previous);
        $paidCount = $this->collected($current)->count();
        $previousPaidCount = $this->collected($previous)->count();
        $visitors = (int) $visits->sum('visitor_count');
        $previousVisitors = (int) $previousVisits->sum('visitor_count');

        $newCustomers = $includeCustomers ? $this->usersCreatedIn($range) : null;
        $previousNewCustomers = $includeCustomers ? $this->usersCreatedIn($range->previous()) : null;

        return [
            'kpis' => [
                'revenue' => ['value' => $revenue, 'previous' => $previousRevenue],
                'orders' => ['value' => $current->count(), 'previous' => $previous->count()],
                'average_order_value' => [
                    'value' => $paidCount > 0 ? round($revenue / $paidCount, 2) : 0.0,
                    'previous' => $previousPaidCount > 0 ? round($previousRevenue / $previousPaidCount, 2) : 0.0,
                ],
                'visitors' => ['value' => $visitors, 'previous' => $previousVisitors],
                'conversion_rate' => [
                    'value' => $this->percent($current->count(), $visitors),
                    'previous' => $this->percent($previous->count(), $previousVisitors),
                ],
                'new_customers' => $includeCustomers ? ['value' => $newCustomers, 'previous' => $previousNewCustomers] : null,
            ],
            'trend' => $this->trend($range, $current, $previous, $visits, $includeCustomers),
            'today' => $this->todaySoFar(),
            'target' => $this->target(),
        ];
    }

    /**
     * One point per bucket, with the previous period's same-position value.
     *
     * @param  Collection<int, object>  $current
     * @param  Collection<int, object>  $previous
     * @param  Collection<int, DailyVisit>  $visits
     * @return list<array<string, mixed>>
     */
    private function trend(DashboardRange $range, Collection $current, Collection $previous, Collection $visits, bool $includeCustomers): array
    {
        $previousRange = $range->previous();
        $refunds = $this->refundsFor($current->merge($previous));

        $sumBy = function (Collection $orders, DashboardRange $bucketRange) use ($refunds): array {
            $revenue = [];
            $counts = [];

            foreach ($orders as $order) {
                $key = $bucketRange->bucketKey($this->dhaka($order->created_at));
                $counts[$key] = ($counts[$key] ?? 0) + 1;

                if ($this->isCollected($order)) {
                    $revenue[$key] = ($revenue[$key] ?? 0.0) + (float) $order->total - ($refunds[$order->id] ?? 0.0);
                }
            }

            return [$revenue, $counts];
        };

        [$revenue, $orders] = $sumBy($current, $range);
        [$previousRevenue, $previousOrders] = $sumBy($previous, $previousRange);

        $visitorsByKey = [];

        foreach ($visits as $visit) {
            $key = $range->bucketKey($visit->date->copy()->setTimezone(DashboardRange::TIMEZONE)->startOfDay());
            $visitorsByKey[$key] = ($visitorsByKey[$key] ?? 0) + $visit->visitor_count;
        }

        $customersByKey = [];

        if ($includeCustomers) {
            User::query()->whereBetween('created_at', $range->utc())->pluck('created_at')
                ->each(function ($createdAt) use ($range, &$customersByKey): void {
                    $key = $range->bucketKey($this->dhaka($createdAt));
                    $customersByKey[$key] = ($customersByKey[$key] ?? 0) + 1;
                });
        }

        $previousBuckets = $previousRange->buckets();

        return collect($range->buckets())->values()->map(function (array $bucket, int $index) use ($revenue, $orders, $previousRevenue, $previousOrders, $previousBuckets, $visitorsByKey, $customersByKey, $range): array {
            $previousKey = $previousBuckets[$index]['key'] ?? null;

            return [
                'key' => $bucket['key'],
                'label' => $bucket['label'],
                'revenue' => round($revenue[$bucket['key']] ?? 0.0, 2),
                'orders' => $orders[$bucket['key']] ?? 0,
                'previous_revenue' => $previousKey === null ? 0.0 : round($previousRevenue[$previousKey] ?? 0.0, 2),
                'previous_orders' => $previousKey === null ? 0 : ($previousOrders[$previousKey] ?? 0),
                // Visits are only counted per day, so a single-day range has no hourly split.
                'visitors' => $range->bucketUnit() === 'hour' ? null : ($visitorsByKey[$bucket['key']] ?? 0),
                'new_customers' => $customersByKey[$bucket['key']] ?? 0,
            ];
        })->all();
    }

    /**
     * Today up to now against yesterday up to the same clock time.
     *
     * @return array{revenue: float, orders: int, yesterday_revenue: float, yesterday_orders: int, as_of: string}
     */
    private function todaySoFar(): array
    {
        $now = Carbon::now(DashboardRange::TIMEZONE);
        $todayStart = $now->copy()->startOfDay();
        $yesterdayStart = $todayStart->copy()->subDay();
        $yesterdaySameTime = $now->copy()->subDay();

        $today = $this->ordersBetween($todayStart, $now);
        $yesterday = $this->ordersBetween($yesterdayStart, $yesterdaySameTime);

        return [
            'revenue' => $this->netRevenue($today),
            'orders' => $today->count(),
            'yesterday_revenue' => $this->netRevenue($yesterday),
            'yesterday_orders' => $yesterday->count(),
            'as_of' => $now->format('g:i A'),
        ];
    }

    /**
     * @return array{target: float|null, month_revenue: float, projected: float, day_of_month: int, days_in_month: int, month_label: string}
     */
    public function target(): array
    {
        $now = Carbon::now(DashboardRange::TIMEZONE);
        $monthRevenue = $this->netRevenue($this->ordersBetween($now->copy()->startOfMonth(), $now));
        $dayOfMonth = (int) $now->day;
        $daysInMonth = (int) $now->daysInMonth;

        return [
            'target' => self::monthlyTarget(),
            'month_revenue' => $monthRevenue,
            'projected' => round($monthRevenue / max($dayOfMonth, 1) * $daysInMonth, 2),
            'day_of_month' => $dayOfMonth,
            'days_in_month' => $daysInMonth,
            'month_label' => $now->format('F Y'),
        ];
    }

    public static function monthlyTarget(): ?float
    {
        $value = Setting::where('key', self::TARGET_KEY)->value('value');

        return is_numeric($value) && (float) $value > 0 ? (float) $value : null;
    }

    public static function saveMonthlyTarget(?float $target): void
    {
        if ($target === null || $target <= 0) {
            Setting::where('key', self::TARGET_KEY)->first()?->delete();

            return;
        }

        Setting::updateOrCreate(['key' => self::TARGET_KEY], ['value' => round($target, 2), 'group' => 'general']);
    }

    // ── Needs action now (never cached) ─────────────────────────────────────

    /**
     * Each entry is null when the admin can't open the page it links to.
     *
     * @param  callable(string): bool  $can
     * @return array<string, mixed>
     */
    public function actionNeeded(callable $can): array
    {
        $orders = $can('orders');
        $codWithCourier = $orders
            ? Order::query()
                ->where('payment_method', PaymentMethod::COD)
                ->where('payment_status', '!=', PaymentStatus::PAID)
                ->whereIn('status', [OrderStatus::PROCESSING, OrderStatus::SHIPPED])
                ->where(fn (Builder $query) => $query->whereNotNull('pathao_consignment_id')->orWhereNotNull('steadfast_consignment_id'))
                ->selectRaw('COUNT(*) as orders, COALESCE(SUM(total), 0) as amount')
                ->first()
            : null;

        return [
            'pending_orders' => $orders ? Order::where('status', OrderStatus::PENDING)->count() : null,
            'processing_not_sent' => $orders ? Order::where('status', OrderStatus::PROCESSING)
                ->whereNull('pathao_consignment_id')
                ->whereNull('steadfast_consignment_id')
                ->count() : null,
            'mobile_payments_to_check' => $orders ? Order::where('payment_method', PaymentMethod::MobileBanking)
                ->where('payment_status', PaymentStatus::PENDING)
                ->where('status', '!=', OrderStatus::CANCELLED)
                ->count() : null,
            'courier_unknown' => $orders ? CourierSubmission::whereIn('state', [CourierSubmissionState::UNKNOWN, CourierSubmissionState::SUBMITTING])
                ->whereNull('resolved_at')
                ->count() : null,
            'cod_with_courier' => $codWithCourier === null ? null : [
                'orders' => (int) $codWithCourier->orders,
                'amount' => (float) $codWithCourier->amount,
            ],
            'pending_returns' => $can('returns') ? ReturnRequest::where('status', ReturnStatus::PENDING)->count() : null,
            'pending_reviews' => $can('reviews') ? Review::where('is_approved', false)->count() : null,
            'out_of_stock' => $can('products') ? $this->outOfStockQuery()->count() : null,
            'low_stock' => $can('products') ? Product::where('is_active', true)->lowStock()->count() : null,
            'abandoned_checkouts' => $can('incomplete_orders')
                ? IncompleteOrder::where('created_at', '>=', now()->subDays(7))->count()
                : null,
        ];
    }

    // ── Order health ────────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    public function orderHealth(DashboardRange $range): array
    {
        return $this->cached('order-health', $range, function () use ($range): array {
            $orders = $this->ordersIn($range);
            $visitors = (int) $this->visitsIn($range)->sum('visitor_count');
            $placed = $orders->count();
            $cancelled = $orders->where('status', OrderStatus::CANCELLED->value)->count();
            $delivered = $orders->where('status', OrderStatus::DELIVERED->value)->count();
            $sentToCourier = $orders->filter(fn (object $order): bool => $order->pathao_consignment_id !== null || $order->steadfast_consignment_id !== null)->count();
            $returned = ReturnRequest::whereIn('order_id', $orders->where('status', OrderStatus::DELIVERED->value)->pluck('id')->take(5000))
                ->distinct()
                ->count('order_id');
            $refunds = $this->refundCalculator->refundTotalsBetween(...$range->utc());
            $notCancelled = $orders->where('status', '!=', OrderStatus::CANCELLED->value);

            return [
                'statuses' => collect(OrderStatus::cases())
                    ->map(fn (OrderStatus $status): array => [
                        'status' => $status->value,
                        'count' => $orders->where('status', $status->value)->count(),
                    ])
                    ->filter(fn (array $row): bool => $row['count'] > 0)
                    ->values()
                    ->all(),
                'payment_methods' => $notCancelled->groupBy('payment_method')
                    ->map(fn (Collection $group, string $method): array => [
                        'method' => $method,
                        'orders' => $group->count(),
                        'amount' => round((float) $group->sum('total'), 2),
                    ])
                    ->sortByDesc('orders')
                    ->values()
                    ->all(),
                'funnel' => [
                    ['step' => 'Visitors', 'count' => $visitors],
                    ['step' => 'Orders placed', 'count' => $placed],
                    ['step' => 'Sent to courier', 'count' => $sentToCourier],
                    ['step' => 'Delivered', 'count' => $delivered],
                ],
                'cancellation_rate' => $this->percent($cancelled, $placed),
                'cancelled' => $cancelled,
                'return_rate' => $this->percent($returned, $delivered),
                'returned' => $returned,
                'refunds' => $refunds,
                'discounts' => [
                    'amount' => round((float) $notCancelled->sum('discount'), 2),
                    'orders' => $notCancelled->filter(fn (object $order): bool => (float) $order->discount > 0)->count(),
                ],
            ];
        });
    }

    // ── Couriers ────────────────────────────────────────────────────────────

    /**
     * Parcels booked in the range, per courier.
     *
     * @return list<array<string, mixed>>
     */
    public function couriers(DashboardRange $range): array
    {
        return $this->cached('couriers', $range, function () use ($range): array {
            $rows = [];

            foreach (['pathao' => ['pathao_consignment_id', 'pathao_sent_at'], 'steadfast' => ['steadfast_consignment_id', 'steadfast_sent_at']] as $courier => [$idColumn, $sentColumn]) {
                $orders = Order::query()
                    ->whereNotNull($idColumn)
                    ->whereBetween($sentColumn, $range->utc())
                    ->get(['id', 'status', $sentColumn, 'delivered_at', 'total']);

                $delivered = $orders->where('status', OrderStatus::DELIVERED);
                $deliveryDays = $delivered
                    ->filter(fn (Order $order): bool => $order->delivered_at !== null && $order->{$sentColumn} !== null)
                    ->map(fn (Order $order): float => max(0, $order->{$sentColumn}->diffInHours($order->delivered_at)) / 24);
                $cancelled = $orders->where('status', OrderStatus::CANCELLED)->count();

                $rows[] = [
                    'courier' => $courier,
                    'sent' => $orders->count(),
                    'delivered' => $delivered->count(),
                    'in_transit' => $orders->whereIn('status', [OrderStatus::PROCESSING, OrderStatus::SHIPPED])->count(),
                    'cancelled' => $cancelled,
                    'delivered_rate' => $this->percent($delivered->count(), $orders->count()),
                    'cancelled_rate' => $this->percent($cancelled, $orders->count()),
                    'average_days' => $deliveryDays->isEmpty() ? null : round($deliveryDays->avg(), 1),
                ];
            }

            return $rows;
        });
    }

    // ── Products ────────────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    public function products(DashboardRange $range, bool $includeStockActivity): array
    {
        $data = $this->cached('products', $range, function () use ($range): array {
            $sold = $this->soldItemsQuery($range);

            $topProducts = (clone $sold)
                ->selectRaw('order_items.product_id, MAX(order_items.product_name) as name, SUM(order_items.quantity) as units, SUM(order_items.subtotal) as revenue')
                ->groupBy('order_items.product_id')
                ->orderByDesc('revenue')
                ->limit(8)
                ->get()
                ->map(fn (object $row): array => [
                    'id' => $row->product_id === null ? null : (int) $row->product_id,
                    'name' => (string) $row->name,
                    'units' => (int) $row->units,
                    'revenue' => round((float) $row->revenue, 2),
                ])
                ->all();

            $byGroup = fn (string $table, string $column): array => (clone $sold)
                ->join('products', 'products.id', '=', 'order_items.product_id')
                ->join($table, "{$table}.id", '=', "products.{$column}")
                ->selectRaw("{$table}.id, {$table}.name, SUM(order_items.quantity) as units, SUM(order_items.subtotal) as revenue")
                ->groupBy("{$table}.id", "{$table}.name")
                ->orderByDesc('revenue')
                ->limit(6)
                ->get()
                ->map(fn (object $row): array => [
                    'id' => (int) $row->id,
                    'name' => (string) $row->name,
                    'units' => (int) $row->units,
                    'revenue' => round((float) $row->revenue, 2),
                ])
                ->all();

            return [
                'top_products' => $topProducts,
                'top_categories' => $byGroup('categories', 'category_id'),
                'top_brands' => $byGroup('brands', 'brand_id'),
                'slow_movers' => $this->slowMovers(),
                'most_wishlisted' => Wishlist::query()
                    ->join('products', 'products.id', '=', 'wishlists.product_id')
                    ->whereNull('products.deleted_at')
                    ->selectRaw('products.id, products.name, COUNT(*) as wishlists')
                    ->groupBy('products.id', 'products.name')
                    ->orderByDesc('wishlists')
                    ->limit(6)
                    ->get()
                    ->map(fn (object $row): array => ['id' => (int) $row->id, 'name' => (string) $row->name, 'wishlists' => (int) $row->wishlists])
                    ->all(),
                'out_of_stock' => $this->outOfStockQuery()->orderBy('name')->limit(6)->get(['id', 'name', 'sku'])
                    ->map(fn (Product $product): array => ['id' => $product->id, 'name' => $product->name, 'sku' => $product->sku])
                    ->all(),
            ];
        });

        // Always current: it changes on every stock adjustment.
        $data['stock_activity'] = $includeStockActivity ? $this->stockActivity() : null;

        return $data;
    }

    /**
     * Active products in stock that sold nothing in the last SLOW_MOVER_DAYS
     * days (and are older than that), most stock first.
     *
     * @return list<array{id: int, name: string, stock: int, days_listed: int}>
     */
    private function slowMovers(): array
    {
        $since = now()->subDays(self::SLOW_MOVER_DAYS);
        $recentlySold = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNull('orders.deleted_at')
            ->where('orders.status', '!=', OrderStatus::CANCELLED->value)
            ->where('orders.created_at', '>=', $since)
            ->whereNotNull('order_items.product_id')
            ->select('order_items.product_id');

        return Product::query()
            ->where('is_active', true)
            ->where('created_at', '<', $since)
            ->whereNotIn('id', $recentlySold)
            ->withSum('variants', 'stock_quantity')
            ->withCount('variants')
            ->get(['id', 'name', 'stock_quantity', 'created_at'])
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'stock' => (int) ($product->variants_count > 0 ? $product->variants_sum_stock_quantity : $product->stock_quantity),
                'days_listed' => (int) $product->created_at->diffInDays(now()),
            ])
            ->filter(fn (array $row): bool => $row['stock'] > 0)
            ->sortByDesc('stock')
            ->take(6)
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function stockActivity(): array
    {
        return InventoryLog::query()
            ->with('product:id,name')
            ->latest('id')
            ->limit(8)
            ->get(['id', 'product_id', 'type', 'quantity', 'note', 'created_at'])
            ->map(fn (InventoryLog $log): array => [
                'id' => $log->id,
                'product_id' => $log->product_id,
                'product' => $log->product?->name ?? 'Deleted product',
                'type' => $log->type instanceof \BackedEnum ? $log->type->value : (string) $log->type,
                'quantity' => (int) $log->quantity,
                'note' => $log->note,
                'created_at' => $log->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /** Active products with nothing left to sell (variant-aware, like the stock page). */
    private function outOfStockQuery(): Builder
    {
        return Product::query()
            ->where('is_active', true)
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $withVariants) => $withVariants
                    ->whereHas('variants')
                    ->whereDoesntHave('variants', fn (Builder $variants) => $variants->where('stock_quantity', '>', 0)))
                ->orWhere(fn (Builder $withoutVariants) => $withoutVariants
                    ->whereDoesntHave('variants')
                    ->where('products.stock_quantity', '<=', 0)));
    }

    // ── Promotions: flash sales, bundles, coupons ───────────────────────────

    /**
     * @param  callable(string): bool  $can
     * @return array<string, mixed>
     */
    public function promotions(DashboardRange $range, callable $can): array
    {
        $data = $this->cached('promotions', $range, function () use ($range): array {
            $sold = $this->soldItemsQuery($range);

            $flashSales = FlashSale::query()
                ->where(fn (Builder $query) => $query
                    ->where('ends_at', '>=', $range->start->copy()->utc())
                    ->orWhereNull('ends_at'))
                ->where(fn (Builder $query) => $query
                    ->where('starts_at', '<=', $range->end->copy()->utc())
                    ->orWhereNull('starts_at'))
                ->withSum('items', 'sold_count')
                ->withSum('items', 'stock_limit')
                ->orderByDesc('starts_at')
                ->limit(5)
                ->get()
                ->map(function (FlashSale $sale) use ($sold): array {
                    $revenue = (clone $sold)
                        ->join('flash_sale_items', 'flash_sale_items.id', '=', 'order_items.flash_sale_item_id')
                        ->where('flash_sale_items.flash_sale_id', $sale->id)
                        ->sum('order_items.subtotal');

                    return [
                        'id' => $sale->id,
                        'name' => (string) $sale->name,
                        'sold' => (int) $sale->items_sum_sold_count,
                        'stock_limit' => $sale->items_sum_stock_limit === null ? null : (int) $sale->items_sum_stock_limit,
                        'revenue' => round((float) $revenue, 2),
                        'is_active' => (bool) $sale->is_active && ($sale->ends_at === null || $sale->ends_at->isFuture()),
                    ];
                })
                ->all();

            $bundles = (clone $sold)
                ->whereNotNull('order_items.bundle_id')
                ->join('product_bundles', 'product_bundles.id', '=', 'order_items.bundle_id')
                ->selectRaw('product_bundles.id, product_bundles.name, COUNT(DISTINCT order_items.order_id) as orders, SUM(order_items.subtotal) as revenue')
                ->groupBy('product_bundles.id', 'product_bundles.name')
                ->orderByDesc('revenue')
                ->limit(5)
                ->get()
                ->map(fn (object $row): array => [
                    'id' => (int) $row->id,
                    'name' => (string) $row->name,
                    'orders' => (int) $row->orders,
                    'revenue' => round((float) $row->revenue, 2),
                ])
                ->all();

            $coupons = CouponUse::query()
                ->join('coupons', 'coupons.id', '=', 'coupon_uses.coupon_id')
                ->whereBetween('coupon_uses.created_at', $range->utc())
                ->selectRaw('coupons.id, coupons.code, COUNT(*) as uses, SUM(coupon_uses.discount_amount) as discount')
                ->groupBy('coupons.id', 'coupons.code')
                ->orderByDesc('uses')
                ->limit(6)
                ->get()
                ->map(fn (object $row): array => [
                    'id' => (int) $row->id,
                    'code' => (string) $row->code,
                    'uses' => (int) $row->uses,
                    'discount' => round((float) $row->discount, 2),
                ])
                ->all();

            return ['flash_sales' => $flashSales, 'bundles' => $bundles, 'coupons' => $coupons];
        });

        return [
            'flash_sales' => $can('flash_sales') ? $data['flash_sales'] : null,
            'bundles' => $can('bundles') ? $data['bundles'] : null,
            'coupons' => $can('coupons') ? $data['coupons'] : null,
        ];
    }

    // ── Customers ───────────────────────────────────────────────────────────

    /**
     * @param  callable(string): bool  $can
     * @return array<string, mixed>
     */
    public function customers(DashboardRange $range, callable $can): array
    {
        $data = $this->cached('customers', $range, function () use ($range): array {
            $orders = $this->ordersIn($range)->where('status', '!=', OrderStatus::CANCELLED->value);
            $registered = $orders->whereNotNull('user_id');
            $customerIds = $registered->pluck('user_id')->unique()->values();

            $returningIds = $customerIds->isEmpty() ? collect() : $customerIds->chunk(500)->flatMap(
                fn (Collection $ids) => Order::query()
                    ->whereIn('user_id', $ids->all())
                    ->where('created_at', '<', $range->start->copy()->utc())
                    ->where('status', '!=', OrderStatus::CANCELLED)
                    ->distinct()
                    ->pluck('user_id')
            );

            $lifetime = Order::query()
                ->whereNotNull('user_id')
                ->where('status', '!=', OrderStatus::CANCELLED)
                ->selectRaw('user_id, COUNT(*) as orders')
                ->groupBy('user_id');
            $buyers = DB::query()->fromSub($lifetime, 'buyers')
                ->selectRaw('COUNT(*) as customers, SUM(CASE WHEN orders > 1 THEN 1 ELSE 0 END) as repeaters')
                ->first();
            $lifetimeSpend = DB::query()->fromSub(
                Order::query()
                    ->whereNotNull('user_id')
                    ->where('payment_status', PaymentStatus::PAID)
                    ->where('status', '!=', OrderStatus::CANCELLED)
                    ->selectRaw('user_id, SUM(total) as spent')
                    ->groupBy('user_id'),
                'spend'
            )->selectRaw('AVG(spent) as average')->value('average');

            $refunds = $this->refundsFor($registered);
            $topCustomers = $registered->filter(fn (object $order): bool => $this->isCollected($order))
                ->groupBy('user_id')
                ->map(fn (Collection $group, int|string $userId): array => [
                    'id' => (int) $userId,
                    'orders' => $group->count(),
                    'spent' => round((float) $group->sum(fn (object $order): float => (float) $order->total - ($refunds[$order->id] ?? 0.0)), 2),
                ])
                ->sortByDesc('spent')
                ->take(6)
                ->values();
            $names = User::withTrashed()->whereIn('id', $topCustomers->pluck('id')->all())->pluck('name', 'id');

            $districts = $orders->map(fn (object $order): string => $this->district($order->shipping_address))
                ->countBy()
                ->sortDesc()
                ->take(10)
                ->map(fn (int $count, string $district): array => ['district' => $district, 'orders' => $count])
                ->values()
                ->all();

            return [
                'new_customers' => $customerIds->diff($returningIds)->count(),
                'returning_customers' => $returningIds->unique()->count(),
                'guest_orders' => $orders->whereNull('user_id')->count(),
                'registered_orders' => $registered->count(),
                'repeat_rate' => $this->percent((int) ($buyers->repeaters ?? 0), (int) ($buyers->customers ?? 0)),
                'average_lifetime_spend' => round((float) $lifetimeSpend, 2),
                'top_customers' => $topCustomers
                    ->map(fn (array $row): array => $row + ['name' => (string) ($names[$row['id']] ?? 'Customer #'.$row['id'])])
                    ->all(),
                'districts' => $districts,
                'loyalty' => [
                    'points_outstanding' => (int) User::where('points_balance', '>', 0)->sum('points_balance'),
                    'points_earned' => (int) ReferralPointTransaction::whereBetween('created_at', $range->utc())->where('points', '>', 0)->sum('points'),
                    'points_redeemed' => (int) abs((int) ReferralPointTransaction::whereBetween('created_at', $range->utc())
                        ->where('type', PointTransactionType::REDEEM)
                        ->sum('points')),
                ],
                'referrals' => User::whereNotNull('referred_by_id')->whereBetween('created_at', $range->utc())->count(),
                'newsletter' => $this->newsletter($range),
            ];
        });

        if (! $can('newsletter')) {
            $data['newsletter'] = null;
        }

        return $data;
    }

    /**
     * @return array{subscribers: int, joined: int, left: int}
     */
    private function newsletter(DashboardRange $range): array
    {
        return [
            'subscribers' => NewsletterSubscriber::where('is_active', true)->count(),
            'joined' => NewsletterSubscriber::whereBetween('subscribed_at', $range->utc())->count(),
            'left' => NewsletterSubscriber::whereBetween('unsubscribed_at', $range->utc())->count(),
        ];
    }

    // ── Marketing & traffic ─────────────────────────────────────────────────

    /**
     * @param  callable(string): bool  $can
     * @return array<string, mixed>
     */
    public function marketing(DashboardRange $range, callable $can): array
    {
        $data = $this->cached('marketing', $range, function () use ($range): array {
            $orders = $this->ordersIn($range)->where('status', '!=', OrderStatus::CANCELLED->value);
            $refunds = $this->refundsFor($orders);
            $visits = $this->visitsIn($range);

            $heatmap = array_fill(0, 7, array_fill(0, 24, 0));

            foreach ($this->ordersIn($range) as $order) {
                $time = $this->dhaka($order->created_at);
                $heatmap[$time->dayOfWeekIso - 1][$time->hour]++;
            }

            $landingIds = $orders->whereNotNull('landing_page_id')->pluck('landing_page_id')->unique()->values();
            $landingTitles = $landingIds->isEmpty() ? collect() : DB::table('landing_pages')->whereIn('id', $landingIds->all())->pluck('title', 'id');

            $searches = SearchLog::query()
                ->whereDate('date', '>=', $range->start->toDateString())
                ->whereDate('date', '<=', $range->end->toDateString())
                ->selectRaw('term, SUM(searches) as searches, MIN(results_count) as results_count')
                ->groupBy('term');

            return [
                'traffic_sources' => $orders->groupBy(fn (object $order): string => $order->traffic_source ?: 'direct')
                    ->map(fn (Collection $group, string $source): array => [
                        'source' => $source,
                        'orders' => $group->count(),
                        'revenue' => round((float) $group->filter(fn (object $order): bool => $this->isCollected($order))
                            ->sum(fn (object $order): float => (float) $order->total - ($refunds[$order->id] ?? 0.0)), 2),
                    ])
                    ->sortByDesc('orders')
                    ->values()
                    ->all(),
                'devices' => [
                    'mobile' => (int) $visits->sum('mobile_visitors'),
                    'tablet' => (int) $visits->sum('tablet_visitors'),
                    'desktop' => (int) $visits->sum('desktop_visitors'),
                ],
                'heatmap' => $heatmap,
                'landing_pages' => $orders->whereNotNull('landing_page_id')->groupBy('landing_page_id')
                    ->map(fn (Collection $group, int|string $id): array => [
                        'id' => (int) $id,
                        'title' => (string) ($landingTitles[$id] ?? 'Landing page #'.$id),
                        'orders' => $group->count(),
                        'amount' => round((float) $group->sum('total'), 2),
                    ])
                    ->sortByDesc('orders')
                    ->take(6)
                    ->values()
                    ->all(),
                'abandoned' => [
                    'count' => IncompleteOrder::whereBetween('created_at', $range->utc())->count(),
                    'value' => round((float) IncompleteOrder::whereBetween('created_at', $range->utc())->sum('subtotal'), 2),
                    'reminded' => IncompleteOrder::whereBetween('created_at', $range->utc())->whereNotNull('reminder_sent_at')->count(),
                ],
                'top_searches' => (clone $searches)->orderByDesc('searches')->limit(8)->get()
                    ->map(fn (object $row): array => ['term' => (string) $row->term, 'searches' => (int) $row->searches, 'results' => (int) $row->results_count])
                    ->all(),
                'no_result_searches' => (clone $searches)->havingRaw('MIN(results_count) = 0')->orderByDesc('searches')->limit(8)->get()
                    ->map(fn (object $row): array => ['term' => (string) $row->term, 'searches' => (int) $row->searches])
                    ->all(),
            ];
        });

        $orders = $can('orders');

        return [
            'traffic_sources' => $orders ? $data['traffic_sources'] : null,
            'devices' => $data['devices'],
            'heatmap' => $orders ? $data['heatmap'] : null,
            'landing_pages' => $can('landing_pages') ? $data['landing_pages'] : null,
            'abandoned' => $can('incomplete_orders') ? $data['abandoned'] : null,
            'top_searches' => $can('search_keywords') ? $data['top_searches'] : null,
            'no_result_searches' => $can('search_keywords') ? $data['no_result_searches'] : null,
        ];
    }

    // ── Reviews ─────────────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    public function reviews(DashboardRange $range): array
    {
        return $this->cached('reviews', $range, function (): array {
            $approved = Review::query()->where('is_approved', true);
            $distribution = (clone $approved)->selectRaw('rating, COUNT(*) as reviews')->groupBy('rating')->pluck('reviews', 'rating');

            return [
                'average' => round((float) (clone $approved)->avg('rating'), 2),
                'total' => (int) $distribution->sum(),
                'distribution' => collect(range(5, 1))
                    ->map(fn (int $stars): array => ['stars' => $stars, 'reviews' => (int) ($distribution[$stars] ?? 0)])
                    ->all(),
                'latest' => Review::query()
                    ->with(['product:id,name', 'user:id,name'])
                    ->latest('id')
                    ->limit(5)
                    ->get(['id', 'product_id', 'user_id', 'rating', 'title', 'body', 'is_approved', 'created_at'])
                    ->map(fn (Review $review): array => [
                        'id' => $review->id,
                        'product' => $review->product?->name ?? 'Deleted product',
                        'customer' => $review->user?->name ?? 'Customer',
                        'rating' => (int) $review->rating,
                        'text' => mb_strimwidth(trim(strip_tags((string) ($review->title ?: $review->body))), 0, 120, '…'),
                        'is_approved' => (bool) $review->is_approved,
                        'created_at' => $review->created_at?->toIso8601String(),
                    ])
                    ->all(),
                'lowest_rated' => (clone $approved)
                    ->join('products', 'products.id', '=', 'reviews.product_id')
                    ->whereNull('products.deleted_at')
                    ->selectRaw('products.id, products.name, AVG(reviews.rating) as average, COUNT(*) as reviews')
                    ->groupBy('products.id', 'products.name')
                    ->havingRaw('COUNT(*) >= 2')
                    ->orderBy('average')
                    ->limit(5)
                    ->get()
                    ->map(fn (object $row): array => [
                        'id' => (int) $row->id,
                        'name' => (string) $row->name,
                        'average' => round((float) $row->average, 1),
                        'reviews' => (int) $row->reviews,
                    ])
                    ->all(),
            ];
        });
    }

    // ── Live order feed (never cached) ──────────────────────────────────────

    /**
     * @return array{orders: list<array<string, mixed>>, today_orders: int, server_time: string}
     */
    public function liveOrders(): array
    {
        $todayStart = Carbon::now(DashboardRange::TIMEZONE)->startOfDay()->utc();

        return [
            'orders' => Order::query()
                ->with('user:id,name')
                ->latest('id')
                ->limit(8)
                ->get(['id', 'order_number', 'user_id', 'total', 'status', 'payment_method', 'shipping_address', 'created_at'])
                ->map(fn (Order $order): array => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'customer' => $order->user?->name ?? (is_array($order->shipping_address) ? ($order->shipping_address['name'] ?? null) : null) ?? 'Guest',
                    'district' => $this->district($order->shipping_address),
                    'total' => (float) $order->total,
                    'status' => $order->status instanceof \BackedEnum ? $order->status->value : (string) $order->status,
                    'payment_method' => $order->payment_method instanceof \BackedEnum ? $order->payment_method->value : (string) $order->payment_method,
                    'created_at' => $order->created_at?->toIso8601String(),
                ])
                ->all(),
            'today_orders' => Order::where('created_at', '>=', $todayStart)->count(),
            'server_time' => now()->toIso8601String(),
        ];
    }

    // ── Shared helpers ──────────────────────────────────────────────────────

    /**
     * Orders placed in the range, as light rows (trashed excluded, like
     * every other admin count). Memoised per request.
     *
     * @return Collection<int, object>
     */
    private function ordersIn(DashboardRange $range): Collection
    {
        return $this->ordersBetween($range->start, $range->end);
    }

    /**
     * @return Collection<int, object>
     */
    private function ordersBetween(Carbon $dhakaStart, Carbon $dhakaEnd): Collection
    {
        $key = $dhakaStart->toIso8601String().'|'.$dhakaEnd->toIso8601String();

        return $this->ordersMemo[$key] ??= Order::query()
            ->whereBetween('created_at', [$dhakaStart->copy()->utc(), $dhakaEnd->copy()->utc()])
            ->toBase()
            ->get([
                'id', 'user_id', 'status', 'payment_status', 'payment_method', 'total', 'discount', 'created_at',
                'traffic_source', 'landing_page_id', 'shipping_address', 'pathao_consignment_id', 'steadfast_consignment_id',
            ]);
    }

    /**
     * @return Collection<int, DailyVisit>
     */
    private function visitsIn(DashboardRange $range): Collection
    {
        return DailyVisit::query()
            // whereDate, not whereBetween: SQLite stores the date cast with a time part.
            ->whereDate('date', '>=', $range->start->toDateString())
            ->whereDate('date', '<=', $range->end->toDateString())
            ->get();
    }

    private function usersCreatedIn(DashboardRange $range): int
    {
        return User::whereBetween('created_at', $range->utc())->count();
    }

    /** Order lines of non-cancelled, non-trashed orders placed in the range. */
    private function soldItemsQuery(DashboardRange $range): \Illuminate\Database\Query\Builder
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNull('orders.deleted_at')
            ->where('orders.status', '!=', OrderStatus::CANCELLED->value)
            ->whereBetween('orders.created_at', $range->utc());
    }

    /** LOW-08: money actually collected. */
    private function isCollected(object $order): bool
    {
        return $order->payment_status === PaymentStatus::PAID->value
            && $order->status !== OrderStatus::CANCELLED->value;
    }

    /**
     * @param  Collection<int, object>  $orders
     * @return Collection<int, object>
     */
    private function collected(Collection $orders): Collection
    {
        return $orders->filter(fn (object $order): bool => $this->isCollected($order));
    }

    /**
     * BIZ-008: collected revenue minus what the refund ledger paid back.
     *
     * @param  Collection<int, object>  $orders
     */
    private function netRevenue(Collection $orders): float
    {
        $collected = $this->collected($orders);
        $refunds = $this->refundsFor($collected);

        return round((float) $collected->sum(fn (object $order): float => (float) $order->total - ($refunds[$order->id] ?? 0.0)), 2);
    }

    /**
     * @param  Collection<int, object>  $orders
     * @return array<int, float>
     */
    private function refundsFor(Collection $orders): array
    {
        $refunds = [];

        // Chunked: a long range can hold more ids than SQLite allows in one IN().
        foreach ($orders->pluck('id')->unique()->chunk(500) as $ids) {
            $refunds += $this->refundCalculator->refundedAmountsFor($ids->values()->all());
        }

        return $refunds;
    }

    private function percent(int|float $part, int|float $whole): float
    {
        return $whole > 0 ? round($part / $whole * 100, 1) : 0.0;
    }

    private function dhaka(mixed $timestamp): Carbon
    {
        return Carbon::parse($timestamp, 'UTC')->setTimezone(DashboardRange::TIMEZONE);
    }

    /** The checkout's District field is stored as the address's `state`. */
    private function district(mixed $address): string
    {
        if (is_string($address)) {
            $address = json_decode($address, true);
        }

        $district = is_array($address) ? trim((string) ($address['state'] ?? '')) : '';

        return $district !== '' ? $district : 'Unknown';
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function cached(string $section, DashboardRange $range, callable $callback): mixed
    {
        return Cache::remember("dashboard:{$section}:{$range->cacheKey()}", self::CACHE_SECONDS, $callback);
    }
}
