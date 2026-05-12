import { Head, Link, usePage } from '@inertiajs/react';
import { XCircle, ArrowLeft, Phone } from 'lucide-react';
import { motion } from 'framer-motion';
import CheckoutLayout from '@/components/layout/CheckoutLayout';
import { Button } from '@/components/ui/button';
import { formatCurrency } from '@/lib/utils';

interface Props {
    order: {
        order_number: string;
        total: number;
        payment_method: string;
    };
}

export default function PaymentFailed({ order }: Props): React.JSX.Element {
    const { flash } = usePage<any>().props;

    return (
        <CheckoutLayout>
            <Head title="Payment Failed" />

            <div className="mx-auto max-w-lg text-center">
                <motion.div
                    initial={{ scale: 0 }}
                    animate={{ scale: 1 }}
                    transition={{ type: 'spring', damping: 12, stiffness: 200 }}
                    className="mx-auto mb-6 flex h-24 w-24 items-center justify-center rounded-full bg-red-100 text-red-500 shadow-xl shadow-red-100"
                >
                    <XCircle size={52} />
                </motion.div>

                <motion.h1
                    initial={{ opacity: 0, y: 10 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ delay: 0.2 }}
                    className="mb-3 text-3xl font-bold text-[var(--color-text-primary)]"
                >
                    Payment Failed
                </motion.h1>

                <motion.p
                    initial={{ opacity: 0, y: 10 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ delay: 0.3 }}
                    className="mb-2 text-[var(--color-text-secondary)]"
                >
                    {flash?.info || flash?.error || 'Your payment could not be completed. No money has been deducted from your account.'}
                </motion.p>

                <motion.div
                    initial={{ opacity: 0, y: 10 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ delay: 0.4 }}
                    className="mb-8 rounded-2xl border border-[var(--color-border)] bg-white p-6 text-left shadow-sm"
                >
                    <div className="flex justify-between text-sm">
                        <span className="text-[var(--color-text-secondary)]">Order Number</span>
                        <span className="font-mono font-bold text-[var(--color-text-primary)]">{order.order_number}</span>
                    </div>
                    <div className="mt-3 flex justify-between text-sm">
                        <span className="text-[var(--color-text-secondary)]">Amount</span>
                        <span className="font-bold text-[var(--color-primary)]">{formatCurrency(order.total)}</span>
                    </div>
                </motion.div>

                <motion.div
                    initial={{ opacity: 0, y: 10 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ delay: 0.5 }}
                    className="flex flex-col gap-3"
                >
                    <Button asChild size="lg" className="h-13 w-full rounded-xl font-bold">
                        <Link href={route('checkout.index')}>
                            <ArrowLeft size={16} className="mr-2" />
                            Try Again
                        </Link>
                    </Button>

                    <Button variant="outline" asChild size="lg" className="h-13 w-full rounded-xl bg-white font-bold">
                        <Link href={route('contact')}>
                            <Phone size={16} className="mr-2" />
                            Contact Support
                        </Link>
                    </Button>
                </motion.div>
            </div>
        </CheckoutLayout>
    );
}
