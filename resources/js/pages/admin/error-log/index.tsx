import { Head, router, usePage } from '@inertiajs/react';
import {
    ChevronDown,
    ChevronRight,
    Download,
    Info,
    ScrollText,
    Trash2,
} from 'lucide-react';
import { Fragment, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { Pagination } from '@/components/ui/pagination';
import { cn } from '@/lib/utils';
import type { PaginatedData } from '@/types';

type Level =
    | 'emergency'
    | 'alert'
    | 'critical'
    | 'error'
    | 'warning'
    | 'notice'
    | 'info'
    | 'debug';

type LogEntry = {
    id: number;
    logged_at: string;
    environment: string;
    level: Level;
    message: string;
    exception: string | null;
    location: string | null;
    text: string;
};

type LogFile = { name: string; size: number; modified_at: number };

type Props = {
    files: LogFile[];
    file: string | null;
    level: Level | null;
    levelCounts: Record<Level, number>;
    entries: PaginatedData<LogEntry>;
    truncated: boolean;
    windowBytes: number;
};

const LEVELS: Level[] = [
    'emergency',
    'alert',
    'critical',
    'error',
    'warning',
    'notice',
    'info',
    'debug',
];

const LEVEL_VARIANT: Record<Level, 'error' | 'warning' | 'info' | 'secondary'> =
    {
        emergency: 'error',
        alert: 'error',
        critical: 'error',
        error: 'error',
        warning: 'warning',
        notice: 'info',
        info: 'info',
        debug: 'secondary',
    };

function formatBytes(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

function formatModified(seconds: number): string {
    return new Date(seconds * 1000).toLocaleString('en-BD', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

export default function ErrorLogIndex({
    files,
    file,
    level,
    levelCounts,
    entries,
    truncated,
    windowBytes,
}: Props) {
    const { props } = usePage<any>();
    const flash = props.flash as
        | { success?: string; error?: string }
        | undefined;
    const [expandedId, setExpandedId] = useState<number | null>(null);
    const [confirmingClear, setConfirmingClear] = useState(false);
    const [processing, setProcessing] = useState(false);

    const currentFile = files.find((candidate) => candidate.name === file);
    const totalShown = LEVELS.reduce(
        (sum, candidate) => sum + levelCounts[candidate],
        0,
    );

    const visit = (query: {
        file?: string | null;
        level?: Level | null;
        page?: number;
    }) => {
        setExpandedId(null);
        router.get(
            route('admin.error-log.index'),
            {
                file: query.file ?? file ?? undefined,
                level:
                    query.level === undefined
                        ? (level ?? undefined)
                        : (query.level ?? undefined),
                page: query.page,
            },
            { preserveState: true, replace: true },
        );
    };

    const handleClear = () =>
        router.delete(route('admin.error-log.clear'), {
            data: { confirmed: true, file },
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => {
                setProcessing(false);
                setConfirmingClear(false);
                setExpandedId(null);
            },
        });

    return (
        <>
            <Head title="Error Log — Admin" />

            <ConfirmDialog
                open={confirmingClear}
                onOpenChange={setConfirmingClear}
                title={`Clear ${file ?? 'this log'}?`}
                description={`This empties the whole file (${formatBytes(currentFile?.size ?? 0)}), including anything older than what this page shows. New log entries will keep being written to it. Download it first if you might need it. This cannot be undone.`}
                confirmLabel="Clear Log"
                onConfirm={handleClear}
                processing={processing}
            />

            <div className="space-y-5">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="flex items-center gap-2 text-xl font-bold text-slate-900">
                            <ScrollText className="size-5 text-slate-400" />
                            Error Log
                        </h1>
                        <p className="text-sm text-slate-600">
                            {currentFile
                                ? `${currentFile.name} · ${formatBytes(currentFile.size)} · last written ${formatModified(currentFile.modified_at)}`
                                : 'No log files yet'}
                        </p>
                    </div>
                    {file !== null && (
                        <div className="flex flex-wrap items-center gap-2">
                            {files.length > 1 && (
                                <select
                                    aria-label="Log file"
                                    value={file}
                                    onChange={(event) =>
                                        visit({
                                            file: event.target.value,
                                            level: null,
                                        })
                                    }
                                    className="h-9 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 focus:ring-1 focus:ring-[#c8102e] focus:outline-none"
                                >
                                    {files.map((candidate) => (
                                        <option
                                            key={candidate.name}
                                            value={candidate.name}
                                        >
                                            {candidate.name} (
                                            {formatBytes(candidate.size)})
                                        </option>
                                    ))}
                                </select>
                            )}
                            <Button variant="outline" size="sm" asChild>
                                <a
                                    href={route('admin.error-log.download', {
                                        file,
                                    })}
                                >
                                    <Download className="size-4" />
                                    Download
                                </a>
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setConfirmingClear(true)}
                                disabled={(currentFile?.size ?? 0) === 0}
                                className="border-red-200 text-red-600 hover:bg-red-50"
                            >
                                <Trash2 className="size-4" />
                                Clear Log
                            </Button>
                        </div>
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

                <div className="flex items-start gap-2 rounded-lg bg-slate-50 px-4 py-3 text-xs text-slate-600">
                    <Info className="mt-0.5 size-4 shrink-0 text-slate-400" />
                    <p>
                        Newest entries first. Click a row for the full message
                        and stack trace.{' '}
                        {truncated
                            ? `This file is large, so only its most recent ${formatBytes(windowBytes)} is shown here — download it to see everything.`
                            : 'The whole file is shown.'}{' '}
                        Only the main admin can see this page, because log
                        entries can contain customer details.
                    </p>
                </div>

                {totalShown > 0 && (
                    <div className="flex flex-wrap gap-2">
                        <button
                            type="button"
                            onClick={() => visit({ level: null })}
                            className={cn(
                                'rounded-full border px-3 py-1 text-xs font-medium transition',
                                level === null
                                    ? 'border-slate-800 bg-slate-800 text-white'
                                    : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50',
                            )}
                        >
                            All · {totalShown}
                        </button>
                        {LEVELS.filter(
                            (candidate) => levelCounts[candidate] > 0,
                        ).map((candidate) => (
                            <button
                                key={candidate}
                                type="button"
                                onClick={() => visit({ level: candidate })}
                                className={cn(
                                    'rounded-full border px-3 py-1 text-xs font-medium capitalize transition',
                                    level === candidate
                                        ? 'border-slate-800 bg-slate-800 text-white'
                                        : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50',
                                )}
                            >
                                {candidate} · {levelCounts[candidate]}
                            </button>
                        ))}
                    </div>
                )}

                <Card className="overflow-hidden p-0 shadow-sm">
                    {entries.data.length === 0 ? (
                        <div className="py-16 text-center">
                            <ScrollText className="mx-auto mb-3 size-10 text-slate-300" />
                            <p className="text-sm text-slate-600">
                                {file === null
                                    ? 'No log files yet.'
                                    : level !== null
                                      ? `No ${level} entries in this file.`
                                      : 'This log is empty.'}
                            </p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[44rem] text-sm">
                                <thead className="bg-slate-50 text-left text-xs text-slate-600">
                                    <tr>
                                        <th className="w-8 px-3 py-2" />
                                        <th className="px-3 py-2 font-medium">
                                            Time
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Level
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Message
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {entries.data.map((entry) => {
                                        const expanded =
                                            expandedId === entry.id;

                                        return (
                                            <Fragment key={entry.id}>
                                                <tr
                                                    className="cursor-pointer align-top hover:bg-slate-50/60"
                                                    onClick={() =>
                                                        setExpandedId(
                                                            expanded
                                                                ? null
                                                                : entry.id,
                                                        )
                                                    }
                                                >
                                                    <td className="px-3 py-2.5 text-slate-600">
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
                                                        {entry.logged_at}
                                                        <span className="block text-[11px] text-slate-600">
                                                            {entry.environment}
                                                        </span>
                                                    </td>
                                                    <td className="px-3 py-2.5">
                                                        <Badge
                                                            variant={
                                                                LEVEL_VARIANT[
                                                                    entry.level
                                                                ] ?? 'secondary'
                                                            }
                                                            size="sm"
                                                            className="uppercase"
                                                        >
                                                            {entry.level}
                                                        </Badge>
                                                    </td>
                                                    <td className="px-3 py-2.5">
                                                        <p className="font-medium break-words text-slate-800">
                                                            {entry.message ||
                                                                '(no message)'}
                                                        </p>
                                                        {entry.exception && (
                                                            <p className="mt-0.5 font-mono text-[11px] break-all text-slate-600">
                                                                {
                                                                    entry.exception
                                                                }
                                                                {entry.location &&
                                                                    ` · ${entry.location}`}
                                                            </p>
                                                        )}
                                                    </td>
                                                </tr>
                                                {expanded && (
                                                    <tr className="bg-slate-50/60">
                                                        <td
                                                            colSpan={4}
                                                            className="px-4 py-4"
                                                        >
                                                            <pre className="max-h-[28rem] overflow-auto rounded-lg bg-slate-900 p-3 text-[11px] leading-relaxed break-words whitespace-pre-wrap text-slate-100">
                                                                {entry.text}
                                                            </pre>
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

                    {entries.last_page > 1 && (
                        <div className="border-t border-slate-100 px-4 py-4">
                            <div className="flex flex-col items-center gap-2">
                                <Pagination
                                    data={entries}
                                    onPageChange={(page) => visit({ page })}
                                />
                                <p className="text-xs text-slate-600">
                                    Showing {entries.from}–{entries.to} of{' '}
                                    {entries.total}
                                </p>
                            </div>
                        </div>
                    )}
                </Card>
            </div>
        </>
    );
}
