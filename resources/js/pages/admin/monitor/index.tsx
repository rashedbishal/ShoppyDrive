import { Head, Link, router } from '@inertiajs/react';
import { Activity, CircleCheck, CircleHelp, TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

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
    failed_jobs: number;
    alert_active_since: number | null;
    alert_email_configured: boolean;
    external_ping_configured: boolean;
    events: StallEvent[];
};

type Props = {
    monitor: Monitor;
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

export default function SystemMonitor({ monitor }: Props) {
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
                        <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-[#c8102e]/10 text-[#c8102e]">
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

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
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
                                className="text-xs font-medium text-[#c8102e] hover:underline"
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
        </>
    );
}
