import { Link } from '@inertiajs/react';
import {
    Crown,
    Gift,
    Link2,
    MapPin,
    Megaphone,
    MonitorSmartphone,
    Search,
    SearchX,
    ShoppingCart,
    Star,
    ThumbsDown,
    UserCheck,
    Users,
} from 'lucide-react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { Donut } from './orders';
import type { CustomerInsights, Marketing, ReviewInsights } from './types';
import {
    BarRow,
    CountUp,
    Empty,
    MiniStat,
    money,
    number,
    Panel,
    percent,
    SERIES,
    SOURCE_LABELS,
    timeAgo,
    titleCase,
    TooltipBox,
} from './ui';

// ── Customers ─────────────────────────────────────────────────────────────────

export function CustomerPanels({ data }: { data: CustomerInsights }) {
    const maxDistrict = Math.max(...data.districts.map((row) => row.orders), 0);

    return (
        <div className="grid grid-cols-1 gap-5 lg:grid-cols-2 xl:grid-cols-3">
            <Panel
                title="Customer mix"
                subtitle="Who ordered in this period"
                icon={Users}
            >
                <Donut
                    centerLabel="customers"
                    slices={[
                        {
                            key: 'new',
                            label: 'First-time customers',
                            value: data.new_customers,
                            color: SERIES[0],
                        },
                        {
                            key: 'returning',
                            label: 'Returning customers',
                            value: data.returning_customers,
                            color: SERIES[1],
                        },
                    ]}
                />
                <div className="mt-4 grid grid-cols-2 gap-2">
                    <MiniStat
                        label="Repeat buyers"
                        value={percent(data.repeat_rate)}
                        sub="ordered more than once"
                        tone="emerald"
                    />
                    <MiniStat
                        label="Avg. lifetime spend"
                        value={money(data.average_lifetime_spend)}
                        sub="per paying customer"
                        tone="blue"
                    />
                    <MiniStat
                        label="Guest orders"
                        value={number(data.guest_orders)}
                        sub={`${percent(
                            data.guest_orders + data.registered_orders > 0
                                ? (data.guest_orders /
                                      (data.guest_orders +
                                          data.registered_orders)) *
                                      100
                                : 0,
                        )} of orders`}
                    />
                    <MiniStat
                        label="Registered orders"
                        value={number(data.registered_orders)}
                        sub="placed with an account"
                    />
                </div>
            </Panel>

            <Panel
                title="Top customers"
                subtitle="By money collected in this period"
                icon={Crown}
            >
                {data.top_customers.length === 0 ? (
                    <Empty>No paid orders from registered customers yet</Empty>
                ) : (
                    <ul className="space-y-2">
                        {data.top_customers.map((customer, index) => (
                            <li key={customer.id}>
                                <Link
                                    href={route(
                                        'admin.customers.show',
                                        customer.id,
                                    )}
                                    className="flex items-center gap-3 rounded-xl px-1 py-1.5 transition hover:bg-slate-50"
                                >
                                    <span
                                        className={`flex size-8 shrink-0 items-center justify-center rounded-full text-xs font-bold ${
                                            index === 0
                                                ? 'bg-brand text-brand-foreground'
                                                : 'bg-slate-100 text-slate-600'
                                        }`}
                                    >
                                        {customer.name
                                            .slice(0, 1)
                                            .toUpperCase()}
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <span className="block truncate text-xs font-medium text-slate-800">
                                            {customer.name}
                                        </span>
                                        <span className="block text-[11px] text-slate-400">
                                            {customer.orders} order
                                            {customer.orders === 1 ? '' : 's'}
                                        </span>
                                    </span>
                                    <span className="text-sm font-bold text-slate-900 tabular-nums">
                                        {money(customer.spent)}
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </Panel>

            <Panel
                title="Top districts"
                subtitle="Where orders are going"
                icon={MapPin}
                className="lg:col-span-2 xl:col-span-1"
            >
                {data.districts.length === 0 ? (
                    <Empty>No orders in this period yet</Empty>
                ) : (
                    <div className="space-y-2">
                        {data.districts.map((row) => (
                            <BarRow
                                key={row.district}
                                label={row.district}
                                value={row.orders}
                                max={maxDistrict}
                                display={`${number(row.orders)} orders`}
                                color={SERIES[2]}
                            />
                        ))}
                    </div>
                )}
            </Panel>

            <Panel
                title="Loyalty & referrals"
                subtitle="Points and invited customers"
                icon={Gift}
                className="lg:col-span-2 xl:col-span-3"
            >
                <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6">
                    <MiniStat
                        label="Points held"
                        value={
                            <CountUp value={data.loyalty.points_outstanding} />
                        }
                        sub="by all customers"
                    />
                    <MiniStat
                        label="Points earned"
                        value={<CountUp value={data.loyalty.points_earned} />}
                        sub="in this period"
                        tone="emerald"
                    />
                    <MiniStat
                        label="Points redeemed"
                        value={<CountUp value={data.loyalty.points_redeemed} />}
                        sub="in this period"
                        tone="amber"
                    />
                    <MiniStat
                        label="Referred sign-ups"
                        value={<CountUp value={data.referrals} />}
                        sub="in this period"
                        tone="blue"
                    />
                    {data.newsletter && (
                        <>
                            <MiniStat
                                label="Newsletter"
                                value={
                                    <CountUp
                                        value={data.newsletter.subscribers}
                                    />
                                }
                                sub="active subscribers"
                            />
                            <MiniStat
                                label="Newsletter change"
                                value={`${data.newsletter.joined - data.newsletter.left >= 0 ? '+' : ''}${number(data.newsletter.joined - data.newsletter.left)}`}
                                sub={`${number(data.newsletter.joined)} joined · ${number(data.newsletter.left)} left`}
                                tone={
                                    data.newsletter.joined >=
                                    data.newsletter.left
                                        ? 'emerald'
                                        : 'red'
                                }
                            />
                        </>
                    )}
                </div>
            </Panel>
        </div>
    );
}

// ── Marketing & traffic ───────────────────────────────────────────────────────

export function MarketingPanels({ data }: { data: Marketing }) {
    const devices = data.devices;
    const deviceTotal = devices.mobile + devices.tablet + devices.desktop;
    const sources = (data.traffic_sources ?? []).map((row, index) => ({
        ...row,
        label: SOURCE_LABELS[row.source] ?? titleCase(row.source),
        color: SERIES[index % SERIES.length],
    }));

    return (
        <div className="grid grid-cols-1 gap-5 lg:grid-cols-2 xl:grid-cols-3">
            {data.traffic_sources && (
                <Panel
                    title="Sales by traffic source"
                    subtitle="Where ordering customers came from"
                    icon={Megaphone}
                    className="lg:col-span-2 xl:col-span-2"
                >
                    {sources.length === 0 ? (
                        <Empty>No orders in this period yet</Empty>
                    ) : (
                        <div className="h-60">
                            <ResponsiveContainer width="100%" height="100%">
                                <BarChart
                                    data={sources}
                                    layout="vertical"
                                    margin={{
                                        top: 0,
                                        right: 16,
                                        bottom: 0,
                                        left: 0,
                                    }}
                                    barCategoryGap={8}
                                >
                                    <CartesianGrid
                                        stroke="#f1f5f9"
                                        horizontal={false}
                                    />
                                    <XAxis
                                        type="number"
                                        tick={{ fontSize: 11, fill: '#64748b' }}
                                        tickLine={false}
                                        axisLine={false}
                                        allowDecimals={false}
                                    />
                                    <YAxis
                                        type="category"
                                        dataKey="label"
                                        width={110}
                                        tick={{ fontSize: 11, fill: '#334155' }}
                                        tickLine={false}
                                        axisLine={false}
                                    />
                                    <Tooltip
                                        cursor={{ fill: '#f8fafc' }}
                                        content={({ active, payload }) => {
                                            if (!active || !payload?.length) {
                                                return null;
                                            }

                                            const row = payload[0]
                                                .payload as (typeof sources)[number];

                                            return (
                                                <TooltipBox
                                                    title={row.label}
                                                    rows={[
                                                        {
                                                            label: 'Orders',
                                                            value: number(
                                                                row.orders,
                                                            ),
                                                            color: row.color,
                                                        },
                                                        {
                                                            label: 'Collected',
                                                            value: money(
                                                                row.revenue,
                                                            ),
                                                        },
                                                    ]}
                                                />
                                            );
                                        }}
                                    />
                                    <Bar
                                        dataKey="orders"
                                        radius={[0, 4, 4, 0]}
                                        animationDuration={900}
                                    >
                                        {sources.map((row) => (
                                            <Cell
                                                key={row.source}
                                                fill={row.color}
                                            />
                                        ))}
                                    </Bar>
                                </BarChart>
                            </ResponsiveContainer>
                        </div>
                    )}
                </Panel>
            )}

            <Panel
                title="Visitor devices"
                subtitle="Counted since this dashboard update"
                icon={MonitorSmartphone}
            >
                {deviceTotal === 0 ? (
                    <Empty>Device data starts collecting from today</Empty>
                ) : (
                    <Donut
                        centerLabel="visitors"
                        slices={[
                            {
                                key: 'mobile',
                                label: 'Mobile',
                                value: devices.mobile,
                                color: SERIES[0],
                            },
                            {
                                key: 'desktop',
                                label: 'Desktop',
                                value: devices.desktop,
                                color: SERIES[1],
                            },
                            {
                                key: 'tablet',
                                label: 'Tablet',
                                value: devices.tablet,
                                color: SERIES[2],
                            },
                        ].filter((slice) => slice.value > 0)}
                    />
                )}
            </Panel>

            {data.abandoned && (
                <Panel
                    title="Abandoned checkouts"
                    subtitle="Started checkout, didn't order"
                    icon={ShoppingCart}
                >
                    <div className="grid grid-cols-2 gap-2">
                        <MiniStat
                            label="Checkouts left"
                            value={<CountUp value={data.abandoned.count} />}
                            sub="still not ordered"
                            tone="amber"
                        />
                        <MiniStat
                            label="Cart value"
                            value={money(data.abandoned.value)}
                            sub="left behind"
                            tone="red"
                        />
                        <MiniStat
                            label="Reminded"
                            value={<CountUp value={data.abandoned.reminded} />}
                            sub="got a reminder email"
                            tone="blue"
                        />
                        <MiniStat
                            label="Reminder reach"
                            value={percent(
                                data.abandoned.count > 0
                                    ? (data.abandoned.reminded /
                                          data.abandoned.count) *
                                          100
                                    : 0,
                            )}
                            sub="only customers with accounts"
                        />
                    </div>
                    <p className="mt-3 text-[11px] text-slate-400">
                        A checkout leaves this list once that phone number
                        places an order.
                    </p>
                </Panel>
            )}

            {data.landing_pages && (
                <Panel
                    title="Landing pages"
                    subtitle="Orders from landing pages"
                    icon={Link2}
                >
                    {data.landing_pages.length === 0 ? (
                        <Empty>No landing page orders in this period</Empty>
                    ) : (
                        <div className="space-y-2">
                            {data.landing_pages.map((page) => (
                                <BarRow
                                    key={page.id}
                                    label={page.title}
                                    sub={money(page.amount)}
                                    value={page.orders}
                                    max={data.landing_pages![0].orders}
                                    display={`${number(page.orders)} orders`}
                                    color={SERIES[6]}
                                />
                            ))}
                        </div>
                    )}
                </Panel>
            )}

            {data.top_searches && (
                <Panel
                    title="Top searches"
                    subtitle="What shoppers type in the search bar"
                    icon={Search}
                >
                    {data.top_searches.length === 0 ? (
                        <Empty>
                            Searches are counted from this dashboard update
                            onward
                        </Empty>
                    ) : (
                        <div className="space-y-2">
                            {data.top_searches.map((row) => (
                                <BarRow
                                    key={row.term}
                                    label={row.term}
                                    sub={
                                        row.results === 0
                                            ? 'no results'
                                            : `${number(row.results)} results`
                                    }
                                    value={row.searches}
                                    max={data.top_searches![0].searches}
                                    display={number(row.searches)}
                                    color={SERIES[0]}
                                />
                            ))}
                        </div>
                    )}
                </Panel>
            )}

            {data.no_result_searches && (
                <Panel
                    title="Searches with no results"
                    subtitle="Products shoppers wanted but didn't find"
                    icon={SearchX}
                >
                    {data.no_result_searches.length === 0 ? (
                        <Empty>Every search found something</Empty>
                    ) : (
                        <ul className="flex flex-wrap gap-2">
                            {data.no_result_searches.map((row) => (
                                <li
                                    key={row.term}
                                    className="rounded-full border border-red-100 bg-red-50 px-3 py-1 text-xs font-medium text-red-700"
                                >
                                    {row.term}
                                    <span className="ml-1.5 text-red-400 tabular-nums">
                                        ×{row.searches}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </Panel>
            )}
        </div>
    );
}

// ── Reviews ───────────────────────────────────────────────────────────────────

function Stars({ rating }: { rating: number }) {
    return (
        <span className="inline-flex" aria-label={`${rating} out of 5 stars`}>
            {[1, 2, 3, 4, 5].map((star) => (
                <Star
                    key={star}
                    className={`size-3 ${star <= Math.round(rating) ? 'fill-amber-400 text-amber-400' : 'text-slate-200'}`}
                />
            ))}
        </span>
    );
}

export function ReviewPanels({ data }: { data: ReviewInsights }) {
    const maxStars = Math.max(
        ...data.distribution.map((row) => row.reviews),
        0,
    );

    return (
        <div className="grid grid-cols-1 gap-5 lg:grid-cols-2 xl:grid-cols-3">
            <Panel title="Ratings" subtitle="All approved reviews" icon={Star}>
                {data.total === 0 ? (
                    <Empty>No approved reviews yet</Empty>
                ) : (
                    <div className="flex items-center gap-5">
                        <div className="text-center">
                            <p className="text-4xl font-bold text-slate-900 tabular-nums">
                                <CountUp
                                    value={data.average}
                                    format={(n) => n.toFixed(1)}
                                />
                            </p>
                            <Stars rating={data.average} />
                            <p className="mt-1 text-[11px] text-slate-400">
                                {number(data.total)} reviews
                            </p>
                        </div>
                        <div className="min-w-0 flex-1 space-y-1">
                            {data.distribution.map((row) => (
                                <div
                                    key={row.stars}
                                    className="flex items-center gap-2 text-[11px]"
                                >
                                    <span className="w-3 text-slate-500">
                                        {row.stars}
                                    </span>
                                    <div className="h-2 flex-1 overflow-hidden rounded-full bg-slate-100">
                                        <div
                                            className="dash-grow h-full rounded-full bg-amber-400"
                                            style={{
                                                width: `${maxStars > 0 ? (row.reviews / maxStars) * 100 : 0}%`,
                                            }}
                                        />
                                    </div>
                                    <span className="w-8 text-right text-slate-500 tabular-nums">
                                        {row.reviews}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </Panel>

            <Panel
                title="Latest reviews"
                subtitle="Newest first"
                icon={UserCheck}
            >
                {data.latest.length === 0 ? (
                    <Empty>No reviews yet</Empty>
                ) : (
                    <ul className="space-y-3">
                        {data.latest.map((review) => (
                            <li key={review.id} className="text-xs">
                                <div className="flex items-center justify-between gap-2">
                                    <Stars rating={review.rating} />
                                    <span className="text-[11px] text-slate-400">
                                        {timeAgo(review.created_at)}
                                    </span>
                                </div>
                                <p className="mt-0.5 truncate font-medium text-slate-800">
                                    {review.product}
                                </p>
                                <p className="line-clamp-2 text-slate-500">
                                    {review.text}
                                    <span className="text-slate-400">
                                        {' '}
                                        — {review.customer}
                                    </span>
                                </p>
                                {!review.is_approved && (
                                    <span className="mt-1 inline-block rounded-full bg-amber-50 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700">
                                        Waiting for approval
                                    </span>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </Panel>

            <Panel
                title="Lowest rated products"
                subtitle="At least 2 approved reviews"
                icon={ThumbsDown}
                className="lg:col-span-2 xl:col-span-1"
            >
                {data.lowest_rated.length === 0 ? (
                    <Empty>Not enough reviews yet</Empty>
                ) : (
                    <ul className="divide-y divide-slate-50">
                        {data.lowest_rated.map((row) => (
                            <li key={row.id}>
                                <Link
                                    href={route('admin.products.edit', row.id)}
                                    className="flex items-center justify-between gap-3 py-2 text-xs transition hover:text-brand"
                                >
                                    <span className="min-w-0 truncate font-medium text-slate-700">
                                        {row.name}
                                    </span>
                                    <span className="flex shrink-0 items-center gap-1.5">
                                        <Stars rating={row.average} />
                                        <span className="text-slate-400 tabular-nums">
                                            ({row.reviews})
                                        </span>
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </Panel>
        </div>
    );
}
