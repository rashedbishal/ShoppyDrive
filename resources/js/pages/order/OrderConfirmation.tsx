import { Head, Link } from '@inertiajs/react';
import { motion } from 'framer-motion';
import {
    CheckCircle2,
    Package,
    Truck,
    ArrowRight,
    Download,
    Home,
} from 'lucide-react';
import React, { useEffect } from 'react';

import CheckoutLayout from '@/components/layout/CheckoutLayout';
import { Button } from '@/components/ui/button';
import { trackMetaPurchase } from '@/lib/meta-tracking';
import { formatCurrency } from '@/lib/utils';
import { useCartStore } from '@/stores/cartStore';
import type { Order } from '@/types';

const DELIVERY_ESTIMATE_DAYS: [number, number] = [3, 5];

function addDays(dateString: string, days: number): string {
    const date = new Date(dateString);
    date.setDate(date.getDate() + days);

    return date.toLocaleDateString();
}

interface OrderConfirmationProps {
    order: Order;
    /** True for a "Buy Now" order, which never touched the customer's cart. */
    keepCart?: boolean;
}

export default function OrderConfirmation({
    order,
    keepCart = false,
}: OrderConfirmationProps): React.JSX.Element {
    const clearCart = useCartStore((state) => state.clearCart);

    // BIZ-030: this hardcoded "Total Paid" regardless of payment_status —
    // every COD order (unpaid until the courier collects it) told the
    // customer they'd already paid, with nothing saying to pay the
    // courier, inviting refusals at the door.
    const totalLabel =
        order.payment_status === 'paid'
            ? 'Total Paid'
            : order.payment_status === 'refunded'
              ? 'Total Refunded'
              : 'Total Due on Delivery';

    const canDownloadInvoice = (() => {
        if (order.status === 'cancelled' || order.status === 'refunded') {
            return false;
        }

        if (order.payment_method === 'cod') {
            return order.status === 'delivered';
        }

        return true;
    })();

    // Clear local cart on mount if order was successful — except after a
    // "Buy Now", which was bought on its own and left the cart alone.
    useEffect(() => {
        if (!keepCart) {
            clearCart();
        }
    }, []);

    // Pushes to GTM's own expected dataLayer shape, so a Facebook Pixel tag
    // configured in Tag Manager can read `fb_event_id` to deduplicate
    // against the server-side Conversions API Purchase event
    // (FacebookConversionsApiService sends the same "order-{order_number}"
    // id). No line items here — OrderConfirmationAccess::payload() (FSA-03)
    // deliberately never sends items/totals-breakdown to this public page,
    // and that allow-list isn't being widened just for this.
    useEffect(() => {
        try {
            window.dataLayer = window.dataLayer || [];
            window.dataLayer.push({
                event: 'purchase',
                ecommerce: {
                    transaction_id: order.order_number,
                    value: order.total,
                    currency: 'BDT',
                },
                fb_event_id: `order-${order.order_number}`,
            });
        } catch {
            // window.dataLayer is a plain array; nothing here should ever
            // throw, but a tracking push must never break this page if it does.
        }

        // Meta Purchase (browser half), same `order-<number>` event id the
        // server sends; not for an order that has since been cancelled.
        if (order.status !== 'cancelled') {
            trackMetaPurchase({
                orderNumber: order.order_number,
                value: order.total,
            });
        }
    }, []);

    return (
        <CheckoutLayout>
            <Head title="Order Confirmed" />

            <div className="mx-auto max-w-4xl">
                <div className="mb-12 text-center">
                    <motion.div
                        initial={{ scale: 0 }}
                        animate={{ scale: 1 }}
                        transition={{
                            type: 'spring',
                            damping: 12,
                            stiffness: 200,
                        }}
                        className="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-full bg-[var(--color-success)] text-white shadow-xl shadow-green-100"
                    >
                        <CheckCircle2 size={40} />
                    </motion.div>

                    <motion.h1
                        initial={{ opacity: 0, y: 10 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{ delay: 0.2 }}
                        className="mb-3 text-3xl font-bold text-[var(--color-text-primary)] sm:text-4xl"
                    >
                        Order Placed Successfully! 🎉
                    </motion.h1>

                    <motion.p
                        initial={{ opacity: 0, y: 10 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{ delay: 0.3 }}
                        className="mx-auto max-w-lg text-[var(--color-text-secondary)]"
                    >
                        We've received your order and we're getting it ready for
                        shipment.
                        {order.shipping_address.email && (
                            <>
                                {' '}
                                A confirmation email has been sent to{' '}
                                <span className="font-bold text-[var(--color-text-primary)]">
                                    {order.shipping_address.email}
                                </span>
                                .
                            </>
                        )}
                    </motion.p>
                </div>

                <div className="mb-12 grid grid-cols-1 gap-8 md:grid-cols-2">
                    {/* Order Info */}
                    <motion.div
                        initial={{ opacity: 0, x: -20 }}
                        animate={{ opacity: 1, x: 0 }}
                        transition={{ delay: 0.4 }}
                        className="rounded-3xl border border-[var(--color-border)] bg-white p-8 shadow-sm"
                    >
                        <div className="mb-6 flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-[var(--color-surface-alt)] text-[var(--color-primary)]">
                                <Package size={20} />
                            </div>
                            <div>
                                <h3 className="text-[10px] font-bold tracking-widest text-[var(--color-text-muted)] uppercase">
                                    Order Number
                                </h3>
                                <p className="font-mono text-lg font-bold text-[var(--color-text-primary)]">
                                    {order.order_number}
                                </p>
                            </div>
                        </div>

                        <div className="space-y-4">
                            <div className="flex justify-between text-sm">
                                <span className="text-[var(--color-text-secondary)]">
                                    Date
                                </span>
                                <span className="font-bold text-[var(--color-text-primary)]">
                                    {new Date(
                                        order.created_at,
                                    ).toLocaleDateString()}
                                </span>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-[var(--color-text-secondary)]">
                                    Payment Method
                                </span>
                                <span className="font-bold text-[var(--color-text-primary)] uppercase">
                                    {order.payment_method}
                                </span>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-[var(--color-text-secondary)]">
                                    Shipping Method
                                </span>
                                <span className="font-bold text-[var(--color-text-primary)]">
                                    Standard Delivery
                                </span>
                            </div>
                            <div className="flex items-center justify-between border-t border-[var(--color-border)] pt-4">
                                <span className="font-bold text-[var(--color-text-primary)]">
                                    {totalLabel}
                                </span>
                                <span className="text-xl font-bold text-[var(--color-primary)]">
                                    {formatCurrency(order.total)}
                                </span>
                            </div>
                        </div>
                    </motion.div>

                    {/* Delivery Info */}
                    <motion.div
                        initial={{ opacity: 0, x: 20 }}
                        animate={{ opacity: 1, x: 0 }}
                        transition={{ delay: 0.5 }}
                        className="rounded-3xl border border-[var(--color-border)] bg-white p-8 shadow-sm"
                    >
                        <div className="mb-6 flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-[var(--color-surface-alt)] text-[var(--color-primary)]">
                                <Truck size={20} />
                            </div>
                            <div>
                                <h3 className="text-[10px] font-bold tracking-widest text-[var(--color-text-muted)] uppercase">
                                    Shipping To
                                </h3>
                                <p className="truncate text-sm font-bold text-[var(--color-text-primary)]">
                                    {order.shipping_address.name}
                                </p>
                            </div>
                        </div>

                        <div className="space-y-4 text-sm leading-relaxed text-[var(--color-text-secondary)]">
                            <p>{order.shipping_address.address_line1}</p>
                            <p>
                                {order.shipping_address.city},{' '}
                                {order.shipping_address.state}{' '}
                                {order.shipping_address.postal_code}
                            </p>
                            <p>Bangladesh</p>
                            <div className="border-t border-[var(--color-border)] pt-4">
                                <div className="flex gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4">
                                    <CheckCircle2
                                        size={16}
                                        className="mt-0.5 shrink-0 text-amber-600"
                                    />
                                    <p className="text-[10px] font-bold tracking-tight text-amber-800 uppercase">
                                        Estimated Delivery:{' '}
                                        {addDays(
                                            order.created_at,
                                            DELIVERY_ESTIMATE_DAYS[0],
                                        )}{' '}
                                        -{' '}
                                        {addDays(
                                            order.created_at,
                                            DELIVERY_ESTIMATE_DAYS[1],
                                        )}
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
                    className="flex flex-col justify-center gap-4 sm:flex-row"
                >
                    <Button
                        asChild
                        size="lg"
                        className="h-14 rounded-2xl px-10 font-bold shadow-[var(--color-primary)]/20 shadow-lg"
                    >
                        <Link
                            href={
                                route('order.track') +
                                `?order_number=${order.order_number}`
                            }
                        >
                            Track Your Order
                            <ArrowRight size={18} className="ml-2" />
                        </Link>
                    </Button>
                    <Button
                        variant="outline"
                        asChild
                        size="lg"
                        className="h-14 rounded-2xl border-[var(--color-border)] bg-white px-10 font-bold text-[var(--color-text-primary)]"
                    >
                        <Link href={route('home')}>
                            <Home size={18} className="mr-2" />
                            Return Home
                        </Link>
                    </Button>
                    {canDownloadInvoice && (
                        <Button
                            variant="ghost"
                            asChild
                            className="h-14 rounded-2xl font-bold text-[var(--color-text-muted)]"
                        >
                            <a
                                href={route(
                                    'account.orders.invoice',
                                    order.order_number,
                                )}
                            >
                                <Download size={18} className="mr-2" />
                                Download Invoice
                            </a>
                        </Button>
                    )}
                </motion.div>

                <motion.div
                    initial={{ opacity: 0 }}
                    animate={{ opacity: 1 }}
                    transition={{ delay: 1 }}
                    className="mt-20 text-center"
                >
                    <p className="text-xs font-medium text-[var(--color-text-muted)]">
                        Need help with your order?{' '}
                        <Link
                            href={route('contact')}
                            className="font-bold text-[var(--color-primary)] hover:underline"
                        >
                            Contact our support team
                        </Link>
                    </p>
                </motion.div>
            </div>
        </CheckoutLayout>
    );
}
