import { Head, Link, router } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { useState } from 'react';
import { ClearFiltersButton } from '@/components/admin/clear-filters-button';
import { CreateOrderModal } from '@/components/admin/create-order-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Select } from '@/components/ui/select';
import { useLiveSearch } from '@/hooks/use-live-search';

type Order = {
    id: number;
    order_number: string;
    status: string;
    payment_status: string;
    is_seen: boolean;
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
    statuses: { value: string; label: string }[];
};

const statusVariant: Record<
    string,
    'success' | 'warning' | 'error' | 'info' | 'default'
> = {
    pending: 'warning',
    processing: 'info',
    shipped: 'info',
    delivered: 'success',
    cancelled: 'error',
    refunded: 'default',
};

const paymentVariant: Record<
    string,
    'success' | 'warning' | 'error' | 'default'
> = {
    paid: 'success',
    unpaid: 'warning',
    refunded: 'default',
};

export default function OrdersIndex({ orders, filters, statuses }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [createOrderOpen, setCreateOrderOpen] = useState(false);

    const applyFilters = (overrides: Record<string, string>) => {
        if (
            Object.keys(overrides).length === 0 &&
            search === (filters.search ?? '')
        ) {
            return;
        }

        router.get(
            route('admin.orders.index'),
            { ...filters, search, ...overrides },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const clearFilters = () => {
        setSearch('');
        router.get(
            route('admin.orders.index'),
            {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const hasActiveFilters = Boolean(
        search || filters.search || filters.status || filters.payment_status,
    );

    // Results follow the search box as you type (Enter / the Search button still apply at once).
    useLiveSearch(search, filters.search ?? '', () => applyFilters({}));

    return (
        <>
            <Head title="Orders — Admin" />
            <div className="space-y-5">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-xl font-bold text-slate-900">
                            Orders
                        </h1>
                        <p className="text-sm text-slate-600">
                            {orders.total} total
                        </p>
                    </div>
                    <Button
                        size="sm"
                        onClick={() => setCreateOrderOpen(true)}
                        className="w-full bg-[#c8102e] text-white hover:bg-[#a50d25] sm:w-auto"
                    >
                        <Plus className="mr-1.5 size-4" />
                        Create Order
                    </Button>
                </div>

                <Card className="overflow-hidden p-0 shadow-sm">
                    <div className="flex flex-wrap gap-2 border-b border-slate-100 p-4">
                        <div className="relative w-full flex-none sm:w-auto sm:min-w-[200px] sm:flex-1">
                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                            <input
                                maxLength={100}
                                type="text"
                                placeholder="Search order number…"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                onKeyDown={(e) =>
                                    e.key === 'Enter' && applyFilters({})
                                }
                                className="h-9 w-full rounded-lg border border-slate-200 pr-3 pl-9 text-sm focus:border-[#c8102e] focus:outline-none"
                            />
                        </div>
                        <Select
                            value={filters.status ?? ''}
                            onChange={(e) =>
                                applyFilters({ status: e.target.value })
                            }
                            containerClassName="min-w-0 flex-1 sm:min-w-40 sm:flex-none"
                            selectClassName="h-9 rounded-lg border border-slate-200 px-3 text-sm focus:outline-none"
                        >
                            <option value="">All Status</option>
                            {statuses.map((s) => (
                                <option key={s.value} value={s.value}>
                                    {s.label}
                                </option>
                            ))}
                        </Select>
                        <Select
                            value={filters.payment_status ?? ''}
                            onChange={(e) =>
                                applyFilters({ payment_status: e.target.value })
                            }
                            containerClassName="min-w-0 flex-1 sm:min-w-40 sm:flex-none"
                            selectClassName="h-9 rounded-lg border border-slate-200 px-3 text-sm focus:outline-none"
                        >
                            <option value="">All Payments</option>
                            <option value="paid">Paid</option>
                            <option value="unpaid">Unpaid</option>
                            <option value="refunded">Refunded</option>
                        </Select>
                        <ClearFiltersButton
                            active={hasActiveFilters}
                            onClear={clearFilters}
                        />
                    </div>

                    <div className="divide-y divide-slate-50">
                        {orders.data.length === 0 ? (
                            <p className="py-12 text-center text-sm text-slate-600">
                                No orders found.
                            </p>
                        ) : (
                            orders.data.map((order) => (
                                <Link
                                    key={order.id}
                                    href={route('admin.orders.show', order.id)}
                                    className={`admin-row-card flex flex-wrap items-center gap-x-3 gap-y-2 px-4 py-3 hover:bg-slate-50/60 sm:flex-nowrap sm:gap-4 ${
                                        order.is_seen ? '' : 'bg-red-50/40'
                                    }`}
                                >
                                    <div className="w-full min-w-0 sm:w-auto sm:flex-1">
                                        <div className="flex items-center gap-2">
                                            <p className="font-mono text-sm font-bold text-slate-800">
                                                {order.order_number}
                                            </p>
                                            {!order.is_seen && (
                                                <Badge
                                                    variant="error"
                                                    size="sm"
                                                >
                                                    New
                                                </Badge>
                                            )}
                                        </div>
                                        <p className="text-xs text-slate-600">
                                            {order.user?.name ?? 'Guest'} ·{' '}
                                            {new Date(
                                                order.created_at,
                                            ).toLocaleString(undefined, {
                                                year: 'numeric',
                                                month: 'numeric',
                                                day: 'numeric',
                                                hour: 'numeric',
                                                minute: '2-digit',
                                            })}
                                        </p>
                                    </div>
                                    <div className="order-last flex w-full items-center gap-2 sm:order-none sm:w-auto sm:gap-4">
                                        <p className="mr-auto text-sm font-bold text-slate-800 sm:mr-0">
                                            ৳{order.total.toLocaleString()}
                                        </p>
                                        <Badge
                                            variant={
                                                statusVariant[order.status] ??
                                                'default'
                                            }
                                            size="sm"
                                        >
                                            {order.status}
                                        </Badge>
                                        <Badge
                                            variant={
                                                paymentVariant[
                                                    order.payment_status
                                                ] ?? 'default'
                                            }
                                            size="sm"
                                        >
                                            {order.payment_status}
                                        </Badge>
                                    </div>
                                </Link>
                            ))
                        )}
                    </div>

                    {orders.last_page > 1 && (
                        <div className="flex items-center justify-between border-t border-slate-100 px-4 py-3">
                            <p className="text-xs text-slate-600">
                                Page {orders.current_page} of {orders.last_page}
                            </p>
                            <div className="flex gap-1">
                                {orders.links[0]?.url && (
                                    <Link
                                        href={orders.links[0].url}
                                        className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs hover:bg-slate-50"
                                    >
                                        Prev
                                    </Link>
                                )}
                                {orders.links[orders.links.length - 1]?.url && (
                                    <Link
                                        href={
                                            orders.links[
                                                orders.links.length - 1
                                            ].url!
                                        }
                                        className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs hover:bg-slate-50"
                                    >
                                        Next
                                    </Link>
                                )}
                            </div>
                        </div>
                    )}
                </Card>
            </div>

            <CreateOrderModal
                open={createOrderOpen}
                onOpenChange={setCreateOrderOpen}
            />
        </>
    );
}
