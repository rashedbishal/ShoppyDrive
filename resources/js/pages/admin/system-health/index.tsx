import { Head, router } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    CircleSlash,
    HeartPulse,
    RefreshCw,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { cn } from '@/lib/utils';

type Status = 'ok' | 'warning' | 'failed' | 'crashed' | 'skipped';

type HealthCheck = {
    name: string;
    label: string;
    status: Status | string;
    summary: string;
    message: string;
};

/** What each check watches, in plain words (keyed by the check's name). */
const DESCRIPTIONS: Record<string, string> = {
    Database: 'The site can connect to its database.',
    DiskSpace: 'How full the server disk is. Warns above 70%, fails above 90%.',
    DebugMode:
        'Debug mode is off, so visitors never see technical error details.',
    Environment: 'The site is running in production mode.',
    Schedule:
        'Cron is running the scheduled tasks (emails, courier sync, clean-ups).',
    Queue: 'Background jobs such as order emails are being processed.',
    Cache: 'The cache can be written and read.',
    OptimizedApp:
        'The config, route and event caches built by each deploy are in place.',
    DatabaseSize: 'The database is below 1 GB.',
};

const STATUS_STYLES: Record<
    Status,
    { label: string; icon: typeof CheckCircle2; className: string }
> = {
    ok: {
        label: 'OK',
        icon: CheckCircle2,
        className: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    },
    warning: {
        label: 'Warning',
        icon: AlertTriangle,
        className: 'bg-amber-50 text-amber-700 ring-amber-200',
    },
    failed: {
        label: 'Failed',
        icon: XCircle,
        className: 'bg-red-50 text-red-700 ring-red-200',
    },
    crashed: {
        label: 'Could not run',
        icon: XCircle,
        className: 'bg-red-50 text-red-700 ring-red-200',
    },
    skipped: {
        label: 'Skipped',
        icon: CircleSlash,
        className: 'bg-slate-50 text-slate-600 ring-slate-200',
    },
};

function statusStyle(status: string) {
    return STATUS_STYLES[status as Status] ?? STATUS_STYLES.skipped;
}

function formatRanAt(iso: string): string {
    return new Date(iso).toLocaleString('en-BD', {
        dateStyle: 'medium',
        timeStyle: 'medium',
    });
}

export default function SystemHealthIndex({
    lastRanAt,
    checks,
}: {
    lastRanAt: string | null;
    checks: HealthCheck[];
}): React.JSX.Element {
    const [running, setRunning] = useState(false);
    const problems = checks.filter(
        (check) => check.status !== 'ok' && check.status !== 'skipped',
    ).length;

    const runNow = () =>
        router.post(
            route('admin.system-health.run'),
            {},
            {
                preserveScroll: true,
                onStart: () => setRunning(true),
                onFinish: () => setRunning(false),
            },
        );

    return (
        <>
            <Head title="System Health — Admin" />

            <div className="space-y-5">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="flex items-center gap-2 text-xl font-bold text-slate-900">
                            <HeartPulse className="size-5 text-slate-400" />
                            System Health
                        </h1>
                        <p className="text-sm text-slate-600">
                            {lastRanAt
                                ? `Checked automatically every minute · last run ${formatRanAt(lastRanAt)}`
                                : 'No checks have run yet. They run every minute, or run them now.'}
                        </p>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={runNow}
                        disabled={running}
                    >
                        <RefreshCw
                            className={cn('size-4', running && 'animate-spin')}
                        />
                        {running ? 'Running…' : 'Run checks now'}
                    </Button>
                </div>

                {checks.length > 0 && (
                    <div
                        className={cn(
                            'flex items-start gap-2 rounded-xl border p-3 text-sm',
                            problems === 0
                                ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                                : 'border-red-200 bg-red-50 text-red-800',
                        )}
                    >
                        {problems === 0 ? (
                            <CheckCircle2 className="mt-0.5 size-4 shrink-0" />
                        ) : (
                            <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                        )}
                        <p>
                            {problems === 0
                                ? 'Everything looks healthy.'
                                : `${problems} check${problems === 1 ? ' needs' : 's need'} attention. A failed check emails you at most once an hour.`}
                        </p>
                    </div>
                )}

                <Card className="overflow-hidden p-0 shadow-sm">
                    {checks.length === 0 ? (
                        <p className="p-6 text-sm text-slate-600">
                            No results yet.
                        </p>
                    ) : (
                        <ul className="divide-y divide-slate-100">
                            {checks.map((check) => {
                                const style = statusStyle(check.status);
                                const Icon = style.icon;

                                return (
                                    <li
                                        key={check.name}
                                        className="flex flex-col gap-2 p-4 sm:flex-row sm:items-start sm:justify-between"
                                    >
                                        <div className="min-w-0">
                                            <p className="text-sm font-semibold text-slate-900">
                                                {check.label}
                                                {check.summary && (
                                                    <span className="ml-2 font-normal text-slate-500">
                                                        {check.summary}
                                                    </span>
                                                )}
                                            </p>
                                            {DESCRIPTIONS[check.name] && (
                                                <p className="text-xs text-slate-500">
                                                    {DESCRIPTIONS[check.name]}
                                                </p>
                                            )}
                                            {check.status !== 'ok' &&
                                                check.message && (
                                                    <p className="mt-1 text-xs break-words text-red-700">
                                                        {check.message}
                                                    </p>
                                                )}
                                        </div>
                                        <span
                                            className={cn(
                                                'inline-flex shrink-0 items-center gap-1 self-start rounded-full px-2.5 py-1 text-xs font-semibold ring-1',
                                                style.className,
                                            )}
                                        >
                                            <Icon className="size-3.5" />
                                            {style.label}
                                        </span>
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                </Card>
            </div>
        </>
    );
}
