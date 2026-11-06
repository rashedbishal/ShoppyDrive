import { Head, Link } from '@inertiajs/react';
import {
    ShoppingBag,
    Heart,
    MapPin,
    ChevronRight,
    Package,
    TrendingUp,
    ShieldCheck,
} from 'lucide-react';
import React from 'react';

import AccountLayout from '@/components/layout/AccountLayout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatCurrency, formatDate } from '@/lib/utils';
import type { Order } from '@/types';

interface DashboardProps {
    recentOrders: Order[];
    totalOrders: number;
    wishlistCount: number;
    addressCount: number;
    totalSpent: number;
}

export default function Dashboard({
    recentOrders = [],
    totalOrders = 0,
    wishlistCount = 0,
    addressCount = 0,
    totalSpent = 0,
}: DashboardProps): React.JSX.Element {
    const stats = [
        {
            label: 'Total Orders',
            value: totalOrders,
            icon: ShoppingBag,
            color: 'text-blue-600',
            bg: 'bg-blue-50',
            href: route('account.orders'),
        },
        {
            label: 'In Wishlist',
            value: wishlistCount,
            icon: Heart,
            color: 'text-red-600',
            bg: 'bg-red-50',
            href: route('account.wishlist'),
        },
        {
            label: 'Saved Addresses',
            value: addressCount,
            icon: MapPin,
            color: 'text-amber-600',
            bg: 'bg-amber-50',
            href: route('account.addresses'),
        },
        {
            label: 'Total Spent',
            value: formatCurrency(totalSpent),
            icon: TrendingUp,
            color: 'text-green-600',
            bg: 'bg-green-50',
            href: route('account.orders'),
        },
    ];

    return (
        <AccountLayout>
            <Head title="My Dashboard" />

            <div className="space-y-8">
                {/* Header */}
                <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                    <div>
                        <h1 className="mb-1 font-serif text-3xl font-bold text-[var(--color-text-primary)]">
                            Account Dashboard
                        </h1>
                        <p className="text-[var(--color-text-secondary)]">
                            Manage your orders, addresses and account settings
                            here.
                        </p>
                    </div>
                    <Button
                        asChild
                        className="rounded-xl bg-[var(--color-primary)] font-bold text-white hover:bg-[var(--color-primary-hover)]"
                    >
                        <Link href={route('products.index')}>
                            Continue Shopping
                        </Link>
                    </Button>
                </div>

                {/* Stats Grid */}
                <div className="grid grid-cols-2 gap-4 md:grid-cols-4 md:gap-6">
                    {stats.map((stat) => (
                        <Link
                            key={stat.label}
                            href={stat.href}
                            className="group rounded-3xl border border-[var(--color-border)] bg-white p-6 shadow-sm transition-all hover:border-[var(--color-primary)]"
                        >
                            <div
                                className={`h-12 w-12 rounded-2xl ${stat.bg} ${stat.color} mb-4 flex items-center justify-center transition-transform group-hover:scale-110`}
                            >
                                <stat.icon size={24} />
                            </div>
                            <p className="mb-1 text-[10px] font-bold tracking-widest text-[var(--color-text-muted)] uppercase">
                                {stat.label}
                            </p>
                            <p className="text-xl font-bold text-[var(--color-text-primary)]">
                                {stat.value}
                            </p>
                        </Link>
                    ))}
                </div>

                {/* Recent Orders */}
                <div className="overflow-hidden rounded-3xl border border-[var(--color-border)] bg-white shadow-sm">
                    <div className="flex items-center justify-between border-b border-[var(--color-border)] p-6 md:p-8">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-[var(--color-surface-alt)] text-[var(--color-primary)]">
                                <Package size={20} />
                            </div>
                            <h2 className="text-xl font-bold text-[var(--color-text-primary)]">
                                Recent Orders
                            </h2>
                        </div>
                        <Link
                            href={route('account.orders')}
                            className="flex items-center gap-1 text-sm font-bold text-[var(--color-primary)] hover:underline"
                        >
                            View All <ChevronRight size={14} />
                        </Link>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full border-collapse text-left">
                            <thead>
                                <tr className="bg-[var(--color-surface-alt)] text-[10px] font-bold tracking-widest text-[var(--color-text-muted)] uppercase">
                                    <th className="px-8 py-4">Order ID</th>
                                    <th className="px-8 py-4">Date</th>
                                    <th className="px-8 py-4">Status</th>
                                    <th className="px-8 py-4">Items</th>
                                    <th className="px-8 py-4">Total</th>
                                    <th className="px-8 py-4 text-right">
                                        Action
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-[var(--color-border)]">
                                {recentOrders.length > 0 ? (
                                    recentOrders.map((order) => (
                                        <tr
                                            key={order.id}
                                            className="group transition-colors hover:bg-[var(--color-surface-alt)]"
                                        >
                                            <td className="px-8 py-6">
                                                <span className="font-mono text-sm font-bold text-[var(--color-text-primary)]">
                                                    #{order.order_number}
                                                </span>
                                            </td>
                                            <td className="px-8 py-6 text-sm text-[var(--color-text-secondary)]">
                                                {formatDate(order.created_at)}
                                            </td>
                                            <td className="px-8 py-6">
                                                <Badge
                                                    variant={
                                                        order.status ===
                                                        'delivered'
                                                            ? 'success'
                                                            : order.status ===
                                                                'cancelled'
                                                              ? 'error'
                                                              : order.status ===
                                                                  'processing'
                                                                ? 'info'
                                                                : 'warning'
                                                    }
                                                    className="rounded-full text-[10px] font-bold uppercase"
                                                >
                                                    {order.status}
                                                </Badge>
                                            </td>
                                            <td className="px-8 py-6 text-sm font-medium text-[var(--color-text-primary)]">
                                                {order.items.length}{' '}
                                                {order.items.length === 1
                                                    ? 'item'
                                                    : 'items'}
                                            </td>
                                            <td className="px-8 py-6">
                                                <span className="font-bold text-[var(--color-primary)]">
                                                    {formatCurrency(
                                                        order.total,
                                                    )}
                                                </span>
                                            </td>
                                            <td className="px-8 py-6 text-right">
                                                <Button
                                                    asChild
                                                    variant="ghost"
                                                    size="sm"
                                                    className="rounded-lg font-bold"
                                                >
                                                    <Link
                                                        href={route(
                                                            'account.orders.show',
                                                            order.order_number,
                                                        )}
                                                    >
                                                        Details
                                                    </Link>
                                                </Button>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td
                                            colSpan={6}
                                            className="px-8 py-20 text-center"
                                        >
                                            <div className="flex flex-col items-center">
                                                <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-[var(--color-surface-alt)] text-[var(--color-text-muted)]">
                                                    <ShoppingBag size={32} />
                                                </div>
                                                <h3 className="mb-1 text-lg font-bold text-[var(--color-text-primary)]">
                                                    No orders yet
                                                </h3>
                                                <p className="mb-6 text-sm text-[var(--color-text-secondary)]">
                                                    Start shopping to see your
                                                    orders here.
                                                </p>
                                                <Button
                                                    asChild
                                                    className="rounded-xl px-8 font-bold"
                                                >
                                                    <Link
                                                        href={route(
                                                            'products.index',
                                                        )}
                                                    >
                                                        Start Shopping
                                                    </Link>
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Account Security Banner */}
                <div className="flex flex-col items-center justify-between gap-8 rounded-3xl bg-gradient-to-r from-[var(--color-secondary)] to-[#2A2A4E] p-8 text-white md:flex-row md:p-10">
                    <div className="flex items-center gap-6">
                        <div className="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-white/10 text-amber-400">
                            <ShieldCheck size={40} />
                        </div>
                        <div>
                            <h3 className="mb-2 text-xl font-bold">
                                Account Security
                            </h3>
                            <p className="max-w-md text-sm text-blue-100">
                                Keep your account safe by updating your password
                                regularly.
                            </p>
                        </div>
                    </div>
                    <div className="flex gap-4">
                        <Button
                            asChild
                            variant="outline"
                            className="rounded-xl border-white/20 font-bold text-white hover:bg-white/10"
                        >
                            <Link href={route('account.password')}>
                                Change Password
                            </Link>
                        </Button>
                        <Button
                            asChild
                            className="rounded-xl bg-white font-bold text-[var(--color-secondary)] hover:bg-gray-100"
                        >
                            <Link href={route('account.profile')}>
                                Manage Profile
                            </Link>
                        </Button>
                    </div>
                </div>
            </div>
        </AccountLayout>
    );
}
