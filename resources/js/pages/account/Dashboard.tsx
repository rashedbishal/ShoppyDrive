import { Head, Link, router } from '@inertiajs/react';
import {
    ShoppingBag,
    Heart,
    MapPin,
    ChevronRight,
    Package,
} from 'lucide-react';
import React from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatCurrency, formatDate } from '@/lib/utils';
import type { Order } from '@/types';

interface DashboardProps {
    recentOrders: Order[];
    totalOrders: number;
    wishlistCount: number;
    addressCount: number;
}

/** DISC-46: one place for the order-status badge colour (table and cards). */
function statusVariant(
    status: string,
): 'success' | 'error' | 'info' | 'warning' {
    if (status === 'delivered') {
        return 'success';
    }

    if (status === 'cancelled') {
        return 'error';
    }

    return status === 'processing' ? 'info' : 'warning';
}

export default function Dashboard({
    recentOrders = [],
    totalOrders = 0,
    wishlistCount = 0,
    addressCount = 0,
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
    ];

    const openOrder = (orderNumber: string) =>
        router.visit(route('account.orders.show', orderNumber));

    return (
        <>
            <Head title="My Dashboard" />

            <div className="space-y-8">
                {/* Header */}
                <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                    <div>
                        <h1 className="mb-1 font-display text-3xl font-bold text-[var(--color-text-primary)]">
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
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3 md:gap-6">
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
                            <p className="mb-1 text-[10px] font-bold tracking-widest text-[var(--color-text-secondary)] uppercase">
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

                    {recentOrders.length === 0 ? (
                        <div className="flex flex-col items-center px-8 py-20 text-center">
                            <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-[var(--color-surface-alt)] text-[var(--color-text-muted)]">
                                <ShoppingBag size={32} />
                            </div>
                            <h3 className="mb-1 text-lg font-bold text-[var(--color-text-primary)]">
                                No orders yet
                            </h3>
                            <p className="mb-6 text-sm text-[var(--color-text-secondary)]">
                                Start shopping to see your orders here.
                            </p>
                            <Button
                                asChild
                                className="rounded-xl px-8 font-bold"
                            >
                                <Link href={route('products.index')}>
                                    Start Shopping
                                </Link>
                            </Button>
                        </div>
                    ) : (
                        <>
                            {/* DISC-46: tablet/desktop — a table whose whole row opens the order. */}
                            <table className="hidden w-full border-collapse text-left md:table">
                                <thead>
                                    <tr className="bg-[var(--color-surface-alt)] text-[10px] font-bold tracking-widest text-[var(--color-text-secondary)] uppercase">
                                        <th className="px-8 py-4">Order ID</th>
                                        <th className="px-8 py-4">Date</th>
                                        <th className="px-8 py-4">Status</th>
                                        <th className="px-8 py-4">Items</th>
                                        <th className="px-8 py-4">Total</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-[var(--color-border)]">
                                    {recentOrders.map((order) => (
                                        <tr
                                            key={order.id}
                                            role="link"
                                            tabIndex={0}
                                            aria-label={`View order ${order.order_number}`}
                                            onClick={() =>
                                                openOrder(order.order_number)
                                            }
                                            onKeyDown={(e) => {
                                                if (
                                                    e.key === 'Enter' ||
                                                    e.key === ' '
                                                ) {
                                                    e.preventDefault();
                                                    openOrder(
                                                        order.order_number,
                                                    );
                                                }
                                            }}
                                            className="group cursor-pointer transition-colors hover:bg-[var(--color-surface-alt)] focus-visible:bg-[var(--color-surface-alt)] focus-visible:outline-none"
                                        >
                                            <td className="px-8 py-6">
                                                <span className="font-mono text-sm font-bold text-[var(--color-text-primary)] group-hover:text-[var(--color-primary)]">
                                                    #{order.order_number}
                                                </span>
                                            </td>
                                            <td className="px-8 py-6 text-sm text-[var(--color-text-secondary)]">
                                                {formatDate(order.created_at)}
                                            </td>
                                            <td className="px-8 py-6">
                                                <Badge
                                                    variant={statusVariant(
                                                        order.status,
                                                    )}
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
                                        </tr>
                                    ))}
                                </tbody>
                            </table>

                            {/* DISC-46: phones — one tappable card per order, no sideways scrolling. */}
                            <ul className="divide-y divide-[var(--color-border)] md:hidden">
                                {recentOrders.map((order) => (
                                    <li key={order.id}>
                                        <Link
                                            href={route(
                                                'account.orders.show',
                                                order.order_number,
                                            )}
                                            className="flex items-center gap-3 px-5 py-4 transition-colors hover:bg-[var(--color-surface-alt)]"
                                        >
                                            <div className="min-w-0 flex-1 space-y-1.5">
                                                <div className="flex items-center justify-between gap-2">
                                                    <span className="truncate font-mono text-sm font-bold text-[var(--color-text-primary)]">
                                                        #{order.order_number}
                                                    </span>
                                                    <Badge
                                                        variant={statusVariant(
                                                            order.status,
                                                        )}
                                                        className="shrink-0 rounded-full text-[10px] font-bold uppercase"
                                                    >
                                                        {order.status}
                                                    </Badge>
                                                </div>
                                                <div className="flex items-center justify-between gap-2 text-sm">
                                                    <span className="text-[var(--color-text-secondary)]">
                                                        {formatDate(
                                                            order.created_at,
                                                        )}{' '}
                                                        · {order.items.length}{' '}
                                                        {order.items.length ===
                                                        1
                                                            ? 'item'
                                                            : 'items'}
                                                    </span>
                                                    <span className="font-bold text-[var(--color-primary)]">
                                                        {formatCurrency(
                                                            order.total,
                                                        )}
                                                    </span>
                                                </div>
                                            </div>
                                            <ChevronRight
                                                size={16}
                                                className="shrink-0 text-[var(--color-text-muted)]"
                                            />
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </>
                    )}
                </div>
            </div>
        </>
    );
}
