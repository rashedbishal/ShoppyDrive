import { Head, Link, useForm } from '@inertiajs/react';
import {
    Truck,
    Package,
    Mail,
    Phone,
    AlertCircle,
    CheckCircle2,
} from 'lucide-react';
import React, { useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';

import { OrderTimeline } from '@/components/shared/OrderTimeline';
import { OrderStatusBadge } from '@/components/shared/OrderStatusBadge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatCurrency, formatDate } from '@/lib/utils';
import type { Order } from '@/types';

interface TrackOrderProps {
    order?: Order | null;
    error?: string;
    prefill_order_number?: string;
}

export default function TrackOrder({
    order: initialOrder,
    error: initialError,
    prefill_order_number = '',
}: TrackOrderProps): React.JSX.Element {
    const form = useForm({
        order_number: prefill_order_number,
        email: '',
        phone: '',
    });

    const [order, setOrder] = useState<Order | null>(initialOrder || null);
    const [error, setError] = useState<string | null>(initialError || null);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setError(null);

        if (!form.data.email && !form.data.phone) {
            setError(
                'Enter the email or phone number used when the order was placed.',
            );

            return;
        }

        form.get(route('order.track'), {
            preserveState: true,
            onSuccess: (page) => {
                const props = page.props as unknown as TrackOrderProps;
                if (props.order) {
                    setOrder(props.order);
                } else if (props.error) {
                    setError(props.error);
                }
            },
        });
    };

    return (
        <div className="min-h-screen bg-[var(--color-surface-alt)] px-4 py-12 md:py-20">
            <Head title="Track Your Order" />

            <div className="container mx-auto max-w-4xl">
                <div className="mb-12 text-center">
                    <div className="mx-auto mb-6 flex h-20 w-20 rotate-3 items-center justify-center rounded-3xl bg-[var(--color-primary)] text-white shadow-xl shadow-red-100">
                        <Truck size={40} />
                    </div>
                    <h1 className="mb-4 font-serif text-2xl font-bold text-[var(--color-text-primary)] sm:text-4xl">
                        Track Your Order
                    </h1>
                    <p className="mx-auto max-w-md text-[var(--color-text-secondary)]">
                        Enter your order number and either the email or phone
                        number used when the order was placed.
                    </p>
                </div>

                <div className="mb-8 rounded-3xl border border-[var(--color-border)] bg-white p-5 shadow-sm md:p-12">
                    <form
                        onSubmit={handleSubmit}
                        className="grid grid-cols-1 items-end gap-6 md:grid-cols-12"
                    >
                        <div className="space-y-2 md:col-span-12">
                            <Label htmlFor="order_number">Order Number</Label>
                            <div className="relative">
                                <Input
                                    id="order_number"
                                    value={form.data.order_number}
                                    onChange={(e) =>
                                        form.setData(
                                            'order_number',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="e.g. ORD-123456"
                                    className="h-14 rounded-2xl pl-12"
                                    required
                                />
                                <Package
                                    size={20}
                                    className="absolute top-1/2 left-4 -translate-y-1/2 text-[var(--color-text-muted)]"
                                />
                            </div>
                        </div>
                        <div className="space-y-2 md:col-span-5">
                            <Label htmlFor="email">Email Address</Label>
                            <div className="relative">
                                <Input
                                    id="email"
                                    type="email"
                                    value={form.data.email}
                                    onChange={(e) =>
                                        form.setData('email', e.target.value)
                                    }
                                    placeholder="your@email.com"
                                    className="h-14 rounded-2xl pl-12"
                                />
                                <Mail
                                    size={20}
                                    className="absolute top-1/2 left-4 -translate-y-1/2 text-[var(--color-text-muted)]"
                                />
                            </div>
                        </div>
                        <div className="space-y-2 md:col-span-5">
                            <Label htmlFor="phone">Phone Number</Label>
                            <div className="relative">
                                <Input
                                    id="phone"
                                    value={form.data.phone}
                                    onChange={(e) =>
                                        form.setData('phone', e.target.value)
                                    }
                                    placeholder="01XXXXXXXXX"
                                    className="h-14 rounded-2xl pl-12"
                                />
                                <Phone
                                    size={20}
                                    className="absolute top-1/2 left-4 -translate-y-1/2 text-[var(--color-text-muted)]"
                                />
                            </div>
                        </div>
                        <div className="md:col-span-2">
                            <Button
                                type="submit"
                                disabled={form.processing}
                                className="h-14 w-full rounded-2xl font-bold shadow-[var(--color-primary)]/20 shadow-lg"
                            >
                                {form.processing ? 'Searching...' : 'Track'}
                            </Button>
                        </div>
                    </form>

                    {error && (
                        <motion.div
                            initial={{ opacity: 0, y: 10 }}
                            animate={{ opacity: 1, y: 0 }}
                            className="mt-8 flex gap-3 rounded-2xl border border-red-100 bg-red-50 p-4 text-red-800"
                        >
                            <AlertCircle size={20} className="shrink-0" />
                            <p className="text-sm font-bold">{error}</p>
                        </motion.div>
                    )}
                </div>

                <AnimatePresence mode="wait">
                    {order && (
                        <motion.div
                            key={order.order_number}
                            initial={{ opacity: 0, scale: 0.98 }}
                            animate={{ opacity: 1, scale: 1 }}
                            exit={{ opacity: 0, scale: 0.98 }}
                            className="space-y-8"
                        >
                            <div className="rounded-3xl border border-[var(--color-border)] bg-white p-8 shadow-sm md:p-12">
                                <div className="mb-12 flex flex-wrap items-center justify-between gap-6 border-b border-[var(--color-border)] pb-8">
                                    <div className="flex items-center gap-4">
                                        <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-[var(--color-surface-alt)] text-[var(--color-primary)]">
                                            <Package size={28} />
                                        </div>
                                        <div>
                                            <div className="mb-1 flex items-center gap-3">
                                                <h2 className="text-2xl font-bold text-[var(--color-text-primary)]">
                                                    #{order.order_number}
                                                </h2>
                                                <OrderStatusBadge
                                                    status={order.status}
                                                />
                                            </div>
                                            <p className="text-sm font-medium text-[var(--color-text-muted)]">
                                                Placed on{' '}
                                                {formatDate(
                                                    order.created_at,
                                                    'long',
                                                )}
                                            </p>
                                        </div>
                                    </div>

                                    <div className="text-right">
                                        <p className="mb-1 text-[10px] font-bold tracking-widest text-[var(--color-text-muted)] uppercase">
                                            Current Status
                                        </p>
                                        <p className="text-xl font-bold tracking-tight text-[var(--color-text-primary)] uppercase">
                                            {order.status.replace('_', ' ')}
                                        </p>
                                    </div>
                                </div>

                                <div className="grid grid-cols-1 gap-6 lg:grid-cols-12 lg:gap-12">
                                    <div className="lg:col-span-7">
                                        <h3 className="mb-8 text-lg font-bold text-[var(--color-text-primary)]">
                                            Delivery Journey
                                        </h3>
                                        <OrderTimeline
                                            histories={order.status_histories}
                                        />
                                    </div>
                                    <div className="space-y-8 lg:col-span-5">
                                        <div className="rounded-3xl border border-[var(--color-border)] bg-[var(--color-surface-alt)] p-6">
                                            <h3 className="mb-4 text-sm font-bold tracking-widest text-[var(--color-text-primary)] uppercase">
                                                Estimated Delivery
                                            </h3>
                                            <div className="flex items-center gap-4">
                                                <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-white text-[var(--color-success)] shadow-sm">
                                                    <CheckCircle2 size={24} />
                                                </div>
                                                <div>
                                                    <p className="text-lg font-bold text-[var(--color-text-primary)]">
                                                        3-5 Business Days
                                                    </p>
                                                    <p className="text-xs font-medium text-[var(--color-text-muted)]">
                                                        Inside Dhaka Delivery
                                                    </p>
                                                </div>
                                            </div>
                                        </div>

                                        {order.tracking_number && (
                                            <div className="rounded-3xl bg-[var(--color-secondary)] p-6 text-white shadow-lg">
                                                <p className="mb-1 text-[10px] font-bold tracking-widest text-blue-200 uppercase">
                                                    Tracking ID
                                                </p>
                                                <p className="mb-6 font-mono text-xl font-bold">
                                                    {order.tracking_number}
                                                </p>
                                                <Button className="h-11 w-full rounded-xl bg-white font-bold text-[var(--color-secondary)] hover:bg-gray-100">
                                                    Track on Carrier Site
                                                </Button>
                                            </div>
                                        )}

                                        <div className="rounded-3xl border border-[var(--color-border)] p-6">
                                            <h3 className="mb-4 text-sm font-bold tracking-widest text-[var(--color-text-primary)] uppercase">
                                                Order Summary
                                            </h3>
                                            <div className="space-y-3">
                                                {order.items.map((item) => (
                                                    <div
                                                        key={item.id}
                                                        className="flex justify-between text-sm"
                                                    >
                                                        <span className="max-w-[200px] truncate font-medium text-[var(--color-text-secondary)]">
                                                            {item.product_name}{' '}
                                                            x {item.quantity}
                                                        </span>
                                                        <span className="font-bold text-[var(--color-text-primary)]">
                                                            {formatCurrency(
                                                                item.subtotal,
                                                            )}
                                                        </span>
                                                    </div>
                                                ))}
                                                <div className="flex items-end justify-between border-t border-[var(--color-border)] pt-3">
                                                    <span className="font-bold text-[var(--color-text-primary)]">
                                                        Total Paid
                                                    </span>
                                                    <span className="text-xl font-bold text-[var(--color-primary)]">
                                                        {formatCurrency(
                                                            order.total,
                                                        )}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </motion.div>
                    )}
                </AnimatePresence>

                <div className="mt-12 text-center text-sm text-[var(--color-text-muted)]">
                    <p>
                        Have a question about your order?{' '}
                        <Link
                            href={route('contact')}
                            className="font-bold text-[var(--color-primary)] hover:underline"
                        >
                            Contact Support
                        </Link>
                    </p>
                </div>
            </div>
        </div>
    );
}
