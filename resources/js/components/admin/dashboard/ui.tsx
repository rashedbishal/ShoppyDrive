import { Link } from '@inertiajs/react';
import { ArrowDownRight, ArrowUpRight, Minus } from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useRef, useState } from 'react';

// ── Formatting ────────────────────────────────────────────────────────────────

export const money = (n: number) => '৳' + Math.round(n).toLocaleString('en-IN');

/** ৳1.2L / ৳3.4Cr style, for chart axes where space is tight. */
export const compactMoney = (n: number) => {
    const abs = Math.abs(n);

    if (abs >= 1e7) {
        return `৳${(n / 1e7).toFixed(1).replace(/\.0$/, '')}Cr`;
    }

    if (abs >= 1e5) {
        return `৳${(n / 1e5).toFixed(1).replace(/\.0$/, '')}L`;
    }

    if (abs >= 1e3) {
        return `৳${(n / 1e3).toFixed(1).replace(/\.0$/, '')}K`;
    }

    return `৳${Math.round(n)}`;
};

export const number = (n: number) => Math.round(n).toLocaleString('en-IN');

export const percent = (n: number) => `${n.toFixed(1).replace(/\.0$/, '')}%`;

export const timeAgo = (iso: string | null, now = Date.now()) => {
    if (!iso) {
        return '';
    }

    const seconds = Math.max(
        0,
        Math.round((now - new Date(iso).getTime()) / 1000),
    );

    if (seconds < 60) {
        return 'just now';
    }

    if (seconds < 3600) {
        return `${Math.floor(seconds / 60)} min ago`;
    }

    if (seconds < 86400) {
        return `${Math.floor(seconds / 3600)} h ago`;
    }

    return new Date(iso).toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'short',
    });
};

/**
 * Fixed categorical order (validated palette): colour follows the entity,
 * never its rank.
 */
export const SERIES = [
    '#2a78d6',
    '#eb6834',
    '#1baf7a',
    '#eda100',
    '#e87ba4',
    '#008300',
    '#6250d6',
    '#e34948',
];

export const STATUS_COLORS: Record<string, string> = {
    pending: '#eda100',
    processing: '#2a78d6',
    shipped: '#6250d6',
    delivered: '#1baf7a',
    cancelled: '#e34948',
    refunded: '#94a3b8',
};

export const STATUS_LABELS: Record<string, string> = {
    pending: 'Pending',
    processing: 'Processing',
    shipped: 'Shipped',
    delivered: 'Delivered',
    cancelled: 'Cancelled',
    refunded: 'Refunded',
};

export const PAYMENT_LABELS: Record<string, string> = {
    cod: 'Cash on delivery',
    mobile_banking: 'bKash / Nagad',
    zinipay: 'Card (ZiniPay)',
};

export const SOURCE_LABELS: Record<string, string> = {
    direct: 'Direct',
    facebook: 'Facebook',
    google: 'Google',
    instagram: 'Instagram',
    tiktok: 'TikTok',
    youtube: 'YouTube',
    organic: 'Search (organic)',
    other: 'Other websites',
    email: 'Email',
};

export const titleCase = (value: string) =>
    value
        .replace(/[_-]+/g, ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());

// ── Motion ────────────────────────────────────────────────────────────────────

const prefersReducedMotion = () =>
    typeof window !== 'undefined' &&
    window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

/** Counts up from the last shown value to the new one. */
export function CountUp({
    value,
    format = number,
    duration = 900,
}: {
    value: number;
    format?: (n: number) => string;
    duration?: number;
}) {
    const [shown, setShown] = useState(prefersReducedMotion() ? value : 0);
    const from = useRef(shown);

    useEffect(() => {
        if (prefersReducedMotion()) {
            return;
        }

        const start = performance.now();
        const initial = from.current;
        let frame = 0;

        const tick = (time: number) => {
            const progress = Math.min(1, (time - start) / duration);
            const eased = 1 - Math.pow(1 - progress, 3);
            const next = initial + (value - initial) * eased;
            from.current = next;
            setShown(next);

            if (progress < 1) {
                frame = requestAnimationFrame(tick);
            }
        };

        frame = requestAnimationFrame(tick);

        return () => cancelAnimationFrame(frame);
    }, [value, duration]);

    return <>{format(prefersReducedMotion() ? value : shown)}</>;
}

// ── Building blocks ───────────────────────────────────────────────────────────

/** Change against the previous period; `inverse` when lower is better. */
export function Change({
    value,
    previous,
    inverse = false,
    className = '',
}: {
    value: number;
    previous: number;
    inverse?: boolean;
    className?: string;
}) {
    if (previous === 0 && value === 0) {
        return (
            <span
                className={`inline-flex items-center gap-0.5 text-xs font-semibold text-slate-500 ${className}`}
            >
                <Minus className="size-3" /> 0%
            </span>
        );
    }

    const diff =
        previous === 0 ? 100 : ((value - previous) / Math.abs(previous)) * 100;
    const up = diff >= 0;
    const good = inverse ? !up : up;

    return (
        <span
            title="Compared with the previous period"
            className={`inline-flex items-center gap-0.5 rounded-full px-1.5 py-0.5 text-xs font-semibold ${good ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-600'} ${className}`}
        >
            {up ? (
                <ArrowUpRight className="size-3" />
            ) : (
                <ArrowDownRight className="size-3" />
            )}
            {previous === 0 ? 'new' : `${Math.abs(diff).toFixed(1)}%`}
        </span>
    );
}

export function Panel({
    title,
    subtitle,
    icon: Icon,
    action,
    children,
    className = '',
    bodyClassName = 'p-5',
}: {
    title: string;
    subtitle?: string;
    icon?: React.ElementType;
    action?: ReactNode;
    children: ReactNode;
    className?: string;
    bodyClassName?: string;
}) {
    return (
        <section
            className={`dash-rise min-w-0 rounded-2xl border border-slate-100 bg-white shadow-sm ${className}`}
        >
            <header className="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
                <div className="flex min-w-0 items-center gap-2.5">
                    {Icon && (
                        <span className="flex size-8 shrink-0 items-center justify-center rounded-xl bg-brand-light text-brand">
                            <Icon className="size-4" />
                        </span>
                    )}
                    <div className="min-w-0">
                        <h2 className="truncate text-sm font-semibold text-slate-800">
                            {title}
                        </h2>
                        {subtitle && (
                            <p className="truncate text-xs text-slate-500">
                                {subtitle}
                            </p>
                        )}
                    </div>
                </div>
                {action}
            </header>
            <div className={bodyClassName}>{children}</div>
        </section>
    );
}

export function Skeleton({ className = '' }: { className?: string }) {
    return (
        <div
            className={`animate-pulse rounded-2xl border border-slate-100 bg-gradient-to-r from-slate-50 via-slate-100 to-slate-50 ${className}`}
        />
    );
}

export function Empty({ children }: { children: ReactNode }) {
    return (
        <p className="flex min-h-24 items-center justify-center px-4 py-6 text-center text-sm text-slate-400">
            {children}
        </p>
    );
}

/** A labelled horizontal bar, for ranked lists. */
export function BarRow({
    label,
    value,
    max,
    display,
    color = 'var(--brand)',
    href,
    sub,
}: {
    label: string;
    value: number;
    max: number;
    display: string;
    color?: string;
    href?: string;
    sub?: string;
}) {
    const width = max > 0 ? Math.max((value / max) * 100, 2) : 0;
    const content = (
        <>
            <div className="flex items-baseline justify-between gap-3 text-xs">
                <span className="min-w-0 truncate font-medium text-slate-700">
                    {label}
                    {sub && (
                        <span className="ml-1.5 font-normal text-slate-400">
                            {sub}
                        </span>
                    )}
                </span>
                <span className="shrink-0 font-semibold text-slate-900 tabular-nums">
                    {display}
                </span>
            </div>
            <div className="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100">
                <div
                    className="dash-grow h-full rounded-full"
                    style={{ width: `${width}%`, backgroundColor: color }}
                />
            </div>
        </>
    );

    return href ? (
        <Link
            href={href}
            className="block rounded-lg px-1 py-1 transition hover:bg-slate-50"
        >
            {content}
        </Link>
    ) : (
        <div className="px-1 py-1">{content}</div>
    );
}

/** The shared chart tooltip look. */
export function TooltipBox({
    title,
    rows,
}: {
    title: string;
    rows: { label: string; value: string; color?: string; dashed?: boolean }[];
}) {
    return (
        <div className="min-w-36 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs shadow-lg">
            <p className="mb-1 font-semibold text-slate-800">{title}</p>
            {rows.map((row) => (
                <p
                    key={row.label}
                    className="flex items-center justify-between gap-4 text-slate-600"
                >
                    <span className="flex items-center gap-1.5">
                        {row.color && (
                            <span
                                className="inline-block h-0.5 w-3 rounded"
                                style={{
                                    backgroundColor: row.dashed
                                        ? 'transparent'
                                        : row.color,
                                    borderTop: row.dashed
                                        ? `2px dashed ${row.color}`
                                        : undefined,
                                }}
                            />
                        )}
                        {row.label}
                    </span>
                    <span className="font-semibold text-slate-900 tabular-nums">
                        {row.value}
                    </span>
                </p>
            ))}
        </div>
    );
}

/** A small "metric" tile inside a panel. */
export function MiniStat({
    label,
    value,
    sub,
    tone = 'slate',
}: {
    label: string;
    value: ReactNode;
    sub?: ReactNode;
    tone?: 'slate' | 'red' | 'amber' | 'emerald' | 'blue';
}) {
    const tones = {
        slate: 'bg-slate-50',
        red: 'bg-red-50',
        amber: 'bg-amber-50',
        emerald: 'bg-emerald-50',
        blue: 'bg-blue-50',
    };

    return (
        <div className={`min-w-0 rounded-xl p-3 ${tones[tone]}`}>
            <p className="truncate text-[11px] font-medium tracking-wide text-slate-500 uppercase">
                {label}
            </p>
            <p className="mt-1 text-lg font-bold text-slate-900 tabular-nums">
                {value}
            </p>
            {sub && <p className="mt-0.5 text-xs text-slate-500">{sub}</p>}
        </div>
    );
}
