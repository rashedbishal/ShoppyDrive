import { Deferred, Head, Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    BadgePercent,
    CircleDollarSign,
    Gauge,
    Megaphone,
    MessageSquareText,
    Receipt,
    UserPlus,
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
import { useState } from 'react';
import {
    ProductPanels,
    PromotionPanels,
} from '@/components/admin/dashboard/catalog';
import {
    ActionNeededPanel,
    LiveOrders,
} from '@/components/admin/dashboard/operations';
import {
    CourierPanel,
    Heatmap,
    OrderHealthPanels,
} from '@/components/admin/dashboard/orders';
import {
    KpiCard,
    RangePicker,
    TargetRing,
    TrendChart,
} from '@/components/admin/dashboard/overview';
import {
    CustomerPanels,
    MarketingPanels,
    ReviewPanels,
} from '@/components/admin/dashboard/people';
import type {
    ActionNeeded,
    CourierRow,
    CustomerInsights,
    DashboardAccess,
    DashboardRange,
    LiveFeed,
    Marketing,
    OrderHealth,
    Overview,
    ProductInsights,
    Promotions,
    ReviewInsights,
} from '@/components/admin/dashboard/types';
import {
    CountUp,
    money,
    percent,
    Skeleton,
} from '@/components/admin/dashboard/ui';

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
    // 2026-11-14 additions; null when the admin can't open that area,
    // undefined until a deferred panel has loaded.
    range: DashboardRange;
    access: DashboardAccess;
    overview?: Overview | null;
    actionNeeded?: ActionNeeded;
    orderHealth?: OrderHealth | null;
    couriers?: CourierRow[] | null;
    productInsights?: ProductInsights | null;
    promotions?: Promotions | null;
    customerInsights?: CustomerInsights | null;
    marketing?: Marketing;
    reviewInsights?: ReviewInsights | null;
    liveFeed: LiveFeed | null;
};

/** Every prop that depends on the selected date range. */
const RANGE_PROPS = [
    'range',
    'overview',
    'actionNeeded',
    'orderHealth',
    'couriers',
    'productInsights',
    'promotions',
    'customerInsights',
    'marketing',
    'reviewInsights',
];

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
                    <p className="text-xs font-semibold tracking-widest text-slate-600 uppercase">
                        Revenue
                    </p>
                    <p className="mt-1 text-2xl font-bold tracking-tight text-slate-900">
                        {fmt(totalRevenue)}
                    </p>
                    <p className="mt-0.5 text-xs text-slate-600">
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
                                    className={`w-full rounded-t-lg transition-all duration-300 ${isToday ? 'bg-brand' : 'bg-slate-100 group-hover:bg-slate-200'}`}
                                    style={{ height: `${height}%` }}
                                />
                                <span
                                    className={`text-[10px] font-medium ${isToday ? 'text-brand' : 'text-slate-600'}`}
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
                    <p className="text-xs font-semibold tracking-widest text-slate-600 uppercase">
                        Visitors
                    </p>
                    <p className="mt-1 text-2xl font-bold tracking-tight text-slate-900">
                        {totalVisitors.toLocaleString()}
                    </p>
                    <p className="mt-0.5 text-xs text-slate-600">
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
                                    className={`text-[10px] font-medium ${isToday ? 'text-blue-600' : 'text-slate-600'}`}
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
    className = '',
}: {
    label: string;
    value: React.ReactNode;
    sub?: string;
    icon: React.ElementType;
    iconBg: string;
    iconColor: string;
    trend?: { value: string; up: boolean };
    className?: string;
}) {
    return (
        <div
            className={`min-w-0 rounded-2xl border border-slate-100 bg-white p-3 shadow-sm sm:p-5 ${className}`}
        >
            <div className="flex items-center justify-between">
                <div
                    className={`flex size-9 items-center justify-center rounded-xl sm:size-10 ${iconBg}`}
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
            <div className="mt-2.5 sm:mt-3">
                <p className="truncate text-xs font-medium text-slate-600">
                    {label}
                </p>
                <p className="mt-0.5 text-xl font-bold tracking-tight whitespace-nowrap text-slate-900 sm:text-2xl">
                    {value}
                </p>
                {sub && (
                    <p className="mt-0.5 text-[11px] leading-4 text-slate-600 sm:text-xs">
                        {sub}
                    </p>
                )}
            </div>
        </div>
    );
}

/** Today up to now against yesterday up to the same time. */
function TodaySoFar({
    today,
    wide = false,
}: {
    today: Overview['today'];
    wide?: boolean;
}) {
    const items = [
        {
            label: 'Collected',
            value: today.revenue,
            previous: today.yesterday_revenue,
            format: money,
        },
        {
            label: 'Orders',
            value: today.orders,
            previous: today.yesterday_orders,
            format: (n: number) => Math.round(n).toLocaleString('en-IN'),
        },
    ];

    return (
        <div className="dash-rise min-w-0 rounded-2xl border border-slate-100 bg-gradient-to-br from-white to-brand-light p-4 shadow-sm">
            <p className="flex items-center gap-1.5 text-xs font-semibold text-slate-600">
                <Clock className="size-3.5 text-brand" /> Today so far · until{' '}
                {today.as_of}
            </p>
            <div
                className={`mt-3 grid gap-3 ${wide ? 'grid-cols-2 sm:grid-cols-4' : 'grid-cols-1'}`}
            >
                {items.map((item) => {
                    const ahead = item.value >= item.previous;

                    return (
                        <div key={item.label} className="min-w-0">
                            <p className="text-[11px] text-slate-500">
                                {item.label}
                            </p>
                            <p className="truncate text-lg font-bold text-slate-900 tabular-nums">
                                <CountUp
                                    value={item.value}
                                    format={item.format}
                                />
                            </p>
                            <p
                                className={`text-[11px] font-medium ${ahead ? 'text-emerald-600' : 'text-red-500'}`}
                            >
                                {item.value === item.previous
                                    ? 'Same as'
                                    : ahead
                                      ? 'Ahead of'
                                      : 'Behind'}{' '}
                                yesterday ({item.format(item.previous)} by now)
                            </p>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

function SectionTitle({
    icon: Icon,
    title,
    subtitle,
}: {
    icon: React.ElementType;
    title: string;
    subtitle: string;
}) {
    return (
        <div className="flex items-end gap-3 pt-2">
            <span className="flex size-9 items-center justify-center rounded-xl bg-slate-900 text-white shadow-sm">
                <Icon className="size-4" />
            </span>
            <div>
                <h2 className="text-base font-bold tracking-tight text-slate-900">
                    {title}
                </h2>
                <p className="text-xs text-slate-500">{subtitle}</p>
            </div>
        </div>
    );
}

// ── Main page ──────────────────────────────────────────────────────────────────

export default function Dashboard({
    stats,
    chartData,
    visitorChartData,
    recentOrders,
    lowStockProducts,
    range,
    access,
    overview,
    actionNeeded,
    orderHealth,
    couriers,
    productInsights,
    promotions,
    customerInsights,
    marketing,
    reviewInsights,
    liveFeed,
}: Props) {
    const [loadingRange, setLoadingRange] = useState(false);

    const changeRange = (params: Record<string, string>) => {
        router.get(route('admin.dashboard'), params, {
            only: RANGE_PROPS,
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => setLoadingRange(true),
            onFinish: () => setLoadingRange(false),
        });
    };

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

            <div
                className={`space-y-6 pb-10 transition-opacity duration-300 ${loadingRange ? 'opacity-60' : ''}`}
                aria-busy={loadingRange}
            >
                {/* ── Header ── */}
                <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p className="text-[11px] font-semibold tracking-[0.18em] text-slate-600 uppercase">
                            Admin Panel
                        </p>
                        <h1 className="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                            Dashboard
                        </h1>
                        <p className="mt-1 text-sm text-slate-600">
                            {new Date().toLocaleDateString('en-US', {
                                weekday: 'long',
                                year: 'numeric',
                                month: 'long',
                                day: 'numeric',
                            })}
                            <span className="text-slate-400">
                                {' '}
                                · Showing {range.label}
                            </span>
                        </p>
                    </div>
                    <RangePicker
                        key={`${range.preset}-${range.from}-${range.to}`}
                        range={range}
                        loading={loadingRange}
                        onChange={changeRange}
                    />
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

                {/* ── Period KPIs + monthly target (Orders access) ── */}
                {access.orders && (
                    <Deferred
                        data="overview"
                        fallback={
                            <div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4 2xl:grid-cols-8">
                                {Array.from({ length: 7 }, (_, index) => (
                                    <Skeleton
                                        key={index}
                                        className={`h-44 ${index === 6 ? 'col-span-2' : ''}`}
                                    />
                                ))}
                            </div>
                        }
                    >
                        {overview ? (
                            <div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4 2xl:grid-cols-8">
                                <KpiCard
                                    label="Revenue collected"
                                    icon={CircleDollarSign}
                                    compare={overview.kpis.revenue}
                                    format={money}
                                    trend={overview.trend}
                                    trendKey="revenue"
                                />
                                <KpiCard
                                    label="Orders"
                                    icon={Receipt}
                                    compare={overview.kpis.orders}
                                    trend={overview.trend}
                                    trendKey="orders"
                                />
                                <KpiCard
                                    label="Average order value"
                                    icon={BadgePercent}
                                    compare={overview.kpis.average_order_value}
                                    format={money}
                                />
                                <KpiCard
                                    label="Visitors"
                                    icon={Eye}
                                    compare={overview.kpis.visitors}
                                    trend={
                                        range.days > 1
                                            ? overview.trend
                                            : undefined
                                    }
                                    trendKey="visitors"
                                />
                                <KpiCard
                                    label="Conversion rate"
                                    icon={Gauge}
                                    compare={overview.kpis.conversion_rate}
                                    format={percent}
                                    hint="Orders per 100 visitors"
                                />
                                {overview.kpis.new_customers ? (
                                    <KpiCard
                                        label="New customers"
                                        icon={UserPlus}
                                        compare={overview.kpis.new_customers}
                                        trend={overview.trend}
                                        trendKey="new_customers"
                                    />
                                ) : (
                                    <TodaySoFar today={overview.today} />
                                )}
                                {/* Two columns at every width: the ring and its text need ~400px side by side. */}
                                <div className="col-span-2">
                                    <TargetRing
                                        target={overview.target}
                                        canEdit={access.edit_target}
                                    />
                                </div>
                            </div>
                        ) : null}
                    </Deferred>
                )}

                {/* ── Today: the original cards, shown to every admin ── */}
                <div className="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-5">
                    <StatCard
                        label="Today's Visitors"
                        value={<CountUp value={stats.today_visitors} />}
                        sub={`Yesterday: ${stats.yesterday_visitors.toLocaleString()}`}
                        icon={Eye}
                        iconBg="bg-cyan-50"
                        iconColor="text-cyan-600"
                        trend={visitorsChange}
                    />
                    <StatCard
                        label="Today's Revenue"
                        value={
                            <CountUp value={stats.today_revenue} format={fmt} />
                        }
                        sub={`Yesterday: ${fmt(stats.yesterday_revenue)}`}
                        icon={ShoppingBag}
                        iconBg="bg-rose-50"
                        iconColor="text-brand"
                        trend={revenueChange}
                    />
                    <StatCard
                        label="Today's Orders"
                        value={<CountUp value={stats.today_orders} />}
                        sub={`${stats.pending_orders} pending · ${stats.processing_orders} processing`}
                        icon={Package}
                        iconBg="bg-blue-50"
                        iconColor="text-blue-600"
                        trend={ordersChange}
                    />
                    <StatCard
                        label="Total Customers"
                        value={<CountUp value={stats.total_customers} />}
                        sub={`+${stats.new_customers_this_week} this week`}
                        icon={Users}
                        iconBg="bg-violet-50"
                        iconColor="text-violet-600"
                    />
                    <StatCard
                        label="Active Products"
                        value={<CountUp value={stats.total_products} />}
                        sub={
                            stats.low_stock_count > 0
                                ? `${stats.low_stock_count} low stock`
                                : 'All stocked'
                        }
                        icon={Box}
                        iconBg="bg-emerald-50"
                        iconColor="text-emerald-600"
                        className="col-span-2 xl:col-span-1"
                    />
                </div>

                {/* ── Trend + needs action ── */}
                <div className="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1fr)_380px]">
                    {access.orders ? (
                        <Deferred
                            data="overview"
                            fallback={<Skeleton className="h-96" />}
                        >
                            {overview ? (
                                <div className="min-w-0 space-y-5">
                                    <TrendChart
                                        trend={overview.trend}
                                        rangeLabel={range.label}
                                        showCustomers={
                                            overview.kpis.new_customers !== null
                                        }
                                    />
                                    {overview.kpis.new_customers && (
                                        <TodaySoFar
                                            today={overview.today}
                                            wide
                                        />
                                    )}
                                </div>
                            ) : null}
                        </Deferred>
                    ) : (
                        <div className="min-w-0 space-y-5">
                            <RevenueChart data={chartData} />
                            <VisitorsChart data={visitorChartData} />
                        </div>
                    )}
                    <Deferred
                        data="actionNeeded"
                        fallback={<Skeleton className="h-96" />}
                    >
                        <ActionNeededPanel
                            data={actionNeeded}
                            fallback={{
                                pending_orders: stats.pending_orders,
                                low_stock: stats.low_stock_count,
                                pending_reviews: stats.pending_reviews,
                                failed_jobs: stats.failed_jobs,
                            }}
                        />
                    </Deferred>
                </div>

                {/* ── Live orders (or the original recent orders) + low stock + quick actions ── */}
                <div className="grid grid-cols-1 gap-5 lg:grid-cols-[minmax(0,1fr)_360px]">
                    {liveFeed ? (
                        <LiveOrders initial={liveFeed} />
                    ) : (
                        <div className="rounded-2xl border border-slate-100 bg-white shadow-sm">
                            <div className="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                                <p className="text-sm font-semibold text-slate-800">
                                    Recent Orders
                                </p>
                                <Link
                                    href={route('admin.orders.index')}
                                    className="flex items-center gap-1 text-xs font-medium text-brand hover:underline"
                                >
                                    View all <ArrowRight className="size-3" />
                                </Link>
                            </div>

                            {recentOrders.length === 0 ? (
                                <div className="flex flex-col items-center justify-center gap-2 py-14">
                                    <div className="flex size-12 items-center justify-center rounded-2xl bg-slate-100">
                                        <ShoppingCart className="size-5 text-slate-300" />
                                    </div>
                                    <p className="text-sm font-medium text-slate-600">
                                        No orders yet
                                    </p>
                                    <p className="text-xs text-slate-300">
                                        Orders will appear here once customers
                                        start buying
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
                                                    <p className="truncate text-[11px] text-slate-600">
                                                        {order.user?.name ??
                                                            'Guest'}
                                                    </p>
                                                </div>
                                                <div className="text-right">
                                                    <p className="text-sm font-bold text-slate-800">
                                                        {fmt(order.total)}
                                                    </p>
                                                    <p className="text-[11px] text-slate-600">
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
                    )}

                    <div className="min-w-0 space-y-5">
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
                                    className="flex items-center gap-1 text-xs font-medium text-brand hover:underline"
                                >
                                    Manage <ArrowRight className="size-3" />
                                </Link>
                            </div>

                            {lowStockProducts.length === 0 ? (
                                <div className="flex flex-col items-center justify-center gap-2 py-14">
                                    <div className="flex size-12 items-center justify-center rounded-2xl bg-emerald-50">
                                        <CheckCircle2 className="size-5 text-emerald-400" />
                                    </div>
                                    <p className="text-sm font-medium text-slate-600">
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
                                                    <p className="font-mono text-[11px] text-slate-600">
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

                        <div className="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
                            <p className="mb-4 text-xs font-semibold tracking-widest text-slate-600 uppercase">
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
                </div>

                {/* ── Sales ── */}
                {access.orders && (
                    <>
                        <SectionTitle
                            icon={Receipt}
                            title="Sales & orders"
                            subtitle={`How orders did · ${range.label}`}
                        />
                        <Deferred
                            data={['orderHealth', 'couriers']}
                            fallback={
                                <div className="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
                                    <Skeleton className="h-80" />
                                    <Skeleton className="h-80" />
                                    <Skeleton className="h-80" />
                                </div>
                            }
                        >
                            <>
                                {orderHealth && (
                                    <OrderHealthPanels data={orderHealth} />
                                )}
                                <div className="mt-5 grid grid-cols-1 gap-5 xl:grid-cols-2">
                                    {couriers && (
                                        <CourierPanel rows={couriers} />
                                    )}
                                    <Deferred
                                        data="marketing"
                                        fallback={<Skeleton className="h-72" />}
                                    >
                                        {marketing?.heatmap ? (
                                            <Heatmap grid={marketing.heatmap} />
                                        ) : (
                                            <></>
                                        )}
                                    </Deferred>
                                </div>
                            </>
                        </Deferred>
                    </>
                )}

                {/* ── Products ── */}
                {access.products && (
                    <>
                        <SectionTitle
                            icon={Box}
                            title="Products & stock"
                            subtitle={`What sells and what doesn't · ${range.label}`}
                        />
                        <Deferred
                            data="productInsights"
                            fallback={<Skeleton className="h-96" />}
                        >
                            {productInsights ? (
                                <ProductPanels data={productInsights} />
                            ) : (
                                <></>
                            )}
                        </Deferred>
                    </>
                )}

                {access.promotions && (
                    <>
                        <SectionTitle
                            icon={Zap}
                            title="Promotions"
                            subtitle={`Flash sales, coupons and bundles · ${range.label}`}
                        />
                        <Deferred
                            data="promotions"
                            fallback={<Skeleton className="h-64" />}
                        >
                            {promotions ? (
                                <PromotionPanels data={promotions} />
                            ) : (
                                <></>
                            )}
                        </Deferred>
                    </>
                )}

                {/* ── Customers ── */}
                {access.customers && access.orders && (
                    <>
                        <SectionTitle
                            icon={Users}
                            title="Customers"
                            subtitle={`Who buys and where · ${range.label}`}
                        />
                        <Deferred
                            data="customerInsights"
                            fallback={<Skeleton className="h-96" />}
                        >
                            {customerInsights ? (
                                <CustomerPanels data={customerInsights} />
                            ) : (
                                <></>
                            )}
                        </Deferred>
                    </>
                )}

                {/* ── Marketing ── */}
                <SectionTitle
                    icon={Megaphone}
                    title="Marketing & traffic"
                    subtitle={`Where shoppers come from and what they look for · ${range.label}`}
                />
                <Deferred
                    data="marketing"
                    fallback={<Skeleton className="h-72" />}
                >
                    {marketing ? <MarketingPanels data={marketing} /> : <></>}
                </Deferred>

                {/* ── Reviews ── */}
                {access.reviews && (
                    <>
                        <SectionTitle
                            icon={MessageSquareText}
                            title="Reviews"
                            subtitle="What customers say"
                        />
                        <Deferred
                            data="reviewInsights"
                            fallback={<Skeleton className="h-72" />}
                        >
                            {reviewInsights ? (
                                <ReviewPanels data={reviewInsights} />
                            ) : (
                                <></>
                            )}
                        </Deferred>
                    </>
                )}

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
