import { Head, Link, router, useForm } from '@inertiajs/react';
import { AnimatePresence } from 'framer-motion';
import {
    AlertTriangle,
    ArrowLeft,
    Ban,
    Download,
    Globe,
    History,
    Info,
    MapPin,
    Package,
    Pencil,
    RefreshCw,
    Trash2,
    Truck,
    User,
    Wallet,
    X,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import { AdminFade, AdminReveal } from '@/components/admin/admin-motion';
import CourierSubmissionPanel from '@/components/admin/CourierSubmissionPanel';
import type { CourierSubmission } from '@/components/admin/CourierSubmissionPanel';
import DeliveryVerificationDialog from '@/components/admin/DeliveryVerificationDialog';
import type { CourierEvidence } from '@/components/admin/DeliveryVerificationDialog';
import LostParcelDialog from '@/components/admin/LostParcelDialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { CharCount } from '@/components/ui/char-count';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { useAdminListUrl } from '@/hooks/use-admin-list-url';

type OrderItem = {
    id: number;
    product_name: string;
    sku: string | null;
    price: number;
    quantity: number;
    subtotal: number;
    product: { id: number; name: string; slug: string } | null;
    variant: { id: number; attributes: Record<string, string> } | null;
    product_snapshot: {
        image?: string | null;
        /** The options bought, copied when the order was placed. */
        variant_attributes?: Record<string, string> | null;
    } | null;
};

/**
 * The options bought, e.g. "Color: Red | Set: 12pcs": the copy taken when the
 * order was placed, so a later rename or removal never changes it; the live
 * variant only for older orders without that copy.
 */
function orderedOptions(item: OrderItem): string | null {
    const attributes =
        item.product_snapshot?.variant_attributes ??
        item.variant?.attributes ??
        null;
    const parts = Object.entries(attributes ?? {}).map(
        ([name, value]) => `${name}: ${value}`,
    );

    return parts.length > 0 ? parts.join(' | ') : null;
}

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
    pathao_status_updated_at: string | null;
    pathao_delivery_fee: number | null;
    pathao_sent_at: string | null;
    steadfast_consignment_id: string | null;
    steadfast_tracking_code: string | null;
    steadfast_status: string | null;
    steadfast_status_label: string | null;
    steadfast_sent_at: string | null;
    needsCourierInvestigation: boolean;
    courierStatusConflict: {
        order_status: string;
        courier_status: string;
        courier_status_label: string;
        courier_mapped_status: string;
    } | null;
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
    ip_address: string | null;
    user_agent: string | null;
    traffic_source: string | null;
    traffic_referrer: string | null;
    user: { id: number; name: string; email: string; phone?: string } | null;
    items: OrderItem[];
    status_histories: StatusHistory[];
};

type RefundSummary = {
    collected: number;
    product_net: number;
    shipping: number;
    cap: number;
    refunded: number;
    remaining: number;
    product_refund_complete: boolean;
    payment_fully_refunded: boolean;
};

type Props = {
    order: Order;
    courierSubmission: CourierSubmission | null;
    refundSummary: RefundSummary;
    courierEvidence: CourierEvidence;
    canVerifyDelivery: boolean;
    canConfirmLostParcel: boolean;
    /** DISC-38: which invoice copies exist (App\Support\OrderInvoice). */
    invoice: { available: boolean; parcel: boolean };
};

const taka = (value: number | null) =>
    value === null
        ? '—'
        : `৳${value.toLocaleString('en-BD', { minimumFractionDigits: 2 })}`;

const trafficSourceLabel: Record<string, string> = {
    google: 'Google',
    facebook: 'Facebook',
    instagram: 'Instagram',
    direct: 'Direct / Typed URL',
    other: 'Other Website',
};

const trafficSourceVariant: Record<
    string,
    'success' | 'warning' | 'error' | 'info' | 'default' | 'amber' | 'secondary'
> = {
    google: 'info',
    facebook: 'secondary',
    instagram: 'amber',
    direct: 'default',
    other: 'warning',
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

export default function OrderShow({
    order,
    courierSubmission,
    refundSummary,
    courierEvidence,
    canVerifyDelivery,
    canConfirmLostParcel,
    invoice,
}: Props) {
    const listUrl = useAdminListUrl('admin.orders.index');
    const [isVerifyOpen, setIsVerifyOpen] = useState(false);
    // COURIER-SEND-RACE-002 (L2/L3): mirrors the server-side guards, which
    // remain the real boundary — these only avoid offering a refused action.
    const courierUnresolved =
        courierSubmission?.state === 'submitting' ||
        courierSubmission?.state === 'unknown';
    const courierUnresolvedNote =
        'Unavailable while a courier submission is in progress or unresolved — resolve it in the Courier panel first.';

    const {
        data: cancelData,
        setData: setCancelData,
        patch: cancelPatch,
        processing: cancelProcessing,
    } = useForm({
        reason: '',
    });

    const [refundProcessing, setRefundProcessing] = useState(false);
    // BIZ-012: a shipped/delivered order's goods are out of our hands, so
    // they are restocked on refund only when the admin confirms they came back.
    const goodsAreOut =
        order.status === 'shipped' || order.status === 'delivered';
    const [goodsReturned, setGoodsReturned] = useState(false);
    const [isDeleteDialogOpen, setIsDeleteDialogOpen] = useState(false);
    const [deleteProcessing, setDeleteProcessing] = useState(false);
    const [blockingIp, setBlockingIp] = useState(false);

    const [isEditingAddress, setIsEditingAddress] = useState(false);
    const {
        data: addressData,
        setData: setAddressData,
        patch: patchAddress,
        processing: addressProcessing,
        errors: addressErrors,
        clearErrors: clearAddressErrors,
    } = useForm({
        phone: order.shipping_address.phone,
        address_line1: order.shipping_address.address_line1,
        address_line2: order.shipping_address.address_line2 ?? '',
        city: order.shipping_address.city,
        state: order.shipping_address.state,
        postal_code: order.shipping_address.postal_code ?? '',
        country: order.shipping_address.country,
    });

    // Re-seeds from the current (possibly just-saved) address rather than
    // calling the form's reset() — that resets to the values captured when
    // useForm first mounted, which goes stale after a successful save since
    // this component never remounts.
    const seedAddressForm = () => {
        setAddressData({
            phone: order.shipping_address.phone,
            address_line1: order.shipping_address.address_line1,
            address_line2: order.shipping_address.address_line2 ?? '',
            city: order.shipping_address.city,
            state: order.shipping_address.state,
            postal_code: order.shipping_address.postal_code ?? '',
            country: order.shipping_address.country,
        });
    };

    const startEditingAddress = () => {
        seedAddressForm();
        clearAddressErrors();
        setIsEditingAddress(true);
    };

    const cancelEditingAddress = () => {
        seedAddressForm();
        clearAddressErrors();
        setIsEditingAddress(false);
    };

    const handleSaveAddress = (e: React.FormEvent) => {
        e.preventDefault();

        patchAddress(route('admin.orders.updateShippingAddress', order.id), {
            preserveScroll: true,
            onSuccess: () => setIsEditingAddress(false),
        });
    };

    const handleBlockIp = () => {
        if (!order.ip_address) {
            return;
        }

        if (!confirm(`Block ${order.ip_address} from placing future orders?`)) {
            return;
        }

        setBlockingIp(true);
        router.post(
            route('admin.blocked-ips.store'),
            {
                ip_address: order.ip_address,
                reason: `Blocked from order ${order.order_number}`,
            },
            { onFinish: () => setBlockingIp(false) },
        );
    };

    const handleDelete = () => {
        setDeleteProcessing(true);
        router.delete(route('admin.orders.destroy', order.id), {
            onFinish: () => setDeleteProcessing(false),
        });
    };

    // PAY-RETURN-001: records a refund already paid back outside the app,
    // for exactly the remaining refundable amount (the server re-checks it).
    const handleMarkRefunded = () => {
        if (refundSummary.remaining <= 0) {
            return;
        }

        const method = prompt(
            `Refund ${taka(refundSummary.remaining)} for order ${order.order_number} (shipping is not refundable). How was it paid back (e.g. bKash, bank transfer)?`,
        );

        if (!method) {
            return;
        }

        setRefundProcessing(true);
        router.patch(
            route('admin.orders.markRefunded', order.id),
            {
                refund_amount: refundSummary.remaining,
                refund_method: method,
                goods_returned: goodsAreOut && goodsReturned,
            },
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
    const [isLostOpen, setIsLostOpen] = useState(false);
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
                <div className="flex items-start gap-3">
                    <Link
                        href={listUrl}
                        className="mt-0.5 shrink-0 rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                    >
                        <ArrowLeft className="size-4" />
                    </Link>
                    <div className="min-w-0 flex-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="font-mono text-lg font-bold break-all text-slate-900 sm:text-xl">
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
                        <p className="text-sm text-slate-600">
                            {new Date(order.created_at).toLocaleString()}
                        </p>
                    </div>
                    {invoice.available && (
                        <DropdownMenu modal={false}>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="shrink-0"
                                >
                                    <Download className="size-4" />
                                    <span className="hidden sm:inline">
                                        Download invoice
                                    </span>
                                    <span className="sm:hidden">Invoice</span>
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-64">
                                <DropdownMenuItem asChild>
                                    <a
                                        href={route('admin.orders.invoice', {
                                            order: order.id,
                                            copy: 'customer',
                                        })}
                                        className="flex flex-col items-start gap-0.5"
                                    >
                                        <span className="font-medium">
                                            Customer copy
                                        </span>
                                        <span className="text-xs text-slate-500">
                                            The same invoice the customer was
                                            emailed.
                                        </span>
                                    </a>
                                </DropdownMenuItem>
                                {invoice.parcel ? (
                                    <DropdownMenuItem asChild>
                                        <a
                                            href={route(
                                                'admin.orders.invoice',
                                                {
                                                    order: order.id,
                                                    copy: 'parcel',
                                                },
                                            )}
                                            className="flex flex-col items-start gap-0.5"
                                        >
                                            <span className="font-medium">
                                                Parcel copy
                                            </span>
                                            <span className="text-xs text-slate-500">
                                                Adds the courier and consignment
                                                ID.
                                            </span>
                                        </a>
                                    </DropdownMenuItem>
                                ) : (
                                    <DropdownMenuItem
                                        disabled
                                        className="flex flex-col items-start gap-0.5"
                                    >
                                        <span className="font-medium">
                                            Parcel copy
                                        </span>
                                        <span className="text-xs text-slate-500">
                                            Available after the order is sent to
                                            Steadfast or Pathao.
                                        </span>
                                    </DropdownMenuItem>
                                )}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    )}
                </div>

                {order.courierStatusConflict && (
                    <div className="flex items-start gap-2 rounded-xl border border-sky-200 bg-sky-50 p-3 text-sm text-sky-900">
                        <Info className="mt-0.5 size-4 shrink-0" />
                        <div>
                            <p className="font-medium">
                                Courier status differs from the order status
                            </p>
                            <p className="text-sky-800">
                                ShoppyDrive order status:{' '}
                                <span className="font-semibold capitalize">
                                    {order.courierStatusConflict.order_status}
                                </span>
                                . Latest Pathao status:{' '}
                                <span className="font-semibold">
                                    {
                                        order.courierStatusConflict
                                            .courier_status_label
                                    }
                                </span>{' '}
                                (would mean{' '}
                                <span className="capitalize">
                                    {
                                        order.courierStatusConflict
                                            .courier_mapped_status
                                    }
                                </span>
                                ). The order status is final, so nothing was
                                changed automatically — no payment, reward,
                                coupon, stock or email change. Check with Pathao
                                if this is unexpected.
                            </p>
                        </div>
                    </div>
                )}
                {order.needsCourierInvestigation && (
                    <div className="flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                        <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                        <div>
                            <p className="font-medium">
                                Courier report needs investigation
                            </p>
                            <p className="text-amber-700">
                                The last status this courier reported for this
                                order (see below) isn&apos;t one we recognize —
                                possibly a lost parcel. The order hasn&apos;t
                                been changed automatically. Contact the courier
                                to confirm what happened, then resolve this
                                manually through refund or return as
                                appropriate.
                            </p>
                            {canConfirmLostParcel && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="mt-2"
                                    onClick={() => setIsLostOpen(true)}
                                >
                                    Confirm parcel lost
                                </Button>
                            )}
                        </div>
                    </div>
                )}

                <div className="grid grid-cols-1 items-start gap-5 lg:grid-cols-[minmax(0,1fr)_360px]">
                    <div className="min-w-0 space-y-5">
                        <Card className="shadow-sm">
                            <CardHeader className="border-b border-slate-100">
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
                                            className="flex items-center gap-3 px-4 py-3"
                                        >
                                            <img
                                                src={
                                                    item.product_snapshot
                                                        ?.image ??
                                                    '/placeholder.svg'
                                                }
                                                alt=""
                                                className="size-12 shrink-0 rounded-lg border border-slate-100 object-cover"
                                            />
                                            <div className="min-w-0 flex-1">
                                                <p className="text-sm font-medium break-words text-slate-800">
                                                    {item.product_name}
                                                </p>
                                                {orderedOptions(item) && (
                                                    <p className="text-xs font-medium tracking-wide text-slate-600 uppercase">
                                                        {orderedOptions(item)}
                                                    </p>
                                                )}
                                                {item.sku && (
                                                    <p className="font-mono text-xs text-slate-600">
                                                        {item.sku}
                                                    </p>
                                                )}
                                            </div>
                                            <div className="shrink-0 text-right">
                                                <p className="text-sm font-semibold text-slate-800">
                                                    ৳
                                                    {item.subtotal.toLocaleString()}
                                                </p>
                                                <p className="text-xs text-slate-600">
                                                    ×{item.quantity}
                                                </p>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                                <div className="space-y-1.5 border-t border-slate-100 px-4 py-3">
                                    <div className="flex justify-between text-xs text-slate-600">
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
                                    <div className="flex justify-between text-xs text-slate-600">
                                        <span>Shipping</span>
                                        <span>
                                            ৳
                                            {order.shipping_cost.toLocaleString()}
                                        </span>
                                    </div>
                                    {order.tax > 0 && (
                                        <div className="flex justify-between text-xs text-slate-600">
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

                        <div className="grid items-stretch gap-5 md:grid-cols-2">
                            <Card className="h-full shadow-sm">
                                <CardHeader className="border-b border-slate-100">
                                    <CardTitle className="flex items-center gap-1.5 text-sm font-semibold text-slate-700">
                                        <User className="size-4" /> Customer
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-1 pt-3 text-sm text-slate-700">
                                    {order.user ? (
                                        <>
                                            <p className="font-semibold break-words">
                                                {order.user.name}
                                            </p>
                                            <p className="break-all text-slate-600">
                                                {order.user.email}
                                            </p>
                                            {order.user.phone && (
                                                <p className="text-slate-600">
                                                    {order.user.phone}
                                                </p>
                                            )}
                                            <Link
                                                href={route(
                                                    'admin.customers.show',
                                                    order.user.id,
                                                )}
                                                className="text-xs text-brand hover:underline"
                                            >
                                                View customer →
                                            </Link>
                                        </>
                                    ) : (
                                        <p className="text-slate-600">
                                            Guest order
                                        </p>
                                    )}
                                </CardContent>
                            </Card>
                            <Card className="h-full shadow-sm">
                                <CardHeader className="flex-row items-center justify-between border-b border-slate-100">
                                    <CardTitle className="flex items-center gap-1.5 text-sm font-semibold text-slate-700">
                                        <MapPin className="size-4" /> Shipping
                                        Address
                                    </CardTitle>
                                    <AnimatePresence initial={false}>
                                        {!isEditingAddress && (
                                            <AdminFade key="edit-address-button">
                                                <button
                                                    type="button"
                                                    onClick={
                                                        startEditingAddress
                                                    }
                                                    disabled={courierUnresolved}
                                                    title={
                                                        courierUnresolved
                                                            ? courierUnresolvedNote
                                                            : undefined
                                                    }
                                                    className="flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-medium text-slate-500 hover:bg-slate-100 hover:text-slate-700 disabled:cursor-not-allowed disabled:opacity-50"
                                                >
                                                    <Pencil className="size-3.5" />{' '}
                                                    Edit
                                                </button>
                                            </AdminFade>
                                        )}
                                    </AnimatePresence>
                                </CardHeader>
                                <CardContent className="pt-3">
                                    <AnimatePresence
                                        initial={false}
                                        mode="wait"
                                    >
                                        {isEditingAddress ? (
                                            <AdminReveal key="address-a">
                                                <form
                                                    onSubmit={handleSaveAddress}
                                                    className="space-y-3"
                                                >
                                                    <div>
                                                        <p className="mb-1 text-xs font-medium text-slate-600">
                                                            Recipient name
                                                        </p>
                                                        <p className="text-sm font-semibold text-slate-800">
                                                            {
                                                                order
                                                                    .shipping_address
                                                                    .name
                                                            }
                                                        </p>
                                                        <p className="mt-0.5 text-xs text-slate-600">
                                                            Not editable here.
                                                        </p>
                                                    </div>
                                                    <Input
                                                        maxLength={11}
                                                        showCount
                                                        label="Phone"
                                                        value={
                                                            addressData.phone
                                                        }
                                                        onChange={(e) =>
                                                            setAddressData(
                                                                'phone',
                                                                e.target.value,
                                                            )
                                                        }
                                                        error={
                                                            addressErrors.phone
                                                        }
                                                    />
                                                    <Input
                                                        maxLength={200}
                                                        showCount
                                                        label="Address line 1"
                                                        value={
                                                            addressData.address_line1
                                                        }
                                                        onChange={(e) =>
                                                            setAddressData(
                                                                'address_line1',
                                                                e.target.value,
                                                            )
                                                        }
                                                        error={
                                                            addressErrors.address_line1
                                                        }
                                                    />
                                                    <Input
                                                        maxLength={200}
                                                        showCount
                                                        label="Address line 2"
                                                        value={
                                                            addressData.address_line2
                                                        }
                                                        onChange={(e) =>
                                                            setAddressData(
                                                                'address_line2',
                                                                e.target.value,
                                                            )
                                                        }
                                                        error={
                                                            addressErrors.address_line2
                                                        }
                                                    />
                                                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                                        <Input
                                                            maxLength={100}
                                                            showCount
                                                            label="City"
                                                            value={
                                                                addressData.city
                                                            }
                                                            onChange={(e) =>
                                                                setAddressData(
                                                                    'city',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            error={
                                                                addressErrors.city
                                                            }
                                                        />
                                                        <Input
                                                            maxLength={100}
                                                            showCount
                                                            label="District / State"
                                                            value={
                                                                addressData.state
                                                            }
                                                            onChange={(e) =>
                                                                setAddressData(
                                                                    'state',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            error={
                                                                addressErrors.state
                                                            }
                                                        />
                                                    </div>
                                                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                                        <Input
                                                            maxLength={20}
                                                            showCount
                                                            label="Postal code"
                                                            value={
                                                                addressData.postal_code
                                                            }
                                                            onChange={(e) =>
                                                                setAddressData(
                                                                    'postal_code',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            error={
                                                                addressErrors.postal_code
                                                            }
                                                        />
                                                        <Input
                                                            maxLength={100}
                                                            showCount
                                                            label="Country"
                                                            value={
                                                                addressData.country
                                                            }
                                                            onChange={(e) =>
                                                                setAddressData(
                                                                    'country',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            error={
                                                                addressErrors.country
                                                            }
                                                        />
                                                    </div>
                                                    <div className="flex gap-2 pt-1">
                                                        <Button
                                                            type="submit"
                                                            disabled={
                                                                addressProcessing
                                                            }
                                                            className="flex-1 bg-brand text-brand-foreground hover:bg-brand-hover"
                                                        >
                                                            {addressProcessing
                                                                ? 'Saving…'
                                                                : 'Save address'}
                                                        </Button>
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            disabled={
                                                                addressProcessing
                                                            }
                                                            onClick={
                                                                cancelEditingAddress
                                                            }
                                                        >
                                                            <X className="size-4" />
                                                        </Button>
                                                    </div>
                                                </form>
                                            </AdminReveal>
                                        ) : (
                                            <AdminReveal key="address-b">
                                                <div className="space-y-0.5 text-sm text-slate-700">
                                                    <p className="font-semibold">
                                                        {
                                                            order
                                                                .shipping_address
                                                                .name
                                                        }
                                                    </p>
                                                    <p>
                                                        {
                                                            order
                                                                .shipping_address
                                                                .phone
                                                        }
                                                    </p>
                                                    <p>
                                                        {
                                                            order
                                                                .shipping_address
                                                                .address_line1
                                                        }
                                                    </p>
                                                    {order.shipping_address
                                                        .address_line2 && (
                                                        <p>
                                                            {
                                                                order
                                                                    .shipping_address
                                                                    .address_line2
                                                            }
                                                        </p>
                                                    )}
                                                    <p>
                                                        {
                                                            order
                                                                .shipping_address
                                                                .city
                                                        }
                                                        ,{' '}
                                                        {
                                                            order
                                                                .shipping_address
                                                                .state
                                                        }{' '}
                                                        {
                                                            order
                                                                .shipping_address
                                                                .postal_code
                                                        }
                                                    </p>
                                                    <p>
                                                        {
                                                            order
                                                                .shipping_address
                                                                .country
                                                        }
                                                    </p>
                                                </div>
                                            </AdminReveal>
                                        )}
                                    </AnimatePresence>
                                </CardContent>
                            </Card>
                        </div>

                        <Card className="shadow-sm">
                            <CardHeader className="border-b border-slate-100">
                                <CardTitle className="flex items-center gap-1.5 text-sm font-semibold text-slate-700">
                                    <History className="size-4" /> Status
                                    History
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="pt-3">
                                {order.status_histories.length === 0 ? (
                                    <p className="text-sm text-slate-600">
                                        No history yet.
                                    </p>
                                ) : (
                                    order.status_histories.map((h, index) => (
                                        <div
                                            key={h.id}
                                            className="relative flex items-start gap-3 pb-4 last:pb-0"
                                        >
                                            {index <
                                                order.status_histories.length -
                                                    1 && (
                                                <span className="absolute top-3.5 bottom-0 left-[3px] w-px bg-slate-200" />
                                            )}
                                            <div
                                                className={`relative mt-1 size-2 shrink-0 rounded-full ${index === order.status_histories.length - 1 ? 'bg-brand ring-4 ring-red-50' : 'bg-slate-300'}`}
                                            />
                                            <div>
                                                <p className="text-xs font-medium text-slate-700">
                                                    {h.status}
                                                </p>
                                                {h.notes && (
                                                    <p className="text-xs text-slate-600">
                                                        {h.notes}
                                                    </p>
                                                )}
                                                <p className="text-[11px] text-slate-600">
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

                        {(order.ip_address ||
                            order.user_agent ||
                            order.traffic_source) && (
                            <Card className="shadow-sm">
                                <CardHeader className="border-b border-slate-100">
                                    <CardTitle className="flex items-center gap-1.5 text-sm font-semibold text-slate-700">
                                        <Globe className="size-4" /> Device &
                                        Traffic Source
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-2 pt-3 text-sm text-slate-700">
                                    {order.traffic_source && (
                                        <div>
                                            <p className="text-xs text-slate-600">
                                                Came From
                                            </p>
                                            <div className="mt-0.5 flex items-center gap-2">
                                                <Badge
                                                    variant={
                                                        trafficSourceVariant[
                                                            order.traffic_source
                                                        ] ?? 'default'
                                                    }
                                                >
                                                    {trafficSourceLabel[
                                                        order.traffic_source
                                                    ] ?? order.traffic_source}
                                                </Badge>
                                                {order.traffic_referrer && (
                                                    <span className="text-xs text-slate-600">
                                                        via{' '}
                                                        {order.traffic_referrer}
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                    )}
                                    {order.ip_address && (
                                        <div className="flex items-center justify-between gap-2">
                                            <div>
                                                <p className="text-xs text-slate-600">
                                                    IP Address
                                                </p>
                                                <p className="font-mono font-medium">
                                                    {order.ip_address}
                                                </p>
                                            </div>
                                            <button
                                                type="button"
                                                onClick={handleBlockIp}
                                                disabled={blockingIp}
                                                className="flex items-center gap-1 rounded-lg border border-red-200 px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50 disabled:opacity-50"
                                            >
                                                <Ban className="size-3" />
                                                {blockingIp
                                                    ? 'Blocking…'
                                                    : 'Block IP'}
                                            </button>
                                        </div>
                                    )}
                                    {order.user_agent && (
                                        <div>
                                            <p className="text-xs text-slate-600">
                                                Browser / Device
                                            </p>
                                            <p className="text-xs font-medium break-all text-slate-600">
                                                {order.user_agent}
                                            </p>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        )}
                    </div>

                    <div className="space-y-5">
                        <Card className="shadow-sm">
                            <CardHeader className="border-b border-slate-100">
                                <CardTitle className="flex items-center gap-1.5 text-sm font-semibold text-slate-700">
                                    <Wallet className="size-4" /> Status &amp;
                                    Refunds
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3 pt-3">
                                <p className="text-xs text-slate-600">
                                    Status now advances automatically from the
                                    courier (see the Courier card below) and can
                                    no longer be set by hand, except marking a
                                    paid order as refunded.
                                </p>
                                <dl className="grid grid-cols-2 gap-x-2 gap-y-1 rounded-lg bg-slate-50 p-2 text-xs text-slate-600">
                                    <dt>Collected</dt>
                                    <dd className="text-right">
                                        {taka(refundSummary.collected)}
                                    </dd>
                                    <dt>Product refundable</dt>
                                    <dd className="text-right">
                                        {taka(refundSummary.cap)}
                                    </dd>
                                    <dt>Product refunded</dt>
                                    <dd className="text-right">
                                        {taka(refundSummary.refunded)}
                                    </dd>
                                    <dt>Remaining</dt>
                                    <dd className="text-right">
                                        {taka(refundSummary.remaining)}
                                    </dd>
                                    <dt>Shipping (kept)</dt>
                                    <dd className="text-right">
                                        {taka(refundSummary.shipping)}
                                    </dd>
                                </dl>
                                {refundSummary.product_refund_complete && (
                                    <p className="text-xs text-slate-700">
                                        {refundSummary.payment_fully_refunded
                                            ? 'Payment fully refunded.'
                                            : 'Product refund complete — shipping/rounding retained, so the payment is not fully refunded.'}
                                    </p>
                                )}
                                {courierEvidence.courier_involved && (
                                    <div className="space-y-1 rounded-lg border border-slate-200 p-2 text-xs">
                                        <p className="font-semibold text-slate-700">
                                            Courier verification{' '}
                                            {courierEvidence.eligible
                                                ? '— complete'
                                                : '— required before any refund'}
                                        </p>
                                        {courierEvidence.consignments.map(
                                            (c) => (
                                                <p
                                                    key={c.provider}
                                                    className="text-slate-600"
                                                >
                                                    {c.provider}{' '}
                                                    {c.consignment_id}
                                                    {c.gap_count > 0 &&
                                                        ` · ${c.gap_count} evidence gap(s)`}
                                                    {' · '}
                                                    {c.verification === null
                                                        ? 'not verified'
                                                        : c.verification
                                                                .stale_reason
                                                          ? `verification stale: ${c.verification.stale_reason}`
                                                          : `verified ${c.verification.outcome.replace('_', ' ')}`}
                                                </p>
                                            ),
                                        )}
                                        {courierEvidence.reasons.map(
                                            (reason) => (
                                                <p
                                                    key={reason}
                                                    className="text-amber-700"
                                                >
                                                    {reason}
                                                </p>
                                            ),
                                        )}
                                        {canVerifyDelivery &&
                                            courierEvidence.partial_blockers
                                                .length === 0 &&
                                            (courierEvidence.consignments
                                                .length > 0 ||
                                                courierEvidence
                                                    .verifiable_submissions
                                                    .length > 0) && (
                                                <Button
                                                    variant="outline"
                                                    className="w-full"
                                                    onClick={() =>
                                                        setIsVerifyOpen(true)
                                                    }
                                                >
                                                    Verify courier outcome
                                                </Button>
                                            )}
                                    </div>
                                )}
                                {canConfirmLostParcel && (
                                    <LostParcelDialog
                                        open={isLostOpen}
                                        onClose={() => setIsLostOpen(false)}
                                        orderId={order.id}
                                        orderNumber={order.order_number}
                                    />
                                )}
                                {canVerifyDelivery && (
                                    <DeliveryVerificationDialog
                                        open={isVerifyOpen}
                                        onClose={() => setIsVerifyOpen(false)}
                                        order={order}
                                        evidence={courierEvidence}
                                    />
                                )}
                                {order.payment_status === 'paid' &&
                                    order.status !== 'refunded' && (
                                        <>
                                            {goodsAreOut && (
                                                <label className="flex items-start gap-2 text-xs text-slate-600">
                                                    <input
                                                        type="checkbox"
                                                        className="mt-0.5"
                                                        checked={goodsReturned}
                                                        onChange={(e) =>
                                                            setGoodsReturned(
                                                                e.target
                                                                    .checked,
                                                            )
                                                        }
                                                    />
                                                    <span>
                                                        The goods have come back
                                                        to us. Put them back in
                                                        stock. (Leave unticked
                                                        if the customer kept
                                                        them or they were lost.)
                                                    </span>
                                                </label>
                                            )}
                                            <Button
                                                variant="outline"
                                                className="w-full border-slate-300 text-slate-700 hover:bg-slate-50"
                                                disabled={
                                                    refundProcessing ||
                                                    courierUnresolved ||
                                                    !courierEvidence.eligible ||
                                                    refundSummary.remaining <= 0
                                                }
                                                onClick={handleMarkRefunded}
                                            >
                                                {refundProcessing
                                                    ? 'Updating…'
                                                    : 'Mark as Refunded'}
                                            </Button>
                                            {courierUnresolved && (
                                                <p className="text-xs text-amber-700">
                                                    {courierUnresolvedNote}
                                                </p>
                                            )}
                                        </>
                                    )}
                            </CardContent>
                        </Card>

                        <Card className="shadow-sm">
                            <CardHeader className="border-b border-slate-100">
                                <CardTitle className="flex items-center gap-1.5 text-sm font-semibold text-slate-700">
                                    <Truck className="size-4" /> Courier
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3 pt-3">
                                {order.tracking_number && (
                                    <div className="flex items-center justify-between gap-3 rounded-lg bg-slate-50 px-3 py-2 text-sm">
                                        <span className="text-slate-600">
                                            Tracking
                                        </span>
                                        <span className="font-mono font-medium text-slate-800">
                                            {order.tracking_number}
                                        </span>
                                    </div>
                                )}
                                {courierSubmission &&
                                (courierSubmission.state === 'submitting' ||
                                    courierSubmission.state === 'unknown') ? (
                                    <CourierSubmissionPanel
                                        orderId={order.id}
                                        submission={courierSubmission}
                                    />
                                ) : order.pathao_consignment_id ? (
                                    <>
                                        <div className="space-y-1 text-sm text-slate-700">
                                            <div className="flex justify-between">
                                                <span className="text-slate-600">
                                                    Provider
                                                </span>
                                                <span className="font-medium">
                                                    Pathao
                                                </span>
                                            </div>
                                            <div className="flex justify-between">
                                                <span className="text-slate-600">
                                                    Consignment ID
                                                </span>
                                                <span className="font-mono font-medium">
                                                    {
                                                        order.pathao_consignment_id
                                                    }
                                                </span>
                                            </div>
                                            <div className="flex justify-between">
                                                <span className="text-slate-600">
                                                    Courier status
                                                </span>
                                                <Badge variant="info">
                                                    {order.pathao_status_label ??
                                                        'Unknown'}
                                                </Badge>
                                            </div>
                                            {order.pathao_status_updated_at && (
                                                <div className="flex justify-between">
                                                    <span className="text-slate-600">
                                                        Status Received At
                                                    </span>
                                                    <span className="font-medium">
                                                        {new Date(
                                                            order.pathao_status_updated_at,
                                                        ).toLocaleString()}
                                                    </span>
                                                </div>
                                            )}
                                            {order.pathao_delivery_fee !==
                                                null && (
                                                <div className="flex justify-between">
                                                    <span className="text-slate-600">
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
                                                <span className="text-slate-600">
                                                    Provider
                                                </span>
                                                <span className="font-medium">
                                                    Steadfast
                                                </span>
                                            </div>
                                            <div className="flex justify-between">
                                                <span className="text-slate-600">
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
                                                    <span className="text-slate-600">
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
                                                <span className="text-slate-600">
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
                                        {courierSubmission?.state ===
                                            'failed' &&
                                            courierSubmission.resolution ===
                                                null && (
                                                <p className="rounded-lg border border-red-200 bg-red-50 p-2 text-xs break-words text-red-700">
                                                    Last{' '}
                                                    {
                                                        courierSubmission.courier_label
                                                    }{' '}
                                                    attempt failed and was not
                                                    created:{' '}
                                                    {
                                                        courierSubmission.error_message
                                                    }
                                                </p>
                                            )}
                                        <p className="text-xs text-slate-600">
                                            Not sent to a courier yet. This
                                            books a pickup from the selected
                                            provider using this order's shipping
                                            address and total as the COD amount
                                            to collect.
                                        </p>
                                        <Select
                                            selectClassName="h-9 w-full rounded-lg border border-slate-200 px-3 text-sm focus:ring-1 focus:ring-brand focus:outline-none"
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
                                            className="w-full bg-brand text-brand-foreground hover:bg-brand-hover"
                                            disabled={
                                                courierSending ||
                                                [
                                                    'cancelled',
                                                    'refunded',
                                                    'delivered',
                                                ].includes(order.status)
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

                        {order.status === 'shipped' && (
                            <Card className="border-amber-100 shadow-sm">
                                <CardHeader className="border-b border-amber-100">
                                    <CardTitle className="flex items-center gap-1.5 text-sm font-semibold text-amber-700">
                                        <XCircle className="size-4" /> Cancel
                                        Order
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="pt-3">
                                    <p className="text-xs text-amber-700">
                                        This order is with the courier. Cancel
                                        it or request a return in the
                                        courier&apos;s portal — the order is
                                        cancelled and its stock restored
                                        automatically when the courier reports
                                        it.
                                    </p>
                                </CardContent>
                            </Card>
                        )}

                        {order.status !== 'cancelled' &&
                            order.status !== 'refunded' &&
                            order.status !== 'delivered' &&
                            order.status !== 'shipped' &&
                            order.payment_status !== 'paid' && (
                                <Card className="border-red-100 shadow-sm">
                                    <CardHeader className="border-b border-red-100">
                                        <CardTitle className="flex items-center gap-1.5 text-sm font-semibold text-red-600">
                                            <XCircle className="size-4" />{' '}
                                            Cancel Order
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="pt-3">
                                        <form
                                            onSubmit={handleCancel}
                                            className="space-y-3"
                                        >
                                            <textarea
                                                maxLength={500}
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
                                            <CharCount
                                                value={cancelData.reason}
                                                max={500}
                                                className="mt-1 block text-right"
                                            />

                                            <Button
                                                type="submit"
                                                disabled={
                                                    cancelProcessing ||
                                                    courierUnresolved
                                                }
                                                className="w-full bg-red-600 text-white hover:bg-red-700"
                                            >
                                                {cancelProcessing
                                                    ? 'Cancelling…'
                                                    : 'Cancel This Order'}
                                            </Button>
                                            {courierUnresolved && (
                                                <p className="text-xs text-amber-700">
                                                    {courierUnresolvedNote}
                                                </p>
                                            )}
                                        </form>
                                    </CardContent>
                                </Card>
                            )}

                        {order.status === 'cancelled' && (
                            <Card className="border-red-100 shadow-sm">
                                <CardHeader className="border-b border-red-100">
                                    <CardTitle className="flex items-center gap-1.5 text-sm font-semibold text-red-600">
                                        <Trash2 className="size-4" /> Delete
                                        Order
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="pt-3">
                                    <p className="mb-3 text-xs text-slate-600">
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
