import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Package, XCircle } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

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
    statuses: string[];
};

const statusVariant: Record<string, 'success' | 'warning' | 'error' | 'info' | 'default'> = {
    pending: 'warning',
    processing: 'info',
    shipped: 'info',
    delivered: 'success',
    cancelled: 'error',
    refunded: 'default',
};

export default function OrderShow({ order, statuses }: Props) {
    const { props } = usePage<any>();
    const flash = props.flash as { success?: string } | undefined;

    const { data, setData, patch, processing } = useForm({
        status: order.status,
        notes: '',
    });

    const { data: cancelData, setData: setCancelData, patch: cancelPatch, processing: cancelProcessing } = useForm({
        reason: '',
    });

    const handleStatusUpdate = (e: React.FormEvent) => {
        e.preventDefault();
        patch(route('admin.orders.updateStatus', order.id));
    };

    const handleCancel = (e: React.FormEvent) => {
        e.preventDefault();
        if (!confirm('Cancel this order? This will set the status to cancelled.')) return;
        cancelPatch(route('admin.orders.cancel', order.id));
    };

    return (
        <>
            <Head title={`Order ${order.order_number} — Admin`} />
            <div className="space-y-5">
                <div className="flex items-center gap-3">
                    <Link href={route('admin.orders.index')} className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                        <ArrowLeft className="size-4" />
                    </Link>
                    <div className="flex-1">
                        <div className="flex items-center gap-2">
                            <h1 className="font-mono text-xl font-bold text-slate-900">{order.order_number}</h1>
                            <Badge variant={statusVariant[order.status] ?? 'default'}>{order.status}</Badge>
                            <Badge variant={order.payment_status === 'paid' ? 'success' : 'warning'}>{order.payment_status}</Badge>
                        </div>
                        <p className="text-sm text-slate-500">{new Date(order.created_at).toLocaleString()}</p>
                    </div>
                </div>

                {flash?.success && (
                    <div className="rounded-lg bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-700">{flash.success}</div>
                )}

                <div className="grid gap-5 lg:grid-cols-[1fr_320px]">
                    <div className="space-y-5">
                        <Card className="shadow-sm">
                            <CardHeader className="border-b border-slate-100 pb-4">
                                <CardTitle className="text-sm font-semibold text-slate-700">
                                    <Package className="mr-1.5 inline size-4" /> Items
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="p-0">
                                <div className="divide-y divide-slate-50">
                                    {order.items.map((item) => (
                                        <div key={item.id} className="flex items-center gap-4 px-4 py-3">
                                            <div className="min-w-0 flex-1">
                                                <p className="text-sm font-medium text-slate-800">{item.product_name}</p>
                                                {item.sku && <p className="font-mono text-xs text-slate-400">{item.sku}</p>}
                                            </div>
                                            <p className="text-xs text-slate-500">×{item.quantity}</p>
                                            <p className="text-sm font-semibold text-slate-800">৳{item.subtotal.toLocaleString()}</p>
                                        </div>
                                    ))}
                                </div>
                                <div className="space-y-1.5 border-t border-slate-100 px-4 py-3">
                                    <div className="flex justify-between text-xs text-slate-500">
                                        <span>Subtotal</span><span>৳{order.subtotal.toLocaleString()}</span>
                                    </div>
                                    {order.discount > 0 && (
                                        <div className="flex justify-between text-xs text-emerald-600">
                                            <span>Discount{order.coupon_code ? ` (${order.coupon_code})` : ''}</span>
                                            <span>-৳{order.discount.toLocaleString()}</span>
                                        </div>
                                    )}
                                    <div className="flex justify-between text-xs text-slate-500">
                                        <span>Shipping</span><span>৳{order.shipping_cost.toLocaleString()}</span>
                                    </div>
                                    {order.tax > 0 && (
                                        <div className="flex justify-between text-xs text-slate-500">
                                            <span>Tax</span><span>৳{order.tax.toLocaleString()}</span>
                                        </div>
                                    )}
                                    <div className="flex justify-between border-t border-slate-100 pt-1.5 text-sm font-bold text-slate-800">
                                        <span>Total</span><span>৳{order.total.toLocaleString()}</span>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="shadow-sm">
                            <CardHeader className="border-b border-slate-100 pb-4">
                                <CardTitle className="text-sm font-semibold text-slate-700">Status History</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-2 pt-4">
                                {order.status_histories.length === 0 ? (
                                    <p className="text-sm text-slate-400">No history yet.</p>
                                ) : order.status_histories.map((h) => (
                                    <div key={h.id} className="flex items-start gap-3">
                                        <div className="mt-1 size-2 shrink-0 rounded-full bg-slate-300" />
                                        <div>
                                            <p className="text-xs font-medium text-slate-700">{h.status}</p>
                                            {h.notes && <p className="text-xs text-slate-400">{h.notes}</p>}
                                            <p className="text-[11px] text-slate-400">{new Date(h.created_at).toLocaleString()}</p>
                                        </div>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                    </div>

                    <div className="space-y-5">
                        <Card className="shadow-sm">
                            <CardHeader className="border-b border-slate-100 pb-4">
                                <CardTitle className="text-sm font-semibold text-slate-700">Update Status</CardTitle>
                            </CardHeader>
                            <CardContent className="pt-4">
                                <form onSubmit={handleStatusUpdate} className="space-y-3">
                                    <select
                                        className="h-9 w-full rounded-lg border border-slate-200 px-3 text-sm focus:outline-none focus:ring-1 focus:ring-[#c8102e]"
                                        value={data.status}
                                        onChange={(e) => setData('status', e.target.value)}
                                    >
                                        {statuses.map((s) => <option key={s} value={s}>{s}</option>)}
                                    </select>
                                    <textarea
                                        rows={2}
                                        placeholder="Notes (optional)"
                                        className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#c8102e]"
                                        value={data.notes}
                                        onChange={(e) => setData('notes', e.target.value)}
                                    />
                                    <Button type="submit" disabled={processing} className="w-full bg-[#c8102e] text-white hover:bg-[#a50d25]">
                                        {processing ? 'Updating…' : 'Update Status'}
                                    </Button>
                                </form>
                            </CardContent>
                        </Card>

                        {order.status !== 'cancelled' && order.status !== 'refunded' && order.status !== 'delivered' && (
                            <Card className="shadow-sm border-red-100">
                                <CardHeader className="border-b border-red-100 pb-4">
                                    <CardTitle className="flex items-center gap-1.5 text-sm font-semibold text-red-600">
                                        <XCircle className="size-4" /> Cancel Order
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="pt-4">
                                    <form onSubmit={handleCancel} className="space-y-3">
                                        <textarea
                                            rows={2}
                                            placeholder="Reason (optional)"
                                            className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-red-400"
                                            value={cancelData.reason}
                                            onChange={(e) => setCancelData('reason', e.target.value)}
                                        />
                                        <Button type="submit" disabled={cancelProcessing} className="w-full bg-red-600 text-white hover:bg-red-700">
                                            {cancelProcessing ? 'Cancelling…' : 'Cancel This Order'}
                                        </Button>
                                    </form>
                                </CardContent>
                            </Card>
                        )}

                        <Card className="shadow-sm">
                            <CardHeader className="border-b border-slate-100 pb-4">
                                <CardTitle className="text-sm font-semibold text-slate-700">Customer</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-1 pt-4 text-sm text-slate-700">
                                {order.user ? (
                                    <>
                                        <p className="font-semibold">{order.user.name}</p>
                                        <p className="text-slate-500">{order.user.email}</p>
                                        {order.user.phone && <p className="text-slate-500">{order.user.phone}</p>}
                                        <Link href={route('admin.customers.show', order.user.id)} className="text-xs text-[#c8102e] hover:underline">View customer →</Link>
                                    </>
                                ) : <p className="text-slate-400">Guest order</p>}
                            </CardContent>
                        </Card>

                        <Card className="shadow-sm">
                            <CardHeader className="border-b border-slate-100 pb-4">
                                <CardTitle className="text-sm font-semibold text-slate-700">Shipping Address</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-0.5 pt-4 text-sm text-slate-700">
                                <p className="font-semibold">{order.shipping_address.name}</p>
                                <p>{order.shipping_address.phone}</p>
                                <p>{order.shipping_address.address_line1}</p>
                                {order.shipping_address.address_line2 && <p>{order.shipping_address.address_line2}</p>}
                                <p>{order.shipping_address.city}, {order.shipping_address.state} {order.shipping_address.postal_code}</p>
                                <p>{order.shipping_address.country}</p>
                            </CardContent>
                        </Card>

                        {order.tracking_number && (
                            <Card className="shadow-sm">
                                <CardContent className="pt-4">
                                    <p className="text-xs text-slate-500">Tracking</p>
                                    <p className="font-mono text-sm font-medium text-slate-800">{order.tracking_number}</p>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}
