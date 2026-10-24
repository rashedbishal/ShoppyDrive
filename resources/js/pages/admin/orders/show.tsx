import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    Package,
    RefreshCw,
    Trash2,
    Truck,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { Select } from '@/components/ui/select';

type OrderItem = {
    id: number;
    product_name: string;
    sku: string | null;
    price: number;
    quantity: number;
    subtotal: number;
    product: { id: number; name: string; slug: string } | null;
};

type StatusHistory = {
    id: number;
    status: string;
    notes: string | null;
    created_at: string;
};

type Order = {
    id: number;
    order_number: string;
    status: string;
    payment_status: string;
    payment_method: string;
    subtotal: number;
    shipping_cost: number;
    discount: number;
    tax: number;
    total: number;
    coupon_code: string | null;
    tracking_number: string | null;
    notes: string | null;
    pathao_consignment_id: string | null;
    pathao_order_status: string | null;
    pathao_status_label: string | null;
    pathao_delivery_fee: number | null;
    pathao_sent_at: string | null;
    steadfast_consignment_id: string | null;
    steadfast_tracking_code: string | null;
    steadfast_status: string | null;
    steadfast_status_label: string | null;
    steadfast_sent_at: string | null;
    shipping_address: {
        name: string;
        phone: string;
        address_line1: string;
        address_line2?: string;
        city: string;
        state: string;
        postal_code?: string;
        country: string;
    };
    created_at: string;
    user: { id: number; name: string; email: string; phone?: string } | null;
    items: OrderItem[];
    status_histories: StatusHistory[];
};

type Props = {
    order: Order;
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

export default function OrderShow({ order }: Props) {
    const { props } = usePage<any>();
    const flash = props.flash as { success?: string } | undefined;

    const {
        data: cancelData,
        setData: setCancelData,
        patch: cancelPatch,
        processing: cancelProcessing,
    } = useForm({
        reason: '',
    });

    const [refundProcessing, setRefundProcessing] = useState(false);
    const [isDeleteDialogOpen, setIsDeleteDialogOpen] = useState(false);
    const [deleteProcessing, setDeleteProcessing] = useState(false);

    const handleDelete = () => {
        setDeleteProcessing(true);
        router.delete(route('admin.orders.destroy', order.id), {
            onFinish: () => setDeleteProcessing(false),
        });
    };

    const handleMarkRefunded = () => {
        if (
            !confirm(
                `Mark order ${order.order_number} as refunded? This cannot be undone.`,
            )
        ) {
            return;
        }

        setRefundProcessing(true);
        router.patch(
            route('admin.orders.markRefunded', order.id),
            {},
            {
                onFinish: () => setRefundProcessing(false),
            },
        );
    };

    const handleCancel = (e: React.FormEvent) => {
        e.preventDefault();

        if (
            !confirm(
                'Cancel this order? This will set the status to cancelled.',
            )
        ) {
            return;
        }

        cancelPatch(route('admin.orders.cancel', order.id));
    };

    const [courierProvider, setCourierProvider] = useState<
        'pathao' | 'steadfast'
    >('pathao');
    const [courierSending, setCourierSending] = useState(false);
    const [pathaoRefreshing, setPathaoRefreshing] = useState(false);
    const [steadfastRefreshing, setSteadfastRefreshing] = useState(false);

    const handleSendToCourier = () => {
        const label = courierProvider === 'pathao' ? 'Pathao' : 'Steadfast';

        if (
            !confirm(`Send order ${order.order_number} to ${label} for pickup?`)
        ) {
            return;
        }

        setCourierSending(true);
        router.post(
            route(
                courierProvider === 'pathao'
                    ? 'admin.orders.pathao.send'
                    : 'admin.orders.steadfast.send',
                order.id,
            ),
            {},
            {
                onFinish: () => setCourierSending(false),
            },
        );
    };

    const handleRefreshPathaoStatus = () => {
        setPathaoRefreshing(true);
        router.post(
            route('admin.orders.pathao.refresh', order.id),
            {},
            {
                onFinish: () => setPathaoRefreshing(false),
            },
        );
    };

    const handleRefreshSteadfastStatus = () => {
        setSteadfastRefreshing(true);
        router.post(
            route('admin.orders.steadfast.refresh', order.id),
            {},
            {
                onFinish: () => setSteadfastRefreshing(false),
            },
        );
    };

    return (
        <>
            <Head title={`Order ${order.order_number} — Admin`} />
            <div className="space-y-5">
                <div className="flex items-center gap-3">
                    <Link
                        href={route('admin.orders.index')}
                        className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                    >
                        <ArrowLeft className="size-4" />
                    </Link>
                    <div className="flex-1">
                        <div className="flex items-center gap-2">
                            <h1 className="font-mono text-xl font-bold text-slate-900">
                                {order.order_number}
                            </h1>
                            <Badge
                                variant={
                                    statusVariant[order.status] ?? 'default'
                                }
                            >
                                {order.status}
                            </Badge>
                            <Badge
                                variant={
                                    order.payment_status === 'paid'
                                        ? 'success'
                                        : 'warning'
                                }
                            >
                                {order.payment_status}
                            </Badge>
                        </div>
                        <p className="text-sm text-slate-500">
                            {new Date(order.created_at).toLocaleString()}
                        </p>
                    </div>
                </div>

                {flash?.success && (
                    <div className="rounded-lg bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-700">
                        {flash.success}
                    </div>
                )}

                <div className="grid gap-5 lg:grid-cols-[1fr_320px]">
                    <div className="space-y-5">
                        <Card className="shadow-sm">
                            <CardHeader className="border-b border-slate-100 pb-4">
                                <CardTitle className="text-sm font-semibold text-slate-700">
                                    <Package className="mr-1.5 inline size-4" />{' '}
                                    Items
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="p-0">
                                <div className="divide-y divide-slate-50">
                                    {order.items.map((item) => (
                                        <div
                                            key={item.id}
                                            className="flex items-center gap-4 px-4 py-3"
                                        >
                                            <div className="min-w-0 flex-1">
                                                <p className="text-sm font-medium text-slate-800">
                                                    {item.product_name}
                                                </p>
                                                {item.sku && (
                                                    <p className="font-mono text-xs text-slate-400">
                                                        {item.sku}
                                                    </p>
                                                )}
                                            </div>
                                            <p className="text-xs text-slate-500">
                                                ×{item.quantity}
                                            </p>
                                            <p className="text-sm font-semibold text-slate-800">
                                                ৳
                                                {item.subtotal.toLocaleString()}
                                            </p>
                                        </div>
                                    ))}
                                </div>
                                <div className="space-y-1.5 border-t border-slate-100 px-4 py-3">
                                    <div className="flex justify-between text-xs text-slate-500">
                                        <span>Subtotal</span>
                                        <span>
                                            ৳{order.subtotal.toLocaleString()}
                                        </span>
                                    </div>
                                    {order.discount > 0 && (
                                        <div className="flex justify-between text-xs text-emerald-600">
                                            <span>
                                                Discount
                                                {order.coupon_code
                                                    ? ` (${order.coupon_code})`
                                                    : ''}
                                            </span>
                                            <span>
                                                -৳
                                                {order.discount.toLocaleString()}
                                            </span>
                                        </div>
                                    )}
                                    <div className="flex justify-between text-xs text-slate-500">
                                        <span>Shipping</span>
                                        <span>
                                            ৳
                                            {order.shipping_cost.toLocaleString()}
                                        </span>
                                    </div>
                                    {order.tax > 0 && (
                                        <div className="flex justify-between text-xs text-slate-500">
                                            <span>Tax</span>
                                            <span>
                                                ৳{order.tax.toLocaleString()}
                                            </span>
                                        </div>
                                    )}
                                    <div className="flex justify-between border-t border-slate-100 pt-1.5 text-sm font-bold text-slate-800">
                                        <span>Total</span>
                                        <span>
                                            ৳{order.total.toLocaleString()}
                                        </span>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="shadow-sm">
                            <CardHeader className="border-b border-slate-100 pb-4">
                                <CardTitle className="text-sm font-semibold text-slate-700">
                                    Status History
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-2 pt-4">
                                {order.status_histories.length === 0 ? (
                                    <p className="text-sm text-slate-400">
                                        No history yet.
                                    </p>
                                ) : (
                                    order.status_histories.map((h) => (
                                        <div
                                            key={h.id}
                                            className="flex items-start gap-3"
                                        >
                                            <div className="mt-1 size-2 shrink-0 rounded-full bg-slate-300" />
                                            <div>
                                                <p className="text-xs font-medium text-slate-700">
                                                    {h.status}
                                                </p>
                                                {h.notes && (
                                                    <p className="text-xs text-slate-400">
                                                        {h.notes}
                                                    </p>
                                                )}
                                                <p className="text-[11px] text-slate-400">
                                                    {new Date(
                                                        h.created_at,
                                                    ).toLocaleString()}
                                                </p>
                                            </div>
                                        </div>
                                    ))
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    <div className="space-y-5">
                        <Card className="shadow-sm">
                            <CardHeader className="border-b border-slate-100 pb-4">
                                <CardTitle className="text-sm font-semibold text-slate-700">
                                    Status
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3 pt-4">
                                <p className="text-xs text-slate-500">
                                    Status now advances automatically from the
                                    courier (see the Courier card below) and can
                                    no longer be set by hand, except marking a
                                    paid order as refunded.
                                </p>
                                {order.payment_status === 'paid' &&
                                    order.status !== 'refunded' && (
                                        <Button
                                            variant="outline"
                                            className="w-full border-slate-300 text-slate-700 hover:bg-slate-50"
                                            disabled={refundProcessing}
                                            onClick={handleMarkRefunded}
                                        >
                                            {refundProcessing
                                                ? 'Updating…'
                                                : 'Mark as Refunded'}
                                        </Button>
                                    )}
                            </CardContent>
                        </Card>

                        {order.status !== 'cancelled' &&
                            order.status !== 'refunded' &&
                            order.status !== 'delivered' && (
                                <Card className="border-red-100 shadow-sm">
                                    <CardHeader className="border-b border-red-100 pb-4">
                                        <CardTitle className="flex items-center gap-1.5 text-sm font-semibold text-red-600">
                                            <XCircle className="size-4" />{' '}
                                            Cancel Order
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="pt-4">
                                        <form
                                            onSubmit={handleCancel}
                                            className="space-y-3"
                                        >
                                            <textarea
                                                rows={2}
                                                placeholder="Reason (optional)"
                                                className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-1 focus:ring-red-400 focus:outline-none"
                                                value={cancelData.reason}
                                                onChange={(e) =>
                                                    setCancelData(
                                                        'reason',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                            <Button
                                                type="submit"
                                                disabled={cancelProcessing}
                                                className="w-full bg-red-600 text-white hover:bg-red-700"
                                            >
                                                {cancelProcessing
                                                    ? 'Cancelling…'
                                                    : 'Cancel This Order'}
                                            </Button>
                                        </form>
                                    </CardContent>
                                </Card>
                            )}

                        {order.status === 'cancelled' && (
                            <Card className="border-red-100 shadow-sm">
                                <CardHeader className="border-b border-red-100 pb-4">
                                    <CardTitle className="flex items-center gap-1.5 text-sm font-semibold text-red-600">
                                        <Trash2 className="size-4" /> Delete
                                        Order
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="pt-4">
                                    <p className="mb-3 text-xs text-slate-500">
                                        Moves this cancelled order to trash. It
                                        can be restored or permanently deleted
                                        from there.
                                    </p>
                                    <Button
                                        className="w-full bg-red-600 text-white hover:bg-red-700"
                                        onClick={() =>
                                            setIsDeleteDialogOpen(true)
                                        }
                                    >
                                        <Trash2 className="size-4" /> Delete
                                        This Order
                                    </Button>
                                </CardContent>
                            </Card>
                        )}

                        <Card className="shadow-sm">
                            <CardHeader className="border-b border-slate-100 pb-4">
                                <CardTitle className="text-sm font-semibold text-slate-700">
                                    Customer
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-1 pt-4 text-sm text-slate-700">
                                {order.user ? (
                                    <>
                                        <p className="font-semibold">
                                            {order.user.name}
                                        </p>
                                        <p className="text-slate-500">
                                            {order.user.email}
                                        </p>
                                        {order.user.phone && (
                                            <p className="text-slate-500">
                                                {order.user.phone}
                                            </p>
                                        )}
                                        <Link
                                            href={route(
                                                'admin.customers.show',
                                                order.user.id,
                                            )}
                                            className="text-xs text-[#c8102e] hover:underline"
                                        >
                                            View customer →
                                        </Link>
                                    </>
                                ) : (
                                    <p className="text-slate-400">
                                        Guest order
                                    </p>
                                )}
                            </CardContent>
                        </Card>

                        <Card className="shadow-sm">
                            <CardHeader className="border-b border-slate-100 pb-4">
                                <CardTitle className="text-sm font-semibold text-slate-700">
                                    Shipping Address
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-0.5 pt-4 text-sm text-slate-700">
                                <p className="font-semibold">
                                    {order.shipping_address.name}
                                </p>
                                <p>{order.shipping_address.phone}</p>
                                <p>{order.shipping_address.address_line1}</p>
                                {order.shipping_address.address_line2 && (
                                    <p>
                                        {order.shipping_address.address_line2}
                                    </p>
                                )}
                                <p>
                                    {order.shipping_address.city},{' '}
                                    {order.shipping_address.state}{' '}
                                    {order.shipping_address.postal_code}
                                </p>
                                <p>{order.shipping_address.country}</p>
                            </CardContent>
                        </Card>

                        {order.tracking_number && (
                            <Card className="shadow-sm">
                                <CardContent className="pt-4">
                                    <p className="text-xs text-slate-500">
                                        Tracking
                                    </p>
                                    <p className="font-mono text-sm font-medium text-slate-800">
                                        {order.tracking_number}
                                    </p>
                                </CardContent>
                            </Card>
                        )}

                        <Card className="shadow-sm">
                            <CardHeader className="border-b border-slate-100 pb-4">
                                <CardTitle className="flex items-center gap-1.5 text-sm font-semibold text-slate-700">
                                    <Truck className="size-4" /> Courier
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3 pt-4">
                                {order.pathao_consignment_id ? (
                                    <>
                                        <div className="space-y-1 text-sm text-slate-700">
                                            <div className="flex justify-between">
                                                <span className="text-slate-500">
                                                    Provider
                                                </span>
                                                <span className="font-medium">
                                                    Pathao
                                                </span>
                                            </div>
                                            <div className="flex justify-between">
                                                <span className="text-slate-500">
                                                    Consignment ID
                                                </span>
                                                <span className="font-mono font-medium">
                                                    {
                                                        order.pathao_consignment_id
                                                    }
                                                </span>
                                            </div>
                                            <div className="flex justify-between">
                                                <span className="text-slate-500">
                                                    Status
                                                </span>
                                                <Badge variant="info">
                                                    {order.pathao_status_label ??
                                                        'Unknown'}
                                                </Badge>
                                            </div>
                                            {order.pathao_delivery_fee !==
                                                null && (
                                                <div className="flex justify-between">
                                                    <span className="text-slate-500">
                                                        Delivery fee
                                                    </span>
                                                    <span className="font-medium">
                                                        ৳
                                                        {order.pathao_delivery_fee.toLocaleString()}
                                                    </span>
                                                </div>
                                            )}
                                        </div>
                                        <Button
                                            variant="outline"
                                            className="w-full"
                                            disabled={pathaoRefreshing}
                                            onClick={handleRefreshPathaoStatus}
                                        >
                                            <RefreshCw
                                                className={`size-4 ${pathaoRefreshing ? 'animate-spin' : ''}`}
                                            />
                                            {pathaoRefreshing
                                                ? 'Checking…'
                                                : 'Refresh Status'}
                                        </Button>
                                    </>
                                ) : order.steadfast_consignment_id ? (
                                    <>
                                        <div className="space-y-1 text-sm text-slate-700">
                                            <div className="flex justify-between">
                                                <span className="text-slate-500">
                                                    Provider
                                                </span>
                                                <span className="font-medium">
                                                    Steadfast
                                                </span>
                                            </div>
                                            <div className="flex justify-between">
                                                <span className="text-slate-500">
                                                    Consignment ID
                                                </span>
                                                <span className="font-mono font-medium">
                                                    {
                                                        order.steadfast_consignment_id
                                                    }
                                                </span>
                                            </div>
                                            {order.steadfast_tracking_code && (
                                                <div className="flex justify-between">
                                                    <span className="text-slate-500">
                                                        Tracking code
                                                    </span>
                                                    <span className="font-mono font-medium">
                                                        {
                                                            order.steadfast_tracking_code
                                                        }
                                                    </span>
                                                </div>
                                            )}
                                            <div className="flex justify-between">
                                                <span className="text-slate-500">
                                                    Status
                                                </span>
                                                <Badge variant="info">
                                                    {order.steadfast_status_label ??
                                                        'Unknown'}
                                                </Badge>
                                            </div>
                                        </div>
                                        <Button
                                            variant="outline"
                                            className="w-full"
                                            disabled={steadfastRefreshing}
                                            onClick={
                                                handleRefreshSteadfastStatus
                                            }
                                        >
                                            <RefreshCw
                                                className={`size-4 ${steadfastRefreshing ? 'animate-spin' : ''}`}
                                            />
                                            {steadfastRefreshing
                                                ? 'Checking…'
                                                : 'Refresh Status'}
                                        </Button>
                                    </>
                                ) : (
                                    <>
                                        <p className="text-xs text-slate-500">
                                            Not sent to a courier yet. This
                                            books a pickup from the selected
                                            provider using this order's shipping
                                            address and total as the COD amount
                                            to collect.
                                        </p>
                                        <Select
                                            selectClassName="h-9 w-full rounded-lg border border-slate-200 px-3 text-sm focus:ring-1 focus:ring-[#c8102e] focus:outline-none"
                                            value={courierProvider}
                                            onChange={(e) =>
                                                setCourierProvider(
                                                    e.target.value as
                                                        | 'pathao'
                                                        | 'steadfast',
                                                )
                                            }
                                        >
                                            <option value="pathao">
                                                Pathao
                                            </option>
                                            <option value="steadfast">
                                                Steadfast
                                            </option>
                                        </Select>
                                        <Button
                                            className="w-full bg-[#c8102e] text-white hover:bg-[#a50d25]"
                                            disabled={
                                                courierSending ||
                                                order.status === 'cancelled'
                                            }
                                            onClick={handleSendToCourier}
                                        >
                                            {courierSending
                                                ? 'Sending…'
                                                : `Send to ${courierProvider === 'pathao' ? 'Pathao' : 'Steadfast'}`}
                                        </Button>
                                    </>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>

            <ConfirmDialog
                open={isDeleteDialogOpen}
                onOpenChange={setIsDeleteDialogOpen}
                title="Delete this order?"
                description={`This will move order ${order.order_number} to trash. It can be restored later, or permanently deleted from the Trashed Orders section.`}
                confirmLabel="Delete Order"
                onConfirm={handleDelete}
                processing={deleteProcessing}
            />
        </>
    );
}
