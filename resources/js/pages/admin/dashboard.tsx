import { Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowRight,
    ArrowUpRight,
    ArrowDownRight,
    Box,
    CheckCircle2,
    Clock,
    Eye,
    Package,
    RefreshCcw,
    ShoppingBag,
    ShoppingCart,
    Star,
    Tag,
    TrendingUp,
    Truck,
    Users,
    XCircle,
    Zap,
    Image,
} from 'lucide-react';

// ── Types ──────────────────────────────────────────────────────────────────────

type Stats = {
    today_revenue: number;
    yesterday_revenue: number;
    today_orders: number;
    yesterday_orders: number;
    total_customers: number;
    new_customers_this_week: number;
    pending_orders: number;
    processing_orders: number;
    total_products: number;
    low_stock_count: number;
    pending_reviews: number;
    failed_jobs: number | null;
    queue_last_drained_seconds_ago: number | null;
    queue_oldest_job_seconds: number | null;
    pathao_sync_problem: {
        failing_for_seconds: number;
        last_error: string;
    } | null;
    steadfast_sync_problem: {
        failing_for_seconds: number;
        last_error: string;
    } | null;
    today_visitors: number;
    yesterday_visitors: number;
};

type ChartPoint = {
    date: string;
    label: string;
    revenue: number;
    orders: number;
};
type VisitorChartPoint = {
    date: string;
    label: string;
    visitors: number;
    page_views: number;
};

type RecentOrder = {
    id: number;
    order_number: string;
    total: number;
    status: string;
    payment_status: string;
    created_at: string;
    user: { id: number; name: string; email: string } | null;
};

type LowStockProduct = {
    id: number;
    name: string;
    sku: string | null;
    stock_quantity: number;
    low_stock_threshold: number;
};

type Props = {
    stats: Stats;
    chartData: ChartPoint[];
    visitorChartData: VisitorChartPoint[];
    recentOrders: RecentOrder[];
    lowStockProducts: LowStockProduct[];
};

// ── Helpers ────────────────────────────────────────────────────────────────────

const fmt = (n: number) => '৳' + n.toLocaleString('en-IN');

const pct = (
    current: number,
    previous: number,
): { value: string; up: boolean } => {
    if (previous === 0) {
        return { value: current > 0 ? '+100%' : '—', up: current > 0 };
    }

    const diff = ((current - previous) / previous) * 100;

    return {
        value: (diff >= 0 ? '+' : '') + diff.toFixed(1) + '%',
        up: diff >= 0,
    };
};

const diff = (
    current: number,
    previous: number,
): { value: string; up: boolean } => {
    const d = current - previous;

    return { value: (d >= 0 ? '+' : '') + d, up: d >= 0 };
};

// ── Status configs ─────────────────────────────────────────────────────────────

const orderStatusConfig: Record<
    string,
    {
        label: string;
        icon: React.ElementType;
        dot: string;
        text: string;
        bg: string;
    }
> = {
    pending: {
        label: 'Pending',
        icon: Clock,
        dot: 'bg-amber-400',
        text: 'text-amber-700',
        bg: 'bg-amber-50',
    },
    processing: {
        label: 'Processing',
        icon: RefreshCcw,
        dot: 'bg-blue-400',
        text: 'text-blue-700',
        bg: 'bg-blue-50',
    },
    shipped: {
        label: 'Shipped',
        icon: Truck,
        dot: 'bg-violet-400',
        text: 'text-violet-700',
        bg: 'bg-violet-50',
    },
    delivered: {
        label: 'Delivered',
        icon: CheckCircle2,
        dot: 'bg-emerald-400',
        text: 'text-emerald-700',
        bg: 'bg-emerald-50',
    },
    cancelled: {
        label: 'Cancelled',
        icon: XCircle,
        dot: 'bg-red-400',
        text: 'text-red-700',
        bg: 'bg-red-50',
    },
    refunded: {
        label: 'Refunded',
        icon: RefreshCcw,
        dot: 'bg-slate-400',
        text: 'text-slate-600',
        bg: 'bg-slate-100',
    },
};

// ── Sub-components ─────────────────────────────────────────────────────────────

function RevenueChart({ data }: { data: ChartPoint[] }) {
    const maxRevenue = Math.max(...data.map((d) => d.revenue), 1);
    const totalRevenue = data.reduce((s, d) => s + d.revenue, 0);
    const totalOrders = data.reduce((s, d) => s + d.orders, 0);

    return (
        <div className="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm">
            <div className="mb-5 flex items-start justify-between">
                <div>
                    <p className="text-xs font-semibold tracking-widest text-slate-400 uppercase">
                        Revenue
                    </p>
                    <p className="mt-1 text-2xl font-bold tracking-tight text-slate-900">
                        {fmt(totalRevenue)}
                    </p>
                    <p className="mt-0.5 text-xs text-slate-400">
                        {totalOrders} orders · last 7 days
                    </p>
                </div>
                <span className="flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-600">
                    <TrendingUp className="size-3" /> 7-day
                </span>
            </div>

            {totalRevenue === 0 ? (
                <div className="flex h-36 items-center justify-center">
                    <p className="text-sm text-slate-300">
                        No orders yet — data will appear here
                    </p>
                </div>
            ) : (
                <div className="flex h-36 items-end gap-1.5">
                    {data.map((point, i) => {
                        const height =
                            maxRevenue > 0
                                ? Math.max(
                                      (point.revenue / maxRevenue) * 100,
                                      2,
                                  )
                                : 2;
                        const isToday = i === data.length - 1;

                        return (
                            <div
                                key={point.date}
                                className="group relative flex flex-1 flex-col items-center gap-1"
                            >
                                {point.revenue > 0 && (
                                    <div className="absolute -top-7 left-1/2 hidden -translate-x-1/2 rounded-lg bg-slate-800 px-2 py-1 text-[10px] font-medium whitespace-nowrap text-white group-hover:flex">
                                        {fmt(point.revenue)}
                                    </div>
                                )}
                                <div
                                    className={`w-full rounded-t-lg transition-all duration-300 ${isToday ? 'bg-[#c8102e]' : 'bg-slate-100 group-hover:bg-slate-200'}`}
                                    style={{ height: `${height}%` }}
                                />
                                <span
                                    className={`text-[10px] font-medium ${isToday ? 'text-[#c8102e]' : 'text-slate-400'}`}
                                >
                                    {point.label}
                                </span>
                            </div>
                        );
                    })}
                </div>
            )}
        </div>
    );
}

function VisitorsChart({ data }: { data: VisitorChartPoint[] }) {
    const maxVisitors = Math.max(...data.map((d) => d.visitors), 1);
    const totalVisitors = data.reduce((s, d) => s + d.visitors, 0);
    const totalPageViews = data.reduce((s, d) => s + d.page_views, 0);

    return (
        <div className="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm">
            <div className="mb-5 flex items-start justify-between">
                <div>
                    <p className="text-xs font-semibold tracking-widest text-slate-400 uppercase">
                        Visitors
                    </p>
                    <p className="mt-1 text-2xl font-bold tracking-tight text-slate-900">
                        {totalVisitors.toLocaleString()}
                    </p>
                    <p className="mt-0.5 text-xs text-slate-400">
                        {totalPageViews.toLocaleString()} page views · last 7
                        days
                    </p>
                </div>
                <span className="flex items-center gap-1 rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-600">
                    <Eye className="size-3" /> 7-day
                </span>
            </div>

            {totalVisitors === 0 ? (
                <div className="flex h-36 items-center justify-center">
                    <p className="text-sm text-slate-300">
                        No visits yet — data will appear here
                    </p>
                </div>
            ) : (
                <div className="flex h-36 items-end gap-1.5">
                    {data.map((point, i) => {
                        const height =
                            maxVisitors > 0
                                ? Math.max(
                                      (point.visitors / maxVisitors) * 100,
                                      2,
                                  )
                                : 2;
                        const isToday = i === data.length - 1;

                        return (
                            <div
                                key={point.date}
                                className="group relative flex flex-1 flex-col items-center gap-1"
                            >
                                {point.visitors > 0 && (
                                    <div className="absolute -top-7 left-1/2 hidden -translate-x-1/2 rounded-lg bg-slate-800 px-2 py-1 text-[10px] font-medium whitespace-nowrap text-white group-hover:flex">
                                        {point.visitors.toLocaleString()}
                                    </div>
                                )}
                                <div
                                    className={`w-full rounded-t-lg transition-all duration-300 ${isToday ? 'bg-blue-500' : 'bg-slate-100 group-hover:bg-slate-200'}`}
                                    style={{ height: `${height}%` }}
                                />
                                <span
                                    className={`text-[10px] font-medium ${isToday ? 'text-blue-600' : 'text-slate-400'}`}
                                >
                                    {point.label}
                                </span>
                            </div>
                        );
                    })}
                </div>
            )}
        </div>
    );
}

function StatCard({
    label,
    value,
    sub,
    icon: Icon,
    iconBg,
    iconColor,
    trend,
}: {
    label: string;
    value: string | number;
    sub?: string;
    icon: React.ElementType;
    iconBg: string;
    iconColor: string;
    trend?: { value: string; up: boolean };
}) {
    return (
        <div className="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
            <div className="flex items-center justify-between">
                <div
                    className={`flex size-10 items-center justify-center rounded-xl ${iconBg}`}
                >
                    <Icon className={`size-5 ${iconColor}`} />
                </div>
                {trend && (
                    <span
                        className={`flex items-center gap-0.5 text-xs font-semibold ${trend.up ? 'text-emerald-600' : 'text-red-500'}`}
                    >
                        {trend.up ? (
                            <ArrowUpRight className="size-3.5" />
                        ) : (
                            <ArrowDownRight className="size-3.5" />
                        )}
                        {trend.value}
                    </span>
                )}
            </div>
            <div className="mt-3">
                <p className="text-xs font-medium text-slate-400">{label}</p>
                <p className="mt-0.5 text-2xl font-bold tracking-tight text-slate-900">
                    {value}
                </p>
                {sub && <p className="mt-0.5 text-xs text-slate-400">{sub}</p>}
            </div>
        </div>
    );
}

function AlertBadge({
    count,
    label,
    href,
    color,
}: {
    count: number;
    label: string;
    href: string;
    color: string;
}) {
    if (count === 0) {
        return null;
    }

    return (
        <Link
            href={href}
            className={`flex items-center justify-between rounded-xl border px-4 py-2.5 transition hover:shadow-sm ${color}`}
        >
            <span className="text-sm font-medium">{label}</span>
            <span className="rounded-full bg-white/70 px-2 py-0.5 text-xs font-bold">
                {count}
            </span>
        </Link>
    );
}

// ── Main page ──────────────────────────────────────────────────────────────────

export default function Dashboard({
    stats,
    chartData,
    visitorChartData,
    recentOrders,
    lowStockProducts,
}: Props) {
    const revenueChange = pct(stats.today_revenue, stats.yesterday_revenue);
    const ordersChange = diff(stats.today_orders, stats.yesterday_orders);
    const visitorsChange = diff(stats.today_visitors, stats.yesterday_visitors);

    // OPS-002: a stalled cron (so app:drain-queue never runs) creates no
    // failed_jobs rows, so that alert alone wouldn't show it — 10 minutes
    // is generous slack over the every-minute schedule.
    const QUEUE_STALL_THRESHOLD_SECONDS = 600;
    const canSeeQueueHealth = stats.failed_jobs !== null;
    const queueStalled =
        canSeeQueueHealth &&
        ((stats.queue_last_drained_seconds_ago ?? Infinity) >
            QUEUE_STALL_THRESHOLD_SECONDS ||
            (stats.queue_oldest_job_seconds ?? 0) >
                QUEUE_STALL_THRESHOLD_SECONDS);
    const formatAgo = (seconds: number): string => {
        const minutes = Math.round(seconds / 60);

        return minutes < 60
            ? `${minutes} minute${minutes === 1 ? '' : 's'}`
            : `${Math.round(minutes / 60)} hour${Math.round(minutes / 60) === 1 ? '' : 's'}`;
    };

    const quickActions = [
        {
            label: 'Add Product',
            icon: Box,
            href: route('admin.products.create'),
            color: 'bg-blue-500',
        },
        {
            label: 'New Coupon',
            icon: Tag,
            href: route('admin.coupons.create'),
            color: 'bg-amber-500',
        },
        {
            label: 'New Banner',
            icon: Image,
            href: route('admin.promotional-banners.create'),
            color: 'bg-indigo-500',
        },
        {
            label: 'Flash Sale',
            icon: Zap,
            href: route('admin.flash-sales.create'),
            color: 'bg-rose-500',
        },
        {
            label: 'View Orders',
            icon: ShoppingCart,
            href: route('admin.orders.index'),
            color: 'bg-violet-500',
        },
        {
            label: 'Customers',
            icon: Users,
            href: route('admin.customers.index'),
            color: 'bg-teal-500',
        },
    ];

    return (
        <>
            <Head title="Dashboard — Admin" />

            <div className="space-y-6 pb-10">
                {/* ── Header ── */}
                <div className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p className="text-[11px] font-semibold tracking-[0.18em] text-slate-400 uppercase">
                            Admin Panel
                        </p>
                        <h1 className="text-2xl font-bold tracking-tight text-slate-900">
                            Dashboard
                        </h1>
                    </div>
                    <p className="text-sm text-slate-400">
                        {new Date().toLocaleDateString('en-US', {
                            weekday: 'long',
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric',
                        })}
                    </p>
                </div>

                {/* BE-011: every Pathao status check is failing, so courier statuses stopped updating. */}
                {stats.pathao_sync_problem && (
                    <div className="flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-800">
                        <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                        <p className="text-sm font-medium">
                            Pathao status updates are failing (for{' '}
                            {formatAgo(
                                stats.pathao_sync_problem.failing_for_seconds,
                            )}
                            ). Order statuses for Pathao parcels are not
                            updating. Check the Pathao settings in Admin ›
                            Courier, or whether Pathao is down. Last error:{' '}
                            {stats.pathao_sync_problem.last_error || 'unknown'}
                        </p>
                    </div>
                )}

                {/* BIZ-027: the same for Steadfast's fallback check. */}
                {stats.steadfast_sync_problem && (
                    <div className="flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-800">
                        <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                        <p className="text-sm font-medium">
                            Steadfast status updates are failing (for{' '}
                            {formatAgo(
                                stats.steadfast_sync_problem
                                    .failing_for_seconds,
                            )}
                            ). Order statuses for Steadfast parcels may not be
                            updating. Check the Steadfast settings in Admin ›
                            Courier, or whether Steadfast is down. Last error:{' '}
                            {stats.steadfast_sync_problem.last_error ||
                                'unknown'}
                        </p>
                    </div>
                )}

                {/* OPS-002: a stalled cron creates no failed_jobs rows, so nothing else here would catch it. */}
                {queueStalled && (
                    <div className="flex items-start gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-800">
                        <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                        <p className="text-sm font-medium">
                            Background jobs may be stalled — the queue drain
                            last ran{' '}
                            {stats.queue_last_drained_seconds_ago === null
                                ? 'never (since this page last checked)'
                                : `${formatAgo(stats.queue_last_drained_seconds_ago)} ago`}
                            {stats.queue_oldest_job_seconds !== null &&
                                stats.queue_oldest_job_seconds >
                                    QUEUE_STALL_THRESHOLD_SECONDS &&
                                `, and the oldest pending job has been waiting ${formatAgo(stats.queue_oldest_job_seconds)}`}
                            . Order and courier update emails may not be
                            sending. Check the cron/scheduler on the server.
                        </p>
                    </div>
                )}

                {/* ── Alert bar (only shown if there's something to act on) ── */}
                {(stats.pending_orders > 0 ||
                    stats.low_stock_count > 0 ||
                    stats.pending_reviews > 0 ||
                    (stats.failed_jobs ?? 0) > 0) && (
                    <div className="grid gap-2 sm:grid-cols-3">
                        <AlertBadge
                            count={stats.pending_orders}
                            label="Pending orders"
                            href={
                                route('admin.orders.index') + '?status=pending'
                            }
                            color="border-amber-200 bg-amber-50 text-amber-800"
                        />
                        <AlertBadge
                            count={stats.low_stock_count}
                            label="Low stock products"
                            href={route('admin.products.index')}
                            color="border-red-200 bg-red-50 text-red-800"
                        />
                        <AlertBadge
                            count={stats.pending_reviews}
                            label="Reviews need approval"
                            href={
                                route('admin.reviews.index') + '?status=pending'
                            }
                            color="border-violet-200 bg-violet-50 text-violet-800"
                        />
                        <AlertBadge
                            count={stats.failed_jobs ?? 0}
                            label="Failed background jobs"
                            href={route('admin.failed-jobs.index')}
                            color="border-red-200 bg-red-50 text-red-800"
                        />
                    </div>
                )}

                {/* ── Stat cards ── */}
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    <StatCard
                        label="Today's Visitors"
                        value={stats.today_visitors.toLocaleString()}
                        sub={`Yesterday: ${stats.yesterday_visitors.toLocaleString()}`}
                        icon={Eye}
                        iconBg="bg-cyan-50"
                        iconColor="text-cyan-600"
                        trend={visitorsChange}
                    />
                    <StatCard
                        label="Today's Revenue"
                        value={fmt(stats.today_revenue)}
                        sub={`Yesterday: ${fmt(stats.yesterday_revenue)}`}
                        icon={ShoppingBag}
                        iconBg="bg-rose-50"
                        iconColor="text-[#c8102e]"
                        trend={revenueChange}
                    />
                    <StatCard
                        label="Today's Orders"
                        value={stats.today_orders}
                        sub={`${stats.pending_orders} pending · ${stats.processing_orders} processing`}
                        icon={Package}
                        iconBg="bg-blue-50"
                        iconColor="text-blue-600"
                        trend={ordersChange}
                    />
                    <StatCard
                        label="Total Customers"
                        value={stats.total_customers.toLocaleString()}
                        sub={`+${stats.new_customers_this_week} this week`}
                        icon={Users}
                        iconBg="bg-violet-50"
                        iconColor="text-violet-600"
                    />
                    <StatCard
                        label="Active Products"
                        value={stats.total_products.toLocaleString()}
                        sub={
                            stats.low_stock_count > 0
                                ? `${stats.low_stock_count} low stock`
                                : 'All stocked'
                        }
                        icon={Box}
                        iconBg="bg-emerald-50"
                        iconColor="text-emerald-600"
                    />
                </div>

                {/* ── Revenue chart + quick actions ── */}
                <div className="grid gap-5 lg:grid-cols-[1fr_260px]">
                    <RevenueChart data={chartData} />

                    <div className="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
                        <p className="mb-4 text-xs font-semibold tracking-widest text-slate-400 uppercase">
                            Quick Actions
                        </p>
                        <div className="grid grid-cols-3 gap-2.5">
                            {quickActions.map((action) => {
                                const Icon = action.icon;

                                return (
                                    <Link
                                        key={action.label}
                                        href={action.href}
                                        className="group flex flex-col items-center gap-2 rounded-xl border border-slate-100 bg-slate-50/60 p-3 text-center transition hover:border-slate-200 hover:bg-white hover:shadow-sm"
                                    >
                                        <div
                                            className={`flex size-9 items-center justify-center rounded-xl ${action.color} text-white shadow-sm transition group-hover:scale-105`}
                                        >
                                            <Icon className="size-4" />
                                        </div>
                                        <span className="text-[10px] leading-tight font-semibold text-slate-600">
                                            {action.label}
                                        </span>
                                    </Link>
                                );
                            })}
                        </div>
                    </div>
                </div>

                {/* ── Visitors chart ── */}
                <VisitorsChart data={visitorChartData} />

                {/* ── Recent orders + low stock ── */}
                <div className="grid gap-5 lg:grid-cols-[1fr_340px]">
                    {/* Recent orders */}
                    <div className="rounded-2xl border border-slate-100 bg-white shadow-sm">
                        <div className="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                            <p className="text-sm font-semibold text-slate-800">
                                Recent Orders
                            </p>
                            <Link
                                href={route('admin.orders.index')}
                                className="flex items-center gap-1 text-xs font-medium text-[#c8102e] hover:underline"
                            >
                                View all <ArrowRight className="size-3" />
                            </Link>
                        </div>

                        {recentOrders.length === 0 ? (
                            <div className="flex flex-col items-center justify-center gap-2 py-14">
                                <div className="flex size-12 items-center justify-center rounded-2xl bg-slate-100">
                                    <ShoppingCart className="size-5 text-slate-300" />
                                </div>
                                <p className="text-sm font-medium text-slate-400">
                                    No orders yet
                                </p>
                                <p className="text-xs text-slate-300">
                                    Orders will appear here once customers start
                                    buying
                                </p>
                            </div>
                        ) : (
                            <div className="divide-y divide-slate-50">
                                {recentOrders.map((order) => {
                                    const sc =
                                        orderStatusConfig[order.status] ??
                                        orderStatusConfig['pending'];
                                    const StatusIcon = sc.icon;

                                    return (
                                        <Link
                                            key={order.id}
                                            href={route(
                                                'admin.orders.show',
                                                order.id,
                                            )}
                                            className="flex items-center gap-4 px-5 py-3 transition hover:bg-slate-50/60"
                                        >
                                            <div
                                                className={`flex size-8 shrink-0 items-center justify-center rounded-xl ${sc.bg}`}
                                            >
                                                <StatusIcon
                                                    className={`size-3.5 ${sc.text}`}
                                                />
                                            </div>
                                            <div className="min-w-0 flex-1">
                                                <p className="font-mono text-xs font-semibold text-slate-800">
                                                    {order.order_number}
                                                </p>
                                                <p className="truncate text-[11px] text-slate-400">
                                                    {order.user?.name ??
                                                        'Guest'}
                                                </p>
                                            </div>
                                            <div className="text-right">
                                                <p className="text-sm font-bold text-slate-800">
                                                    {fmt(order.total)}
                                                </p>
                                                <p className="text-[11px] text-slate-400">
                                                    {new Date(
                                                        order.created_at,
                                                    ).toLocaleDateString(
                                                        'en-US',
                                                        {
                                                            month: 'short',
                                                            day: 'numeric',
                                                        },
                                                    )}
                                                </p>
                                            </div>
                                            <span
                                                className={`hidden shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold sm:inline ${sc.bg} ${sc.text}`}
                                            >
                                                {sc.label}
                                            </span>
                                        </Link>
                                    );
                                })}
                            </div>
                        )}
                    </div>

                    {/* Low stock */}
                    <div className="rounded-2xl border border-slate-100 bg-white shadow-sm">
                        <div className="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                            <div className="flex items-center gap-2">
                                <AlertTriangle className="size-4 text-amber-500" />
                                <p className="text-sm font-semibold text-slate-800">
                                    Low Stock
                                </p>
                            </div>
                            <Link
                                href={route('admin.products.index')}
                                className="flex items-center gap-1 text-xs font-medium text-[#c8102e] hover:underline"
                            >
                                Manage <ArrowRight className="size-3" />
                            </Link>
                        </div>

                        {lowStockProducts.length === 0 ? (
                            <div className="flex flex-col items-center justify-center gap-2 py-14">
                                <div className="flex size-12 items-center justify-center rounded-2xl bg-emerald-50">
                                    <CheckCircle2 className="size-5 text-emerald-400" />
                                </div>
                                <p className="text-sm font-medium text-slate-400">
                                    All products stocked
                                </p>
                                <p className="text-xs text-slate-300">
                                    No items are below their threshold
                                </p>
                            </div>
                        ) : (
                            <div className="divide-y divide-slate-50">
                                {lowStockProducts.map((product) => {
                                    const ratio =
                                        product.stock_quantity /
                                        Math.max(
                                            product.low_stock_threshold,
                                            1,
                                        );
                                    const urgent = ratio <= 0.5;

                                    return (
                                        <Link
                                            key={product.id}
                                            href={route(
                                                'admin.products.edit',
                                                product.id,
                                            )}
                                            className="flex items-center gap-3 px-5 py-3 transition hover:bg-slate-50/60"
                                        >
                                            <div
                                                className={`flex size-8 shrink-0 items-center justify-center rounded-xl ${urgent ? 'bg-red-50' : 'bg-amber-50'}`}
                                            >
                                                <Box
                                                    className={`size-3.5 ${urgent ? 'text-red-500' : 'text-amber-500'}`}
                                                />
                                            </div>
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate text-xs font-semibold text-slate-800">
                                                    {product.name}
                                                </p>
                                                <p className="font-mono text-[11px] text-slate-400">
                                                    {product.sku ?? '—'}
                                                </p>
                                            </div>
                                            <div className="shrink-0 text-right">
                                                <span
                                                    className={`rounded-full px-2 py-0.5 text-xs font-bold ${urgent ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700'}`}
                                                >
                                                    {product.stock_quantity}{' '}
                                                    left
                                                </span>
                                            </div>
                                        </Link>
                                    );
                                })}
                            </div>
                        )}
                    </div>
                </div>

                {/* ── Bottom strip — nav shortcuts ── */}
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    {[
                        {
                            label: 'All Products',
                            sub: `${stats.total_products} active`,
                            icon: Box,
                            href: route('admin.products.index'),
                            accent: 'border-blue-100   bg-blue-50/50   text-blue-600',
                        },
                        {
                            label: 'All Orders',
                            sub: `${stats.pending_orders} pending`,
                            icon: ShoppingCart,
                            href: route('admin.orders.index'),
                            accent: 'border-violet-100 bg-violet-50/50 text-violet-600',
                        },
                        {
                            label: 'Reviews',
                            sub: `${stats.pending_reviews} to approve`,
                            icon: Star,
                            href: route('admin.reviews.index'),
                            accent: 'border-amber-100  bg-amber-50/50  text-amber-600',
                        },
                        {
                            label: 'Flash Sales',
                            sub: 'Manage deals',
                            icon: Zap,
                            href: route('admin.flash-sales.index'),
                            accent: 'border-rose-100   bg-rose-50/50   text-rose-600',
                        },
                    ].map((item) => {
                        const Icon = item.icon;

                        return (
                            <Link
                                key={item.label}
                                href={item.href}
                                className={`group flex items-center gap-3 rounded-2xl border p-4 transition hover:shadow-sm ${item.accent}`}
                            >
                                <Icon className="size-5 shrink-0 opacity-70" />
                                <div className="min-w-0">
                                    <p className="text-sm font-semibold">
                                        {item.label}
                                    </p>
                                    <p className="truncate text-xs opacity-60">
                                        {item.sub}
                                    </p>
                                </div>
                                <ArrowRight className="ml-auto size-3.5 shrink-0 opacity-0 transition group-hover:opacity-60" />
                            </Link>
                        );
                    })}
                </div>
            </div>
        </>
    );
}
