import { useForm } from '@inertiajs/react';
import { AlertTriangle, Loader2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';

export type CourierSubmission = {
    id: number;
    courier: 'pathao' | 'steadfast';
    courier_label: string;
    state: 'submitting' | 'sent' | 'failed' | 'unknown';
    reference: string;
    failure_kind: string | null;
    error_message: string | null;
    returned_consignment_id: string | null;
    returned_tracking_code: string | null;
    returned_at: string | null;
    claimed_at: string | null;
    finished_at: string | null;
    resolution: string | null;
    resolved_by: string | null;
    resolved_at: string | null;
    can_record_consignment: boolean;
};

type Props = {
    orderId: number;
    submission: CourierSubmission;
};

/**
 * Shown while a courier booking is unresolved (COURIER-SEND-RACE-001):
 * either still in flight, or its outcome is unknown and an admin has to
 * check the courier's portal and reconcile it before anything else can
 * happen to the order.
 */
export default function CourierSubmissionPanel({ orderId, submission }: Props) {
    const label = submission.courier_label;

    if (submission.state === 'submitting') {
        return (
            <div className="space-y-2 text-sm text-slate-700">
                <p className="flex items-center gap-2 font-medium">
                    <Loader2 className="size-4 animate-spin" />
                    Sending to {label}…
                </p>
                <p className="text-xs text-slate-600">
                    Started{' '}
                    {submission.claimed_at
                        ? new Date(submission.claimed_at).toLocaleString()
                        : 'just now'}
                    . The order can't be sent again, cancelled or refunded until
                    this finishes. Reload the page to see the result.
                </p>
                <ReturnedConsignment submission={submission} />
            </div>
        );
    }

    return (
        <div className="space-y-4">
            <div className="space-y-1 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                <p className="flex items-center gap-1.5 font-semibold">
                    <AlertTriangle className="size-4" /> {label} booking outcome
                    unknown
                </p>
                <p className="text-xs">
                    {label} didn't confirm whether it created a consignment for
                    invoice{' '}
                    <span className="font-mono">{submission.reference}</span>.
                    Check the {label} portal, then record what you found below.
                    Until then the order can't be sent again, cancelled or
                    refunded.
                </p>
                {submission.error_message && (
                    <p className="text-xs break-words whitespace-pre-line text-amber-800/80">
                        {submission.error_message}
                    </p>
                )}
            </div>

            <ReturnedConsignment submission={submission} />

            {submission.returned_consignment_id && (
                <ConfirmReturnedConsignmentForm
                    orderId={orderId}
                    submission={submission}
                />
            )}

            {submission.can_record_consignment ? (
                <RecordConsignmentForm
                    orderId={orderId}
                    submission={submission}
                />
            ) : (
                <p className="text-xs text-slate-600">
                    Entering a {label} consignment ID by hand isn't available
                    yet.
                    {submission.returned_consignment_id
                        ? ` Link the ID ${label} returned above, or confirm below that no parcel was created.`
                        : ` If ${label} did create the parcel, leave this unresolved and contact support; if it didn't, confirm below.`}
                </p>
            )}

            <ConfirmNotCreatedForm orderId={orderId} submission={submission} />
        </div>
    );
}

function RecordConsignmentForm({ orderId, submission }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        consignment_id: '',
        tracking_code: '',
        note: '',
        verified_in_portal: false,
    });

    return (
        <form
            className="space-y-2 rounded-lg border border-slate-200 p-3"
            onSubmit={(e) => {
                e.preventDefault();
                post(
                    route('admin.orders.courier.recordConsignment', [
                        orderId,
                        submission.id,
                    ]),
                    { preserveScroll: true },
                );
            }}
        >
            <p className="text-xs font-semibold text-slate-700">
                Found it in the {submission.courier_label} portal
            </p>
            <Input
                maxLength={100}
                showCount
                label="Consignment ID"
                value={data.consignment_id}
                onChange={(e) => setData('consignment_id', e.target.value)}
                error={errors.consignment_id}
            />
            <Input
                maxLength={100}
                showCount
                label="Tracking code (optional)"
                value={data.tracking_code}
                onChange={(e) => setData('tracking_code', e.target.value)}
                error={errors.tracking_code}
            />
            <Textarea
                maxLength={1000}
                showCount
                label="Note"
                rows={2}
                placeholder="What you checked in the portal"
                value={data.note}
                onChange={(e) => setData('note', e.target.value)}
                error={errors.note}
            />
            <label className="flex items-start gap-2 text-xs text-slate-600">
                <input
                    type="checkbox"
                    className="mt-0.5"
                    checked={data.verified_in_portal}
                    onChange={(e) =>
                        setData('verified_in_portal', e.target.checked)
                    }
                />
                I found this consignment for invoice {submission.reference} in
                the {submission.courier_label} portal.
            </label>
            {errors.verified_in_portal && (
                <p className="text-xs text-red-600">
                    {errors.verified_in_portal}
                </p>
            )}
            <Button type="submit" className="w-full" disabled={processing}>
                Record consignment
            </Button>
        </form>
    );
}

function ConfirmNotCreatedForm({ orderId, submission }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        note: '',
        confirmed: false,
    });

    return (
        <form
            className="space-y-2 rounded-lg border border-slate-200 p-3"
            onSubmit={(e) => {
                e.preventDefault();
                post(
                    route('admin.orders.courier.confirmNotCreated', [
                        orderId,
                        submission.id,
                    ]),
                    { preserveScroll: true },
                );
            }}
        >
            <p className="text-xs font-semibold text-slate-700">
                Not in the {submission.courier_label} portal
            </p>
            <Textarea
                maxLength={1000}
                showCount
                label="Note"
                rows={2}
                placeholder="What you checked in the portal"
                value={data.note}
                onChange={(e) => setData('note', e.target.value)}
                error={errors.note}
            />
            <label className="flex items-start gap-2 text-xs text-slate-600">
                <input
                    type="checkbox"
                    className="mt-0.5"
                    checked={data.confirmed}
                    onChange={(e) => setData('confirmed', e.target.checked)}
                />
                No consignment exists for invoice {submission.reference} in the{' '}
                {submission.courier_label} portal. The order may be sent again.
            </label>
            {errors.confirmed && (
                <p className="text-xs text-red-600">{errors.confirmed}</p>
            )}
            <Button
                type="submit"
                variant="outline"
                className="w-full"
                disabled={processing}
            >
                Confirm not created
            </Button>
        </form>
    );
}

/**
 * COURIER-SEND-RACE-002 (H1): the consignment ID the courier itself returned
 * for this attempt, kept even when recording it on the order failed.
 */
function ReturnedConsignment({
    submission,
}: {
    submission: CourierSubmission;
}) {
    if (!submission.returned_consignment_id) {
        return null;
    }

    return (
        <div className="rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs text-slate-700">
            <p className="font-semibold">
                {submission.courier_label} returned a consignment for this
                attempt
            </p>
            <p>
                Consignment ID:{' '}
                <span className="font-mono">
                    {submission.returned_consignment_id}
                </span>
                {submission.returned_tracking_code && (
                    <>
                        {' '}
                        · Tracking:{' '}
                        <span className="font-mono">
                            {submission.returned_tracking_code}
                        </span>
                    </>
                )}
            </p>
        </div>
    );
}

function ConfirmReturnedConsignmentForm({ orderId, submission }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        note: '',
        confirmed: false,
    });

    return (
        <form
            className="space-y-2 rounded-lg border border-slate-200 p-3"
            onSubmit={(e) => {
                e.preventDefault();
                post(
                    route('admin.orders.courier.confirmReturnedConsignment', [
                        orderId,
                        submission.id,
                    ]),
                    { preserveScroll: true },
                );
            }}
        >
            <p className="text-xs font-semibold text-slate-700">
                Link the returned consignment
            </p>
            <Textarea
                maxLength={1000}
                showCount
                label="Note"
                rows={2}
                placeholder="Why this consignment belongs to this order"
                value={data.note}
                onChange={(e) => setData('note', e.target.value)}
                error={errors.note}
            />
            <label className="flex items-start gap-2 text-xs text-slate-600">
                <input
                    type="checkbox"
                    className="mt-0.5"
                    checked={data.confirmed}
                    onChange={(e) => setData('confirmed', e.target.checked)}
                />
                Link this order to consignment{' '}
                {submission.returned_consignment_id}, which{' '}
                {submission.courier_label} returned for this send attempt.
            </label>
            {errors.confirmed && (
                <p className="text-xs text-red-600">{errors.confirmed}</p>
            )}
            <Button type="submit" className="w-full" disabled={processing}>
                Link returned consignment
            </Button>
        </form>
    );
}
