import { Link, useForm } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';

export type CourierEvidence = {
    courier_involved: boolean;
    eligible: boolean;
    reasons: string[];
    readiness_current: boolean;
    partial_blockers: string[];
    consignments: {
        provider: 'pathao' | 'steadfast';
        consignment_id: string;
        raw_status: string | null;
        anchor_kind: 'send' | 'reconcile' | null;
        gap_count: number;
        verification: {
            outcome: string;
            by: string | null;
            at: string | null;
            stale_reason: string | null;
        } | null;
    }[];
    verifiable_submissions: {
        id: number;
        courier: 'pathao' | 'steadfast';
        resolution: string | null;
    }[];
};

type Props = {
    open: boolean;
    onClose: () => void;
    order: {
        id: number;
        order_number: string;
        payment_method: string;
        total: number;
    };
    evidence: CourierEvidence;
};

const selectClass =
    'w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm';

/**
 * PAY-RETURN-001: the mother admin's audited verification of one exact
 * consignment or failed submission, checked against the courier's own
 * record. The server enforces everything (readiness, partial evidence,
 * exact consignment, COD amount, password); this only collects the input.
 */
export default function DeliveryVerificationDialog({
    open,
    onClose,
    order,
    evidence,
}: Props) {
    const first = evidence.consignments[0];
    const form = useForm({
        target_type: first ? 'consignment' : 'submission',
        provider:
            first?.provider ??
            evidence.verifiable_submissions[0]?.courier ??
            'pathao',
        consignment_id: '',
        courier_submission_id: evidence.verifiable_submissions[0]?.id ?? '',
        outcome: first ? 'full_delivery' : 'not_created',
        verified_collected_amount: '',
        timeline_reviewed: false,
        note: '',
        password: '',
    });

    const close = () => {
        form.reset();
        form.clearErrors();
        onClose();
    };

    const isCod = order.payment_method === 'cod';
    const errors = form.errors as Record<string, string | undefined>;

    return (
        <Dialog open={open} onOpenChange={(value) => !value && close()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <ShieldCheck className="size-5" /> Verify courier
                        outcome — {order.order_number}
                    </DialogTitle>
                    <DialogDescription>
                        Check the courier's own record (full status timeline)
                        before confirming. Partial deliveries cannot be
                        verified. Each verification covers one consignment or
                        attempt only and goes stale if anything changes.
                    </DialogDescription>
                </DialogHeader>

                {!evidence.readiness_current && (
                    <p className="rounded-lg border border-amber-200 bg-amber-50 p-2 text-xs text-amber-800">
                        No current readiness attestation for this release —{' '}
                        <Link
                            href={route('admin.courierReadiness.index')}
                            className="underline"
                        >
                            complete the readiness checks
                        </Link>{' '}
                        first.
                    </p>
                )}

                {(errors.verification ?? errors.provider) && (
                    <p className="rounded-lg border border-red-200 bg-red-50 p-2 text-xs text-red-700">
                        {errors.verification ?? errors.provider}
                    </p>
                )}

                <form
                    className="space-y-3"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post(
                            route(
                                'admin.orders.deliveryVerifications.store',
                                order.id,
                            ),
                            {
                                preserveScroll: true,
                                onSuccess: () => close(),
                                onFinish: () => form.reset('password'),
                            },
                        );
                    }}
                >
                    <label className="block text-xs font-semibold text-slate-700">
                        What are you verifying?
                        <select
                            className={selectClass}
                            value={form.data.target_type}
                            onChange={(e) => {
                                const target = e.target.value;
                                form.setData((d) => ({
                                    ...d,
                                    target_type: target,
                                    outcome:
                                        target === 'submission'
                                            ? 'not_created'
                                            : 'full_delivery',
                                }));
                            }}
                        >
                            {evidence.consignments.length > 0 && (
                                <option value="consignment">
                                    A consignment
                                </option>
                            )}
                            {evidence.verifiable_submissions.length > 0 && (
                                <option value="submission">
                                    A failed send attempt (not created)
                                </option>
                            )}
                        </select>
                    </label>

                    {form.data.target_type === 'consignment' ? (
                        <>
                            <label className="block text-xs font-semibold text-slate-700">
                                Courier
                                <select
                                    className={selectClass}
                                    value={form.data.provider}
                                    onChange={(e) =>
                                        form.setData(
                                            'provider',
                                            e.target.value as
                                                | 'pathao'
                                                | 'steadfast',
                                        )
                                    }
                                >
                                    {evidence.consignments.map((c) => (
                                        <option
                                            key={c.provider}
                                            value={c.provider}
                                        >
                                            {c.provider} — {c.consignment_id}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <Input
                                label="Type the consignment ID exactly"
                                autoComplete="off"
                                value={form.data.consignment_id}
                                onChange={(e) =>
                                    form.setData(
                                        'consignment_id',
                                        e.target.value,
                                    )
                                }
                                error={errors.consignment_id}
                            />
                            <label className="block text-xs font-semibold text-slate-700">
                                Outcome in the courier's record
                                <select
                                    className={selectClass}
                                    value={form.data.outcome}
                                    onChange={(e) =>
                                        form.setData('outcome', e.target.value)
                                    }
                                >
                                    <option value="full_delivery">
                                        Fully delivered
                                    </option>
                                    <option value="not_delivered">
                                        Not delivered (cancelled / returned to
                                        us)
                                    </option>
                                </select>
                            </label>
                            {isCod && form.data.outcome === 'full_delivery' && (
                                <Input
                                    label={`COD amount the courier collected (must be ৳${Math.round(order.total)})`}
                                    inputMode="decimal"
                                    value={form.data.verified_collected_amount}
                                    onChange={(e) =>
                                        form.setData(
                                            'verified_collected_amount',
                                            e.target.value,
                                        )
                                    }
                                    error={errors.verified_collected_amount}
                                />
                            )}
                        </>
                    ) : (
                        <label className="block text-xs font-semibold text-slate-700">
                            Failed attempt
                            <select
                                className={selectClass}
                                value={String(form.data.courier_submission_id)}
                                onChange={(e) => {
                                    const id = Number(e.target.value);
                                    const s =
                                        evidence.verifiable_submissions.find(
                                            (x) => x.id === id,
                                        );
                                    form.setData((d) => ({
                                        ...d,
                                        courier_submission_id: id,
                                        provider: s?.courier ?? d.provider,
                                    }));
                                }}
                            >
                                {evidence.verifiable_submissions.map((s) => (
                                    <option key={s.id} value={s.id}>
                                        #{s.id} — {s.courier}
                                        {s.resolution
                                            ? ` (${s.resolution})`
                                            : ''}
                                    </option>
                                ))}
                            </select>
                        </label>
                    )}

                    <label className="flex items-start gap-2 text-xs text-slate-700">
                        <input
                            type="checkbox"
                            checked={form.data.timeline_reviewed}
                            onChange={(e) =>
                                form.setData(
                                    'timeline_reviewed',
                                    e.target.checked,
                                )
                            }
                        />
                        I reviewed the courier's full status timeline and it
                        shows no partial delivery.
                    </label>
                    {errors.timeline_reviewed && (
                        <p className="text-xs text-red-600">
                            {errors.timeline_reviewed}
                        </p>
                    )}

                    <Input
                        label="What you verified (portal, date, details)"
                        value={form.data.note}
                        onChange={(e) => form.setData('note', e.target.value)}
                        error={errors.note}
                    />
                    <Input
                        type="password"
                        label="Your password"
                        autoComplete="current-password"
                        value={form.data.password}
                        onChange={(e) =>
                            form.setData('password', e.target.value)
                        }
                        error={errors.password}
                    />
                    <Button
                        type="submit"
                        className="w-full"
                        disabled={
                            form.processing ||
                            !evidence.readiness_current ||
                            !form.data.password ||
                            !form.data.timeline_reviewed
                        }
                    >
                        Record verification
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}
