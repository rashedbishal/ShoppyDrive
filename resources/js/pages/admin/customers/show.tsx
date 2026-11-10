import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, RotateCcw, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { DeleteCustomerDialog } from '@/components/admin/delete-customer-dialog';
import { RestoreCustomerDialog } from '@/components/admin/restore-customer-dialog';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type Order = {
    id: number;
    order_number: string;
    status: string;
    total: number;
    created_at: string;
};

type SocialAccount = {
    id: number;
    provider: string;
};

type Customer = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    avatar: string | null;
    email_verified_at: string | null;
    created_at: string;
    orders_count: number;
    social_accounts: SocialAccount[];
    anonymized_at: string | null;
    can_restore: boolean;
};

type Props = {
    customer: Customer;
    orders: Order[];
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

export default function CustomerShow({ customer, orders }: Props) {
    const [deleteTarget, setDeleteTarget] = useState<Customer | null>(null);
    const [restoreTarget, setRestoreTarget] = useState<Customer | null>(null);

    return (
        <>
            <Head title={`${customer.name} — Admin`} />
            <div className="space-y-5">
                <div className="flex items-center justify-between gap-3">
                    <div className="flex items-center gap-3">
                        <Link
                            href={route('admin.customers.index')}
                            className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                        >
                            <ArrowLeft className="size-4" />
                        </Link>
                        <div className="flex items-center gap-3">
                            {customer.avatar ? (
                                <img
                                    src={customer.avatar}
                                    alt={customer.name}
                                    className="size-10 rounded-full object-cover"
                                />
                            ) : (
                                <div className="flex size-10 items-center justify-center rounded-full bg-slate-200 text-sm font-bold text-slate-500">
                                    {customer.name.slice(0, 2).toUpperCase()}
                                </div>
                            )}
                            <div>
                                <div className="flex items-center gap-2">
                                    <h1 className="text-xl font-bold text-slate-900">
                                        {customer.name}
                                    </h1>
                                    {customer.anonymized_at && (
                                        <Badge variant="default" size="sm">
                                            Anonymized{' '}
                                            {new Date(
                                                customer.anonymized_at,
                                            ).toLocaleDateString()}
                                        </Badge>
                                    )}
                                </div>
                                <p className="text-sm text-slate-500">
                                    {customer.email}
                                </p>
                            </div>
                        </div>
                    </div>

                    {!customer.anonymized_at && (
                        <button
                            type="button"
                            onClick={() => setDeleteTarget(customer)}
                            className="flex items-center gap-1.5 rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50"
                        >
                            <Trash2 className="size-3.5" />
                            Anonymize Customer
                        </button>
                    )}
                    {customer.anonymized_at && customer.can_restore && (
                        <button
                            type="button"
                            onClick={() => setRestoreTarget(customer)}
                            className="flex items-center gap-1.5 rounded-lg border border-[#c8102e]/20 px-3 py-1.5 text-xs font-semibold text-[#c8102e] hover:bg-[#c8102e]/5"
                        >
                            <RotateCcw className="size-3.5" />
                            Restore Customer
                        </button>
                    )}
                </div>

                <div className="grid grid-cols-1 gap-5 lg:grid-cols-[minmax(0,1fr)_280px]">
                    <div className="space-y-5">
                        <Card className="shadow-sm">
                            <CardHeader className="border-b border-slate-100">
                                <CardTitle className="text-sm font-semibold text-slate-700">
                                    Recent Orders
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="p-0">
                                {orders.length === 0 ? (
                                    <p className="px-4 py-8 text-center text-sm text-slate-400">
                                        No orders yet.
                                    </p>
                                ) : (
                                    <div className="divide-y divide-slate-50">
                                        {orders.map((order) => (
                                            <div
                                                key={order.id}
                                                className="flex items-center gap-4 px-4 py-3 hover:bg-slate-50/60"
                                            >
                                                <div className="min-w-0 flex-1">
                                                    <p className="font-mono text-sm font-bold text-slate-800">
                                                        {order.order_number}
                                                    </p>
                                                    <p className="text-xs text-slate-400">
                                                        {new Date(
                                                            order.created_at,
                                                        ).toLocaleDateString()}
                                                    </p>
                                                </div>
                                                <p className="text-sm font-semibold text-slate-800">
                                                    ৳
                                                    {order.total.toLocaleString()}
                                                </p>
                                                <Badge
                                                    variant={
                                                        statusVariant[
                                                            order.status
                                                        ] ?? 'default'
                                                    }
                                                    size="sm"
                                                >
                                                    {order.status}
                                                </Badge>
                                                <Link
                                                    href={route(
                                                        'admin.orders.show',
                                                        order.id,
                                                    )}
                                                    className="text-xs text-[#c8102e] hover:underline"
                                                >
                                                    View →
                                                </Link>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    <div className="space-y-5">
                        <Card className="shadow-sm">
                            <CardHeader className="border-b border-slate-100">
                                <CardTitle className="text-sm font-semibold text-slate-700">
                                    Profile
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3 pt-3 text-sm">
                                <div>
                                    <p className="text-xs text-slate-400">
                                        Email
                                    </p>
                                    <p className="text-slate-800">
                                        {customer.email}
                                    </p>
                                    <p className="text-xs text-slate-400">
                                        {customer.email_verified_at
                                            ? '✓ Verified'
                                            : 'Not verified'}
                                    </p>
                                </div>
                                {customer.phone && (
                                    <div>
                                        <p className="text-xs text-slate-400">
                                            Phone
                                        </p>
                                        <p className="text-slate-800">
                                            {customer.phone}
                                        </p>
                                    </div>
                                )}
                                <div>
                                    <p className="text-xs text-slate-400">
                                        Joined
                                    </p>
                                    <p className="text-slate-800">
                                        {new Date(
                                            customer.created_at,
                                        ).toLocaleDateString()}
                                    </p>
                                </div>
                                {customer.social_accounts.length > 0 && (
                                    <div>
                                        <p className="text-xs text-slate-400">
                                            Connected via
                                        </p>
                                        <div className="flex gap-1.5">
                                            {customer.social_accounts.map(
                                                (sa) => (
                                                    <span
                                                        key={sa.id}
                                                        className="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600 capitalize"
                                                    >
                                                        {sa.provider}
                                                    </span>
                                                ),
                                            )}
                                        </div>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>

            <DeleteCustomerDialog
                customer={deleteTarget}
                onOpenChange={(open) => !open && setDeleteTarget(null)}
            />
            <RestoreCustomerDialog
                customer={restoreTarget}
                onOpenChange={(open) => !open && setRestoreTarget(null)}
            />
        </>
    );
}
