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
    XCircle
} from 'lucide-react';
import React, { useState } from 'react';

import AccountLayout from '@/components/layout/AccountLayout';
import { OrderStatusBadge, PaymentStatusBadge } from '@/components/shared/OrderStatusBadge';
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
import { formatCurrency, formatDate } from '@/lib/utils';
import type { Order, SharedPageProps } from '@/types';

const RETURN_STATUS_LABEL: Record<string, string> = {
    pending: 'Return requested',
    approved: 'Return approved',
    rejected: 'Return rejected',
    refunded: 'Return refunded',
};

const RETURN_STATUS_VARIANT: Record<string, 'warning' | 'success' | 'error' | 'info'> = {
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
        setQuantities((prev) => ({ ...prev, [itemId]: prev[itemId] ?? maxQuantity }));
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
                onError: (pageErrors) => setErrors(pageErrors as Record<string, string>),
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
                        Select the item(s) you'd like to return and tell us why. We'll review your request and follow up by email.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-3 max-h-64 overflow-y-auto pr-1">
                    {order.items.map((item) => (
                        <div key={item.id} className="flex items-start gap-3 rounded-xl border border-[var(--color-border)] p-3">
                            <Checkbox
                                checked={!!selected[item.id]}
                                onCheckedChange={() => toggleItem(item.id, item.quantity)}
                            />
                            <div className="min-w-0 flex-1">
                                <p className="text-sm font-semibold text-[var(--color-text-primary)] line-clamp-1">{item.product_name}</p>
                                <p className="text-xs text-[var(--color-text-muted)]">Ordered: {item.quantity}</p>
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
                                            [item.id]: Math.max(1, Math.min(item.quantity, Number(e.target.value) || 1)),
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
                        value={reason}
                        onChange={(e) => setReason(e.target.value)}
                        placeholder="Tell us what went wrong…"
                        rows={3}
                    />
                    {errors.reason && <p className="text-xs text-red-500">{errors.reason}</p>}
                    {errors.items && <p className="text-xs text-red-500">{errors.items}</p>}
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
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

export default function OrderDetail({
    order,
}: OrderDetailProps): React.JSX.Element {
    const { props } = usePage<SharedPageProps>();
    const { flash } = props;

    const handleCancel = () => {
        if (!confirm('Are you sure you want to cancel this order? This cannot be undone.')) return;
        router.patch(route('account.orders.cancel', order.order_number), {}, { preserveScroll: true });
    };

    const canDownloadInvoice = (() => {
        if (order.status === 'cancelled' || order.status === 'refunded') return false;
        if (order.payment_method === 'cod') return order.status === 'delivered';
        return true;
    })();

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
        <AccountLayout>
            <Head title={`Order #${order.order_number}`} />

            <div className="space-y-8">
                {flash?.success && (
                    <div className="rounded-2xl bg-green-50 border border-green-200 px-5 py-4 text-sm font-medium text-green-700">
                        {flash.success}
                    </div>
                )}
                {flash?.error && (
                    <div className="rounded-2xl bg-red-50 border border-red-200 px-5 py-4 text-sm font-medium text-red-700">
                        {flash.error}
                    </div>
                )}

                {/* Header */}
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div className="flex items-center gap-4">
                        <Link 
                            href={route('account.orders')}
                            className="p-2 rounded-full bg-white border border-[var(--color-border)] text-[var(--color-text-muted)] hover:text-[var(--color-primary)] transition-colors"
                        >
                            <ArrowLeft size={18} />
                        </Link>
                        <div>
                            <div className="flex items-center gap-3 mb-1">
                                <h1 className="text-2xl font-serif font-bold text-[var(--color-text-primary)]">Order #{order.order_number}</h1>
                                <OrderStatusBadge status={order.status} />
                            </div>
                            <p className="text-sm text-[var(--color-text-secondary)] font-medium flex items-center gap-2">
                                <Calendar size={14} />
                                Placed on {formatDate(order.created_at, 'long')}
                            </p>
                        </div>
                    </div>
                    
                    <div className="flex flex-wrap gap-3">
                        {canDownloadInvoice && (
                            <a href={route('account.orders.invoice', order.order_number)}>
                                <Button variant="outline" className="rounded-xl font-bold border-[var(--color-border)] hover:bg-[var(--color-surface-alt)]">
                                    <Download size={16} className="mr-2" />
                                    Invoice
                                </Button>
                            </a>
                        )}
                        {canRequestReturn && (
                            <Button
                                onClick={() => setReturnDialogOpen(true)}
                                className="rounded-xl font-bold bg-[var(--color-primary)] text-white hover:bg-[var(--color-primary-hover)]"
                            >
                                <Undo2 size={16} className="mr-2" />
                                Return Items
                            </Button>
                        )}
                        {latestReturn && latestReturn.status !== 'rejected' && (
                            <Badge variant={RETURN_STATUS_VARIANT[latestReturn.status]} className="self-center">
                                {RETURN_STATUS_LABEL[latestReturn.status]}
                            </Badge>
                        )}
                        {order.status === 'pending' && (
                            <Button
                                variant="outline"
                                className="rounded-xl font-bold border-red-200 text-red-600 hover:bg-red-50 hover:border-red-300"
                                onClick={handleCancel}
                            >
                                <XCircle size={16} className="mr-2" />
                                Cancel Order
                            </Button>
                        )}
                    </div>
                </div>

                {latestReturn?.status === 'rejected' && (
                    <div className="rounded-2xl bg-amber-50 border border-amber-200 px-5 py-4 text-sm text-amber-800">
                        Your previous return request for this order was rejected
                        {latestReturn.admin_note ? `: ${latestReturn.admin_note}` : '.'} You can submit a new request if you'd like.
                    </div>
                )}

                <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                    {/* Left: Timeline and Items */}
                    <div className="lg:col-span-8 space-y-8">
                        {/* Status Timeline */}
                        <div className="bg-white rounded-3xl p-8 md:p-10 shadow-sm border border-[var(--color-border)]">
                            <h2 className="text-xl font-bold text-[var(--color-text-primary)] mb-8 flex items-center gap-3">
                                <ClockIcon size={20} className="text-[var(--color-primary)]" />
                                Order Journey
                            </h2>
                            <OrderTimeline histories={order.status_histories} />
                        </div>

                        {/* Items Table */}
                        <div className="bg-white rounded-3xl shadow-sm border border-[var(--color-border)] overflow-hidden">
                            <div className="p-6 md:p-8 border-b border-[var(--color-border)]">
                                <h2 className="text-xl font-bold text-[var(--color-text-primary)]">Order Items</h2>
                            </div>
                            <div className="flex flex-col">
                                {order.items.map((item, index) => (
                                    <div key={item.id} className={`flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-6 md:p-8 hover:bg-[var(--color-surface-alt)]/50 transition-colors ${index !== order.items.length - 1 ? 'border-b border-[var(--color-border)]' : ''}`}>
                                        <div className="flex items-start sm:items-center gap-4 flex-1 min-w-0">
                                            <div className="w-20 h-20 sm:w-16 sm:h-16 rounded-xl bg-[var(--color-surface-alt)] overflow-hidden border border-[var(--color-border)] shrink-0">
                                                <img 
                                                    src={item.product?.primary_image_url || '/placeholder.webp'} 
                                                    alt={item.product_name} 
                                                    className="w-full h-full object-cover" 
                                                />
                                            </div>
                                            <div className="flex flex-col justify-center min-w-0">
                                                <p className="text-sm font-bold text-[var(--color-text-primary)] line-clamp-2">{item.product_name}</p>
                                                {item.variant && (
                                                    <p className="text-[10px] font-bold text-[var(--color-text-muted)] uppercase tracking-widest mt-1">
                                                        {Object.entries(item.variant.attributes).map(([k, v]) => `${k}: ${v}`).join(' | ')}
                                                    </p>
                                                )}
                                                <p className="text-xs text-[var(--color-text-muted)] mt-1.5 font-mono">SKU: {item.sku || 'N/A'}</p>
                                            </div>
                                        </div>
                                        
                                        <div className="flex items-center justify-between sm:justify-end gap-4 sm:gap-8 bg-[var(--color-surface-alt)] sm:bg-transparent p-4 sm:p-0 rounded-xl sm:rounded-none mt-2 sm:mt-0">
                                            <div className="text-left sm:text-right">
                                                <p className="text-[10px] font-bold text-[var(--color-text-muted)] uppercase tracking-widest sm:hidden mb-1">Price</p>
                                                <p className="text-sm font-bold text-[var(--color-text-primary)]">
                                                    {formatCurrency(item.price)}
                                                </p>
                                            </div>
                                            <div className="text-center">
                                                <p className="text-[10px] font-bold text-[var(--color-text-muted)] uppercase tracking-widest sm:hidden mb-1">Qty</p>
                                                <p className="text-sm font-bold text-[var(--color-text-secondary)]">
                                                    x{item.quantity}
                                                </p>
                                            </div>
                                            <div className="text-right">
                                                <p className="text-[10px] font-bold text-[var(--color-text-muted)] uppercase tracking-widest sm:hidden mb-1">Subtotal</p>
                                                <p className="text-sm font-bold text-[var(--color-primary)]">
                                                    {formatCurrency(item.subtotal)}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                            
                            {/* Detailed Totals */}
                            <div className="bg-[var(--color-surface-alt)]/50 p-8 flex justify-end">
                                <div className="w-full max-w-xs space-y-3">
                                    <div className="flex justify-between text-sm">
                                        <span className="text-[var(--color-text-secondary)] font-medium">Subtotal</span>
                                        <span className="font-bold text-[var(--color-text-primary)]">{formatCurrency(order.subtotal)}</span>
                                    </div>
                                    <div className="flex justify-between text-sm">
                                        <span className="text-[var(--color-text-secondary)] font-medium">Shipping Cost</span>
                                        <span className="font-bold text-[var(--color-text-primary)]">{formatCurrency(order.shipping_cost)}</span>
                                    </div>
                                    <div className="flex justify-between text-sm">
                                        <span className="text-[var(--color-text-secondary)] font-medium">Tax</span>
                                        <span className="font-bold text-[var(--color-text-primary)]">{formatCurrency(order.tax)}</span>
                                    </div>
                                    {order.discount > 0 && (
                                        <div className="flex justify-between text-sm">
                                            <span className="text-[var(--color-text-secondary)] font-medium">Discount ({order.coupon_code})</span>
                                            <span className="font-bold text-[var(--color-success)]">-{formatCurrency(order.discount)}</span>
                                        </div>
                                    )}
                                    <div className="h-px bg-[var(--color-border)] w-full my-4" />
                                    <div className="flex justify-between items-end">
                                        <span className="text-lg font-bold text-[var(--color-text-primary)]">Total</span>
                                        <span className="text-2xl font-bold text-[var(--color-primary)]">{formatCurrency(order.total)}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Right: Order Summary Details */}
                    <div className="lg:col-span-4 space-y-8">
                        {/* Tracking Info */}
                        {order.tracking_number && (
                            <div className="bg-gradient-to-br from-[var(--color-secondary)] to-[#2A2A4E] rounded-3xl p-8 text-white shadow-lg shadow-blue-100/50">
                                <div className="flex items-center gap-3 mb-6">
                                    <Truck size={24} className="text-blue-400" />
                                    <h3 className="text-lg font-bold">Track Shipment</h3>
                                </div>
                                <div className="p-4 rounded-2xl bg-white/10 border border-white/10 mb-6">
                                    <p className="text-[10px] font-bold text-blue-200 uppercase tracking-widest mb-1">Tracking ID</p>
                                    <p className="text-xl font-mono font-bold tracking-tight">{order.tracking_number}</p>
                                </div>
                                <Button className="w-full h-12 rounded-xl bg-white text-[var(--color-secondary)] hover:bg-gray-100 font-bold">
                                    Track on Carrier Site
                                </Button>
                            </div>
                        )}

                        {/* Customer & Address Details */}
                        <div className="bg-white rounded-3xl p-8 shadow-sm border border-[var(--color-border)] space-y-8">
                            <div>
                                <h3 className="text-sm font-bold text-[var(--color-text-primary)] uppercase tracking-widest mb-4 flex items-center gap-2">
                                    <MapPin size={16} className="text-[var(--color-primary)]" />
                                    Shipping Address
                                </h3>
                                <div className="p-5 rounded-2xl bg-[var(--color-surface-alt)] border border-[var(--color-border)]">
                                    <p className="font-bold text-[var(--color-text-primary)] text-sm mb-1">{order.shipping_address.name}</p>
                                    <p className="text-xs text-[var(--color-text-secondary)] font-medium mb-3">{order.shipping_address.phone}</p>
                                    <p className="text-xs text-[var(--color-text-secondary)] leading-relaxed">
                                        {order.shipping_address.address_line1}<br />
                                        {order.shipping_address.address_line2 && <>{order.shipping_address.address_line2}<br /></>}
                                        {order.shipping_address.city}, {order.shipping_address.state} {order.shipping_address.postal_code}<br />
                                        {order.shipping_address.country}
                                    </p>
                                </div>
                            </div>

                            <div>
                                <h3 className="text-sm font-bold text-[var(--color-text-primary)] uppercase tracking-widest mb-4 flex items-center gap-2">
                                    <CreditCard size={16} className="text-[var(--color-primary)]" />
                                    Payment Method
                                </h3>
                                <div className="p-5 rounded-2xl bg-[var(--color-surface-alt)] border border-[var(--color-border)]">
                                    <div className="flex items-center justify-between mb-4">
                                        <p className="text-sm font-bold text-[var(--color-text-primary)] uppercase">{order.payment_method}</p>
                                        <PaymentStatusBadge status={order.payment_status} />
                                    </div>
                                    <div className="flex items-center gap-3 p-3 rounded-xl bg-white border border-[var(--color-border)]">
                                        <div className="w-10 h-10 rounded-lg bg-[var(--color-surface-alt)] flex items-center justify-center text-[var(--color-primary)]">
                                            {order.payment_method === 'sslcommerz' ? <CreditCard size={20} /> : <ShoppingBag size={20} />}
                                        </div>
                                        <div>
                                            <p className="text-xs font-bold text-[var(--color-text-primary)]">
                                                {order.payment_method === 'sslcommerz' ? 'Paid via Online' : 'Cash on Delivery'}
                                            </p>
                                            <p className="text-[10px] text-[var(--color-text-muted)] font-medium">Transaction ID: {order.payment_method === 'sslcommerz' ? 'TXN-98234723' : 'N/A'}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div>
                                <h3 className="text-sm font-bold text-[var(--color-text-primary)] uppercase tracking-widest mb-4">Help & Support</h3>
                                <div className="space-y-3">
                                    <Button variant="outline" className="w-full justify-start rounded-xl font-bold border-[var(--color-border)] text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)]">
                                        <MessageSquare size={16} className="mr-3" />
                                        Contact Support
                                    </Button>
                                    <Button variant="outline" className="w-full justify-start rounded-xl font-bold border-[var(--color-border)] text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)]">
                                        <Package size={16} className="mr-3" />
                                        Return Policy
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
        </AccountLayout>
    );
}

function ClockIcon({ size = 20, className = "" }) {
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
