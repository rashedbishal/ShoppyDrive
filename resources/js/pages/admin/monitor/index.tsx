import { Head, Link, router, usePage } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import {
    Activity,
    CircleCheck,
    CircleHelp,
    PauseCircle,
    ShieldAlert,
    TriangleAlert,
} from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dialogOverlayMotion, dialogPanel } from '@/lib/dialog-motion';

type StallEvent = {
    stalled_at: number;
    recovered_at: number | null;
};

type Monitor = {
    status: 'ok' | 'stalled' | 'unknown';
    threshold_seconds: number;
    last_drained_seconds_ago: number | null;
    oldest_pending_job_seconds: number | null;
    pending_jobs: number;
    parked_jobs: number;
    failed_jobs: number;
    alert_active_since: number | null;
    alert_email_configured: boolean;
    external_ping_configured: boolean;
    events: StallEvent[];
};

/** A job on a queue the worker never reads, e.g. `held-backlog`. */
type ParkedJob = {
    id: number;
    queue: string;
    name: string;
    attempts: number;
    created_at: number;
    /** e.g. "Order ORD-1234 · to r***@gmail.com"; mother admin only. */
    summary: string | null;
};

type Props = {
    monitor: Monitor;
    parkedJobs: ParkedJob[];
    canDeleteParkedJobs: boolean;
};

const formatDuration = (seconds: number): string => {
    if (seconds < 60) {
        return 'less than a minute';
    }

    const minutes = Math.round(seconds / 60);

    if (minutes < 60) {
        return `${minutes} minute${minutes === 1 ? '' : 's'}`;
    }

    const hours = Math.round(minutes / 60);

    return hours < 48
        ? `${hours} hour${hours === 1 ? '' : 's'}`
        : `${Math.round(hours / 24)} days`;
};

const formatTime = (unixSeconds: number): string =>
    new Date(unixSeconds * 1000).toLocaleString();

const STATUS = {
    ok: {
        title: 'Everything is running',
        icon: CircleCheck,
        classes: 'border-emerald-200 bg-emerald-50 text-emerald-900',
        iconClasses: 'text-emerald-600',
    },
    stalled: {
        title: 'Background tasks have stopped',
        icon: TriangleAlert,
        classes: 'border-red-200 bg-red-50 text-red-900',
        iconClasses: 'text-red-600',
    },
    unknown: {
        title: 'No information yet',
        icon: CircleHelp,
        classes: 'border-amber-200 bg-amber-50 text-amber-900',
        iconClasses: 'text-amber-600',
    },
} as const;

function Stat({
    label,
    value,
    hint,
}: {
    label: string;
    value: string;
    hint?: string;
}) {
    return (
        <Card className="shadow-sm">
            <CardContent className="space-y-1 pt-4">
                <p className="text-xs font-semibold tracking-wide text-slate-600 uppercase">
                    {label}
                </p>
                <p className="text-lg font-bold text-slate-900">{value}</p>
                {hint && <p className="text-xs text-slate-600">{hint}</p>}
            </CardContent>
        </Card>
    );
}

function DeleteParkedJobsModal({
    jobs,
    onClose,
}: {
    jobs: ParkedJob[];
    onClose: () => void;
}) {
    const { props } = usePage<{ errors?: Record<string, string> }>();
    const errors = props.errors ?? {};
    const [password, setPassword] = useState('');
    const [processing, setProcessing] = useState(false);

    const handleConfirm = () => {
        setProcessing(true);
        router.delete(route('admin.monitor.parked-jobs.destroy'), {
            // Exactly the jobs shown: one parked after this page loaded is
            // never deleted without being seen first.
            data: { password, ids: jobs.map((job) => job.id) },
            preserveScroll: true,
            onSuccess: () => onClose(),
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <motion.div
                className="absolute inset-0 bg-black/40"
                onClick={onClose}
                {...dialogOverlayMotion}
            />
            <motion.div
                variants={dialogPanel}
                initial="hidden"
                animate="visible"
                exit="exit"
                className="relative z-10 w-full max-w-md rounded-xl bg-white p-6 shadow-2xl"
            >
                <div className="mb-4 flex items-start gap-3">
                    <div className="flex size-10 shrink-0 items-center justify-center rounded-full bg-red-100">
                        <ShieldAlert className="size-5 text-red-600" />
                    </div>
                    <div>
                        <h2 className="text-base font-semibold text-slate-900">
                            Delete {jobs.length} parked job
                            {jobs.length === 1 ? '' : 's'}?
                        </h2>
                        <p className="mt-0.5 text-sm text-slate-600">
                            They will never be sent: whatever they were going to
                            do (for example an email) is dropped for good. This
                            cannot be undone. Enter your password to confirm.
                        </p>
                    </div>
                </div>

                <div className="mb-4">
                    <label className="mb-1 block text-sm font-medium text-slate-700">
                        Your Password
                    </label>
                    <input
                        maxLength={255}
                        type="password"
                        autoFocus
                        value={password}
                        onChange={(e) => setPassword(e.target.value)}
                        onKeyDown={(e) =>
                            e.key === 'Enter' &&
                            password &&
                            !processing &&
                            handleConfirm()
                        }
                        placeholder="Enter your admin password"
                        className="h-9 w-full rounded-lg border border-slate-200 px-3 text-sm focus:border-red-400 focus:ring-1 focus:ring-red-400 focus:outline-none"
                    />
                    {(errors.password || errors.ids) && (
                        <p className="mt-1 text-xs font-medium text-red-600">
                            {errors.password ?? errors.ids}
                        </p>
                    )}
                </div>

                <div className="flex justify-end gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={onClose}
                        disabled={processing}
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        disabled={!password || processing}
                        onClick={handleConfirm}
                        className="bg-red-600 text-white hover:bg-red-700"
                    >
                        {processing ? 'Deleting…' : 'Delete parked jobs'}
                    </Button>
                </div>
            </motion.div>
        </div>
    );
}

export default function SystemMonitor({
    monitor,
    parkedJobs,
    canDeleteParkedJobs,
}: Props) {
    const { props } = usePage<{ flash?: { success?: string } }>();
    const [confirmDelete, setConfirmDelete] = useState(false);
    const [refreshing, setRefreshing] = useState(false);
    const status = STATUS[monitor.status];
    const StatusIcon = status.icon;
    const thresholdMinutes = Math.round(monitor.threshold_seconds / 60);
    const oldestJobIsOld =
        (monitor.oldest_pending_job_seconds ?? 0) > monitor.threshold_seconds;

    const refresh = () =>
        router.reload({
            onStart: () => setRefreshing(true),
            onFinish: () => setRefreshing(false),
        });

    return (
        <>
            <Head title="System Monitor — Admin" />
            <div className="space-y-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="flex items-start gap-3">
                        <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand/10 text-brand">
                            <Activity className="size-5" />
                        </span>
                        <div>
                            <h1 className="text-xl font-bold text-slate-900">
                                System Monitor
                            </h1>
                            <p className="text-sm text-slate-600">
                                A scheduled task (cron) runs every minute. It
                                sends customer order emails and updates courier
                                status. This page shows whether it is still
                                running.
                            </p>
                        </div>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={refresh}
                        disabled={refreshing}
                    >
                        {refreshing ? 'Refreshing…' : 'Refresh'}
                    </Button>
                </div>

                <div
                    className={`flex items-start gap-3 rounded-xl border px-4 py-3 ${status.classes}`}
                >
                    <StatusIcon
                        className={`mt-0.5 size-5 shrink-0 ${status.iconClasses}`}
                    />
                    <div className="text-sm">
                        <p className="font-semibold">{status.title}</p>
                        {monitor.status === 'ok' && (
                            <p>
                                The scheduled task last ran{' '}
                                {formatDuration(
                                    monitor.last_drained_seconds_ago ?? 0,
                                )}{' '}
                                ago.
                            </p>
                        )}
                        {monitor.status === 'stalled' && (
                            <p>
                                The scheduled task last ran{' '}
                                {formatDuration(
                                    monitor.last_drained_seconds_ago ?? 0,
                                )}{' '}
                                ago. Customer order emails and courier updates
                                are not being sent. Check the Cron Jobs page in
                                your Hostinger hPanel.
                            </p>
                        )}
                        {monitor.status === 'unknown' && (
                            <p>
                                The scheduled task has not reported in since the
                                site was last updated. If this stays for more
                                than a few minutes, check the Cron Jobs page in
                                your Hostinger hPanel.
                            </p>
                        )}
                    </div>
                </div>

                {props.flash?.success && (
                    <div className="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">
                        {props.flash.success}
                    </div>
                )}

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                    <Stat
                        label="Scheduled task last ran"
                        value={
                            monitor.last_drained_seconds_ago === null
                                ? 'Unknown'
                                : `${formatDuration(monitor.last_drained_seconds_ago)} ago`
                        }
                        hint={`Flagged after ${thresholdMinutes} minutes of silence`}
                    />
                    <Stat
                        label="Jobs waiting"
                        value={String(monitor.pending_jobs)}
                        hint={
                            monitor.oldest_pending_job_seconds === null
                                ? 'Nothing is waiting'
                                : `Oldest has waited ${formatDuration(monitor.oldest_pending_job_seconds)}${oldestJobIsOld ? ' (too long)' : ''}`
                        }
                    />
                    <Stat
                        label="Parked jobs"
                        value={String(monitor.parked_jobs)}
                        hint={
                            monitor.parked_jobs === 0
                                ? 'None set aside'
                                : 'Set aside on purpose; never run on their own'
                        }
                    />
                    <Card className="shadow-sm">
                        <CardContent className="space-y-1 pt-4">
                            <p className="text-xs font-semibold tracking-wide text-slate-600 uppercase">
                                Failed jobs
                            </p>
                            <p
                                className={`text-lg font-bold ${monitor.failed_jobs > 0 ? 'text-red-600' : 'text-slate-900'}`}
                            >
                                {monitor.failed_jobs}
                            </p>
                            <Link
                                href="/admin/failed-jobs"
                                className="text-xs font-medium text-brand hover:underline"
                            >
                                Open Failed Jobs
                            </Link>
                        </CardContent>
                    </Card>
                    <Stat
                        label="Email alert"
                        value={
                            monitor.alert_email_configured
                                ? 'Active'
                                : 'No admin email'
                        }
                        hint={
                            monitor.alert_email_configured
                                ? 'Sent to the main admin when cron stops'
                                : 'No main admin email is configured'
                        }
                    />
                </div>

                {monitor.alert_active_since !== null && (
                    <p className="text-sm text-slate-700">
                        An alert email was sent at{' '}
                        {formatTime(monitor.alert_active_since)}.
                    </p>
                )}

                {parkedJobs.length > 0 && (
                    <Card className="shadow-sm">
                        <CardHeader className="flex flex-row flex-wrap items-start justify-between gap-3 border-b border-slate-100">
                            <div className="min-w-0">
                                <CardTitle className="flex items-center gap-2 text-sm font-semibold text-slate-900">
                                    <PauseCircle className="size-4 text-amber-600" />
                                    Parked jobs
                                </CardTitle>
                                <p className="mt-1 text-xs text-slate-600">
                                    These jobs were set aside on a separate
                                    queue, so the scheduled task never runs
                                    them. They are harmless where they are.
                                    Delete them if what they would do (such as
                                    an old email) is no longer wanted.
                                </p>
                            </div>
                            {canDeleteParkedJobs && (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={() => setConfirmDelete(true)}
                                    className="border-red-200 text-red-600 hover:bg-red-50 hover:text-red-700"
                                >
                                    Delete parked jobs
                                </Button>
                            )}
                        </CardHeader>
                        <CardContent className="overflow-x-auto pt-3">
                            <table className="w-full min-w-[520px] text-left text-sm">
                                <thead>
                                    <tr className="text-xs text-slate-500">
                                        <th className="py-2 pr-3 font-medium">
                                            #
                                        </th>
                                        <th className="py-2 pr-3 font-medium">
                                            Job
                                        </th>
                                        <th className="py-2 pr-3 font-medium">
                                            Queue
                                        </th>
                                        <th className="py-2 pr-3 font-medium">
                                            Added
                                        </th>
                                        <th className="py-2 font-medium">
                                            Tries
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {parkedJobs.map((job) => (
                                        <tr key={job.id}>
                                            <td className="py-2 pr-3 font-mono text-xs text-slate-500">
                                                {job.id}
                                            </td>
                                            <td className="py-2 pr-3">
                                                <span className="block font-medium text-slate-800">
                                                    {job.name}
                                                </span>
                                                {job.summary && (
                                                    <span className="block text-xs text-slate-500">
                                                        {job.summary}
                                                    </span>
                                                )}
                                            </td>
                                            <td className="py-2 pr-3">
                                                <span className="rounded-full bg-amber-50 px-2 py-0.5 font-mono text-xs text-amber-800">
                                                    {job.queue}
                                                </span>
                                            </td>
                                            <td className="py-2 pr-3 text-slate-600">
                                                {formatTime(job.created_at)}
                                            </td>
                                            <td className="py-2 text-slate-600">
                                                {job.attempts}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                            {monitor.parked_jobs > parkedJobs.length && (
                                <p className="mt-2 text-xs text-slate-500">
                                    Showing the first {parkedJobs.length} of{' '}
                                    {monitor.parked_jobs}.
                                </p>
                            )}
                        </CardContent>
                    </Card>
                )}

                <Card className="shadow-sm">
                    <CardHeader className="border-b border-slate-100">
                        <CardTitle className="text-sm font-semibold text-slate-900">
                            Recent stoppages
                        </CardTitle>
                        <p className="text-xs text-slate-600">
                            Only stoppages that were announced by email are
                            listed. This list restarts whenever the site is
                            updated.
                        </p>
                    </CardHeader>
                    <CardContent className="pt-3">
                        {monitor.events.length === 0 ? (
                            <p className="text-sm text-slate-600">
                                No stoppages recorded.
                            </p>
                        ) : (
                            <ul className="divide-y divide-slate-100 text-sm">
                                {monitor.events.map((event) => (
                                    <li
                                        key={event.stalled_at}
                                        className="flex flex-wrap items-center justify-between gap-2 py-2"
                                    >
                                        <span className="text-slate-800">
                                            Alerted{' '}
                                            {formatTime(event.stalled_at)}
                                        </span>
                                        <span
                                            className={
                                                event.recovered_at === null
                                                    ? 'font-medium text-red-600'
                                                    : 'text-emerald-700'
                                            }
                                        >
                                            {event.recovered_at === null
                                                ? 'Still stopped'
                                                : `Back at ${formatTime(event.recovered_at)}`}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>

                <p className="text-xs text-slate-600">
                    The email alert is triggered by customers visiting the shop,
                    so during a quiet period with no visitors it can arrive
                    late.{' '}
                    {monitor.external_ping_configured
                        ? 'An outside monitoring service is also configured under Security.'
                        : 'You can also add an outside monitoring service under Security for night-time coverage.'}
                </p>
            </div>

            <AnimatePresence>
                {confirmDelete && (
                    <DeleteParkedJobsModal
                        jobs={parkedJobs}
                        onClose={() => setConfirmDelete(false)}
                    />
                )}
            </AnimatePresence>
        </>
    );
}
