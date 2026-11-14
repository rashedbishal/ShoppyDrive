import { Activity, Clock3, Filter, Truck } from 'lucide-react';
import { Cell, Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts';
import type { CourierRow, OrderHealth } from './types';
import {
    CountUp,
    Empty,
    MiniStat,
    money,
    number,
    Panel,
    PAYMENT_LABELS,
    percent,
    SERIES,
    STATUS_COLORS,
    STATUS_LABELS,
    TooltipBox,
} from './ui';

// ── Donut ─────────────────────────────────────────────────────────────────────

export type DonutSlice = {
    key: string;
    label: string;
    value: number;
    color: string;
    detail?: string;
};

export function Donut({
    slices,
    centerLabel,
    format = number,
}: {
    slices: DonutSlice[];
    centerLabel: string;
    format?: (n: number) => string;
}) {
    const total = slices.reduce((sum, slice) => sum + slice.value, 0);

    if (total === 0) {
        return <Empty>No data for this period yet</Empty>;
    }

    return (
        <div className="flex flex-col items-center gap-4 sm:flex-row">
            <div className="relative size-36 shrink-0">
                <ResponsiveContainer width="100%" height="100%">
                    <PieChart>
                        <Pie
                            data={slices}
                            dataKey="value"
                            nameKey="label"
                            innerRadius="66%"
                            outerRadius="100%"
                            paddingAngle={slices.length > 1 ? 2 : 0}
                            stroke="#fff"
                            strokeWidth={2}
                            animationDuration={900}
                        >
                            {slices.map((slice) => (
                                <Cell key={slice.key} fill={slice.color} />
                            ))}
                        </Pie>
                        <Tooltip
                            content={({ active, payload }) => {
                                if (!active || !payload?.length) {
                                    return null;
                                }

                                const slice = payload[0].payload as DonutSlice;

                                return (
                                    <TooltipBox
                                        title={slice.label}
                                        rows={[
                                            {
                                                label: 'Count',
                                                value: format(slice.value),
                                                color: slice.color,
                                            },
                                            {
                                                label: 'Share',
                                                value: percent(
                                                    (slice.value / total) * 100,
                                                ),
                                            },
                                        ]}
                                    />
                                );
                            }}
                        />
                    </PieChart>
                </ResponsiveContainer>
                <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                    <span className="text-lg font-bold text-slate-900 tabular-nums">
                        <CountUp value={total} format={format} />
                    </span>
                    <span className="text-[10px] text-slate-500">
                        {centerLabel}
                    </span>
                </div>
            </div>
            <ul className="w-full min-w-0 space-y-1.5">
                {slices.map((slice) => (
                    <li
                        key={slice.key}
                        className="flex items-center justify-between gap-2 text-xs"
                    >
                        <span className="flex min-w-0 items-center gap-2 text-slate-600">
                            <span
                                className="size-2.5 shrink-0 rounded-sm"
                                style={{ backgroundColor: slice.color }}
                            />
                            <span className="truncate">{slice.label}</span>
                        </span>
                        <span className="shrink-0 text-slate-900 tabular-nums">
                            <span className="font-semibold">
                                {format(slice.value)}
                            </span>
                            <span className="ml-1.5 text-slate-400">
                                {percent((slice.value / total) * 100)}
                            </span>
                            {slice.detail && (
                                <span className="block text-right text-[10px] text-slate-400">
                                    {slice.detail}
                                </span>
                            )}
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}

// ── Order health ──────────────────────────────────────────────────────────────

export function OrderHealthPanels({ data }: { data: OrderHealth }) {
    const statusSlices: DonutSlice[] = data.statuses.map((row) => ({
        key: row.status,
        label: STATUS_LABELS[row.status] ?? row.status,
        value: row.count,
        color: STATUS_COLORS[row.status] ?? '#94a3b8',
    }));
    const paymentSlices: DonutSlice[] = data.payment_methods.map(
        (row, index) => ({
            key: row.method,
            label: PAYMENT_LABELS[row.method] ?? row.method,
            value: row.orders,
            color: SERIES[index % SERIES.length],
            detail: money(row.amount),
        }),
    );
    // Visitors dwarf every later step, so the order steps are drawn against
    // orders placed; each label still shows the step-to-step percentage.
    const ordersPlaced = Math.max(data.funnel[1]?.count ?? 0, 1);

    return (
        <div className="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
            <Panel
                title="Order status"
                subtitle="Orders placed in this period"
                icon={Activity}
            >
                <Donut slices={statusSlices} centerLabel="orders" />
            </Panel>

            <Panel
                title="Payment methods"
                subtitle="Orders not cancelled"
                icon={Activity}
            >
                <Donut slices={paymentSlices} centerLabel="orders" />
            </Panel>

            <Panel
                title="Sales funnel"
                subtitle="From visit to delivery"
                icon={Filter}
                className="md:col-span-2 xl:col-span-1"
            >
                <div className="space-y-2.5">
                    {data.funnel.map((step, index) => {
                        const base =
                            index === 0
                                ? Math.max(step.count, 1)
                                : ordersPlaced;
                        const width = Math.max(
                            (step.count / base) * 100,
                            step.count > 0 ? 3 : 0,
                        );
                        const previous =
                            index > 0 ? data.funnel[index - 1].count : null;

                        return (
                            <div key={step.step}>
                                <div className="flex items-baseline justify-between text-xs">
                                    <span className="font-medium text-slate-700">
                                        {step.step}
                                    </span>
                                    <span className="text-slate-900 tabular-nums">
                                        <span className="font-semibold">
                                            {number(step.count)}
                                        </span>
                                        {previous !== null && previous > 0 && (
                                            <span className="ml-1.5 text-slate-400">
                                                {percent(
                                                    (step.count / previous) *
                                                        100,
                                                )}
                                            </span>
                                        )}
                                    </span>
                                </div>
                                <div className="mt-1 h-6 overflow-hidden rounded-lg bg-slate-50">
                                    <div
                                        className="dash-grow h-full rounded-lg"
                                        style={{
                                            width: `${width}%`,
                                            backgroundColor: 'var(--brand)',
                                            opacity: 1 - index * 0.18,
                                            animationDelay: `${index * 120}ms`,
                                        }}
                                    />
                                </div>
                            </div>
                        );
                    })}
                </div>
                <div className="mt-4 grid grid-cols-2 gap-2">
                    <MiniStat
                        label="Cancelled"
                        value={percent(data.cancellation_rate)}
                        sub={`${number(data.cancelled)} orders`}
                        tone={data.cancellation_rate > 15 ? 'red' : 'slate'}
                    />
                    <MiniStat
                        label="Returned"
                        value={percent(data.return_rate)}
                        sub={`of delivered (${number(data.returned)})`}
                        tone={data.return_rate > 10 ? 'amber' : 'slate'}
                    />
                    <MiniStat
                        label="Refunds paid"
                        value={money(data.refunds.amount)}
                        sub={`${number(data.refunds.count)} refund${data.refunds.count === 1 ? '' : 's'}`}
                    />
                    <MiniStat
                        label="Discounts given"
                        value={money(data.discounts.amount)}
                        sub={`on ${number(data.discounts.orders)} orders`}
                    />
                </div>
            </Panel>
        </div>
    );
}

// ── Couriers ──────────────────────────────────────────────────────────────────

const COURIER_NAMES: Record<string, string> = {
    pathao: 'Pathao',
    steadfast: 'Steadfast',
};

export function CourierPanel({ rows }: { rows: CourierRow[] }) {
    const anything = rows.some((row) => row.sent > 0);

    return (
        <Panel
            title="Courier performance"
            subtitle="Parcels booked in this period"
            icon={Truck}
            className="h-full"
        >
            {!anything ? (
                <Empty>
                    No parcels were booked with a courier in this period
                </Empty>
            ) : (
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    {rows.map((row) => (
                        <div
                            key={row.courier}
                            className="rounded-xl border border-slate-100 p-4"
                        >
                            <div className="flex items-center justify-between">
                                <p className="text-sm font-semibold text-slate-800">
                                    {COURIER_NAMES[row.courier]}
                                </p>
                                <span className="text-xs text-slate-500">
                                    {number(row.sent)} sent
                                </span>
                            </div>
                            <div className="mt-3 flex h-2.5 overflow-hidden rounded-full bg-slate-100">
                                {row.sent > 0 && (
                                    <>
                                        <div
                                            className="dash-grow h-full"
                                            style={{
                                                width: `${(row.delivered / row.sent) * 100}%`,
                                                backgroundColor: '#1baf7a',
                                            }}
                                            title="Delivered"
                                        />
                                        <div
                                            className="h-full"
                                            style={{
                                                width: `${(row.in_transit / row.sent) * 100}%`,
                                                backgroundColor: '#2a78d6',
                                                marginLeft:
                                                    row.delivered > 0 ? 2 : 0,
                                            }}
                                            title="In transit"
                                        />
                                        <div
                                            className="h-full"
                                            style={{
                                                width: `${(row.cancelled / row.sent) * 100}%`,
                                                backgroundColor: '#e34948',
                                                marginLeft: 2,
                                            }}
                                            title="Cancelled or returned"
                                        />
                                    </>
                                )}
                            </div>
                            <dl className="mt-3 grid grid-cols-3 gap-2 text-center">
                                <div>
                                    <dt className="text-[10px] text-slate-500">
                                        Delivered
                                    </dt>
                                    <dd className="text-sm font-bold text-emerald-700 tabular-nums">
                                        {percent(row.delivered_rate)}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-[10px] text-slate-500">
                                        Cancelled
                                    </dt>
                                    <dd className="text-sm font-bold text-red-600 tabular-nums">
                                        {percent(row.cancelled_rate)}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-[10px] text-slate-500">
                                        Avg. days
                                    </dt>
                                    <dd className="text-sm font-bold text-slate-800 tabular-nums">
                                        {row.average_days === null
                                            ? '—'
                                            : row.average_days}
                                    </dd>
                                </div>
                            </dl>
                            <p className="mt-2 text-[11px] text-slate-400">
                                {number(row.in_transit)} still on the way
                            </p>
                        </div>
                    ))}
                </div>
            )}
            {anything && (
                <div className="mt-3 flex flex-wrap gap-4 text-[11px] text-slate-500">
                    <span className="flex items-center gap-1.5">
                        <span className="size-2.5 rounded-sm bg-[#1baf7a]" />
                        Delivered
                    </span>
                    <span className="flex items-center gap-1.5">
                        <span className="size-2.5 rounded-sm bg-[#2a78d6]" />
                        On the way
                    </span>
                    <span className="flex items-center gap-1.5">
                        <span className="size-2.5 rounded-sm bg-[#e34948]" />
                        Cancelled / returned
                    </span>
                </div>
            )}
        </Panel>
    );
}

// ── Busiest hours heatmap ─────────────────────────────────────────────────────

const DAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

export function Heatmap({ grid }: { grid: number[][] }) {
    const max = Math.max(...grid.flat(), 0);
    let best = { day: 0, hour: 0, count: 0 };

    grid.forEach((row, day) =>
        row.forEach((count, hour) => {
            if (count > best.count) {
                best = { day, hour, count };
            }
        }),
    );

    const hourLabel = (hour: number) =>
        `${hour % 12 === 0 ? 12 : hour % 12}${hour < 12 ? 'am' : 'pm'}`;

    return (
        <Panel
            title="Busiest order times"
            subtitle={
                max > 0
                    ? `Peak: ${DAYS[best.day]} around ${hourLabel(best.hour)} (Dhaka time)`
                    : 'When customers order'
            }
            icon={Clock3}
            className="h-full"
        >
            {max === 0 ? (
                <Empty>No orders in this period yet</Empty>
            ) : (
                <div className="overflow-x-auto">
                    <div className="min-w-[520px]">
                        <div className="grid grid-cols-[36px_repeat(24,minmax(0,1fr))] gap-[3px]">
                            <span />
                            {Array.from({ length: 24 }, (_, hour) => (
                                <span
                                    key={hour}
                                    className="text-center text-[9px] text-slate-400"
                                >
                                    {hour % 3 === 0 ? hourLabel(hour) : ''}
                                </span>
                            ))}
                            {grid.map((row, day) => (
                                <div key={DAYS[day]} className="contents">
                                    <span className="flex items-center text-[10px] font-medium text-slate-500">
                                        {DAYS[day]}
                                    </span>
                                    {row.map((count, hour) => (
                                        <span
                                            key={hour}
                                            title={`${DAYS[day]} ${hourLabel(hour)}: ${count} order${count === 1 ? '' : 's'}`}
                                            className="aspect-square rounded-[4px] transition hover:ring-2 hover:ring-slate-900/30"
                                            style={{
                                                backgroundColor:
                                                    count === 0
                                                        ? '#f1f5f9'
                                                        : `rgb(var(--brand-rgb) / ${0.15 + (count / max) * 0.85})`,
                                            }}
                                        />
                                    ))}
                                </div>
                            ))}
                        </div>
                        <div className="mt-3 flex items-center justify-end gap-1.5 text-[10px] text-slate-400">
                            Fewer
                            {[0.15, 0.35, 0.55, 0.75, 1].map((alpha) => (
                                <span
                                    key={alpha}
                                    className="size-3 rounded-[3px]"
                                    style={{
                                        backgroundColor: `rgb(var(--brand-rgb) / ${alpha})`,
                                    }}
                                />
                            ))}
                            More
                        </div>
                    </div>
                </div>
            )}
        </Panel>
    );
}
