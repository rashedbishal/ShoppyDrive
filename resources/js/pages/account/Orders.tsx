import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ShoppingBag,
    Search,
    Calendar,
    ArrowRight,
    XCircle,
    Download,
    Eye,
    Truck,
} from 'lucide-react';
import React, { useState } from 'react';

import {
    OrderStatusBadge,
    PaymentStatusBadge,
} from '@/components/shared/OrderStatusBadge';
import { Button } from '@/components/ui/button';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
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

/** Status and payment pills at the slim list size (this page only). */
const PILL_CLASS = 'px-1.5 py-0 text-[9px] leading-4';

/** A 24px icon action in the order table. */
const ICON_BUTTON_CLASS =
    'size-6 rounded-md border-[var(--color-border)] p-0 hover:bg-[var(--color-surface-alt)]';

/** The delivery line shown under each order (phones) or as the date's tooltip (table). */
function deliveryNote(order: Order): string {
    if (order.status === 'delivered') {
        return order.delivered_at
            ? `Delivered on ${formatDate(order.delivered_at)}`
            : 'Delivered';
    }

    return 'Est. delivery 3–5 days';
}

/** Keeps an action's column when that action doesn't apply to the order, so the icons line up row to row. */
function EmptyActionSlot(): React.JSX.Element {
    return <span aria-hidden="true" className="size-6 shrink-0" />;
}

type OrderItemRow = Order['items'][number];

/** The best image for an order line: variant, saved snapshot, product, else the placeholder. */
function itemImageSrc(item: OrderItemRow): string {
    return (
        item.variant?.image ||
        (item.product_snapshot?.image as string | undefined) ||
        (item.product ? getPrimaryImagePath(item.product) : undefined) ||
        '/placeholder.svg'
    );
}

/** A missing product image shows the placeholder, not its alt text squeezed into the thumbnail. */
function showPlaceholderOnError(
    event: React.SyntheticEvent<HTMLImageElement>,
): void {
    const image = event.currentTarget;

    if (!image.src.endsWith('/placeholder.svg')) {
        image.src = '/placeholder.svg';
    }
}

/** Phone card thumbnail: always 36px, the extra-item count as a corner badge, so the text column lines up card to card. */
function CardThumbnail({ order }: { order: Order }): React.JSX.Element {
    const first = order.items[0];
    const more = order.items.length - 1;

    return (
        <div className="relative size-9">
            <div className="size-9 overflow-hidden rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-alt)]">
                {first && (
                    <img
                        src={itemImageSrc(first)}
                        alt={first.product_name}
                        className="h-full w-full object-cover"
                        onError={showPlaceholderOnError}
                    />
                )}
            </div>
            {more > 0 && (
                <span className="absolute -right-1.5 -bottom-1.5 flex h-4 min-w-4 items-center justify-center rounded-full border border-white bg-[var(--color-primary)] px-1 text-[9px] leading-none font-bold text-white">
                    +{more}
                </span>
            )}
        </div>
    );
}

/** Up to `max` overlapping item thumbnails, then a "+N" chip for the rest. */
function OrderThumbnails({
    order,
    max,
}: {
    order: Order;
    max: number;
}): React.JSX.Element {
    return (
        <div className="flex shrink-0 -space-x-2">
            {order.items.slice(0, max).map((item, idx) => (
                <div
                    key={idx}
                    className="relative z-[10] size-7 overflow-hidden rounded-md border-[1.5px] border-white bg-[var(--color-surface-alt)] shadow-sm"
                    style={{ zIndex: 10 - idx }}
                >
                    <img
                        src={itemImageSrc(item)}
                        alt={item.product_name}
                        className="h-full w-full object-cover"
                        onError={showPlaceholderOnError}
                    />
                </div>
            ))}
            {order.items.length > max && (
                <div className="relative z-0 flex size-7 items-center justify-center rounded-md border-[1.5px] border-white bg-[var(--color-primary-light)] text-[10px] font-bold text-[var(--color-primary)] shadow-sm">
                    +{order.items.length - max}
                </div>
            )}
        </div>
    );
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

    // The order number awaiting the customer's confirmation to cancel.
    const [cancelTarget, setCancelTarget] = useState<string | null>(null);
    const [cancelling, setCancelling] = useState(false);

    const handleCancel = (orderNumber: string) => {
        setCancelTarget(orderNumber);
    };

    const confirmCancel = () => {
        if (cancelTarget === null || cancelling) {
            return;
        }

        router.patch(
            route('account.orders.cancel', cancelTarget),
            {},
            {
                preserveScroll: true,
                onStart: () => setCancelling(true),
                onFinish: () => {
                    setCancelling(false);
                    setCancelTarget(null);
                },
            },
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

                {/* Orders List: cards on phones, a one-row-per-order table from md (DISC-52, owner request). */}
                {orders.data.length > 0 ? (
                    <>
                        {/*
                         * Phones: one card per order on a grid, so every line
                         * starts at the same place: a fixed thumbnail column,
                         * then order number (total on the right), date and
                         * delivery note, and the pills. Below a divider, the
                         * actions fill the width (text buttons share it, icons
                         * at the end), the same on every card.
                         */}
                        <div className="space-y-2 md:hidden">
                            {orders.data.map((order) => (
                                <div
                                    key={order.id}
                                    className="rounded-xl border border-[var(--color-border)] bg-white p-2.5 shadow-sm"
                                >
                                    <div className="grid grid-cols-[auto_minmax(0,1fr)_auto] items-start gap-x-2.5 gap-y-1">
                                        <div className="row-span-3">
                                            <CardThumbnail order={order} />
                                        </div>
                                        <p className="truncate font-mono text-xs leading-5 font-bold text-[var(--color-text-primary)]">
                                            #{order.order_number}
                                        </p>
                                        <p className="text-right text-xs leading-5 font-bold whitespace-nowrap text-[var(--color-primary)]">
                                            {formatCurrency(order.total)}
                                        </p>
                                        <p className="col-span-2 col-start-2 flex flex-wrap items-center gap-x-1 text-[11px] leading-4 text-[var(--color-text-secondary)]">
                                            <Calendar
                                                size={11}
                                                className="text-[var(--color-text-muted)]"
                                            />
                                            <span className="whitespace-nowrap">
                                                {formatDate(order.created_at)}
                                            </span>
                                            <span aria-hidden="true">·</span>
                                            <span className="whitespace-nowrap italic">
                                                {deliveryNote(order)}
                                            </span>
                                        </p>
                                        <div className="col-span-2 col-start-2 flex items-center gap-1">
                                            <OrderStatusBadge
                                                className={PILL_CLASS}
                                                status={order.status}
                                            />
                                            <PaymentStatusBadge
                                                className={PILL_CLASS}
                                                status={order.payment_status}
                                            />
                                        </div>
                                    </div>

                                    <div className="mt-2 flex items-center gap-1.5 border-t border-[var(--color-border)] pt-2">
                                        <Button
                                            asChild
                                            variant="outline"
                                            size="sm"
                                            className="h-6 flex-1 gap-1 rounded-md border-[var(--color-border)] px-2 text-[11px] font-bold hover:bg-[var(--color-surface-alt)]"
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
                                                className="h-6 flex-1 gap-1 rounded-md bg-[var(--color-secondary)] px-2 text-[11px] font-bold text-white hover:bg-[#2A2A4E]"
                                            >
                                                <Link
                                                    href={route('order.track', {
                                                        order: order.order_number,
                                                    })}
                                                >
                                                    Track Order
                                                    <ArrowRight size={11} />
                                                </Link>
                                            </Button>
                                        )}
                                        {orderHasInvoice(order) && (
                                            <Button
                                                asChild
                                                variant="outline"
                                                size="sm"
                                                className={`${ICON_BUTTON_CLASS} shrink-0`}
                                            >
                                                <a
                                                    href={route(
                                                        'account.orders.invoice',
                                                        order.order_number,
                                                    )}
                                                    aria-label="Download invoice"
                                                    title="Download invoice"
                                                >
                                                    <Download size={12} />
                                                </a>
                                            </Button>
                                        )}
                                        {order.status === 'pending' && (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                className="size-6 shrink-0 rounded-md border-red-200 p-0 text-red-600 hover:border-red-300 hover:bg-red-50"
                                                aria-label="Cancel order"
                                                title="Cancel order"
                                                onClick={() =>
                                                    handleCancel(
                                                        order.order_number,
                                                    )
                                                }
                                            >
                                                <XCircle size={12} />
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>

                        {/*
                         * md and up: one row per order, dividing lines between
                         * them. Sized for the narrowest case (~685px at lg with
                         * the account menu beside it): ~660px of columns. The
                         * actions are 24px icons in fixed slots, so View,
                         * Track, Invoice and Cancel line up from row to row.
                         * If a very long total ever overflows, the table
                         * scrolls sideways instead of squeezing.
                         */}
                        <div className="hidden overflow-x-auto rounded-2xl border border-[var(--color-border)] bg-white shadow-sm md:block">
                            <table className="w-full text-left text-xs">
                                <thead className="border-b border-[var(--color-border)] bg-[var(--color-surface-alt)] text-[10px] font-semibold tracking-wide text-[var(--color-text-secondary)] uppercase">
                                    <tr>
                                        <th scope="col" className="px-2.5 py-2">
                                            Order
                                        </th>
                                        <th scope="col" className="px-2.5 py-2">
                                            Date
                                        </th>
                                        <th scope="col" className="px-2.5 py-2">
                                            Status
                                        </th>
                                        <th scope="col" className="px-2.5 py-2">
                                            Payment
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-2.5 py-2 text-right"
                                        >
                                            Total
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-2.5 py-2 text-right"
                                        >
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-[var(--color-border)]">
                                    {orders.data.map((order) => (
                                        <tr
                                            key={order.id}
                                            className="whitespace-nowrap transition-colors hover:bg-[var(--color-surface-alt)]"
                                        >
                                            <td className="px-2.5 py-1.5">
                                                <div className="flex items-center gap-2">
                                                    <OrderThumbnails
                                                        order={order}
                                                        max={1}
                                                    />
                                                    <span className="font-mono font-bold text-[var(--color-text-primary)]">
                                                        #{order.order_number}
                                                    </span>
                                                </div>
                                            </td>
                                            {/* The delivery note has no column of its own: it's the date's tooltip (and read out by screen readers). */}
                                            <td
                                                className="px-2.5 py-1.5 text-[11px] text-[var(--color-text-secondary)]"
                                                title={deliveryNote(order)}
                                            >
                                                {formatDate(order.created_at)}
                                                <span className="sr-only">
                                                    , {deliveryNote(order)}
                                                </span>
                                            </td>
                                            <td className="px-2.5 py-1.5">
                                                <OrderStatusBadge
                                                    className={PILL_CLASS}
                                                    status={order.status}
                                                />
                                            </td>
                                            <td className="px-2.5 py-1.5">
                                                <PaymentStatusBadge
                                                    className={PILL_CLASS}
                                                    status={
                                                        order.payment_status
                                                    }
                                                />
                                            </td>
                                            <td className="px-2.5 py-1.5 text-right font-bold text-[var(--color-primary)]">
                                                {formatCurrency(order.total)}
                                            </td>
                                            <td className="px-2.5 py-1.5">
                                                <div className="flex items-center justify-end gap-1">
                                                    <Button
                                                        asChild
                                                        variant="outline"
                                                        size="sm"
                                                        className={
                                                            ICON_BUTTON_CLASS
                                                        }
                                                    >
                                                        <Link
                                                            href={route(
                                                                'account.orders.show',
                                                                order.order_number,
                                                            )}
                                                            aria-label="View details"
                                                            title="View details"
                                                        >
                                                            <Eye size={13} />
                                                        </Link>
                                                    </Button>
                                                    {order.status !==
                                                    'cancelled' ? (
                                                        <Button
                                                            asChild
                                                            size="sm"
                                                            className="size-6 rounded-md bg-[var(--color-secondary)] p-0 text-white hover:bg-[#2A2A4E]"
                                                        >
                                                            <Link
                                                                href={route(
                                                                    'order.track',
                                                                    {
                                                                        order: order.order_number,
                                                                    },
                                                                )}
                                                                aria-label="Track order"
                                                                title="Track order"
                                                            >
                                                                <Truck
                                                                    size={13}
                                                                />
                                                            </Link>
                                                        </Button>
                                                    ) : (
                                                        <EmptyActionSlot />
                                                    )}
                                                    {orderHasInvoice(order) && (
                                                        <Button
                                                            asChild
                                                            variant="outline"
                                                            size="sm"
                                                            className={
                                                                ICON_BUTTON_CLASS
                                                            }
                                                        >
                                                            <a
                                                                href={route(
                                                                    'account.orders.invoice',
                                                                    order.order_number,
                                                                )}
                                                                aria-label="Download invoice"
                                                                title="Download invoice"
                                                            >
                                                                <Download
                                                                    size={13}
                                                                />
                                                            </a>
                                                        </Button>
                                                    )}
                                                    {!orderHasInvoice(
                                                        order,
                                                    ) && <EmptyActionSlot />}
                                                    {order.status ===
                                                    'pending' ? (
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            className="size-6 rounded-md border-red-200 p-0 text-red-600 hover:border-red-300 hover:bg-red-50"
                                                            aria-label="Cancel order"
                                                            title="Cancel order"
                                                            onClick={() =>
                                                                handleCancel(
                                                                    order.order_number,
                                                                )
                                                            }
                                                        >
                                                            <XCircle
                                                                size={13}
                                                            />
                                                        </Button>
                                                    ) : (
                                                        <EmptyActionSlot />
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </>
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

            <ConfirmDialog
                open={cancelTarget !== null}
                onOpenChange={(open) =>
                    !open && !cancelling && setCancelTarget(null)
                }
                title="Cancel this order?"
                description={
                    cancelTarget
                        ? `Are you sure you want to cancel order #${cancelTarget}? This cannot be undone.`
                        : undefined
                }
                confirmLabel="Cancel Order"
                processingLabel="Cancelling…"
                cancelLabel="Keep Order"
                onConfirm={confirmCancel}
                processing={cancelling}
            />
        </>
    );
}
