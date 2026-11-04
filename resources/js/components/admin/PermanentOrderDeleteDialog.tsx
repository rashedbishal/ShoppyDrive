import { useForm } from '@inertiajs/react';
import { AnimatePresence } from 'framer-motion';
import { ShieldAlert } from 'lucide-react';
import { AdminReveal } from '@/components/admin/admin-motion';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';

type Props = {
    order: { id: number; order_number: string } | null;
    /** True once the server holds a valid first-step confirmation for this order. */
    confirmedForThisOrder: boolean;
    onClose: () => void;
};

/**
 * DB-001: mother-admin permanent deletion — two separate, server-verified
 * requests. Step 1 checks the password; step 2 needs the password again and
 * the exact order number within five minutes. The server enforces all of it;
 * this only collects the input.
 */
export default function PermanentOrderDeleteDialog({
    order,
    confirmedForThisOrder,
    onClose,
}: Props) {
    const step1 = useForm({ password: '' });
    const step2 = useForm({ password: '', order_number: '' });

    const close = () => {
        step1.reset();
        step1.clearErrors();
        step2.reset();
        step2.clearErrors();
        onClose();
    };

    if (!order) {
        return null;
    }

    const errors = { ...step1.errors, ...step2.errors } as Record<
        string,
        string | undefined
    >;
    // A refused second step spends the server-side confirmation, so the
    // dialog falls back to step 1 — keep showing why.
    const bannerError =
        errors.order ??
        errors.confirmation ??
        (!confirmedForThisOrder
            ? (step2.errors.password ?? step2.errors.order_number)
            : undefined);

    return (
        <Dialog open onOpenChange={(open) => !open && close()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-red-600">
                        <ShieldAlert className="size-5" /> Permanently delete{' '}
                        {order.order_number}
                    </DialogTitle>
                    <DialogDescription>
                        This cannot be undone. Only cancelled orders with no
                        payment, loyalty, coupon, courier, stock or review
                        history can be permanently deleted. A record of this
                        deletion is kept.
                    </DialogDescription>
                </DialogHeader>

                {bannerError && (
                    <p className="rounded-lg border border-red-200 bg-red-50 p-2 text-xs text-red-700">
                        {bannerError}
                    </p>
                )}

                <AnimatePresence initial={false} mode="wait">
                    {!confirmedForThisOrder ? (
                        <AdminReveal key="step-a">
                            <form
                                className="space-y-3"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    step1.post(
                                        route(
                                            'admin.orders.permanentDelete.confirm',
                                            order.id,
                                        ),
                                        {
                                            preserveScroll: true,
                                            preserveState: true,
                                            onFinish: () =>
                                                step1.reset('password'),
                                        },
                                    );
                                }}
                            >
                                <p className="text-xs font-semibold text-slate-700">
                                    Step 1 of 2 — confirm your password
                                </p>
                                <Input
                                    type="password"
                                    label="Your password"
                                    autoComplete="current-password"
                                    value={step1.data.password}
                                    onChange={(e) =>
                                        step1.setData(
                                            'password',
                                            e.target.value,
                                        )
                                    }
                                    error={step1.errors.password}
                                />
                                <Button
                                    type="submit"
                                    className="w-full"
                                    disabled={
                                        step1.processing || !step1.data.password
                                    }
                                >
                                    Continue
                                </Button>
                            </form>
                        </AdminReveal>
                    ) : (
                        <AdminReveal key="step-b">
                            <form
                                className="space-y-3"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    step2.delete(
                                        route(
                                            'admin.orders.forceDelete',
                                            order.id,
                                        ),
                                        {
                                            preserveScroll: true,
                                            onSuccess: () => close(),
                                            onFinish: () =>
                                                step2.reset('password'),
                                        },
                                    );
                                }}
                            >
                                <p className="text-xs font-semibold text-slate-700">
                                    Step 2 of 2 — confirm again within 5 minutes
                                </p>
                                <Input
                                    type="password"
                                    label="Your password, again"
                                    autoComplete="current-password"
                                    value={step2.data.password}
                                    onChange={(e) =>
                                        step2.setData(
                                            'password',
                                            e.target.value,
                                        )
                                    }
                                    error={step2.errors.password}
                                />
                                <Input
                                    label={`Type the order number (${order.order_number})`}
                                    autoComplete="off"
                                    value={step2.data.order_number}
                                    onChange={(e) =>
                                        step2.setData(
                                            'order_number',
                                            e.target.value,
                                        )
                                    }
                                    error={step2.errors.order_number}
                                />
                                <Button
                                    type="submit"
                                    className="w-full bg-red-600 text-white hover:bg-red-700"
                                    disabled={
                                        step2.processing ||
                                        !step2.data.password ||
                                        !step2.data.order_number
                                    }
                                >
                                    Delete permanently
                                </Button>
                            </form>
                        </AdminReveal>
                    )}
                </AnimatePresence>
            </DialogContent>
        </Dialog>
    );
}
