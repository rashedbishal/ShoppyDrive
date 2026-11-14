import { Link } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowRight,
    Banknote,
    BellRing,
    Box,
    CheckCircle2,
    Clock,
    HelpCircle,
    PackageX,
    RotateCcw,
    ServerCrash,
    ShoppingCart,
    Smartphone,
    Star,
    Truck,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { ActionNeeded, LiveFeed, LiveOrder } from './types';
import {
    money,
    Panel,
    PAYMENT_LABELS,
    STATUS_COLORS,
    STATUS_LABELS,
    timeAgo,
} from './ui';

// ── Needs action now ──────────────────────────────────────────────────────────

type ActionItem = {
    key: string;
    label: string;
    count: number;
    detail?: string;
    href: string;
    icon: React.ElementType;
    tone: 'red' | 'amber' | 'blue' | 'violet';
};

const TONES: Record<ActionItem['tone'], string> = {
    red: 'bg-red-50 text-red-600',
    amber: 'bg-amber-50 text-amber-600',
    blue: 'bg-blue-50 text-blue-600',
    violet: 'bg-violet-50 text-violet-600',
};

/**
 * `fallback` holds the original dashboard's always-visible counts, so an
 * admin who saw "Pending orders" before still does.
 */
export function ActionNeededPanel({
    data,
    fallback,
}: {
    data: ActionNeeded | undefined;
    fallback: {
        pending_orders: number;
        low_stock: number;
        pending_reviews: number;
        failed_jobs: number | null;
    };
}) {
    const items: ActionItem[] = [];
    const push = (
        item: Omit<ActionItem, 'count'> & { count: number | null | undefined },
    ) => {
        if (item.count) {
            items.push({ ...item, count: item.count });
        }
    };

    push({
        key: 'courier_unknown',
        label: 'Courier bookings with an unknown result',
        count: data?.courier_unknown,
        detail: 'Check in the courier panel before sending again',
        href: route('admin.orders.index') + '?status=processing',
        icon: HelpCircle,
        tone: 'red',
    });
    push({
        key: 'failed_jobs',
        label: 'Failed background jobs',
        count: fallback.failed_jobs,
        href: route('admin.failed-jobs.index'),
        icon: ServerCrash,
        tone: 'red',
    });
    push({
        key: 'pending_orders',
        label: 'Pending orders',
        count: data?.pending_orders ?? fallback.pending_orders,
        detail: 'Waiting to be confirmed',
        href: route('admin.orders.index') + '?status=pending',
        icon: Clock,
        tone: 'amber',
    });
    push({
        key: 'mobile_payments',
        label: 'bKash / Nagad payments to check',
        count: data?.mobile_payments_to_check,
        href: route('admin.orders.index') + '?payment_status=pending',
        icon: Smartphone,
        tone: 'amber',
    });
    push({
        key: 'processing_not_sent',
        label: 'Processing, not sent to a courier',
        count: data?.processing_not_sent,
        href: route('admin.orders.index') + '?status=processing',
        icon: Truck,
        tone: 'blue',
    });
    push({
        key: 'pending_returns',
        label: 'Return requests to review',
        count: data?.pending_returns,
        href: route('admin.returns.index'),
        icon: RotateCcw,
        tone: 'violet',
    });
    push({
        key: 'out_of_stock',
        label: 'Products out of stock',
        count: data?.out_of_stock,
        href: route('admin.stock.index'),
        icon: PackageX,
        tone: 'red',
    });
    push({
        key: 'low_stock',
        label: 'Products low on stock',
        count: data?.low_stock ?? fallback.low_stock,
        href: route('admin.products.index'),
        icon: Box,
        tone: 'amber',
    });
    push({
        key: 'pending_reviews',
        label: 'Reviews waiting for approval',
        count: data?.pending_reviews ?? fallback.pending_reviews,
        href: route('admin.reviews.index') + '?status=pending',
        icon: Star,
        tone: 'violet',
    });
    push({
        key: 'abandoned',
        label: 'Abandoned checkouts (last 7 days)',
        count: data?.abandoned_checkouts,
        detail: 'Customers who left before ordering',
        href: route('admin.orders.incomplete.index'),
        icon: ShoppingCart,
        tone: 'blue',
    });

    const cod = data?.cod_with_courier;

    return (
        <Panel
            title="Needs action now"
            subtitle="Always up to date"
            icon={BellRing}
            className="h-full"
            bodyClassName="p-3"
        >
            {cod && cod.orders > 0 && (
                <div className="mb-2 flex items-center gap-3 rounded-xl border border-emerald-100 bg-emerald-50/60 px-3 py-2.5">
                    <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700">
                        <Banknote className="size-4" />
                    </span>
                    <div className="min-w-0 flex-1">
                        <p className="text-xs font-medium text-emerald-900">
                            Cash on delivery still with couriers
                        </p>
                        <p className="text-[11px] text-emerald-700">
                            {cod.orders} parcel{cod.orders === 1 ? '' : 's'} not
                            yet paid
                        </p>
                    </div>
                    <span className="text-sm font-bold text-emerald-900 tabular-nums">
                        {money(cod.amount)}
                    </span>
                </div>
            )}
            {items.length === 0 ? (
                <div className="flex flex-col items-center justify-center gap-2 py-10 text-center">
                    <span className="flex size-11 items-center justify-center rounded-2xl bg-emerald-50">
                        <CheckCircle2 className="size-5 text-emerald-500" />
                    </span>
                    <p className="text-sm font-medium text-slate-700">
                        All clear
                    </p>
                    <p className="text-xs text-slate-400">
                        Nothing is waiting on you right now
                    </p>
                </div>
            ) : (
                <ul className="space-y-1">
                    {items.map((item) => {
                        const Icon = item.icon;

                        return (
                            <li key={item.key}>
                                <Link
                                    href={item.href}
                                    className="group flex items-center gap-3 rounded-xl px-2 py-2 transition hover:bg-slate-50"
                                >
                                    <span
                                        className={`flex size-8 shrink-0 items-center justify-center rounded-lg ${TONES[item.tone]}`}
                                    >
                                        <Icon className="size-4" />
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <span className="block truncate text-xs font-medium text-slate-800">
                                            {item.label}
                                        </span>
                                        {item.detail && (
                                            <span className="block truncate text-[11px] text-slate-400">
                                                {item.detail}
                                            </span>
                                        )}
                                    </span>
                                    <span className="rounded-full bg-slate-900 px-2 py-0.5 text-xs font-bold text-white tabular-nums">
                                        {item.count}
                                    </span>
                                    <ArrowRight className="size-3.5 shrink-0 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-slate-500" />
                                </Link>
                            </li>
                        );
                    })}
                </ul>
            )}
        </Panel>
    );
}

// ── Live orders ───────────────────────────────────────────────────────────────

const POLL_MS = 5000;

/** Polls the live feed every 5 seconds while the tab is visible. */
export function LiveOrders({ initial }: { initial: LiveFeed }) {
    const [feed, setFeed] = useState(initial);
    const [fresh, setFresh] = useState<Set<number>>(new Set());
    const [now, setNow] = useState(() => Date.now());
    const [offline, setOffline] = useState(false);
    const known = useRef(new Set(initial.orders.map((order) => order.id)));

    useEffect(() => {
        let cancelled = false;
        let timer: ReturnType<typeof setTimeout>;

        const poll = async () => {
            if (document.visibilityState === 'visible') {
                try {
                    const response = await fetch(
                        route('admin.dashboard.live'),
                        {
                            headers: {
                                Accept: 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'same-origin',
                        },
                    );

                    if (!response.ok) {
                        throw new Error(String(response.status));
                    }

                    const next = (await response.json()) as LiveFeed;

                    if (cancelled) {
                        return;
                    }

                    const added = next.orders
                        .map((order) => order.id)
                        .filter((id) => !known.current.has(id));
                    added.forEach((id) => known.current.add(id));

                    if (added.length > 0) {
                        setFresh(new Set(added));
                    }

                    setFeed(next);
                    setOffline(false);
                } catch {
                    if (!cancelled) {
                        setOffline(true);
                    }
                }
            }

            setNow(Date.now());

            if (!cancelled) {
                timer = setTimeout(poll, POLL_MS);
            }
        };

        timer = setTimeout(poll, POLL_MS);

        return () => {
            cancelled = true;
            clearTimeout(timer);
        };
    }, []);

    return (
        <Panel
            title="Live orders"
            subtitle={`${feed.today_orders} order${feed.today_orders === 1 ? '' : 's'} today`}
            icon={ShoppingCart}
            className="h-full"
            bodyClassName=""
            action={
                <div className="flex items-center gap-3">
                    <span
                        className={`flex items-center gap-1.5 text-[11px] font-semibold ${offline ? 'text-amber-600' : 'text-emerald-600'}`}
                    >
                        {offline ? (
                            <AlertTriangle className="size-3" />
                        ) : (
                            <span className="relative flex size-2">
                                <span className="dash-ping absolute inline-flex size-full rounded-full bg-emerald-400" />
                                <span className="relative inline-flex size-2 rounded-full bg-emerald-500" />
                            </span>
                        )}
                        {offline ? 'Reconnecting' : 'Live'}
                    </span>
                    <Link
                        href={route('admin.orders.index')}
                        className="flex items-center gap-1 text-xs font-medium text-brand hover:underline"
                    >
                        All <ArrowRight className="size-3" />
                    </Link>
                </div>
            }
        >
            {feed.orders.length === 0 ? (
                <p className="py-14 text-center text-sm text-slate-400">
                    New orders will appear here the moment they're placed
                </p>
            ) : (
                <ul className="divide-y divide-slate-50">
                    {feed.orders.map((order) => (
                        <LiveOrderRow
                            key={order.id}
                            order={order}
                            fresh={fresh.has(order.id)}
                            now={now}
                        />
                    ))}
                </ul>
            )}
        </Panel>
    );
}

function LiveOrderRow({
    order,
    fresh,
    now,
}: {
    order: LiveOrder;
    fresh: boolean;
    now: number;
}) {
    const color = STATUS_COLORS[order.status] ?? '#94a3b8';

    return (
        <li className={fresh ? 'dash-flash' : undefined}>
            <Link
                href={route('admin.orders.show', order.id)}
                className="flex items-center gap-3 px-5 py-3 transition hover:bg-slate-50/70"
            >
                <span
                    className="size-2.5 shrink-0 rounded-full"
                    style={{ backgroundColor: color }}
                    aria-hidden
                />
                <div className="min-w-0 flex-1">
                    <p className="flex items-center gap-2 font-mono text-xs font-semibold text-slate-800">
                        {order.order_number}
                        {fresh && (
                            <span className="rounded-full bg-brand px-1.5 py-0.5 font-sans text-[9px] font-bold tracking-wide text-brand-foreground uppercase">
                                New
                            </span>
                        )}
                    </p>
                    <p className="truncate text-[11px] text-slate-500">
                        {order.customer} · {order.district} ·{' '}
                        {PAYMENT_LABELS[order.payment_method] ??
                            order.payment_method}
                    </p>
                </div>
                <div className="shrink-0 text-right">
                    <p className="text-sm font-bold text-slate-800 tabular-nums">
                        {money(order.total)}
                    </p>
                    <p className="text-[11px] text-slate-400">
                        {timeAgo(order.created_at, now)}
                    </p>
                </div>
                <span
                    className="hidden shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold sm:inline"
                    style={{
                        backgroundColor: `${color}1a`,
                        color: '#334155',
                    }}
                >
                    {STATUS_LABELS[order.status] ?? order.status}
                </span>
            </Link>
        </li>
    );
}
