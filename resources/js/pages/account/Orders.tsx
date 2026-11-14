import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ShoppingBag,
    Search,
    Calendar,
    ArrowRight,
    XCircle,
    Download,
} from 'lucide-react';
import React, { useState } from 'react';

import {
    OrderStatusBadge,
    PaymentStatusBadge,
} from '@/components/shared/OrderStatusBadge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Pagination } from '@/components/ui/pagination';
import { useLiveSearch } from '@/hooks/use-live-search';
import { orderHasInvoice } from '@/lib/order-invoice';
import { formatCurrency, formatDate, getPrimaryImagePath } from '@/lib/utils';
import type { Order, PaginatedData, SharedPageProps } from '@/types';

interface OrdersProps {
    orders: PaginatedData<Order>;
    filters: {
        search?: string;
        status?: string;
    };
}

export default function Orders({
    orders,
    filters,
}: OrdersProps): React.JSX.Element {
    const { props } = usePage<SharedPageProps>();
    const { flash } = props;
    const [search, setSearch] = useState(filters.search || '');

    const runSearch = () => {
        router.get(
            route('account.orders'),
            { search },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        runSearch();
    };

    // Orders follow the search box as you type.
    useLiveSearch(search, filters.search || '', runSearch);

    const handleCancel = (orderNumber: string) => {
        if (
            !confirm(
                'Are you sure you want to cancel this order? This cannot be undone.',
            )
        ) {
            return;
        }

        router.patch(
            route('account.orders.cancel', orderNumber),
            {},
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="My Orders" />

            <div className="space-y-5">
                {flash?.success && (
                    <div className="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700">
                        {flash.success}
                    </div>
                )}
                {flash?.error && (
                    <div
                        data-form-error
                        className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700"
                    >
                        {flash.error}
                    </div>
                )}

                {/* Header */}
                <div className="flex flex-col justify-between gap-3 md:flex-row md:items-center">
                    <div>
                        <h1 className="mb-0.5 font-display text-2xl font-bold text-[var(--color-text-primary)]">
                            My Orders
                        </h1>
                        <p className="text-sm text-[var(--color-text-secondary)]">
                            Check the status of your recent orders and manage
                            returns.
                        </p>
                    </div>

                    <form
                        onSubmit={handleSearch}
                        className="relative w-full max-w-sm"
                    >
                        <Input
                            maxLength={40}
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search by order ID..."
                            className="h-10 rounded-xl border-[var(--color-border)] pl-10 focus:ring-[var(--color-primary)]"
                        />
                        <Search
                            size={16}
                            className="absolute top-1/2 left-3.5 -translate-y-1/2 text-[var(--color-text-muted)]"
                        />
                    </form>
                </div>

                {/* Orders List */}
                {orders.data.length > 0 ? (
                    <div className="space-y-3 md:space-y-0 md:divide-y md:divide-[var(--color-border)] md:overflow-hidden md:rounded-2xl md:border md:border-[var(--color-border)] md:bg-white md:shadow-sm">
                        {orders.data.map((order) => (
                            <div
                                key={order.id}
                                className="flex flex-wrap items-center gap-x-4 gap-y-3 rounded-2xl border border-[var(--color-border)] bg-white p-4 shadow-sm transition-colors hover:bg-[var(--color-surface-alt)] md:rounded-none md:border-0 md:px-5 md:py-3 md:shadow-none"
                            >
                                {/* Full width on phones so the badges and total wrap below it. */}
                                <div className="flex w-full min-w-0 items-center gap-3 md:w-auto md:flex-1 md:gap-4">
                                    {/* Order Items Preview */}
                                    <div className="flex shrink-0 -space-x-3">
                                        {order.items
                                            .slice(0, 3)
                                            .map((item, idx) => (
                                                <div
                                                    key={idx}
                                                    className="relative z-[10] h-10 w-10 overflow-hidden rounded-lg border-2 border-white bg-[var(--color-surface-alt)] shadow-sm"
                                                    style={{
                                                        zIndex: 10 - idx,
                                                    }}
                                                >
                                                    <img
                                                        src={
                                                            item.variant
                                                                ?.image ||
                                                            (item
                                                                .product_snapshot
                                                                ?.image as
                                                                | string
                                                                | undefined) ||
                                                            (item.product
                                                                ? getPrimaryImagePath(
                                                                      item.product,
                                                                  )
                                                                : undefined) ||
                                                            '/placeholder.webp'
                                                        }
                                                        alt={item.product_name}
                                                        className="h-full w-full object-cover"
                                                    />
                                                </div>
                                            ))}
                                        {order.items.length > 3 && (
                                            <div className="relative z-0 flex h-10 w-10 items-center justify-center rounded-lg border-2 border-white bg-[var(--color-primary-light)] text-xs font-bold text-[var(--color-primary)] shadow-sm">
                                                +{order.items.length - 3}
                                            </div>
                                        )}
                                    </div>

                                    {/* Order ID, date and delivery note */}
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate font-mono text-sm font-bold text-[var(--color-text-primary)]">
                                            #{order.order_number}
                                        </p>
                                        <p className="flex flex-wrap items-center gap-x-1.5 text-xs text-[var(--color-text-secondary)]">
                                            <Calendar
                                                size={12}
                                                className="text-[var(--color-text-muted)]"
                                            />
                                            <span className="whitespace-nowrap">
                                                {formatDate(order.created_at)}
                                            </span>
                                            <span aria-hidden="true">·</span>
                                            <span className="italic">
                                                {order.status === 'delivered'
                                                    ? order.delivered_at
                                                        ? `Delivered on ${formatDate(order.delivered_at)}`
                                                        : 'Delivered'
                                                    : 'Estimated Delivery: 3-5 business days'}
                                            </span>
                                        </p>
                                    </div>
                                </div>

                                <div className="flex flex-1 items-center gap-2 md:flex-none">
                                    <OrderStatusBadge status={order.status} />
                                    <PaymentStatusBadge
                                        status={order.payment_status}
                                    />
                                </div>

                                <p className="min-w-20 text-right text-base font-bold text-[var(--color-primary)]">
                                    {formatCurrency(order.total)}
                                </p>

                                <div className="flex w-full flex-wrap justify-end gap-2 border-t border-[var(--color-border)] pt-3 md:w-auto md:border-0 md:pt-0">
                                    <Button
                                        asChild
                                        variant="outline"
                                        size="sm"
                                        className="flex-1 rounded-lg border-[var(--color-border)] font-bold hover:bg-[var(--color-surface-alt)] md:flex-none"
                                    >
                                        <Link
                                            href={route(
                                                'account.orders.show',
                                                order.order_number,
                                            )}
                                        >
                                            View Details
                                        </Link>
                                    </Button>
                                    {order.status !== 'cancelled' && (
                                        <Button
                                            asChild
                                            size="sm"
                                            className="flex-1 rounded-lg bg-[var(--color-secondary)] font-bold text-white hover:bg-[#2A2A4E] md:flex-none"
                                        >
                                            <Link
                                                href={route('order.track', {
                                                    order: order.order_number,
                                                })}
                                            >
                                                Track Order
                                                <ArrowRight
                                                    size={14}
                                                    className="ml-1"
                                                />
                                            </Link>
                                        </Button>
                                    )}
                                    {orderHasInvoice(order) && (
                                        <Button
                                            asChild
                                            variant="outline"
                                            size="sm"
                                            className="flex-1 rounded-lg border-[var(--color-border)] font-bold hover:bg-[var(--color-surface-alt)] md:flex-none"
                                        >
                                            <a
                                                href={route(
                                                    'account.orders.invoice',
                                                    order.order_number,
                                                )}
                                            >
                                                <Download
                                                    size={14}
                                                    className="mr-1"
                                                />
                                                Invoice
                                            </a>
                                        </Button>
                                    )}
                                    {order.status === 'pending' && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            className="flex-1 rounded-lg border-red-200 font-bold text-red-600 hover:border-red-300 hover:bg-red-50 md:flex-none"
                                            onClick={() =>
                                                handleCancel(order.order_number)
                                            }
                                        >
                                            <XCircle
                                                size={14}
                                                className="mr-1"
                                            />
                                            Cancel Order
                                        </Button>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                ) : (
                    <div className="rounded-2xl border border-[var(--color-border)] bg-white p-10 text-center">
                        <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-[var(--color-surface-alt)] text-[var(--color-text-muted)]">
                            <ShoppingBag size={28} />
                        </div>
                        <h3 className="mb-1 text-lg font-bold text-[var(--color-text-primary)]">
                            No orders found
                        </h3>
                        <p className="mx-auto mb-6 max-w-sm text-sm text-[var(--color-text-secondary)]">
                            {search
                                ? `We couldn't find any orders matching "${search}". Try searching with a different order ID.`
                                : "You haven't placed any orders yet. Browse our catalog and start shopping!"}
                        </p>
                        <Button
                            asChild
                            className="h-11 rounded-xl px-8 font-bold"
                        >
                            <Link href={route('products.index')}>
                                Explore Products
                            </Link>
                        </Button>
                    </div>
                )}

                {/* FE-001: older orders were unreachable past page 1. */}
                {orders.last_page > 1 && (
                    <div className="flex justify-center">
                        <Pagination
                            data={orders}
                            onPageChange={(page) =>
                                router.get(
                                    route('account.orders'),
                                    { search: filters.search, page },
                                    { preserveState: true },
                                )
                            }
                        />
                    </div>
                )}
            </div>
        </>
    );
}
