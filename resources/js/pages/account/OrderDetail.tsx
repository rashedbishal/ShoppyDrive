import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ShoppingBag,
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
            <DialogContent className="sm:max-w-lg">
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
}

/**
 * The options bought, e.g. "Color: Red | Set: 12pcs": the copy taken when the
 * order was placed, so a later rename or removal never changes it; the live
 * variant only for older orders without that copy.
 */
function orderedOptions(item: OrderItem): string | null {
    const attributes =
        (item.product_snapshot?.variant_attributes as
            | Record<string, string>
            | null
            | undefined) ??
        item.variant?.attributes ??
        null;
    const parts = Object.entries(attributes ?? {}).map(
        ([name, value]) => `${name}: ${value}`,
    );

    return parts.length > 0 ? parts.join(' | ') : null;
}

export default function OrderDetail({
    order,
}: OrderDetailProps): React.JSX.Element {
    const { props } = usePage<SharedPageProps>();
    const { flash } = props;

    const handleCancel = () => {
        if (
            !confirm(
                'Are you sure you want to cancel this order? This cannot be undone.',
            )
        ) {
            return;
        }

        router.patch(
            route('account.orders.cancel', order.order_number),
            {},
            { preserveScroll: true },
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

                <div className="grid grid-cols-1 items-start gap-8 lg:grid-cols-12">
                    {/* Left: Timeline and Items */}
                    <div className="space-y-8 lg:col-span-8">
                        {/* Status Timeline */}
                        <div className="rounded-3xl border border-[var(--color-border)] bg-white p-8 shadow-sm md:p-10">
                            <h2 className="mb-8 flex items-center gap-3 text-xl font-bold text-[var(--color-text-primary)]">
                                <ClockIcon
                                    size={20}
                                    className="text-[var(--color-primary)]"
                                />
                                Order Journey
                            </h2>
                            <OrderTimeline histories={order.status_histories} />
                        </div>

                        {/* Items Table */}
                        <div className="overflow-hidden rounded-3xl border border-[var(--color-border)] bg-white shadow-sm">
                            <div className="border-b border-[var(--color-border)] p-6 md:p-8">
                                <h2 className="text-xl font-bold text-[var(--color-text-primary)]">
                                    Order Items
                                </h2>
                            </div>
                            <div className="flex flex-col">
                                {order.items.map((item, index) => (
                                    <div
                                        key={item.id}
                                        className={`flex flex-col justify-between gap-4 p-6 transition-colors hover:bg-[var(--color-surface-alt)]/50 sm:flex-row sm:items-center md:p-8 ${index !== order.items.length - 1 ? 'border-b border-[var(--color-border)]' : ''}`}
                                    >
                                        <div className="flex min-w-0 flex-1 items-start gap-4 sm:items-center">
                                            <div className="h-20 w-20 shrink-0 overflow-hidden rounded-xl border border-[var(--color-border)] bg-[var(--color-surface-alt)] sm:h-16 sm:w-16">
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
                                            </div>
                                            <div className="flex min-w-0 flex-col justify-center">
                                                <p className="line-clamp-2 text-sm font-bold text-[var(--color-text-primary)]">
                                                    {item.product_name}
                                                </p>
                                                {orderedOptions(item) && (
                                                    <p className="mt-1 text-[10px] font-bold tracking-widest text-[var(--color-text-secondary)] uppercase">
                                                        {orderedOptions(item)}
                                                    </p>
                                                )}
                                                <p className="mt-1.5 font-mono text-xs text-[var(--color-text-secondary)]">
                                                    SKU: {item.sku || 'N/A'}
                                                </p>
                                            </div>
                                        </div>

                                        <div className="mt-2 flex items-center justify-between gap-4 rounded-xl bg-[var(--color-surface-alt)] p-4 sm:mt-0 sm:justify-end sm:gap-8 sm:rounded-none sm:bg-transparent sm:p-0">
                                            <div className="text-left sm:text-right">
                                                <p className="mb-1 text-[10px] font-bold tracking-widest text-[var(--color-text-secondary)] uppercase sm:hidden">
                                                    Price
                                                </p>
                                                <p className="text-sm font-bold text-[var(--color-text-primary)]">
                                                    {formatCurrency(item.price)}
                                                </p>
                                            </div>
                                            <div className="text-center">
                                                <p className="mb-1 text-[10px] font-bold tracking-widest text-[var(--color-text-secondary)] uppercase sm:hidden">
                                                    Qty
                                                </p>
                                                <p className="text-sm font-bold text-[var(--color-text-secondary)]">
                                                    x{item.quantity}
                                                </p>
                                            </div>
                                            <div className="text-right">
                                                <p className="mb-1 text-[10px] font-bold tracking-widest text-[var(--color-text-secondary)] uppercase sm:hidden">
                                                    Subtotal
                                                </p>
                                                <p className="text-sm font-bold text-[var(--color-primary)]">
                                                    {formatCurrency(
                                                        item.subtotal,
                                                    )}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>

                            {/* Detailed Totals */}
                            <div className="flex justify-end bg-[var(--color-surface-alt)]/50 p-8">
                                <div className="w-full max-w-xs space-y-3">
                                    <div className="flex justify-between text-sm">
                                        <span className="font-medium text-[var(--color-text-secondary)]">
                                            Subtotal
                                        </span>
                                        <span className="font-bold text-[var(--color-text-primary)]">
                                            {formatCurrency(order.subtotal)}
                                        </span>
                                    </div>
                                    <div className="flex justify-between text-sm">
                                        <span className="font-medium text-[var(--color-text-secondary)]">
                                            Shipping Cost
                                        </span>
                                        <span className="font-bold text-[var(--color-text-primary)]">
                                            {formatCurrency(
                                                order.shipping_cost,
                                            )}
                                        </span>
                                    </div>
                                    <div className="flex justify-between text-sm">
                                        <span className="font-medium text-[var(--color-text-secondary)]">
                                            Tax
                                        </span>
                                        <span className="font-bold text-[var(--color-text-primary)]">
                                            {formatCurrency(order.tax)}
                                        </span>
                                    </div>
                                    {order.discount > 0 && (
                                        <div className="flex justify-between text-sm">
                                            <span className="font-medium text-[var(--color-text-secondary)]">
                                                Discount ({order.coupon_code})
                                            </span>
                                            <span className="font-bold text-[var(--color-success)]">
                                                -
                                                {formatCurrency(order.discount)}
                                            </span>
                                        </div>
                                    )}
                                    <div className="my-4 h-px w-full bg-[var(--color-border)]" />
                                    <div className="flex items-end justify-between">
                                        <span className="text-lg font-bold text-[var(--color-text-primary)]">
                                            Total
                                        </span>
                                        <span className="text-2xl font-bold text-[var(--color-primary)]">
                                            {formatCurrency(order.total)}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Right: Order Summary Details */}
                    <div className="space-y-8 lg:col-span-4">
                        {/* Tracking Info */}
                        {order.tracking_number && (
                            <div className="rounded-3xl bg-gradient-to-br from-[var(--color-secondary)] to-[#2A2A4E] p-8 text-white shadow-lg shadow-blue-100/50">
                                <div className="mb-6 flex items-center gap-3">
                                    <Truck
                                        size={24}
                                        className="text-blue-400"
                                    />
                                    <h3 className="text-lg font-bold">
                                        Track Shipment
                                    </h3>
                                </div>
                                <div className="mb-6 rounded-2xl border border-white/10 bg-white/10 p-4">
                                    <p className="mb-1 text-[10px] font-bold tracking-widest text-blue-200 uppercase">
                                        Tracking ID
                                    </p>
                                    <p className="font-mono text-xl font-bold tracking-tight">
                                        {order.tracking_number}
                                    </p>
                                </div>
                                <Button className="h-12 w-full rounded-xl bg-white font-bold text-[var(--color-secondary)] hover:bg-gray-100">
                                    Track on Carrier Site
                                </Button>
                            </div>
                        )}

                        {/* Customer & Address Details */}
                        <div className="space-y-8 rounded-3xl border border-[var(--color-border)] bg-white p-8 shadow-sm">
                            <div>
                                <h3 className="mb-4 flex items-center gap-2 text-sm font-bold tracking-widest text-[var(--color-text-primary)] uppercase">
                                    <MapPin
                                        size={16}
                                        className="text-[var(--color-primary)]"
                                    />
                                    Shipping Address
                                </h3>
                                <div className="rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface-alt)] p-5">
                                    <p className="mb-1 text-sm font-bold text-[var(--color-text-primary)]">
                                        {order.shipping_address.name}
                                    </p>
                                    <p className="mb-3 text-xs font-medium text-[var(--color-text-secondary)]">
                                        {order.shipping_address.phone}
                                    </p>
                                    <p className="text-xs leading-relaxed text-[var(--color-text-secondary)]">
                                        {order.shipping_address.address_line1}
                                        <br />
                                        {order.shipping_address
                                            .address_line2 && (
                                            <>
                                                {
                                                    order.shipping_address
                                                        .address_line2
                                                }
                                                <br />
                                            </>
                                        )}
                                        {order.shipping_address.city},{' '}
                                        {order.shipping_address.state}{' '}
                                        {order.shipping_address.postal_code}
                                        <br />
                                        {order.shipping_address.country}
                                    </p>
                                </div>
                            </div>

                            <div>
                                <h3 className="mb-4 flex items-center gap-2 text-sm font-bold tracking-widest text-[var(--color-text-primary)] uppercase">
                                    <CreditCard
                                        size={16}
                                        className="text-[var(--color-primary)]"
                                    />
                                    Payment Method
                                </h3>
                                <div className="rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface-alt)] p-5">
                                    <div className="mb-4 flex items-center justify-between">
                                        <p className="text-sm font-bold text-[var(--color-text-primary)] uppercase">
                                            {order.payment_method}
                                        </p>
                                        <PaymentStatusBadge
                                            status={order.payment_status}
                                        />
                                    </div>
                                    <div className="flex items-center gap-3 rounded-xl border border-[var(--color-border)] bg-white p-3">
                                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-[var(--color-surface-alt)] text-[var(--color-primary)]">
                                            {(order.payment_method as string) ===
                                            'sslcommerz' ? (
                                                <CreditCard size={20} />
                                            ) : (
                                                <ShoppingBag size={20} />
                                            )}
                                        </div>
                                        <div>
                                            <p className="text-xs font-bold text-[var(--color-text-primary)]">
                                                {(order.payment_method as string) ===
                                                'sslcommerz'
                                                    ? 'Paid via Online'
                                                    : 'Cash on Delivery'}
                                            </p>
                                            <p className="text-[10px] font-medium text-[var(--color-text-secondary)]">
                                                Transaction ID:{' '}
                                                {(order.payment_method as string) ===
                                                'sslcommerz'
                                                    ? 'TXN-98234723'
                                                    : 'N/A'}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <h3 className="mb-4 text-sm font-bold tracking-widest text-[var(--color-text-primary)] uppercase">
                                    Help & Support
                                </h3>
                                <div className="space-y-3">
                                    <Button
                                        asChild
                                        variant="outline"
                                        className="w-full justify-start rounded-xl border-[var(--color-border)] font-bold text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)]"
                                    >
                                        <Link href={route('contact')}>
                                            <MessageSquare
                                                size={16}
                                                className="mr-3"
                                            />
                                            Contact Support
                                        </Link>
                                    </Button>
                                    <Button
                                        asChild
                                        variant="outline"
                                        className="w-full justify-start rounded-xl border-[var(--color-border)] font-bold text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)]"
                                    >
                                        <Link href={route('return-policy')}>
                                            <Package
                                                size={16}
                                                className="mr-3"
                                            />
                                            Return Policy
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <RequestReturnDialog
                order={order}
                open={returnDialogOpen}
                onOpenChange={setReturnDialogOpen}
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
