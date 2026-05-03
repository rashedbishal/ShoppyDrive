import React, { useEffect } from 'react';
import { Head, Link } from '@inertiajs/react';
import { CheckCircle2, Package, Truck, ArrowRight, Download, Home } from 'lucide-react';
import { motion } from 'framer-motion';

import CheckoutLayout from '@/components/layout/CheckoutLayout';
import { Button } from '@/components/ui/button';
import { formatCurrency } from '@/lib/utils';
import { useCartStore } from '@/stores/cartStore';
import type { Order } from '@/types';

interface OrderConfirmationProps {
    order: Order;
}

export default function OrderConfirmation({ order }: OrderConfirmationProps): React.JSX.Element {
    const clearCart = useCartStore((state) => state.clearCart);

    // Clear local cart on mount if order was successful
    useEffect(() => {
        clearCart();
    }, []);

    return (
        <CheckoutLayout>
            <Head title="Order Confirmed" />
            
            <div className="max-w-3xl mx-auto">
                <div className="text-center mb-12">
                    <motion.div
                        initial={{ scale: 0 }}
                        animate={{ scale: 1 }}
                        transition={{ type: 'spring', damping: 12, stiffness: 200 }}
                        className="w-24 h-24 rounded-full bg-[var(--color-success)] text-white flex items-center justify-center mx-auto mb-6 shadow-xl shadow-green-100"
                    >
                        <CheckCircle2 size={48} />
                    </motion.div>
                    
                    <motion.h1 
                        initial={{ opacity: 0, y: 10 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{ delay: 0.2 }}
                        className="text-4xl font-serif font-bold text-[var(--color-text-primary)] mb-4"
                    >
                        Order Placed Successfully! 🎉
                    </motion.h1>
                    
                    <motion.p 
                        initial={{ opacity: 0, y: 10 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{ delay: 0.3 }}
                        className="text-[var(--color-text-secondary)]"
                    >
                        We've received your order and we're getting it ready for shipment.
                        A confirmation email has been sent to <span className="text-[var(--color-text-primary)] font-bold">{order.shipping_address.email || 'your email'}</span>.
                    </motion.p>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-8 mb-12">
                    {/* Order Info */}
                    <motion.div 
                        initial={{ opacity: 0, x: -20 }}
                        animate={{ opacity: 1, x: 0 }}
                        transition={{ delay: 0.4 }}
                        className="bg-white rounded-3xl p-8 border border-[var(--color-border)] shadow-sm"
                    >
                        <div className="flex items-center gap-3 mb-6">
                            <div className="w-10 h-10 rounded-xl bg-[var(--color-surface-alt)] flex items-center justify-center text-[var(--color-primary)]">
                                <Package size={20} />
                            </div>
                            <div>
                                <h3 className="text-[10px] font-bold text-[var(--color-text-muted)] uppercase tracking-widest">Order Number</h3>
                                <p className="text-lg font-mono font-bold text-[var(--color-text-primary)]">{order.order_number}</p>
                            </div>
                        </div>

                        <div className="space-y-4">
                            <div className="flex justify-between text-sm">
                                <span className="text-[var(--color-text-secondary)]">Date</span>
                                <span className="font-bold text-[var(--color-text-primary)]">{new Date(order.created_at).toLocaleDateString()}</span>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-[var(--color-text-secondary)]">Payment Method</span>
                                <span className="font-bold text-[var(--color-text-primary)] uppercase">{order.payment_method}</span>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-[var(--color-text-secondary)]">Shipping Method</span>
                                <span className="font-bold text-[var(--color-text-primary)]">Standard Delivery</span>
                            </div>
                            <div className="pt-4 border-t border-[var(--color-border)] flex justify-between items-center">
                                <span className="font-bold text-[var(--color-text-primary)]">Total Paid</span>
                                <span className="text-xl font-bold text-[var(--color-primary)]">{formatCurrency(order.total)}</span>
                            </div>
                        </div>
                    </motion.div>

                    {/* Delivery Info */}
                    <motion.div 
                        initial={{ opacity: 0, x: 20 }}
                        animate={{ opacity: 1, x: 0 }}
                        transition={{ delay: 0.5 }}
                        className="bg-white rounded-3xl p-8 border border-[var(--color-border)] shadow-sm"
                    >
                        <div className="flex items-center gap-3 mb-6">
                            <div className="w-10 h-10 rounded-xl bg-[var(--color-surface-alt)] flex items-center justify-center text-[var(--color-primary)]">
                                <Truck size={20} />
                            </div>
                            <div>
                                <h3 className="text-[10px] font-bold text-[var(--color-text-muted)] uppercase tracking-widest">Shipping To</h3>
                                <p className="text-sm font-bold text-[var(--color-text-primary)] truncate">{order.shipping_address.name}</p>
                            </div>
                        </div>

                        <div className="space-y-4 text-sm text-[var(--color-text-secondary)] leading-relaxed">
                            <p>{order.shipping_address.address_line1}</p>
                            <p>{order.shipping_address.city}, {order.shipping_address.state} {order.shipping_address.postal_code}</p>
                            <p>Bangladesh</p>
                            <div className="pt-4 border-t border-[var(--color-border)]">
                                <div className="p-4 rounded-2xl bg-amber-50 border border-amber-200 flex gap-3">
                                    <CheckCircle2 size={16} className="text-amber-600 shrink-0 mt-0.5" />
                                    <p className="text-[10px] font-bold text-amber-800 uppercase tracking-tight">
                                        Estimated Delivery: {new Date(Date.now() + 86400000 * 3).toLocaleDateString()} - {new Date(Date.now() + 86400000 * 5).toLocaleDateString()}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </motion.div>
                </div>

                {/* Actions */}
                <motion.div 
                    initial={{ opacity: 0, y: 20 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ delay: 0.6 }}
                    className="flex flex-col sm:flex-row gap-4 justify-center"
                >
                    <Button asChild size="lg" className="rounded-2xl px-10 font-bold h-14 shadow-lg shadow-[var(--color-primary)]/20">
                        <Link href={route('order.track', { order: order.order_number })}>
                            Track Your Order
                            <ArrowRight size={18} className="ml-2" />
                        </Link>
                    </Button>
                    <Button variant="outline" asChild size="lg" className="rounded-2xl px-10 font-bold h-14 bg-white border-[var(--color-border)] text-[var(--color-text-primary)]">
                        <Link href={route('home')}>
                            <Home size={18} className="mr-2" />
                            Return Home
                        </Link>
                    </Button>
                    <Button variant="ghost" className="rounded-2xl h-14 font-bold text-[var(--color-text-muted)]">
                        <Download size={18} className="mr-2" />
                        Download Invoice
                    </Button>
                </motion.div>

                <motion.div 
                    initial={{ opacity: 0 }}
                    animate={{ opacity: 1 }}
                    transition={{ delay: 1 }}
                    className="mt-20 text-center"
                >
                    <p className="text-xs text-[var(--color-text-muted)] font-medium">
                        Need help with your order? <Link href={route('contact')} className="text-[var(--color-primary)] font-bold hover:underline">Contact our support team</Link>
                    </p>
                </motion.div>
            </div>
        </CheckoutLayout>
    );
}
