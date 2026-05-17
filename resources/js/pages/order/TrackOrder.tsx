import { Head, Link, useForm } from '@inertiajs/react';
import {
    Truck,
    Package,
    Mail,
    AlertCircle,
    CheckCircle2
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
    });

    const [order, setOrder] = useState<Order | null>(initialOrder || null);
    const [error, setError] = useState<string | null>(initialError || null);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setError(null);
        
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
        <div className="bg-[var(--color-surface-alt)] min-h-screen py-12 md:py-20 px-4">
            <Head title="Track Your Order" />
            
            <div className="container mx-auto max-w-4xl">
                <div className="text-center mb-12">
                    <div className="w-20 h-20 rounded-3xl bg-[var(--color-primary)] text-white flex items-center justify-center mx-auto mb-6 shadow-xl shadow-red-100 rotate-3">
                        <Truck size={40} />
                    </div>
                    <h1 className="text-2xl font-serif font-bold text-[var(--color-text-primary)] sm:text-4xl mb-4">Track Your Order</h1>
                    <p className="text-[var(--color-text-secondary)] max-w-md mx-auto">
                        Enter your order number and email address to check the current status and estimated delivery time.
                    </p>
                </div>

                <div className="bg-white rounded-3xl p-5 md:p-12 shadow-sm border border-[var(--color-border)] mb-8">
                    <form onSubmit={handleSubmit} className="grid grid-cols-1 md:grid-cols-12 gap-6 items-end">
                        <div className="md:col-span-5 space-y-2">
                            <Label htmlFor="order_number">Order Number</Label>
                            <div className="relative">
                                <Input 
                                    id="order_number" 
                                    value={form.data.order_number} 
                                    onChange={e => form.setData('order_number', e.target.value)} 
                                    placeholder="e.g. ORD-123456"
                                    className="h-14 pl-12 rounded-2xl"
                                    required
                                />
                                <Package size={20} className="absolute left-4 top-1/2 -translate-y-1/2 text-[var(--color-text-muted)]" />
                            </div>
                        </div>
                        <div className="md:col-span-5 space-y-2">
                            <Label htmlFor="email">Email Address</Label>
                            <div className="relative">
                                <Input 
                                    id="email" 
                                    type="email"
                                    value={form.data.email} 
                                    onChange={e => form.setData('email', e.target.value)} 
                                    placeholder="your@email.com"
                                    className="h-14 pl-12 rounded-2xl"
                                    required
                                />
                                <Mail size={20} className="absolute left-4 top-1/2 -translate-y-1/2 text-[var(--color-text-muted)]" />
                            </div>
                        </div>
                        <div className="md:col-span-2">
                            <Button type="submit" disabled={form.processing} className="w-full h-14 rounded-2xl font-bold shadow-lg shadow-[var(--color-primary)]/20">
                                {form.processing ? 'Searching...' : 'Track'}
                            </Button>
                        </div>
                    </form>

                    {error && (
                        <motion.div 
                            initial={{ opacity: 0, y: 10 }}
                            animate={{ opacity: 1, y: 0 }}
                            className="mt-8 p-4 rounded-2xl bg-red-50 border border-red-100 flex gap-3 text-red-800"
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
                            <div className="bg-white rounded-3xl p-8 md:p-12 shadow-sm border border-[var(--color-border)]">
                                <div className="flex flex-wrap items-center justify-between gap-6 mb-12 pb-8 border-b border-[var(--color-border)]">
                                    <div className="flex items-center gap-4">
                                        <div className="w-14 h-14 rounded-2xl bg-[var(--color-surface-alt)] flex items-center justify-center text-[var(--color-primary)]">
                                            <Package size={28} />
                                        </div>
                                        <div>
                                            <div className="flex items-center gap-3 mb-1">
                                                <h2 className="text-2xl font-bold text-[var(--color-text-primary)]">#{order.order_number}</h2>
                                                <OrderStatusBadge status={order.status} />
                                            </div>
                                            <p className="text-sm text-[var(--color-text-muted)] font-medium">Placed on {formatDate(order.created_at, 'long')}</p>
                                        </div>
                                    </div>
                                    
                                    <div className="text-right">
                                        <p className="text-[10px] font-bold text-[var(--color-text-muted)] uppercase tracking-widest mb-1">Current Status</p>
                                        <p className="text-xl font-bold text-[var(--color-text-primary)] uppercase tracking-tight">{order.status.replace('_', ' ')}</p>
                                    </div>
                                </div>

                                <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-12">
                                    <div className="lg:col-span-7">
                                        <h3 className="text-lg font-bold text-[var(--color-text-primary)] mb-8">Delivery Journey</h3>
                                        <OrderTimeline histories={order.status_histories} />
                                    </div>
                                    <div className="lg:col-span-5 space-y-8">
                                        <div className="p-6 rounded-3xl bg-[var(--color-surface-alt)] border border-[var(--color-border)]">
                                            <h3 className="text-sm font-bold text-[var(--color-text-primary)] uppercase tracking-widest mb-4">Estimated Delivery</h3>
                                            <div className="flex items-center gap-4">
                                                <div className="w-12 h-12 rounded-2xl bg-white flex items-center justify-center text-[var(--color-success)] shadow-sm">
                                                    <CheckCircle2 size={24} />
                                                </div>
                                                <div>
                                                    <p className="text-lg font-bold text-[var(--color-text-primary)]">3-5 Business Days</p>
                                                    <p className="text-xs text-[var(--color-text-muted)] font-medium">Inside Dhaka Delivery</p>
                                                </div>
                                            </div>
                                        </div>

                                        {order.tracking_number && (
                                            <div className="p-6 rounded-3xl bg-[var(--color-secondary)] text-white shadow-lg">
                                                <p className="text-[10px] font-bold text-blue-200 uppercase tracking-widest mb-1">Tracking ID</p>
                                                <p className="text-xl font-mono font-bold mb-6">{order.tracking_number}</p>
                                                <Button className="w-full bg-white text-[var(--color-secondary)] hover:bg-gray-100 font-bold rounded-xl h-11">
                                                    Track on Carrier Site
                                                </Button>
                                            </div>
                                        )}
                                        
                                        <div className="p-6 rounded-3xl border border-[var(--color-border)]">
                                            <h3 className="text-sm font-bold text-[var(--color-text-primary)] uppercase tracking-widest mb-4">Order Summary</h3>
                                            <div className="space-y-3">
                                                {order.items.map(item => (
                                                    <div key={item.id} className="flex justify-between text-sm">
                                                        <span className="text-[var(--color-text-secondary)] font-medium truncate max-w-[200px]">{item.product_name} x {item.quantity}</span>
                                                        <span className="font-bold text-[var(--color-text-primary)]">{formatCurrency(item.subtotal)}</span>
                                                    </div>
                                                ))}
                                                <div className="pt-3 border-t border-[var(--color-border)] flex justify-between items-end">
                                                    <span className="font-bold text-[var(--color-text-primary)]">Total Paid</span>
                                                    <span className="text-xl font-bold text-[var(--color-primary)]">{formatCurrency(order.total)}</span>
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
                    <p>Have a question about your order? <Link href={route('contact')} className="text-[var(--color-primary)] font-bold hover:underline">Contact Support</Link></p>
                </div>
            </div>
        </div>
    );
}
