import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
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
    social_accounts: SocialAccount[];
};

type Props = {
    customer: Customer;
    orders: Order[];
};

const statusVariant: Record<string, 'success' | 'warning' | 'error' | 'info' | 'default'> = {
    pending: 'warning',
    processing: 'info',
    shipped: 'info',
    delivered: 'success',
    cancelled: 'error',
    refunded: 'default',
};

export default function CustomerShow({ customer, orders }: Props) {
    return (
        <>
            <Head title={`${customer.name} — Admin`} />
            <div className="space-y-5">
                <div className="flex items-center gap-3">
                    <Link href={route('admin.customers.index')} className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                        <ArrowLeft className="size-4" />
                    </Link>
                    <div className="flex items-center gap-3">
                        {customer.avatar ? (
                            <img src={customer.avatar} alt={customer.name} className="size-10 rounded-full object-cover" />
                        ) : (
                            <div className="flex size-10 items-center justify-center rounded-full bg-slate-200 text-sm font-bold text-slate-500">
                                {customer.name.slice(0, 2).toUpperCase()}
                            </div>
                        )}
                        <div>
                            <h1 className="text-xl font-bold text-slate-900">{customer.name}</h1>
                            <p className="text-sm text-slate-500">{customer.email}</p>
                        </div>
                    </div>
                </div>

                <div className="grid gap-5 lg:grid-cols-[1fr_280px]">
                    <div className="space-y-5">
                        <Card className="shadow-sm">
                            <CardHeader className="border-b border-slate-100 pb-4">
                                <CardTitle className="text-sm font-semibold text-slate-700">Recent Orders</CardTitle>
                            </CardHeader>
                            <CardContent className="p-0">
                                {orders.length === 0 ? (
                                    <p className="px-4 py-8 text-center text-sm text-slate-400">No orders yet.</p>
                                ) : (
                                    <div className="divide-y divide-slate-50">
                                        {orders.map((order) => (
                                            <div key={order.id} className="flex items-center gap-4 px-4 py-3 hover:bg-slate-50/60">
                                                <div className="min-w-0 flex-1">
                                                    <p className="font-mono text-sm font-bold text-slate-800">{order.order_number}</p>
                                                    <p className="text-xs text-slate-400">{new Date(order.created_at).toLocaleDateString()}</p>
                                                </div>
                                                <p className="text-sm font-semibold text-slate-800">৳{order.total.toLocaleString()}</p>
                                                <Badge variant={statusVariant[order.status] ?? 'default'} size="sm">{order.status}</Badge>
                                                <Link href={route('admin.orders.show', order.id)} className="text-xs text-[#c8102e] hover:underline">View →</Link>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    <div className="space-y-5">
                        <Card className="shadow-sm">
                            <CardHeader className="border-b border-slate-100 pb-4">
                                <CardTitle className="text-sm font-semibold text-slate-700">Profile</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3 pt-4 text-sm">
                                <div>
                                    <p className="text-xs text-slate-400">Email</p>
                                    <p className="text-slate-800">{customer.email}</p>
                                    <p className="text-xs text-slate-400">{customer.email_verified_at ? '✓ Verified' : 'Not verified'}</p>
                                </div>
                                {customer.phone && (
                                    <div>
                                        <p className="text-xs text-slate-400">Phone</p>
                                        <p className="text-slate-800">{customer.phone}</p>
                                    </div>
                                )}
                                <div>
                                    <p className="text-xs text-slate-400">Joined</p>
                                    <p className="text-slate-800">{new Date(customer.created_at).toLocaleDateString()}</p>
                                </div>
                                {customer.social_accounts.length > 0 && (
                                    <div>
                                        <p className="text-xs text-slate-400">Connected via</p>
                                        <div className="flex gap-1.5">
                                            {customer.social_accounts.map((sa) => (
                                                <span key={sa.id} className="rounded-full bg-slate-100 px-2 py-0.5 text-xs capitalize text-slate-600">{sa.provider}</span>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}
