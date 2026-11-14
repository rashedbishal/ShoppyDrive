import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    Package,
    Calendar,
    MapPin,
    CreditCard,
    Download,
    Truck,
    Undo2,
    MessageSquare,
    XCircle,
    Banknote,
    Smartphone,
} from 'lucide-react';
import React, { useState } from 'react';

import {
    OrderStatusBadge,
    PaymentStatusBadge,
} from '@/components/shared/OrderStatusBadge';
import { OrderTimeline } from '@/components/shared/OrderTimeline';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { orderHasInvoice } from '@/lib/order-invoice';
import { formatCurrency, formatDate, getPrimaryImagePath } from '@/lib/utils';
import type { Order, OrderItem, SharedPageProps } from '@/types';

const RETURN_STATUS_LABEL: Record<string, string> = {
    pending: 'Return requested',
    approved: 'Return approved',
    rejected: 'Return rejected',
    refunded: 'Return refunded',
};

const RETURN_STATUS_VARIANT: Record<
    string,
    'warning' | 'success' | 'error' | 'info'
> = {
    pending: 'warning',
    approved: 'success',
    rejected: 'error',
    refunded: 'info',
};

function RequestReturnDialog({
    order,
    open,
    onOpenChange,
}: {
    order: Order;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const [selected, setSelected] = useState<Record<number, boolean>>({});
    const [quantities, setQuantities] = useState<Record<number, number>>({});
    const [reason, setReason] = useState('');
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const toggleItem = (itemId: number, maxQuantity: number) => {
        setSelected((prev) => ({ ...prev, [itemId]: !prev[itemId] }));
        setQuantities((prev) => ({
            ...prev,
            [itemId]: prev[itemId] ?? maxQuantity,
        }));
    };

    const hasSelection = Object.values(selected).some(Boolean);

    const submit = () => {
        const items = order.items
            .filter((item) => selected[item.id])
            .map((item) => ({
                order_item_id: item.id,
                quantity: quantities[item.id] ?? item.quantity,
            }));

        if (items.length === 0 || !reason.trim() || processing) {
            return;
        }

        setProcessing(true);
        setErrors({});

        router.post(
            route('account.orders.return.store', order.order_number),
            { reason, items },
            {
                onSuccess: () => {
                    onOpenChange(false);
                    setReason('');
                    setSelected({});
                    setQuantities({});
                },
                onError: (pageErrors) =>
                    setErrors(pageErrors as Record<string, string>),
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent appearance="premium" className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Request a return</DialogTitle>
                    <DialogDescription>
                        Select the item(s) you'd like to return and tell us why.
                        We'll review your request and follow up by email.
                    </DialogDescription>
                </DialogHeader>

                <div className="max-h-64 space-y-3 overflow-y-auto pr-1">
                    {order.items.map((item) => (
                        <div
                            key={item.id}
                            className="flex items-start gap-3 rounded-xl border border-[var(--color-border)] p-3"
                        >
                            <Checkbox
                                checked={!!selected[item.id]}
                                onCheckedChange={() =>
                                    toggleItem(item.id, item.quantity)
                                }
                            />
                            <div className="min-w-0 flex-1">
                                <p className="line-clamp-1 text-sm font-semibold text-[var(--color-text-primary)]">
                                    {item.product_name}
                                </p>
                                <p className="text-xs text-[var(--color-text-secondary)]">
                                    Ordered: {item.quantity}
                                </p>
                            </div>
                            {selected[item.id] && (
                                <Input
                                    type="number"
                                    min={1}
                                    max={item.quantity}
                                    value={quantities[item.id] ?? item.quantity}
                                    onChange={(e) =>
                                        setQuantities((prev) => ({
                                            ...prev,
                                            [item.id]: Math.max(
                                                1,
                                                Math.min(
                                                    item.quantity,
                                                    Number(e.target.value) || 1,
                                                ),
                                            ),
                                        }))
                                    }
                                    className="h-9 w-16 rounded-lg text-center"
                                />
                            )}
                        </div>
                    ))}
                </div>

                <div className="space-y-1.5">
                    <Label htmlFor="return-reason">Reason for return *</Label>
                    <Textarea
                        id="return-reason"
                        maxLength={1000}
                        showCount
                        value={reason}
                        onChange={(e) => setReason(e.target.value)}
                        placeholder="Tell us what went wrong…"
                        rows={3}
                    />
                    {errors.reason && (
                        <p className="text-xs text-red-500">{errors.reason}</p>
                    )}
                    {errors.items && (
                        <p className="text-xs text-red-500">{errors.items}</p>
                    )}
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        disabled={processing || !reason.trim() || !hasSelection}
                        onClick={submit}
                    >
                        {processing ? 'Submitting…' : 'Submit Request'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

interface OrderDetailProps {
    order: Order;
    /** DISC-49: product id → product page URL, only for products still public. */
    productLinks: Record<number, string>;
}

/**
 * The options bought, e.g. "Color: Red | Set: 12pcs": the copy taken when the
 * order was placed, so a later rename or removal never changes it; the live
 * variant only for older orders without that copy.
 */
/**
 * DISC-49: the real payment method (the old card showed "Cash on Delivery"
 * for bKash/Nagad orders and a made-up transaction ID for online ones).
 */
function paymentMethodLabel(method: Order['payment_method']): string {
    if (method === 'cod') {
        return 'Cash on Delivery';
    }

    if (method === 'mobile_banking') {
        return 'bKash / Nagad';
    }

    return 'Online payment';
}

/**
 * The options bought on this line (Size, Color, …): the ones saved on the
 * order, or — when none were saved — the live variant's (DISC-49; mirrors
 * OrderItem::variantLabel()).
 */
function orderedOptions(item: OrderItem): [string, string][] {
    const saved = item.product_snapshot?.variant_attributes as
        | Record<string, string>
        | null
        | undefined;
    const attributes =
        saved && Object.keys(saved).length > 0
            ? saved
            : (item.variant?.attributes ?? {});

    return Object.entries(attributes ?? {}).map(([name, value]) => [
        name,
        String(value),
    ]);
}

export default function OrderDetail({
    order,
    productLinks = {},
}: OrderDetailProps): React.JSX.Element {
    const { props } = usePage<SharedPageProps>();
    const { flash } = props;

    const [cancelDialogOpen, setCancelDialogOpen] = useState(false);
    const [cancelling, setCancelling] = useState(false);

    const handleCancel = () => {
        setCancelDialogOpen(true);
    };

    const confirmCancel = () => {
        if (cancelling) {
            return;
        }

        router.patch(
            route('account.orders.cancel', order.order_number),
            {},
            {
                preserveScroll: true,
                onStart: () => setCancelling(true),
                onFinish: () => {
                    setCancelling(false);
                    setCancelDialogOpen(false);
                },
            },
        );
    };

    const canDownloadInvoice = orderHasInvoice(order);

    const [returnDialogOpen, setReturnDialogOpen] = useState(false);
    const latestReturn = order.latest_return_request;
    // The backend now allows a separate return request per item (not just
    // one per order), and enforces per-item remaining quantity itself with
    // a clear error message — this page doesn't have enough information
    // to know which items (if any) still have returnable quantity left,
    // so it always offers the button for a delivered order and lets the
    // backend be the authority on whether a given submission is valid.
    const canRequestReturn = order.status === 'delivered';

    return (
        <>
            <Head title={`Order #${order.order_number}`} />

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
                <div className="flex flex-col justify-between gap-6 md:flex-row md:items-center">
                    <div className="flex items-center gap-4">
                        <Link
                            href={route('account.orders')}
                            className="rounded-full border border-[var(--color-border)] bg-white p-2 text-[var(--color-text-muted)] transition-colors hover:text-[var(--color-primary)]"
                        >
                            <ArrowLeft size={18} />
                        </Link>
                        <div>
                            <div className="mb-1 flex items-center gap-3">
                                <h1 className="font-display text-2xl font-bold text-[var(--color-text-primary)]">
                                    Order #{order.order_number}
                                </h1>
                                <OrderStatusBadge status={order.status} />
                            </div>
                            <p className="flex items-center gap-2 text-sm font-medium text-[var(--color-text-secondary)]">
                                <Calendar size={14} />
                                Placed on {formatDate(order.created_at, 'long')}
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-wrap gap-3">
                        {canDownloadInvoice && (
                            <a
                                href={route(
                                    'account.orders.invoice',
                                    order.order_number,
                                )}
                            >
                                <Button
                                    variant="outline"
                                    className="rounded-xl border-[var(--color-border)] font-bold hover:bg-[var(--color-surface-alt)]"
                                >
                                    <Download size={16} className="mr-2" />
                                    Invoice
                                </Button>
                            </a>
                        )}
                        {canRequestReturn && (
                            <Button
                                onClick={() => setReturnDialogOpen(true)}
                                className="rounded-xl bg-[var(--color-primary)] font-bold text-white hover:bg-[var(--color-primary-hover)]"
                            >
                                <Undo2 size={16} className="mr-2" />
                                Return Items
                            </Button>
                        )}
                        {latestReturn && latestReturn.status !== 'rejected' && (
                            <Badge
                                variant={
                                    RETURN_STATUS_VARIANT[latestReturn.status]
                                }
                                className="self-center"
                            >
                                {RETURN_STATUS_LABEL[latestReturn.status]}
                            </Badge>
                        )}
                        {order.status === 'pending' && (
                            <Button
                                variant="outline"
                                className="rounded-xl border-red-200 font-bold text-red-600 hover:border-red-300 hover:bg-red-50"
                                onClick={handleCancel}
                            >
                                <XCircle size={16} className="mr-2" />
                                Cancel Order
                            </Button>
                        )}
                    </div>
                </div>

                {latestReturn?.status === 'rejected' && (
                    <div className="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-800">
                        Your previous return request for this order was rejected
                        {latestReturn.admin_note
                            ? `: ${latestReturn.admin_note}`
                            : '.'}{' '}
                        You can submit a new request if you'd like.
                    </div>
                )}

                <div className="grid grid-cols-1 items-start gap-5 lg:grid-cols-12">
                    {/* Left: Timeline and Items (DISC-49: compact) */}
                    <div className="space-y-5 lg:col-span-8">
                        {/* Status Timeline */}
                        <div className="rounded-2xl border border-[var(--color-border)] bg-white p-4 shadow-sm sm:p-5">
                            <h2 className="mb-4 flex items-center gap-2 text-base font-bold text-[var(--color-text-primary)]">
                                <ClockIcon
                                    size={18}
                                    className="text-[var(--color-primary)]"
                                />
                                Order Journey
                            </h2>
                            <OrderTimeline
                                histories={order.status_histories}
                                compact
                            />
                        </div>

                        {/* Items */}
                        <div className="overflow-hidden rounded-2xl border border-[var(--color-border)] bg-white shadow-sm">
                            <div className="flex items-center justify-between border-b border-[var(--color-border)] px-4 py-3 sm:px-5">
                                <h2 className="text-base font-bold text-[var(--color-text-primary)]">
                                    Order Items
                                </h2>
                                <span className="rounded-full bg-[var(--color-surface-alt)] px-2.5 py-0.5 text-xs font-semibold text-[var(--color-text-secondary)]">
                                    {order.items.length}{' '}
                                    {order.items.length === 1
                                        ? 'item'
                                        : 'items'}
                                </span>
                            </div>
                            <ul className="divide-y divide-[var(--color-border)]">
                                {order.items.map((item) => {
                                    const productUrl = item.product_id
                                        ? productLinks[item.product_id]
                                        : undefined;
                                    const options = orderedOptions(item);
                                    const image = (
                                        <img
                                            src={
                                                item.variant?.image ||
                                                (item.product_snapshot
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
                                    );

                                    return (
                                        <li
                                            key={item.id}
                                            className="flex items-start gap-3 px-4 py-3 sm:items-center sm:px-5"
                                        >
                                            {productUrl ? (
                                                <Link
                                                    href={productUrl}
                                                    className="h-14 w-14 shrink-0 overflow-hidden rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-alt)] transition hover:border-[var(--color-primary)]"
                                                    aria-label={`View ${item.product_name}`}
                                                >
                                                    {image}
                                                </Link>
                                            ) : (
                                                <div className="h-14 w-14 shrink-0 overflow-hidden rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-alt)]">
                                                    {image}
                                                </div>
                                            )}
                                            <div className="min-w-0 flex-1">
                                                {productUrl ? (
                                                    <Link
                                                        href={productUrl}
                                                        className="line-clamp-2 text-sm font-semibold text-[var(--color-text-primary)] hover:text-[var(--color-primary)] hover:underline"
                                                    >
                                                        {item.product_name}
                                                    </Link>
                                                ) : (
                                                    <p className="line-clamp-2 text-sm font-semibold text-[var(--color-text-primary)]">
                                                        {item.product_name}
                                                    </p>
                                                )}
                                                <div className="mt-1 flex flex-wrap items-center gap-1">
                                                    {options.map(
                                                        ([name, value]) => (
                                                            <span
                                                                key={name}
                                                                className="rounded-md bg-[var(--color-surface-alt)] px-1.5 py-0.5 text-[11px] font-medium text-[var(--color-text-secondary)]"
                                                            >
                                                                {name}:{' '}
                                                                <span className="font-semibold text-[var(--color-text-primary)]">
                                                                    {value}
                                                                </span>
                                                            </span>
                                                        ),
                                                    )}
                                                    <span className="font-mono text-[11px] text-[var(--color-text-muted)]">
                                                        SKU: {item.sku || 'N/A'}
                                                    </span>
                                                </div>
                                            </div>
                                            <div className="shrink-0 text-right">
                                                <p className="text-sm font-bold text-[var(--color-primary)]">
                                                    {formatCurrency(
                                                        item.subtotal,
                                                    )}
                                                </p>
                                                <p className="mt-0.5 text-xs whitespace-nowrap text-[var(--color-text-secondary)]">
                                                    {formatCurrency(item.price)}{' '}
                                                    × {item.quantity}
                                                </p>
                                            </div>
                                        </li>
                                    );
                                })}
                            </ul>

                            {/* Totals */}
                            <div className="border-t border-[var(--color-border)] bg-[var(--color-surface-alt)]/50 px-4 py-3 sm:px-5">
                                <dl className="ml-auto w-full space-y-1.5 text-sm sm:max-w-xs">
                                    <div className="flex justify-between">
                                        <dt className="text-[var(--color-text-secondary)]">
                                            Subtotal
                                        </dt>
                                        <dd className="font-semibold text-[var(--color-text-primary)]">
                                            {formatCurrency(order.subtotal)}
                                        </dd>
                                    </div>
                                    <div className="flex justify-between">
                                        <dt className="text-[var(--color-text-secondary)]">
                                            Shipping
                                        </dt>
                                        <dd className="font-semibold text-[var(--color-text-primary)]">
                                            {formatCurrency(
                                                order.shipping_cost,
                                            )}
                                        </dd>
                                    </div>
                                    {order.tax > 0 && (
                                        <div className="flex justify-between">
                                            <dt className="text-[var(--color-text-secondary)]">
                                                Tax
                                            </dt>
                                            <dd className="font-semibold text-[var(--color-text-primary)]">
                                                {formatCurrency(order.tax)}
                                            </dd>
                                        </div>
                                    )}
                                    {order.discount > 0 && (
                                        <div className="flex justify-between">
                                            <dt className="text-[var(--color-text-secondary)]">
                                                Discount
                                                {order.coupon_code
                                                    ? ` (${order.coupon_code})`
                                                    : ''}
                                            </dt>
                                            <dd className="font-semibold text-[var(--color-success)]">
                                                -
                                                {formatCurrency(order.discount)}
                                            </dd>
                                        </div>
                                    )}
                                    <div className="flex items-baseline justify-between border-t border-[var(--color-border)] pt-2">
                                        <dt className="font-bold text-[var(--color-text-primary)]">
                                            Total
                                        </dt>
                                        <dd className="text-lg font-bold text-[var(--color-primary)]">
                                            {formatCurrency(order.total)}
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        </div>
                    </div>

                    {/* Right: Order Summary Details */}
                    <div className="space-y-5 lg:col-span-4">
                        {/* Tracking Info */}
                        {order.tracking_number && (
                            <div className="rounded-2xl bg-gradient-to-br from-[var(--color-secondary)] to-[#2A2A4E] p-5 text-white shadow-lg shadow-blue-100/50">
                                <div className="mb-3 flex items-center gap-2">
                                    <Truck
                                        size={18}
                                        className="text-blue-400"
                                    />
                                    <h3 className="text-base font-bold">
                                        Track Shipment
                                    </h3>
                                </div>
                                <div className="mb-3 rounded-xl border border-white/10 bg-white/10 px-3 py-2.5">
                                    <p className="text-[10px] font-bold tracking-widest text-blue-200 uppercase">
                                        Tracking ID
                                    </p>
                                    <p className="font-mono text-base font-bold tracking-tight">
                                        {order.tracking_number}
                                    </p>
                                </div>
                                <Button className="h-10 w-full rounded-xl bg-white font-bold text-[var(--color-secondary)] hover:bg-gray-100">
                                    Track on Carrier Site
                                </Button>
                            </div>
                        )}

                        {/* Delivery & payment (DISC-49) */}
                        <div className="divide-y divide-[var(--color-border)] overflow-hidden rounded-2xl border border-[var(--color-border)] bg-white shadow-sm">
                            <section className="p-4 sm:p-5">
                                <h3 className="mb-2.5 flex items-center gap-2 text-[11px] font-bold tracking-widest text-[var(--color-text-secondary)] uppercase">
                                    <MapPin
                                        size={14}
                                        className="text-[var(--color-primary)]"
                                    />
                                    Shipping Address
                                </h3>
                                <p className="text-sm font-semibold text-[var(--color-text-primary)]">
                                    {order.shipping_address.name}
                                </p>
                                {order.shipping_address.phone && (
                                    <p className="text-xs text-[var(--color-text-secondary)]">
                                        {order.shipping_address.phone}
                                    </p>
                                )}
                                <p className="mt-1.5 text-xs leading-relaxed text-[var(--color-text-secondary)]">
                                    {[
                                        order.shipping_address.address_line1,
                                        order.shipping_address.address_line2,
                                    ]
                                        .filter(Boolean)
                                        .join(', ')}
                                    <br />
                                    {[
                                        order.shipping_address.city,
                                        [
                                            order.shipping_address.state,
                                            order.shipping_address.postal_code,
                                        ]
                                            .filter(Boolean)
                                            .join(' '),
                                    ]
                                        .filter(Boolean)
                                        .join(', ')}
                                    {order.shipping_address.country && (
                                        <>
                                            <br />
                                            {order.shipping_address.country}
                                        </>
                                    )}
                                </p>
                            </section>

                            <section className="p-4 sm:p-5">
                                <h3 className="mb-2.5 flex items-center gap-2 text-[11px] font-bold tracking-widest text-[var(--color-text-secondary)] uppercase">
                                    <CreditCard
                                        size={14}
                                        className="text-[var(--color-primary)]"
                                    />
                                    Payment
                                </h3>
                                <div className="flex items-center justify-between gap-3">
                                    <div className="flex min-w-0 items-center gap-2.5">
                                        <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[var(--color-surface-alt)] text-[var(--color-primary)]">
                                            {order.payment_method === 'cod' ? (
                                                <Banknote size={16} />
                                            ) : order.payment_method ===
                                              'mobile_banking' ? (
                                                <Smartphone size={16} />
                                            ) : (
                                                <CreditCard size={16} />
                                            )}
                                        </span>
                                        <p className="truncate text-sm font-semibold text-[var(--color-text-primary)]">
                                            {paymentMethodLabel(
                                                order.payment_method,
                                            )}
                                        </p>
                                    </div>
                                    <PaymentStatusBadge
                                        status={order.payment_status}
                                    />
                                </div>
                                <div className="mt-3 flex items-baseline justify-between rounded-lg bg-[var(--color-surface-alt)] px-3 py-2 text-sm">
                                    <span className="text-[var(--color-text-secondary)]">
                                        {order.payment_status === 'paid'
                                            ? 'Paid'
                                            : order.payment_status ===
                                                'refunded'
                                              ? 'Refunded'
                                              : order.payment_method === 'cod'
                                                ? 'To pay on delivery'
                                                : 'Amount'}
                                    </span>
                                    <span className="font-bold text-[var(--color-text-primary)]">
                                        {formatCurrency(order.total)}
                                    </span>
                                </div>
                            </section>

                            <section className="p-4 sm:p-5">
                                <h3 className="mb-2.5 text-[11px] font-bold tracking-widest text-[var(--color-text-secondary)] uppercase">
                                    Help & Support
                                </h3>
                                <div className="grid grid-cols-2 gap-2">
                                    <Button
                                        asChild
                                        variant="outline"
                                        size="sm"
                                        className="justify-start rounded-lg border-[var(--color-border)] font-semibold text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)]"
                                    >
                                        <Link href={route('contact')}>
                                            <MessageSquare
                                                size={14}
                                                className="mr-1.5"
                                            />
                                            Support
                                        </Link>
                                    </Button>
                                    <Button
                                        asChild
                                        variant="outline"
                                        size="sm"
                                        className="justify-start rounded-lg border-[var(--color-border)] font-semibold text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)]"
                                    >
                                        <Link href={route('return-policy')}>
                                            <Package
                                                size={14}
                                                className="mr-1.5"
                                            />
                                            Returns
                                        </Link>
                                    </Button>
                                </div>
                            </section>
                        </div>
                    </div>
                </div>
            </div>

            <RequestReturnDialog
                order={order}
                open={returnDialogOpen}
                onOpenChange={setReturnDialogOpen}
            />

            <ConfirmDialog
                open={cancelDialogOpen}
                onOpenChange={(open) =>
                    !cancelling && setCancelDialogOpen(open)
                }
                title="Cancel this order?"
                description={`Are you sure you want to cancel order #${order.order_number}? This cannot be undone.`}
                confirmLabel="Cancel Order"
                processingLabel="Cancelling…"
                cancelLabel="Keep Order"
                onConfirm={confirmCancel}
                processing={cancelling}
            />
        </>
    );
}

function ClockIcon({ size = 20, className = '' }) {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            width={size}
            height={size}
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
            className={className}
        >
            <circle cx="12" cy="12" r="10" />
            <polyline points="12 6 12 12 16 14" />
        </svg>
    );
}
