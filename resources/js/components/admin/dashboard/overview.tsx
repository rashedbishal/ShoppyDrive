import { useForm } from '@inertiajs/react';
import { CalendarDays, Pencil, Target, TrendingUp } from 'lucide-react';
import { useState } from 'react';
import {
    Area,
    AreaChart,
    CartesianGrid,
    ComposedChart,
    Line,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type {
    Compare,
    DashboardRange,
    MonthlyTarget,
    TrendPoint,
} from './types';
import {
    Change,
    compactMoney,
    CountUp,
    money,
    number,
    Panel,
    TooltipBox,
} from './ui';

// ── Date range picker ─────────────────────────────────────────────────────────

const PRESETS: { value: DashboardRange['preset']; label: string }[] = [
    { value: 'today', label: 'Today' },
    { value: '7d', label: '7 days' },
    { value: '30d', label: '30 days' },
    { value: '90d', label: '90 days' },
    { value: 'month', label: 'This month' },
];

export function RangePicker({
    range,
    loading,
    onChange,
}: {
    range: DashboardRange;
    loading: boolean;
    onChange: (params: Record<string, string>) => void;
}) {
    const [customOpen, setCustomOpen] = useState(false);
    const [from, setFrom] = useState(range.from);
    const [to, setTo] = useState(range.to);
    const today = new Date().toLocaleDateString('en-CA', {
        timeZone: 'Asia/Dhaka',
    });

    return (
        <div className="flex flex-col items-stretch gap-2 sm:items-end">
            <div className="flex flex-wrap items-center gap-1 rounded-xl border border-slate-200 bg-white p-1 shadow-sm">
                {PRESETS.map((preset) => (
                    <button
                        key={preset.value}
                        type="button"
                        disabled={loading}
                        onClick={() => {
                            setCustomOpen(false);
                            onChange({ range: preset.value });
                        }}
                        className={`rounded-lg px-2.5 py-1.5 text-xs font-semibold transition sm:px-3 ${
                            range.preset === preset.value
                                ? 'bg-brand text-brand-foreground shadow-sm'
                                : 'text-slate-600 hover:bg-slate-100'
                        }`}
                    >
                        {preset.label}
                    </button>
                ))}
                <button
                    type="button"
                    disabled={loading}
                    onClick={() => setCustomOpen((open) => !open)}
                    aria-expanded={customOpen}
                    className={`flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-semibold transition sm:px-3 ${
                        range.preset === 'custom'
                            ? 'bg-brand text-brand-foreground shadow-sm'
                            : 'text-slate-600 hover:bg-slate-100'
                    }`}
                >
                    <CalendarDays className="size-3.5" /> Custom
                </button>
            </div>
            {customOpen && (
                <form
                    className="dash-rise flex flex-wrap items-end gap-2 rounded-xl border border-slate-200 bg-white p-2 shadow-sm"
                    onSubmit={(event) => {
                        event.preventDefault();
                        setCustomOpen(false);
                        onChange({ range: 'custom', from, to });
                    }}
                >
                    <label className="text-[11px] font-medium text-slate-500">
                        From
                        <input
                            type="date"
                            value={from}
                            max={today}
                            onChange={(event) => setFrom(event.target.value)}
                            className="mt-0.5 block h-8 rounded-lg border border-slate-200 px-2 text-xs text-slate-800 focus:ring-1 focus:ring-brand focus:outline-none"
                        />
                    </label>
                    <label className="text-[11px] font-medium text-slate-500">
                        To
                        <input
                            type="date"
                            value={to}
                            max={today}
                            onChange={(event) => setTo(event.target.value)}
                            className="mt-0.5 block h-8 rounded-lg border border-slate-200 px-2 text-xs text-slate-800 focus:ring-1 focus:ring-brand focus:outline-none"
                        />
                    </label>
                    <Button
                        type="submit"
                        size="sm"
                        disabled={!from || !to}
                        className="h-8 bg-brand text-brand-foreground hover:bg-brand-hover"
                    >
                        Apply
                    </Button>
                </form>
            )}
        </div>
    );
}

// ── KPI cards ─────────────────────────────────────────────────────────────────

function Sparkline({
    data,
    dataKey,
    id,
}: {
    data: TrendPoint[];
    dataKey: keyof TrendPoint;
    id: string;
}) {
    if (data.length < 2) {
        return null;
    }

    return (
        <div className="h-10" aria-hidden>
            <ResponsiveContainer width="100%" height="100%">
                <AreaChart
                    data={data}
                    margin={{ top: 2, right: 0, bottom: 0, left: 0 }}
                >
                    <defs>
                        <linearGradient id={id} x1="0" y1="0" x2="0" y2="1">
                            <stop
                                offset="0%"
                                stopColor="var(--brand)"
                                stopOpacity={0.28}
                            />
                            <stop
                                offset="100%"
                                stopColor="var(--brand)"
                                stopOpacity={0}
                            />
                        </linearGradient>
                    </defs>
                    <Area
                        type="monotone"
                        dataKey={dataKey as string}
                        stroke="var(--brand)"
                        strokeWidth={2}
                        fill={`url(#${id})`}
                        isAnimationActive
                        animationDuration={900}
                    />
                </AreaChart>
            </ResponsiveContainer>
        </div>
    );
}

export function KpiCard({
    label,
    icon: Icon,
    compare,
    format = number,
    inverse = false,
    trend,
    trendKey,
    hint,
}: {
    label: string;
    icon: React.ElementType;
    compare: Compare;
    format?: (n: number) => string;
    inverse?: boolean;
    trend?: TrendPoint[];
    trendKey?: keyof TrendPoint;
    hint?: string;
}) {
    return (
        <div className="dash-rise group relative min-w-0 overflow-hidden rounded-2xl border border-slate-100 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
            <div className="flex items-center justify-between gap-2">
                <span className="flex size-8 items-center justify-center rounded-xl bg-brand-light text-brand">
                    <Icon className="size-4" />
                </span>
                <Change
                    value={compare.value}
                    previous={compare.previous}
                    inverse={inverse}
                />
            </div>
            <p className="mt-3 truncate text-xs font-medium text-slate-500">
                {label}
            </p>
            <p className="mt-0.5 truncate text-xl font-bold tracking-tight text-slate-900 tabular-nums sm:text-2xl">
                <CountUp value={compare.value} format={format} />
            </p>
            <p className="truncate text-[11px] text-slate-400">
                {hint ?? `Before: ${format(compare.previous)}`}
            </p>
            {trend && trendKey && (
                <div className="-mx-1 mt-2">
                    <Sparkline
                        data={trend}
                        dataKey={trendKey}
                        id={`spark-${String(trendKey)}`}
                    />
                </div>
            )}
        </div>
    );
}

// ── Monthly target ring ───────────────────────────────────────────────────────

export function TargetRing({
    target,
    canEdit,
}: {
    target: MonthlyTarget;
    canEdit: boolean;
}) {
    const [editing, setEditing] = useState(false);
    const progress =
        target.target && target.target > 0
            ? Math.min(target.month_revenue / target.target, 1)
            : 0;
    const radius = 46;
    const circumference = 2 * Math.PI * radius;
    const onTrack = target.target !== null && target.projected >= target.target;

    return (
        <div className="dash-rise flex h-full min-w-0 flex-col rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
            <div className="flex items-center justify-between">
                <p className="flex items-center gap-1.5 text-xs font-semibold text-slate-600">
                    <Target className="size-4 text-brand" /> Monthly target
                </p>
                {canEdit && (
                    <button
                        type="button"
                        onClick={() => setEditing(true)}
                        className="rounded-lg p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                        aria-label="Set the monthly target"
                        title="Set the monthly target"
                    >
                        <Pencil className="size-3.5" />
                    </button>
                )}
            </div>

            {target.target === null ? (
                <div className="flex flex-1 flex-col items-center justify-center gap-2 py-3 text-center">
                    <p className="text-lg font-bold text-slate-900 tabular-nums">
                        {money(target.month_revenue)}
                    </p>
                    <p className="text-xs text-slate-500">
                        collected in {target.month_label}
                    </p>
                    {canEdit ? (
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() => setEditing(true)}
                        >
                            Set a target
                        </Button>
                    ) : (
                        <p className="text-[11px] text-slate-400">
                            No target set yet
                        </p>
                    )}
                </div>
            ) : (
                <div className="flex flex-1 items-center gap-4 pt-2">
                    <div className="relative size-28 shrink-0">
                        <svg
                            viewBox="0 0 112 112"
                            className="size-full -rotate-90"
                        >
                            <circle
                                cx="56"
                                cy="56"
                                r={radius}
                                fill="none"
                                stroke="var(--brand-light)"
                                strokeWidth="10"
                            />
                            <circle
                                cx="56"
                                cy="56"
                                r={radius}
                                fill="none"
                                stroke="var(--brand)"
                                strokeWidth="10"
                                strokeLinecap="round"
                                strokeDasharray={circumference}
                                strokeDashoffset={
                                    circumference * (1 - progress)
                                }
                                style={{
                                    transition:
                                        'stroke-dashoffset 1.2s cubic-bezier(0.22, 1, 0.36, 1)',
                                }}
                            />
                        </svg>
                        <div className="absolute inset-0 flex flex-col items-center justify-center">
                            <span className="text-xl font-bold text-slate-900 tabular-nums">
                                <CountUp
                                    value={progress * 100}
                                    format={(n) => `${Math.round(n)}%`}
                                />
                            </span>
                            <span className="text-[10px] text-slate-500">
                                reached
                            </span>
                        </div>
                    </div>
                    <div className="min-w-0 space-y-1 text-xs">
                        <p className="text-slate-500">{target.month_label}</p>
                        <p className="font-semibold text-slate-900 tabular-nums">
                            {money(target.month_revenue)}{' '}
                            <span className="font-normal text-slate-400">
                                of {money(target.target)}
                            </span>
                        </p>
                        <p className="text-slate-500">
                            Day {target.day_of_month} of {target.days_in_month}
                        </p>
                        <p
                            className={`font-semibold ${onTrack ? 'text-emerald-600' : 'text-amber-600'}`}
                        >
                            {onTrack ? 'On track' : 'Behind pace'} · heading for{' '}
                            {compactMoney(target.projected)}
                        </p>
                    </div>
                </div>
            )}

            {canEdit && (
                <TargetDialog
                    open={editing}
                    current={target.target}
                    onClose={() => setEditing(false)}
                />
            )}
        </div>
    );
}

function TargetDialog({
    open,
    current,
    onClose,
}: {
    open: boolean;
    current: number | null;
    onClose: () => void;
}) {
    const { data, setData, put, processing, errors, transform } = useForm<{
        target: string;
    }>({ target: current ? String(current) : '' });

    const save = (value: string) => {
        // An empty value removes the target.
        transform(() => ({ target: value === '' ? null : value }));
        put(route('admin.dashboard.target'), {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Monthly revenue target</DialogTitle>
                    <DialogDescription>
                        The amount of money you aim to collect each month. The
                        ring compares it with this month's collected revenue.
                    </DialogDescription>
                </DialogHeader>
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        save(data.target);
                    }}
                    className="space-y-3"
                >
                    <label className="block text-sm font-medium text-slate-700">
                        Target (৳)
                        <input
                            type="number"
                            min={0}
                            step="any"
                            inputMode="decimal"
                            value={data.target}
                            onChange={(event) =>
                                setData('target', event.target.value)
                            }
                            placeholder="e.g. 500000"
                            className="mt-1 block h-10 w-full rounded-lg border border-slate-200 px-3 text-sm focus:ring-1 focus:ring-brand focus:outline-none"
                        />
                    </label>
                    {errors.target && (
                        <p className="text-xs text-red-500">{errors.target}</p>
                    )}
                    <DialogFooter className="gap-2 sm:justify-between">
                        {current !== null ? (
                            <Button
                                type="button"
                                variant="outline"
                                disabled={processing}
                                onClick={() => save('')}
                            >
                                Remove target
                            </Button>
                        ) : (
                            <span />
                        )}
                        <Button
                            type="submit"
                            disabled={processing || data.target === ''}
                            className="bg-brand text-brand-foreground hover:bg-brand-hover"
                        >
                            {processing ? 'Saving…' : 'Save target'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

// ── Trend chart ───────────────────────────────────────────────────────────────

type TrendMetric = 'revenue' | 'orders' | 'visitors' | 'new_customers';

const METRICS: {
    key: TrendMetric;
    label: string;
    previous?: keyof TrendPoint;
    format: (n: number) => string;
}[] = [
    {
        key: 'revenue',
        label: 'Revenue',
        previous: 'previous_revenue',
        format: money,
    },
    {
        key: 'orders',
        label: 'Orders',
        previous: 'previous_orders',
        format: number,
    },
    { key: 'visitors', label: 'Visitors', format: number },
    { key: 'new_customers', label: 'New customers', format: number },
];

export function TrendChart({
    trend,
    rangeLabel,
    showCustomers,
}: {
    trend: TrendPoint[];
    rangeLabel: string;
    showCustomers: boolean;
}) {
    const [metric, setMetric] = useState<TrendMetric>('revenue');
    const hourly = trend.length === 24 && trend[0]?.visitors === null;
    const available = METRICS.filter(
        (m) =>
            (m.key !== 'visitors' || !hourly) &&
            (m.key !== 'new_customers' || showCustomers),
    );
    const active = available.find((m) => m.key === metric) ?? available[0];
    const total = trend.reduce(
        (sum, point) => sum + Number(point[active.key] ?? 0),
        0,
    );
    const previousTotal = active.previous
        ? trend.reduce(
              (sum, point) => sum + Number(point[active.previous!] ?? 0),
              0,
          )
        : null;

    return (
        <Panel
            title={`${active.label} trend`}
            subtitle={rangeLabel}
            icon={TrendingUp}
            action={
                <div className="flex flex-wrap justify-end gap-1">
                    {available.map((m) => (
                        <button
                            key={m.key}
                            type="button"
                            onClick={() => setMetric(m.key)}
                            className={`rounded-lg px-2 py-1 text-[11px] font-semibold transition ${
                                active.key === m.key
                                    ? 'bg-slate-900 text-white'
                                    : 'text-slate-500 hover:bg-slate-100'
                            }`}
                        >
                            {m.label}
                        </button>
                    ))}
                </div>
            }
        >
            <div className="mb-3 flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <p className="text-2xl font-bold text-slate-900 tabular-nums">
                    <CountUp value={total} format={active.format} />
                </p>
                {previousTotal !== null && (
                    <>
                        <Change value={total} previous={previousTotal} />
                        <span className="text-xs text-slate-400">
                            previous period {active.format(previousTotal)}
                        </span>
                    </>
                )}
            </div>
            <div className="h-64">
                <ResponsiveContainer width="100%" height="100%">
                    <ComposedChart
                        data={trend}
                        margin={{ top: 8, right: 8, bottom: 0, left: 0 }}
                    >
                        <defs>
                            <linearGradient
                                id="trend-fill"
                                x1="0"
                                y1="0"
                                x2="0"
                                y2="1"
                            >
                                <stop
                                    offset="0%"
                                    stopColor="var(--brand)"
                                    stopOpacity={0.25}
                                />
                                <stop
                                    offset="100%"
                                    stopColor="var(--brand)"
                                    stopOpacity={0}
                                />
                            </linearGradient>
                        </defs>
                        <CartesianGrid stroke="#f1f5f9" vertical={false} />
                        <XAxis
                            dataKey="label"
                            tick={{ fontSize: 11, fill: '#64748b' }}
                            tickLine={false}
                            axisLine={false}
                            minTickGap={16}
                        />
                        <YAxis
                            tick={{ fontSize: 11, fill: '#64748b' }}
                            tickLine={false}
                            axisLine={false}
                            width={52}
                            allowDecimals={false}
                            tickFormatter={(value: number) =>
                                active.key === 'revenue'
                                    ? compactMoney(value)
                                    : number(value)
                            }
                        />
                        <Tooltip
                            cursor={{ stroke: '#cbd5e1', strokeWidth: 1 }}
                            content={({ active: hovering, payload, label }) => {
                                if (!hovering || !payload?.length) {
                                    return null;
                                }

                                const point = payload[0].payload as TrendPoint;
                                const rows = [
                                    {
                                        label: 'This period',
                                        value: active.format(
                                            Number(point[active.key] ?? 0),
                                        ),
                                        color: 'var(--brand)',
                                    },
                                ];

                                if (active.previous) {
                                    rows.push({
                                        label: 'Previous period',
                                        value: active.format(
                                            Number(point[active.previous] ?? 0),
                                        ),
                                        color: '#94a3b8',
                                    });
                                }

                                return (
                                    <TooltipBox
                                        title={String(label)}
                                        rows={rows}
                                    />
                                );
                            }}
                        />
                        <Area
                            type="monotone"
                            dataKey={active.key}
                            stroke="var(--brand)"
                            strokeWidth={2.5}
                            fill="url(#trend-fill)"
                            activeDot={{
                                r: 5,
                                stroke: '#fff',
                                strokeWidth: 2,
                            }}
                            animationDuration={1000}
                        />
                        {active.previous && (
                            <Line
                                type="monotone"
                                dataKey={active.previous}
                                stroke="#94a3b8"
                                strokeWidth={2}
                                strokeDasharray="5 5"
                                dot={false}
                                activeDot={false}
                                animationDuration={1000}
                            />
                        )}
                    </ComposedChart>
                </ResponsiveContainer>
            </div>
            {active.previous && (
                <div className="mt-3 flex items-center gap-4 text-[11px] text-slate-500">
                    <span className="flex items-center gap-1.5">
                        <span className="inline-block h-0.5 w-4 rounded bg-brand" />
                        This period
                    </span>
                    <span className="flex items-center gap-1.5">
                        <span className="inline-block w-4 border-t-2 border-dashed border-slate-400" />
                        Previous period
                    </span>
                </div>
            )}
        </Panel>
    );
}
