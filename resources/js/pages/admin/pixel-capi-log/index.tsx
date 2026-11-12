import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ChevronDown,
    ChevronRight,
    Info,
    Radar,
    Trash2,
    TriangleAlert,
} from 'lucide-react';
import { Fragment, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { Pagination } from '@/components/ui/pagination';
import type { PaginatedData } from '@/types';

type EventStatus = 'pending' | 'sending' | 'sent' | 'failed';

type LoggedEvent = {
    id: number;
    event_name: string;
    event_id: string;
    status: EventStatus;
    attempts: number;
    response_status: number | null;
    last_error: string | null;
    action_source: string;
    event_source_url: string | null;
    custom_data: Record<string, unknown> | null;
    client_ip_address: string | null;
    client_user_agent: string | null;
    fbp: string | null;
    fbc: string | null;
    order: { id: number; order_number: string } | null;
    customer: { id: number; name: string } | null;
    event_time: string;
    sent_at: string | null;
    created_at: string;
};

type Props = {
    events: PaginatedData<LoggedEvent>;
    statusCounts: Record<EventStatus, number>;
    conversionsApiAvailable: boolean;
    retentionDays: number;
    canDelete: boolean;
};

const STATUS_META: Record<
    EventStatus,
    { label: string; variant: 'success' | 'error' | 'warning' | 'info' }
> = {
    sent: { label: 'Success', variant: 'success' },
    failed: { label: 'Failed', variant: 'error' },
    pending: { label: 'Pending', variant: 'warning' },
    sending: { label: 'Sending', variant: 'info' },
};

const STATUS_ORDER: EventStatus[] = ['sent', 'failed', 'pending', 'sending'];

function formatDateTime(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString('en-BD', {
        dateStyle: 'medium',
        timeStyle: 'medium',
    });
}

function DetailRow({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div className="grid gap-0.5 sm:grid-cols-[10rem_1fr] sm:gap-3">
            <dt className="text-xs font-medium text-slate-500">{label}</dt>
            <dd className="text-xs break-all text-slate-700">
                {children ?? '—'}
            </dd>
        </div>
    );
}

function EventDetails({ event }: { event: LoggedEvent }) {
    return (
        <div className="grid gap-4 lg:grid-cols-2">
            <dl className="space-y-2">
                <DetailRow label="Event ID">{event.event_id}</DetailRow>
                <DetailRow label="Event time">
                    {formatDateTime(event.event_time)}
                </DetailRow>
                <DetailRow label="Recorded at">
                    {formatDateTime(event.created_at)}
                </DetailRow>
                <DetailRow label="Delivered at">
                    {formatDateTime(event.sent_at)}
                </DetailRow>
                <DetailRow label="Attempts">{event.attempts}</DetailRow>
                <DetailRow label="Meta response code">
                    {event.response_status ?? '—'}
                </DetailRow>
                {event.last_error && (
                    <DetailRow label="Error">
                        <span className="text-red-600">{event.last_error}</span>
                    </DetailRow>
                )}
                <DetailRow label="Action source">
                    {event.action_source}
                </DetailRow>
                <DetailRow label="Page URL">
                    {event.event_source_url ?? '—'}
                </DetailRow>
                <DetailRow label="IP address">
                    {event.client_ip_address ?? '—'}
                </DetailRow>
                <DetailRow label="User agent">
                    {event.client_user_agent ?? '—'}
                </DetailRow>
                <DetailRow label="fbp cookie">{event.fbp ?? '—'}</DetailRow>
                <DetailRow label="fbc cookie">{event.fbc ?? '—'}</DetailRow>
            </dl>
            <div>
                <p className="mb-1 text-xs font-medium text-slate-500">
                    Event data
                </p>
                <pre className="max-h-72 overflow-auto rounded-lg bg-slate-900 p-3 text-[11px] leading-relaxed text-slate-100">
                    {JSON.stringify(event.custom_data ?? {}, null, 2)}
                </pre>
                <p className="mt-1 text-[11px] text-slate-400">
                    Customer email, phone and name are added and hashed only at
                    the moment of sending, so they are never stored here.
                </p>
            </div>
        </div>
    );
}

export default function PixelCapiLogIndex({
    events,
    statusCounts,
    conversionsApiAvailable,
    retentionDays,
    canDelete,
}: Props) {
    const { props } = usePage<any>();
    const flash = props.flash as
        | { success?: string; error?: string }
        | undefined;
    const [expandedId, setExpandedId] = useState<number | null>(null);
    const [confirmingDelete, setConfirmingDelete] = useState(false);
    const [processing, setProcessing] = useState(false);

    const undelivered = statusCounts.pending + statusCounts.sending;

    const handlePageChange = (page: number) =>
        router.get(
            route('admin.pixel-capi-log.index'),
            { page },
            { preserveState: true, replace: true },
        );

    const handleDelete = () =>
        router.delete(route('admin.pixel-capi-log.destroy'), {
            data: { confirmed: true },
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => {
                setProcessing(false);
                setConfirmingDelete(false);
                setExpandedId(null);
            },
        });

    return (
        <>
            <Head title="Pixel & CAPI Log — Admin" />

            <ConfirmDialog
                open={confirmingDelete}
                onOpenChange={setConfirmingDelete}
                title="Delete the entire Pixel & CAPI log?"
                description={
                    `This permanently deletes all ${events.total} logged ${events.total === 1 ? 'event' : 'events'} from the database. This cannot be undone.` +
                    (undelivered > 0
                        ? ` ${undelivered} ${undelivered === 1 ? 'event has' : 'events have'} not been delivered to Facebook yet and will never be sent.`
                        : '')
                }
                confirmLabel="Delete All"
                onConfirm={handleDelete}
                processing={processing}
            />

            <div className="space-y-5">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="flex items-center gap-2 text-xl font-bold text-slate-900">
                            <Radar className="size-5 text-slate-400" />
                            Pixel &amp; CAPI Log
                        </h1>
                        <p className="text-sm text-slate-500">
                            {events.total} logged{' '}
                            {events.total === 1 ? 'event' : 'events'}
                        </p>
                    </div>
                    {canDelete && events.total > 0 && (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setConfirmingDelete(true)}
                            className="border-red-200 text-red-600 hover:bg-red-50"
                        >
                            <Trash2 className="size-4" />
                            Delete All Logs
                        </Button>
                    )}
                </div>

                {flash?.success && (
                    <div className="rounded-lg bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-700">
                        {flash.success}
                    </div>
                )}
                {flash?.error && (
                    <div
                        data-form-error
                        className="rounded-lg bg-red-50 px-4 py-2.5 text-sm font-medium text-red-700"
                    >
                        {flash.error}
                    </div>
                )}

                {!conversionsApiAvailable && (
                    <div className="flex items-start gap-2 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        <TriangleAlert className="mt-0.5 size-4 shrink-0" />
                        <p>
                            The Conversions API is switched off or missing its
                            Pixel ID or access token, so no new events are being
                            recorded or sent. Turn it on under{' '}
                            <Link
                                href={route('admin.settings.marketing.edit')}
                                className="font-medium underline"
                            >
                                Marketing &amp; Tracking
                            </Link>
                            .
                        </p>
                    </div>
                )}

                <div className="flex items-start gap-2 rounded-lg bg-slate-50 px-4 py-3 text-xs text-slate-600">
                    <Info className="mt-0.5 size-4 shrink-0 text-slate-400" />
                    <p>
                        Each row is a server-side (Conversions API) event and
                        shares its event ID with the matching browser Pixel
                        event, so Facebook counts them once. The status shows
                        whether the server delivered it to Facebook; browser
                        Pixel delivery goes straight from the visitor to
                        Facebook and can't be seen here. Entries older than{' '}
                        {retentionDays} days are deleted automatically every
                        night.
                    </p>
                </div>

                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    {STATUS_ORDER.map((status) => (
                        <Card key={status} className="p-4 shadow-sm">
                            <p className="text-xs text-slate-500">
                                {STATUS_META[status].label}
                            </p>
                            <p className="mt-1 text-xl font-bold text-slate-900">
                                {statusCounts[status]}
                            </p>
                        </Card>
                    ))}
                </div>

                <Card className="overflow-hidden p-0 shadow-sm">
                    {events.data.length === 0 ? (
                        <div className="py-16 text-center">
                            <Radar className="mx-auto mb-3 size-10 text-slate-300" />
                            <p className="text-sm text-slate-400">
                                No events logged yet.
                            </p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[44rem] text-sm">
                                <thead className="bg-slate-50 text-left text-xs text-slate-500">
                                    <tr>
                                        <th className="w-8 px-3 py-2" />
                                        <th className="px-3 py-2 font-medium">
                                            Time
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Event
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Status
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Order / Customer
                                        </th>
                                        <th className="px-3 py-2 text-right font-medium">
                                            Attempts
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {events.data.map((event) => {
                                        const expanded =
                                            expandedId === event.id;
                                        const status =
                                            STATUS_META[event.status] ??
                                            STATUS_META.pending;

                                        return (
                                            <Fragment key={event.id}>
                                                <tr
                                                    className="cursor-pointer hover:bg-slate-50/60"
                                                    onClick={() =>
                                                        setExpandedId(
                                                            expanded
                                                                ? null
                                                                : event.id,
                                                        )
                                                    }
                                                >
                                                    <td className="px-3 py-2.5 text-slate-400">
                                                        <button
                                                            type="button"
                                                            aria-expanded={
                                                                expanded
                                                            }
                                                            aria-label={
                                                                expanded
                                                                    ? 'Hide details'
                                                                    : 'Show details'
                                                            }
                                                            className="flex items-center"
                                                        >
                                                            {expanded ? (
                                                                <ChevronDown className="size-4" />
                                                            ) : (
                                                                <ChevronRight className="size-4" />
                                                            )}
                                                        </button>
                                                    </td>
                                                    <td className="px-3 py-2.5 whitespace-nowrap text-slate-600">
                                                        {formatDateTime(
                                                            event.event_time,
                                                        )}
                                                    </td>
                                                    <td className="px-3 py-2.5 font-medium text-slate-800">
                                                        {event.event_name}
                                                    </td>
                                                    <td className="px-3 py-2.5">
                                                        <Badge
                                                            variant={
                                                                status.variant
                                                            }
                                                            size="sm"
                                                        >
                                                            {status.label}
                                                        </Badge>
                                                    </td>
                                                    <td className="px-3 py-2.5 text-slate-600">
                                                        {event.order ? (
                                                            <Link
                                                                href={route(
                                                                    'admin.orders.show',
                                                                    event.order
                                                                        .id,
                                                                )}
                                                                className="font-medium text-slate-800 hover:underline"
                                                                onClick={(e) =>
                                                                    e.stopPropagation()
                                                                }
                                                            >
                                                                {
                                                                    event.order
                                                                        .order_number
                                                                }
                                                            </Link>
                                                        ) : null}
                                                        {event.order &&
                                                            event.customer &&
                                                            ' · '}
                                                        {event.customer?.name}
                                                        {!event.order &&
                                                            !event.customer &&
                                                            'Guest'}
                                                    </td>
                                                    <td className="px-3 py-2.5 text-right text-slate-600">
                                                        {event.attempts}
                                                    </td>
                                                </tr>
                                                {expanded && (
                                                    <tr className="bg-slate-50/60">
                                                        <td
                                                            colSpan={6}
                                                            className="px-4 py-4"
                                                        >
                                                            <EventDetails
                                                                event={event}
                                                            />
                                                        </td>
                                                    </tr>
                                                )}
                                            </Fragment>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {events.last_page > 1 && (
                        <div className="border-t border-slate-100 px-4 py-4">
                            <div className="flex flex-col items-center gap-2">
                                <Pagination
                                    data={events}
                                    onPageChange={handlePageChange}
                                />
                                <p className="text-xs text-slate-400">
                                    Showing {events.from}–{events.to} of{' '}
                                    {events.total}
                                </p>
                            </div>
                        </div>
                    )}
                </Card>
            </div>
        </>
    );
}
