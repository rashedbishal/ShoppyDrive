import { Link } from '@inertiajs/react';
import {
    Heart,
    History,
    Layers,
    PackageX,
    Snail,
    Tag,
    Trophy,
    Zap,
} from 'lucide-react';
import { useState } from 'react';
import type { ProductInsights, Promotions, RankedRow } from './types';
import { BarRow, Empty, money, number, Panel, SERIES, timeAgo } from './ui';

const STOCK_TYPE_LABELS: Record<string, string> = {
    in: 'Back in stock',
    out: 'Sold',
    adjustment: 'Manual change',
    reservation: 'Reserved',
    release: 'Released',
};

type RankTab = 'products' | 'categories' | 'brands';

export function ProductPanels({ data }: { data: ProductInsights }) {
    const [tab, setTab] = useState<RankTab>('products');
    const ranked: Record<RankTab, RankedRow[]> = {
        products: data.top_products,
        categories: data.top_categories,
        brands: data.top_brands,
    };
    const rows = ranked[tab];
    const max = Math.max(...rows.map((row) => row.revenue), 0);

    return (
        <div className="grid grid-cols-1 gap-5 lg:grid-cols-2 xl:grid-cols-3">
            <Panel
                title="Best sellers"
                subtitle="By sales in this period"
                icon={Trophy}
                className="lg:col-span-2 xl:col-span-1 xl:row-span-2"
                action={
                    <div className="flex gap-1">
                        {(
                            ['products', 'categories', 'brands'] as RankTab[]
                        ).map((key) => (
                            <button
                                key={key}
                                type="button"
                                onClick={() => setTab(key)}
                                className={`rounded-lg px-2 py-1 text-[11px] font-semibold capitalize transition ${
                                    tab === key
                                        ? 'bg-slate-900 text-white'
                                        : 'text-slate-500 hover:bg-slate-100'
                                }`}
                            >
                                {key}
                            </button>
                        ))}
                    </div>
                }
            >
                {rows.length === 0 ? (
                    <Empty>No sales in this period yet</Empty>
                ) : (
                    <div className="space-y-2">
                        {rows.map((row, index) => (
                            <BarRow
                                key={`${tab}-${row.id ?? row.name}`}
                                label={`${index + 1}. ${row.name}`}
                                sub={`${number(row.units)} sold`}
                                value={row.revenue}
                                max={max}
                                display={money(row.revenue)}
                                href={
                                    tab === 'products' && row.id
                                        ? route('admin.products.edit', row.id)
                                        : undefined
                                }
                            />
                        ))}
                    </div>
                )}
            </Panel>

            <Panel
                title="Most wishlisted"
                subtitle="Products customers are saving"
                icon={Heart}
            >
                {data.most_wishlisted.length === 0 ? (
                    <Empty>No wishlisted products yet</Empty>
                ) : (
                    <div className="space-y-2">
                        {data.most_wishlisted.map((row) => (
                            <BarRow
                                key={row.id}
                                label={row.name}
                                value={row.wishlists}
                                max={data.most_wishlisted[0].wishlists}
                                display={number(row.wishlists)}
                                color={SERIES[4]}
                                href={route('admin.products.edit', row.id)}
                            />
                        ))}
                    </div>
                )}
            </Panel>

            <Panel
                title="Slow movers"
                subtitle="In stock, no sale in 60 days"
                icon={Snail}
            >
                {data.slow_movers.length === 0 ? (
                    <Empty>Every product in stock sold recently</Empty>
                ) : (
                    <ul className="divide-y divide-slate-50">
                        {data.slow_movers.map((row) => (
                            <li key={row.id}>
                                <Link
                                    href={route('admin.products.edit', row.id)}
                                    className="flex items-center justify-between gap-3 py-2 text-xs transition hover:text-brand"
                                >
                                    <span className="min-w-0 truncate font-medium text-slate-700">
                                        {row.name}
                                    </span>
                                    <span className="shrink-0 rounded-full bg-amber-50 px-2 py-0.5 font-semibold text-amber-700 tabular-nums">
                                        {number(row.stock)} in stock
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </Panel>

            <Panel
                title="Out of stock"
                subtitle="Active products with nothing left"
                icon={PackageX}
            >
                {data.out_of_stock.length === 0 ? (
                    <Empty>Nothing is out of stock</Empty>
                ) : (
                    <ul className="divide-y divide-slate-50">
                        {data.out_of_stock.map((row) => (
                            <li key={row.id}>
                                <Link
                                    href={route('admin.products.edit', row.id)}
                                    className="flex items-center justify-between gap-3 py-2 text-xs transition hover:text-brand"
                                >
                                    <span className="min-w-0 truncate font-medium text-slate-700">
                                        {row.name}
                                    </span>
                                    <span className="shrink-0 font-mono text-[11px] text-slate-400">
                                        {row.sku ?? '—'}
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </Panel>

            {data.stock_activity && (
                <Panel
                    title="Recent stock changes"
                    subtitle="Latest stock movements"
                    icon={History}
                >
                    {data.stock_activity.length === 0 ? (
                        <Empty>No stock changes yet</Empty>
                    ) : (
                        <ul className="divide-y divide-slate-50">
                            {data.stock_activity.map((log) => {
                                const added =
                                    log.type === 'in' ||
                                    log.type === 'release' ||
                                    (log.type === 'adjustment' &&
                                        log.quantity > 0);

                                return (
                                    <li
                                        key={log.id}
                                        className="flex items-center justify-between gap-3 py-2 text-xs"
                                    >
                                        <span className="min-w-0">
                                            <span className="block truncate font-medium text-slate-700">
                                                {log.product}
                                            </span>
                                            <span className="block truncate text-[11px] text-slate-400">
                                                {log.note ||
                                                    (STOCK_TYPE_LABELS[
                                                        log.type
                                                    ] ??
                                                        log.type)}{' '}
                                                · {timeAgo(log.created_at)}
                                            </span>
                                        </span>
                                        <span
                                            className={`shrink-0 rounded-full px-2 py-0.5 font-semibold tabular-nums ${added ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'}`}
                                        >
                                            {added ? '+' : '−'}
                                            {Math.abs(log.quantity)}
                                        </span>
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                </Panel>
            )}
        </div>
    );
}

export function PromotionPanels({ data }: { data: Promotions }) {
    return (
        <div className="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
            {data.flash_sales && (
                <Panel
                    title="Flash sales"
                    subtitle="Running in this period"
                    icon={Zap}
                >
                    {data.flash_sales.length === 0 ? (
                        <Empty>No flash sales in this period</Empty>
                    ) : (
                        <ul className="space-y-3">
                            {data.flash_sales.map((sale) => {
                                const share = sale.stock_limit
                                    ? Math.min(sale.sold / sale.stock_limit, 1)
                                    : null;

                                return (
                                    <li key={sale.id}>
                                        <div className="flex items-center justify-between gap-2 text-xs">
                                            <span className="flex min-w-0 items-center gap-1.5">
                                                <span className="truncate font-medium text-slate-700">
                                                    {sale.name}
                                                </span>
                                                {sale.is_active && (
                                                    <span className="rounded-full bg-emerald-50 px-1.5 py-0.5 text-[9px] font-bold text-emerald-700 uppercase">
                                                        Live
                                                    </span>
                                                )}
                                            </span>
                                            <span className="shrink-0 font-semibold text-slate-900 tabular-nums">
                                                {money(sale.revenue)}
                                            </span>
                                        </div>
                                        <div className="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100">
                                            <div
                                                className="dash-grow h-full rounded-full bg-brand"
                                                style={{
                                                    width: `${share === null ? 100 : Math.max(share * 100, 2)}%`,
                                                    opacity:
                                                        share === null
                                                            ? 0.35
                                                            : 1,
                                                }}
                                            />
                                        </div>
                                        <p className="mt-1 text-[11px] text-slate-400">
                                            {number(sale.sold)} sold
                                            {sale.stock_limit
                                                ? ` of ${number(sale.stock_limit)} available`
                                                : ''}
                                        </p>
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                </Panel>
            )}

            {data.coupons && (
                <Panel
                    title="Coupons"
                    subtitle="Most used in this period"
                    icon={Tag}
                >
                    {data.coupons.length === 0 ? (
                        <Empty>No coupons used in this period</Empty>
                    ) : (
                        <div className="space-y-2">
                            {data.coupons.map((coupon) => (
                                <BarRow
                                    key={coupon.id}
                                    label={coupon.code}
                                    sub={`${money(coupon.discount)} off in total`}
                                    value={coupon.uses}
                                    max={data.coupons![0].uses}
                                    display={`${number(coupon.uses)} uses`}
                                    color={SERIES[3]}
                                />
                            ))}
                        </div>
                    )}
                </Panel>
            )}

            {data.bundles && (
                <Panel
                    title="Bundles"
                    subtitle="Bundle sales in this period"
                    icon={Layers}
                >
                    {data.bundles.length === 0 ? (
                        <Empty>No bundles sold in this period</Empty>
                    ) : (
                        <div className="space-y-2">
                            {data.bundles.map((bundle) => (
                                <BarRow
                                    key={bundle.id}
                                    label={bundle.name}
                                    sub={`${number(bundle.orders)} orders`}
                                    value={bundle.revenue}
                                    max={data.bundles![0].revenue}
                                    display={money(bundle.revenue)}
                                    color={SERIES[6]}
                                />
                            ))}
                        </div>
                    )}
                </Panel>
            )}
        </div>
    );
}
