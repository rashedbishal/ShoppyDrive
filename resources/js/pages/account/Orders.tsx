import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ShoppingBag,
    Search,
    Package,
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

            <div className="space-y-8">
                {flash?.success && (
                    <div className="rounded-2xl border border-green-200 bg-green-50 px-5 py-4 text-sm font-medium text-green-700">
                        {flash.success}
                    </div>
                )}
                {flash?.error && (
                    <div
                        data-form-error
                        className="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm font-medium text-red-700"
                    >
                        {flash.error}
                    </div>
                )}

                {/* Header */}
                <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                    <div>
                        <h1 className="mb-1 font-display text-3xl font-bold text-[var(--color-text-primary)]">
                            My Orders
                        </h1>
                        <p className="text-[var(--color-text-secondary)]">
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
                            className="h-12 rounded-xl border-[var(--color-border)] pl-12 focus:ring-[var(--color-primary)]"
                        />
                        <Search
                            size={18}
                            className="absolute top-1/2 left-4 -translate-y-1/2 text-[var(--color-text-muted)]"
                        />
                    </form>
                </div>

                {/* Orders List */}
                <div className="space-y-6">
                    {orders.data.length > 0 ? (
                        orders.data.map((order) => (
                            <div
                                key={order.id}
                                className="group overflow-hidden rounded-3xl border border-[var(--color-border)] bg-white shadow-sm transition-all hover:shadow-md"
                            >
                                {/* Order Header */}
                                <div className="flex flex-wrap items-center justify-between gap-4 border-b border-[var(--color-border)] bg-[var(--color-surface-alt)]/50 p-6 md:p-8">
                                    <div className="flex items-center gap-4">
                                        <div className="flex h-12 w-12 items-center justify-center rounded-2xl border border-[var(--color-border)] bg-white text-[var(--color-primary)] shadow-sm">
                                            <Package size={24} />
                                        </div>
                                        <div>
                                            <p className="mb-1 text-[10px] leading-none font-bold tracking-widest text-[var(--color-text-secondary)] uppercase">
                                                Order ID
                                            </p>
                                            <h3 className="font-mono text-lg font-bold text-[var(--color-text-primary)]">
                                                #{order.order_number}
                                            </h3>
                                        </div>
                                    </div>

                                    <div className="flex flex-wrap items-center gap-3 sm:gap-6">
                                        <div className="hidden text-right sm:block">
                                            <p className="mb-1 text-[10px] leading-none font-bold tracking-widest text-[var(--color-text-secondary)] uppercase">
                                                Date Placed
                                            </p>
                                            <p className="flex items-center justify-end gap-1.5 text-sm font-bold text-[var(--color-text-primary)]">
                                                <Calendar
                                                    size={14}
                                                    className="text-[var(--color-text-muted)]"
                                                />
                                                {formatDate(order.created_at)}
                                            </p>
                                        </div>
                                        <div className="hidden h-8 w-px bg-[var(--color-border)] sm:block" />
                                        <div className="flex items-center gap-2">
                                            <OrderStatusBadge
                                                status={order.status}
                                            />
                                            <PaymentStatusBadge
                                                status={order.payment_status}
                                            />
                                        </div>
                                    </div>
                                </div>

                                {/* Order Items Preview */}
                                <div className="p-6 md:p-8">
                                    <div className="mb-6 flex items-center justify-between">
                                        <div className="flex -space-x-4">
                                            {order.items
                                                .slice(0, 3)
                                                .map((item, idx) => (
                                                    <div
                                                        key={idx}
                                                        className="relative z-[10] h-16 w-16 overflow-hidden rounded-xl border-4 border-white bg-[var(--color-surface-alt)] shadow-sm"
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
                                                            alt={
                                                                item.product_name
                                                            }
                                                            className="h-full w-full object-cover"
                                                        />
                                                    </div>
                                                ))}
                                            {order.items.length > 3 && (
                                                <div className="relative z-0 flex h-16 w-16 items-center justify-center rounded-xl border-4 border-white bg-[var(--color-primary-light)] text-sm font-bold text-[var(--color-primary)] shadow-sm">
                                                    +{order.items.length - 3}
                                                </div>
                                            )}
                                        </div>

                                        <div className="text-right">
                                            <p className="mb-1 text-[10px] font-bold tracking-widest text-[var(--color-text-secondary)] uppercase">
                                                Total Amount
                                            </p>
                                            <p className="text-2xl font-bold text-[var(--color-primary)]">
                                                {formatCurrency(order.total)}
                                            </p>
                                        </div>
                                    </div>

                                    <div className="flex flex-col items-center justify-between gap-4 border-t border-[var(--color-border)] pt-6 sm:flex-row">
                                        <p className="text-sm font-medium text-[var(--color-text-secondary)] italic">
                                            {order.status === 'delivered'
                                                ? order.delivered_at
                                                    ? `Delivered on ${formatDate(order.delivered_at)}`
                                                    : 'Delivered'
                                                : 'Estimated Delivery: 3-5 business days'}
                                        </p>

                                        <div className="flex w-full flex-wrap gap-3 sm:w-auto">
                                            <Button
                                                asChild
                                                variant="outline"
                                                className="flex-1 rounded-xl border-[var(--color-border)] font-bold hover:bg-[var(--color-surface-alt)] sm:flex-none"
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
                                                    className="flex-1 rounded-xl bg-[var(--color-secondary)] font-bold text-white hover:bg-[#2A2A4E] sm:flex-none"
                                                >
                                                    <Link
                                                        href={route(
                                                            'order.track',
                                                            {
                                                                order: order.order_number,
                                                            },
                                                        )}
                                                    >
                                                        Track Order
                                                        <ArrowRight
                                                            size={16}
                                                            className="ml-2"
                                                        />
                                                    </Link>
                                                </Button>
                                            )}
                                            {orderHasInvoice(order) && (
                                                <Button
                                                    asChild
                                                    variant="outline"
                                                    className="flex-1 rounded-xl border-[var(--color-border)] font-bold hover:bg-[var(--color-surface-alt)] sm:flex-none"
                                                >
                                                    <a
                                                        href={route(
                                                            'account.orders.invoice',
                                                            order.order_number,
                                                        )}
                                                    >
                                                        <Download
                                                            size={16}
                                                            className="mr-2"
                                                        />
                                                        Invoice
                                                    </a>
                                                </Button>
                                            )}
                                            {order.status === 'pending' && (
                                                <Button
                                                    variant="outline"
                                                    className="flex-1 rounded-xl border-red-200 font-bold text-red-600 hover:border-red-300 hover:bg-red-50 sm:flex-none"
                                                    onClick={() =>
                                                        handleCancel(
                                                            order.order_number,
                                                        )
                                                    }
                                                >
                                                    <XCircle
                                                        size={16}
                                                        className="mr-2"
                                                    />
                                                    Cancel Order
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        ))
                    ) : (
                        <div className="rounded-3xl border border-[var(--color-border)] bg-white p-20 text-center">
                            <div className="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-full bg-[var(--color-surface-alt)] text-[var(--color-text-muted)]">
                                <ShoppingBag size={40} />
                            </div>
                            <h3 className="mb-2 text-xl font-bold text-[var(--color-text-primary)]">
                                No orders found
                            </h3>
                            <p className="mx-auto mb-10 max-w-sm text-[var(--color-text-secondary)]">
                                {search
                                    ? `We couldn't find any orders matching "${search}". Try searching with a different order ID.`
                                    : "You haven't placed any orders yet. Browse our catalog and start shopping!"}
                            </p>
                            <Button
                                asChild
                                className="h-14 rounded-xl px-10 font-bold shadow-lg"
                            >
                                <Link href={route('products.index')}>
                                    Explore Products
                                </Link>
                            </Button>
                        </div>
                    )}
                </div>

                {/* FE-001: older orders were unreachable past page 1. */}
                {orders.last_page > 1 && (
                    <div className="mt-12 flex justify-center">
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
