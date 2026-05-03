import { Head, Link, router, usePage } from '@inertiajs/react';
import { Eye, Search } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';

type Order = {
    id: number;
    order_number: string;
    status: string;
    payment_status: string;
    total: number;
    created_at: string;
    user: { id: number; name: string; email: string } | null;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};

type Props = {
    orders: Paginated<Order>;
    filters: { search?: string; status?: string; payment_status?: string };
    statuses: { value: string; name: string }[];
};

const statusVariant: Record<string, 'success' | 'warning' | 'error' | 'info' | 'default'> = {
    pending: 'warning',
    processing: 'info',
    shipped: 'info',
    delivered: 'success',
    cancelled: 'error',
    refunded: 'default',
};

const paymentVariant: Record<string, 'success' | 'warning' | 'error' | 'default'> = {
    paid: 'success',
    unpaid: 'warning',
    refunded: 'default',
};

export default function OrdersIndex({ orders, filters, statuses }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const { props } = usePage<any>();
    const flash = props.flash as { success?: string } | undefined;

    const applyFilters = (overrides: Record<string, string>) => {
        router.get(route('admin.orders.index'), { ...filters, search, ...overrides }, { preserveState: true, replace: true });
    };

    return (
        <>
            <Head title="Orders — Admin" />
            <div className="space-y-5">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-bold text-slate-900">Orders</h1>
                        <p className="text-sm text-slate-500">{orders.total} total</p>
                    </div>
                </div>

                {flash?.success && (
                    <div className="rounded-lg bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-700">{flash.success}</div>
                )}

                <Card className="overflow-hidden p-0 shadow-sm">
                    <div className="flex flex-wrap gap-2 border-b border-slate-100 p-4">
                        <div className="relative flex-1 min-w-[200px]">
                            <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                            <input
                                type="text"
                                placeholder="Search order number…"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                onKeyDown={(e) => e.key === 'Enter' && applyFilters({})}
                                className="h-9 w-full rounded-lg border border-slate-200 pl-9 pr-3 text-sm focus:border-[#c8102e] focus:outline-none"
                            />
                        </div>
                        <select
                            value={filters.status ?? ''}
                            onChange={(e) => applyFilters({ status: e.target.value })}
                            className="h-9 rounded-lg border border-slate-200 px-3 text-sm focus:outline-none"
                        >
                            <option value="">All Status</option>
                            {statuses.map((s) => <option key={s.value} value={s.value}>{s.name ?? s.value}</option>)}
                        </select>
                        <select
                            value={filters.payment_status ?? ''}
                            onChange={(e) => applyFilters({ payment_status: e.target.value })}
                            className="h-9 rounded-lg border border-slate-200 px-3 text-sm focus:outline-none"
                        >
                            <option value="">All Payments</option>
                            <option value="paid">Paid</option>
                            <option value="unpaid">Unpaid</option>
                            <option value="refunded">Refunded</option>
                        </select>
                    </div>

                    <div className="divide-y divide-slate-50">
                        {orders.data.length === 0 ? (
                            <p className="py-12 text-center text-sm text-slate-400">No orders found.</p>
                        ) : orders.data.map((order) => (
                            <div key={order.id} className="flex items-center gap-4 px-4 py-3 hover:bg-slate-50/60">
                                <div className="min-w-0 flex-1">
                                    <p className="font-mono text-sm font-bold text-slate-800">{order.order_number}</p>
                                    <p className="text-xs text-slate-400">{order.user?.name ?? 'Guest'} · {new Date(order.created_at).toLocaleDateString()}</p>
                                </div>
                                <p className="hidden text-sm font-bold text-slate-800 sm:block">৳{order.total.toLocaleString()}</p>
                                <Badge variant={statusVariant[order.status] ?? 'default'} size="sm">{order.status}</Badge>
                                <Badge variant={paymentVariant[order.payment_status] ?? 'default'} size="sm">{order.payment_status}</Badge>
                                <Link href={route('admin.orders.show', order.id)} className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                                    <Eye className="size-4" />
                                </Link>
                            </div>
                        ))}
                    </div>

                    {orders.last_page > 1 && (
                        <div className="flex items-center justify-between border-t border-slate-100 px-4 py-3">
                            <p className="text-xs text-slate-500">Page {orders.current_page} of {orders.last_page}</p>
                            <div className="flex gap-1">
                                {orders.links[0]?.url && <Link href={orders.links[0].url} className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs hover:bg-slate-50">Prev</Link>}
                                {orders.links[orders.links.length - 1]?.url && <Link href={orders.links[orders.links.length - 1].url!} className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs hover:bg-slate-50">Next</Link>}
                            </div>
                        </div>
                    )}
                </Card>
            </div>
        </>
    );
}
