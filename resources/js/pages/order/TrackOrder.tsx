import { Head, Link, useForm } from '@inertiajs/react';
import { motion, AnimatePresence } from 'framer-motion';
import {
    Truck,
    Package,
    Mail,
    Phone,
    AlertCircle,
    CheckCircle2,
    Loader2,
} from 'lucide-react';
import React, { useEffect, useState } from 'react';

import { OrderStatusBadge } from '@/components/shared/OrderStatusBadge';
import { OrderTimeline } from '@/components/shared/OrderTimeline';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatCurrency, formatDate } from '@/lib/utils';
import type { Order } from '@/types';

type RequiredFields = { email: boolean; phone: boolean };

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
    // What the typed order number can actually be verified with — updated
    // live as the customer types, so the right field(s) appear instantly:
    // guests who skipped email at checkout only have a phone on file.
    const [requires, setRequires] = useState<RequiredFields | null>(null);
    const [checking, setChecking] = useState(false);

    useEffect(() => {
        const orderNumber = form.data.order_number.trim();

        if (!orderNumber) {
            // eslint-disable-next-line react-hooks/set-state-in-effect -- clears results at once when the query is emptied; fetched results are set asynchronously
            setRequires(null);
            setChecking(false);

            return;
        }

        setChecking(true);
        const controller = new AbortController();
        const timeoutId = setTimeout(async () => {
            try {
                const res = await fetch(
                    `${route('order.track.check')}?order_number=${encodeURIComponent(orderNumber)}`,
                    {
                        signal: controller.signal,
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    },
                );
                const data = await res.json();

                setRequires(data.found ? data.requires : null);
            } catch (err) {
                // An aborted fetch (superseded by a newer keystroke) is
                // expected and silent; anything else — e.g. a stale Ziggy
                // route list — should be visible in the console instead of
                // just quietly never revealing the fields.
                if ((err as Error)?.name !== 'AbortError') {
                    console.error('Order lookup failed', err);
                }
            } finally {
                setChecking(false);
            }
        }, 300);

        return () => {
            clearTimeout(timeoutId);
            controller.abort();
        };
    }, [form.data.order_number]);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setError(null);

        const orderNumber = form.data.order_number.trim();

        if (!orderNumber) {
            return;
        }

        // The live check below is debounced — if the customer submits
        // before it's had a chance to resolve, `requires` is still null.
        // Resolve it synchronously now rather than letting an incomplete
        // request reach the server and bounce back with a generic error.
        let resolvedRequires = requires;

        if (!resolvedRequires) {
            setChecking(true);

            try {
                const res = await fetch(
                    `${route('order.track.check')}?order_number=${encodeURIComponent(orderNumber)}`,
                    {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    },
                );
                const data = await res.json();

                if (res.status === 429) {
                    setError(
                        data.error ??
                            'Too many order lookups. Please wait a minute and try again.',
                    );

                    return;
                }

                if (!data.found) {
                    setError(
                        'No order found with that order number. Please check and try again.',
                    );

                    return;
                }

                resolvedRequires = data.requires;
                setRequires(resolvedRequires);
            } catch (err) {
                console.error('Order lookup failed', err);
            } finally {
                setChecking(false);
            }
        }

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
                <div className="mb-10 text-center">
                    <div className="mx-auto mb-6 flex h-20 w-20 rotate-3 items-center justify-center rounded-3xl bg-[var(--color-primary)] text-white shadow-xl shadow-red-100">
                        <Truck size={40} />
                    </div>
                    <h1 className="mb-4 text-2xl font-bold text-[var(--color-text-primary)] sm:text-4xl">
                        Track Your Order
                    </h1>
                    <p className="mx-auto max-w-md text-[var(--color-text-secondary)]">
                        Start typing your order number — we'll show you exactly
                        what's needed to verify it.
                    </p>
                </div>

                <div className="mb-8 rounded-3xl border border-[var(--color-border)] bg-white p-5 shadow-sm md:p-10">
                    <form onSubmit={handleSubmit} className="space-y-5">
                        <div className="space-y-2">
                            <Label htmlFor="order_number">Order Number</Label>
                            <div className="relative">
                                <Input
                                    maxLength={40}
                                    id="order_number"
                                    value={form.data.order_number}
                                    onChange={(e) =>
                                        form.setData(
                                            'order_number',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="e.g. ORD-20261024-ABC123"
                                    className="h-14 rounded-2xl pl-12"
                                    required
                                />
                                <Package
                                    size={20}
                                    className="absolute top-1/2 left-4 -translate-y-1/2 text-[var(--color-text-muted)]"
                                />
                                {checking && (
                                    <Loader2
                                        size={18}
                                        className="absolute top-1/2 right-4 -translate-y-1/2 animate-spin text-[var(--color-text-muted)]"
                                    />
                                )}
                            </div>
                        </div>

                        <AnimatePresence>
                            {requires && (requires.email || requires.phone) && (
                                <motion.div
                                    initial={{ opacity: 0, y: -8 }}
                                    animate={{ opacity: 1, y: 0 }}
                                    exit={{ opacity: 0, y: -8 }}
                                    transition={{ duration: 0.2 }}
                                >
                                    <div
                                        className={`grid grid-cols-1 gap-4 pt-1 ${requires.email && requires.phone ? 'sm:grid-cols-2' : ''}`}
                                    >
                                        {requires.email && (
                                            <div className="space-y-2">
                                                <Label htmlFor="email">
                                                    Email Address
                                                </Label>
                                                <div className="relative">
                                                    <Input
                                                        maxLength={150}
                                                        id="email"
                                                        type="email"
                                                        value={form.data.email}
                                                        onChange={(e) =>
                                                            form.setData(
                                                                'email',
                                                                e.target.value,
                                                            )
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
                                        )}
                                        {requires.phone && (
                                            <div className="space-y-2">
                                                <Label htmlFor="phone">
                                                    Phone Number
                                                </Label>
                                                <div className="relative">
                                                    <Input
                                                        maxLength={20}
                                                        id="phone"
                                                        value={form.data.phone}
                                                        onChange={(e) =>
                                                            form.setData(
                                                                'phone',
                                                                e.target.value,
                                                            )
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
                                        )}
                                    </div>
                                </motion.div>
                            )}
                        </AnimatePresence>

                        <Button
                            type="submit"
                            disabled={form.processing || checking}
                            className="h-14 w-full rounded-2xl font-bold shadow-[var(--color-primary)]/20 shadow-lg"
                        >
                            {form.processing || checking
                                ? 'Searching…'
                                : 'Track Order'}
                        </Button>
                    </form>

                    {error && (
                        <motion.div
                            initial={{ opacity: 0, y: 10 }}
                            animate={{ opacity: 1, y: 0 }}
                            className="mt-6 flex gap-3 rounded-2xl border border-red-100 bg-red-50 p-4 text-red-800"
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
                            <div className="rounded-2xl border border-[var(--color-border)] bg-white p-5 shadow-sm md:p-7">
                                <div className="mb-5 flex flex-wrap items-center justify-between gap-4 border-b border-[var(--color-border)] pb-4">
                                    <div className="flex items-center gap-3">
                                        <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-[var(--color-surface-alt)] text-[var(--color-primary)]">
                                            <Package size={20} />
                                        </div>
                                        <div>
                                            <div className="mb-0.5 flex items-center gap-2">
                                                <h2 className="text-base font-bold text-[var(--color-text-primary)]">
                                                    #{order.order_number}
                                                </h2>
                                                <OrderStatusBadge
                                                    status={order.status}
                                                />
                                            </div>
                                            <p className="text-xs font-medium text-[var(--color-text-secondary)]">
                                                Placed on{' '}
                                                {formatDate(
                                                    order.created_at,
                                                    'long',
                                                )}
                                            </p>
                                        </div>
                                    </div>

                                    <div className="text-right">
                                        <p className="mb-0.5 text-[10px] font-bold tracking-widest text-[var(--color-text-secondary)] uppercase">
                                            Current Status
                                        </p>
                                        <p className="text-sm font-bold tracking-tight text-[var(--color-text-primary)] uppercase">
                                            {order.status.replace('_', ' ')}
                                        </p>
                                    </div>
                                </div>

                                <div className="grid grid-cols-1 gap-5 lg:grid-cols-12 lg:gap-8">
                                    <div className="lg:col-span-7">
                                        <h3 className="mb-4 text-sm font-bold text-[var(--color-text-primary)]">
                                            Delivery Journey
                                        </h3>
                                        <OrderTimeline
                                            histories={order.status_histories}
                                        />
                                    </div>
                                    <div className="space-y-4 lg:col-span-5">
                                        <div className="rounded-xl border border-[var(--color-border)] bg-[var(--color-surface-alt)] p-4">
                                            <h3 className="mb-2.5 text-xs font-bold tracking-widest text-[var(--color-text-primary)] uppercase">
                                                Estimated Delivery
                                            </h3>
                                            <div className="flex items-center gap-3">
                                                <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-white text-[var(--color-success)] shadow-sm">
                                                    <CheckCircle2 size={18} />
                                                </div>
                                                <div>
                                                    <p className="text-sm font-bold text-[var(--color-text-primary)]">
                                                        3-5 Business Days
                                                    </p>
                                                    <p className="text-xs font-medium text-[var(--color-text-secondary)]">
                                                        Inside Dhaka Delivery
                                                    </p>
                                                </div>
                                            </div>
                                        </div>

                                        {order.tracking_number && (
                                            <div className="rounded-xl bg-[var(--color-secondary)] p-4 text-white shadow-lg">
                                                <p className="mb-1 text-[10px] font-bold tracking-widest text-blue-200 uppercase">
                                                    Tracking ID
                                                </p>
                                                <p className="mb-3 font-mono text-base font-bold">
                                                    {order.tracking_number}
                                                </p>
                                                <Button className="h-10 w-full rounded-lg bg-white font-bold text-[var(--color-secondary)] hover:bg-gray-100">
                                                    Track on Carrier Site
                                                </Button>
                                            </div>
                                        )}

                                        <div className="rounded-xl border border-[var(--color-border)] p-4">
                                            <h3 className="mb-2.5 text-xs font-bold tracking-widest text-[var(--color-text-primary)] uppercase">
                                                Order Summary
                                            </h3>
                                            <div className="space-y-2">
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
                                                <div className="flex items-end justify-between border-t border-[var(--color-border)] pt-2.5">
                                                    <span className="font-bold text-[var(--color-text-primary)]">
                                                        Total Paid
                                                    </span>
                                                    <span className="text-lg font-bold text-[var(--color-primary)]">
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

                <div className="mt-12 text-center text-sm text-[var(--color-text-secondary)]">
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
