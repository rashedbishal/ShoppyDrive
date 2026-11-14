import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    DatabaseBackup,
    Download,
    Upload,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';

type TableReport = {
    table: string;
    added: number;
    updated: number;
    skipped: number;
    conflicts: number;
};

type RestoreReport = {
    ok: boolean;
    committed: boolean;
    rows: number;
    tables: TableReport[];
    errors: string[];
    error_count: number;
    warnings: string[];
};

type Props = {
    csrfToken: string;
    maxUploadMegabytes: number;
    pendingReport: RestoreReport | null;
    restoreResult: RestoreReport | null;
};

const inputClass =
    'w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-1 focus:ring-brand focus:outline-none';

function ReportTable({ report }: { report: RestoreReport }) {
    const totals = report.tables.reduce(
        (sum, table) => ({
            added: sum.added + table.added,
            updated: sum.updated + table.updated,
            skipped: sum.skipped + table.skipped,
            conflicts: sum.conflicts + table.conflicts,
        }),
        { added: 0, updated: 0, skipped: 0, conflicts: 0 },
    );

    const stats = (row: {
        added: number;
        updated: number;
        skipped: number;
        conflicts: number;
    }) => [
        ['New', row.added],
        ['Updated', row.updated],
        ['Same', row.skipped],
        ['Conflicts', row.conflicts],
    ];

    return (
        <>
            {/* Phone: one small card per table instead of a 5-column grid. */}
            <div className="space-y-2 sm:hidden">
                {report.tables.map((table) => (
                    <div
                        key={table.table}
                        className="rounded-lg border border-slate-200 p-3"
                    >
                        <p className="font-mono text-xs break-all text-slate-700">
                            {table.table}
                        </p>
                        <dl className="mt-2 grid grid-cols-4 gap-2 text-center">
                            {stats(table).map(([label, value]) => (
                                <div key={label}>
                                    <dd className="text-sm font-semibold text-slate-800">
                                        {value}
                                    </dd>
                                    <dt className="text-[10px] text-slate-600">
                                        {label}
                                    </dt>
                                </div>
                            ))}
                        </dl>
                    </div>
                ))}
                <div className="rounded-lg border border-slate-200 bg-slate-50 p-3">
                    <p className="text-xs font-semibold text-slate-700">
                        Total
                    </p>
                    <dl className="mt-2 grid grid-cols-4 gap-2 text-center">
                        {stats(totals).map(([label, value]) => (
                            <div key={label}>
                                <dd className="text-sm font-semibold text-slate-800">
                                    {value}
                                </dd>
                                <dt className="text-[10px] text-slate-600">
                                    {label}
                                </dt>
                            </div>
                        ))}
                    </dl>
                </div>
            </div>

            <div className="hidden overflow-x-auto rounded-lg border border-slate-200 sm:block">
                <table className="w-full text-sm">
                    <thead className="bg-slate-50 text-left text-xs text-slate-600">
                        <tr>
                            <th className="px-3 py-2 font-medium">Table</th>
                            <th className="px-3 py-2 text-right font-medium">
                                New
                            </th>
                            <th className="px-3 py-2 text-right font-medium">
                                Updated
                            </th>
                            <th className="px-3 py-2 text-right font-medium">
                                Already same (skipped)
                            </th>
                            <th className="px-3 py-2 text-right font-medium">
                                Conflicts (skipped)
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {report.tables.map((table) => (
                            <tr
                                key={table.table}
                                className="border-t border-slate-100"
                            >
                                <td className="px-3 py-1.5 font-mono text-xs text-slate-700">
                                    {table.table}
                                </td>
                                <td className="px-3 py-1.5 text-right">
                                    {table.added}
                                </td>
                                <td className="px-3 py-1.5 text-right">
                                    {table.updated}
                                </td>
                                <td className="px-3 py-1.5 text-right">
                                    {table.skipped}
                                </td>
                                <td className="px-3 py-1.5 text-right">
                                    {table.conflicts}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                    <tfoot>
                        <tr className="border-t border-slate-200 bg-slate-50 font-semibold">
                            <td className="px-3 py-2">Total</td>
                            <td className="px-3 py-2 text-right">
                                {totals.added}
                            </td>
                            <td className="px-3 py-2 text-right">
                                {totals.updated}
                            </td>
                            <td className="px-3 py-2 text-right">
                                {totals.skipped}
                            </td>
                            <td className="px-3 py-2 text-right">
                                {totals.conflicts}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </>
    );
}

function Messages({
    title,
    messages,
    total,
    tone,
}: {
    title: string;
    messages: string[];
    total: number;
    tone: 'error' | 'warning';
}) {
    if (messages.length === 0) {
        return null;
    }

    const classes =
        tone === 'error'
            ? 'border-red-200 bg-red-50 text-red-800'
            : 'border-amber-200 bg-amber-50 text-amber-800';

    return (
        <div className={`rounded-lg border p-3 text-sm ${classes}`}>
            <p className="mb-1 font-semibold">{title}</p>
            <ul className="list-disc space-y-0.5 pl-5">
                {messages.map((message) => (
                    <li key={message}>{message}</li>
                ))}
            </ul>
            {total > messages.length && (
                <p className="mt-1 font-medium">
                    And {total - messages.length} more.
                </p>
            )}
        </div>
    );
}

function PendingRestore({ report }: { report: RestoreReport }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        password: '',
    });

    const applyRestore = (event: FormEvent) => {
        event.preventDefault();
        post(route('admin.database-backup.apply'), {
            preserveScroll: true,
            onFinish: () => reset('password'),
        });
    };

    const discard = () => {
        router.delete(route('admin.database-backup.discard'), {
            preserveScroll: true,
        });
    };

    return (
        <div className="space-y-3">
            <div
                className={`flex items-start gap-2 rounded-lg border p-3 text-sm ${
                    report.ok
                        ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                        : 'border-red-200 bg-red-50 text-red-800'
                }`}
            >
                {report.ok ? (
                    <CheckCircle2 className="mt-0.5 size-4 shrink-0" />
                ) : (
                    <XCircle className="mt-0.5 size-4 shrink-0" />
                )}
                <p>
                    {report.ok
                        ? 'Check passed. Nothing has been changed yet. Review the numbers below, then confirm.'
                        : 'This file cannot be restored. Nothing was changed.'}
                </p>
            </div>

            <Messages
                title="Problems"
                messages={report.errors}
                total={report.error_count}
                tone="error"
            />
            <Messages
                title="Worth knowing"
                messages={report.warnings}
                total={report.warnings.length}
                tone="warning"
            />

            {report.tables.length > 0 && <ReportTable report={report} />}

            {report.ok ? (
                <form onSubmit={applyRestore} className="space-y-2">
                    <label
                        htmlFor="restore-password"
                        className="block text-sm font-medium text-slate-700"
                    >
                        Enter your password to restore
                    </label>
                    <input
                        maxLength={255}
                        id="restore-password"
                        type="password"
                        autoComplete="current-password"
                        className={inputClass}
                        value={data.password}
                        onChange={(event) =>
                            setData('password', event.target.value)
                        }
                    />
                    {errors.password && (
                        <p className="text-xs text-red-500">
                            {errors.password}
                        </p>
                    )}
                    <div className="flex gap-2">
                        <Button
                            type="submit"
                            disabled={!data.password || processing}
                        >
                            {processing ? 'Restoring…' : 'Restore this backup'}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={discard}
                        >
                            Cancel
                        </Button>
                    </div>
                </form>
            ) : (
                <Button type="button" variant="outline" onClick={discard}>
                    Remove this file
                </Button>
            )}
        </div>
    );
}

function UploadBackup({ maxMegabytes }: { maxMegabytes: number }) {
    const { setData, post, processing, errors, data } = useForm<{
        backup: File | null;
    }>({ backup: null });

    const check = (event: FormEvent) => {
        event.preventDefault();
        post(route('admin.database-backup.inspect'), {
            forceFormData: true,
            preserveScroll: true,
        });
    };

    return (
        <form onSubmit={check} className="space-y-2">
            <input
                type="file"
                accept=".sql"
                aria-label="Backup file"
                className={inputClass}
                onChange={(event) =>
                    setData('backup', event.target.files?.[0] ?? null)
                }
            />
            <p className="text-xs text-slate-600">
                Up to {maxMegabytes} MB, subject to your hosting upload limit.
            </p>
            {errors.backup && (
                <p className="text-xs text-red-500">{errors.backup}</p>
            )}
            <Button type="submit" disabled={!data.backup || processing}>
                <Upload className="mr-1.5 size-4" />
                {processing ? 'Checking…' : 'Check this file'}
            </Button>
        </form>
    );
}

export default function DatabaseBackupIndex({
    csrfToken,
    maxUploadMegabytes,
    pendingReport,
    restoreResult,
}: Props) {
    const { errors } = usePage().props as { errors: Record<string, string> };
    const [password, setPassword] = useState('');

    return (
        <>
            <Head title="Database Backup — Admin" />

            <div className="space-y-5">
                <div>
                    <h1 className="flex items-center gap-2 text-xl font-bold text-slate-900">
                        <DatabaseBackup className="size-5" />
                        Database Backup
                    </h1>
                    <p className="mt-0.5 text-sm text-slate-600">
                        Download all shop data as a .sql file, or restore one.
                        Only you can see this page.
                    </p>
                </div>

                {restoreResult && (
                    <Card className="space-y-3 p-5 shadow-sm">
                        <h2 className="font-semibold text-slate-900">
                            {restoreResult.committed
                                ? 'Backup restored'
                                : 'Restore did not happen. Nothing was changed.'}
                        </h2>
                        <Messages
                            title="Problems"
                            messages={restoreResult.errors}
                            total={restoreResult.error_count}
                            tone="error"
                        />
                        <Messages
                            title="Worth knowing"
                            messages={restoreResult.warnings}
                            total={restoreResult.warnings.length}
                            tone="warning"
                        />
                        <ReportTable report={restoreResult} />
                    </Card>
                )}

                <div className="grid items-stretch gap-5 lg:grid-cols-2">
                    <Card className="h-full space-y-3 p-5 shadow-sm">
                        <h2 className="font-semibold text-slate-900">
                            Download a backup
                        </h2>
                        <div className="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                            <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                            <p>
                                This file holds every customer's details,
                                password hashes and your courier keys. Keep it
                                somewhere private and never send it by email or
                                chat.
                            </p>
                        </div>
                        {/* A normal form post: the browser saves the download. */}
                        <form
                            method="post"
                            action={route('admin.database-backup.download')}
                            onSubmit={() =>
                                setTimeout(() => setPassword(''), 0)
                            }
                            className="space-y-2"
                        >
                            <input
                                type="hidden"
                                name="_token"
                                value={csrfToken}
                            />
                            <label
                                htmlFor="download-password"
                                className="block text-sm font-medium text-slate-700"
                            >
                                Enter your password to download
                            </label>
                            <input
                                maxLength={255}
                                id="download-password"
                                type="password"
                                name="password"
                                autoComplete="current-password"
                                className={inputClass}
                                value={password}
                                onChange={(event) =>
                                    setPassword(event.target.value)
                                }
                            />
                            {errors.password && !pendingReport && (
                                <p className="text-xs text-red-500">
                                    {errors.password}
                                </p>
                            )}
                            <Button type="submit" disabled={!password}>
                                <Download className="mr-1.5 size-4" />
                                Download backup
                            </Button>
                        </form>
                    </Card>

                    <Card className="h-full space-y-3 p-5 shadow-sm">
                        <h2 className="font-semibold text-slate-900">
                            Restore a backup
                        </h2>
                        <p className="text-sm text-slate-600">
                            Upload a file to check it first. New rows are added,
                            changed rows are updated, identical rows are
                            skipped, and nothing is ever deleted. Restoring
                            rewrites live data, so choose a quiet time. No
                            customer emails are sent, and stock is not adjusted.
                        </p>
                        {pendingReport ? (
                            <PendingRestore report={pendingReport} />
                        ) : (
                            <UploadBackup maxMegabytes={maxUploadMegabytes} />
                        )}
                    </Card>
                </div>
            </div>
        </>
    );
}
