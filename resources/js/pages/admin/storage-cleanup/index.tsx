import { Head, router, usePage } from '@inertiajs/react';
import {
    ArchiveRestore,
    ChevronDown,
    ChevronRight,
    FolderInput,
    HardDrive,
    Info,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';

type Folder = {
    folder: string;
    checked: boolean;
    files: number;
    bytes: number;
    unused: number;
    unused_bytes: number;
};

type Batch = {
    batch: string;
    moved_at: number;
    files: number;
    bytes: number;
    purgeable_at: number;
    purgeable: boolean;
};

type Props = {
    report: {
        folders: Folder[];
        unused: { path: string; bytes: number }[];
        totals: {
            files: number;
            bytes: number;
            unused: number;
            unused_bytes: number;
        };
    };
    batches: Batch[];
    graceDays: Record<string, number>;
    purgeAfterDays: number;
};

type PendingAction =
    | { kind: 'quarantine' }
    | { kind: 'restore'; batch: Batch }
    | { kind: 'delete'; batch: Batch };

function formatBytes(bytes: number): string {
    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(0)} KB`;
    }

    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

function formatDate(seconds: number): string {
    return new Date(seconds * 1000).toLocaleString('en-BD', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

function plural(count: number, word: string): string {
    return `${count} ${word}${count === 1 ? '' : 's'}`;
}

export default function StorageCleanupIndex({
    report,
    batches,
    graceDays,
    purgeAfterDays,
}: Props) {
    const { props } = usePage<any>();
    const flash = props.flash as
        | { success?: string; error?: string; info?: string }
        | undefined;
    const [showUnused, setShowUnused] = useState(false);
    const [pending, setPending] = useState<PendingAction | null>(null);
    const [processing, setProcessing] = useState(false);

    const { totals } = report;
    const shortestGrace = Math.min(...Object.values(graceDays));
    const longestGrace = Math.max(...Object.values(graceDays));

    const run = () => {
        if (pending === null) {
            return;
        }

        const options = {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => {
                setProcessing(false);
                setPending(null);
            },
        };

        if (pending.kind === 'quarantine') {
            router.post(
                route('admin.storage-cleanup.quarantine'),
                { confirmed: true },
                options,
            );
        } else if (pending.kind === 'restore') {
            router.post(
                route('admin.storage-cleanup.restore', pending.batch.batch),
                {},
                options,
            );
        } else {
            router.delete(
                route('admin.storage-cleanup.destroy', pending.batch.batch),
                options,
            );
        }
    };

    const dialog = (() => {
        if (pending?.kind === 'quarantine') {
            return {
                title: `Move ${plural(totals.unused, 'unused file')} aside?`,
                description: `${formatBytes(totals.unused_bytes)} of files nothing on the site uses will be moved into a holding folder. Nothing is deleted: if an image turns out to be missing, you can put the whole batch back from this page. After ${purgeAfterDays} days you can delete the batch for good.`,
                confirmLabel: 'Move Aside',
            };
        }

        if (pending?.kind === 'restore') {
            return {
                title: `Put back batch ${pending.batch.batch}?`,
                description: `${plural(pending.batch.files, 'file')} will go back exactly where they were. A file uploaded since with the same name is never overwritten.`,
                confirmLabel: 'Put Back',
            };
        }

        if (pending?.kind === 'delete') {
            return {
                title: `Delete batch ${pending.batch.batch} permanently?`,
                description: `${plural(pending.batch.files, 'file')} (${formatBytes(pending.batch.bytes)}) will be deleted for good. Only do this once you're sure no image is missing on the site. This cannot be undone.`,
                confirmLabel: 'Delete Permanently',
            };
        }

        return null;
    })();

    return (
        <>
            <Head title="Storage Clean-up — Admin" />

            {dialog && (
                <ConfirmDialog
                    open={pending !== null}
                    onOpenChange={(open) => !open && setPending(null)}
                    title={dialog.title}
                    description={dialog.description}
                    confirmLabel={dialog.confirmLabel}
                    onConfirm={run}
                    processing={processing}
                />
            )}

            <div className="space-y-5">
                <div>
                    <h1 className="flex items-center gap-2 text-xl font-bold text-slate-900">
                        <HardDrive className="size-5 text-slate-400" />
                        Storage Clean-up
                    </h1>
                    <p className="text-sm text-slate-600">
                        {plural(totals.files, 'uploaded file')} ·{' '}
                        {formatBytes(totals.bytes)}
                    </p>
                </div>

                {flash?.success && (
                    <div className="rounded-lg bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-700">
                        {flash.success}
                    </div>
                )}
                {flash?.info && (
                    <div className="rounded-lg bg-sky-50 px-4 py-2.5 text-sm font-medium text-sky-700">
                        {flash.info}
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

                <div className="flex items-start gap-2 rounded-lg bg-slate-50 px-4 py-3 text-xs leading-5 text-slate-600">
                    <Info className="mt-0.5 size-4 shrink-0 text-slate-400" />
                    <div className="space-y-1">
                        <p>
                            Every image or video uploaded in the CMS is saved on
                            the server. Replaced and deleted images now clean
                            themselves up, but a few leftovers can still pile up
                            — for example an image uploaded in an editor and
                            never saved. This page finds uploaded files that{' '}
                            <strong>nothing on the site uses</strong> (product
                            photos still shown on old orders count as used) and
                            are older than {shortestGrace} days ({longestGrace}{' '}
                            for newsletter email images).
                        </p>
                        <p>
                            <strong>1.</strong> Move them aside — nothing is
                            deleted. <strong>2.</strong> Check the site over a
                            few days. <strong>3.</strong> If an image is
                            missing, put the batch back; if not, delete the
                            batch once it is {purgeAfterDays} days old.
                        </p>
                    </div>
                </div>

                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <Card className="p-4 shadow-sm">
                        <p className="text-xs text-slate-600">Uploaded files</p>
                        <p className="mt-1 text-xl font-bold text-slate-900">
                            {totals.files}
                        </p>
                    </Card>
                    <Card className="p-4 shadow-sm">
                        <p className="text-xs text-slate-600">Total size</p>
                        <p className="mt-1 text-xl font-bold text-slate-900">
                            {formatBytes(totals.bytes)}
                        </p>
                    </Card>
                    <Card className="p-4 shadow-sm">
                        <p className="text-xs text-slate-600">Unused files</p>
                        <p
                            className={`mt-1 text-xl font-bold ${totals.unused > 0 ? 'text-amber-600' : 'text-emerald-600'}`}
                        >
                            {totals.unused}
                        </p>
                    </Card>
                    <Card className="p-4 shadow-sm">
                        <p className="text-xs text-slate-600">Space they use</p>
                        <p className="mt-1 text-xl font-bold text-slate-900">
                            {formatBytes(totals.unused_bytes)}
                        </p>
                    </Card>
                </div>

                <Card className="overflow-hidden p-0 shadow-sm">
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-4 py-3">
                        <h2 className="text-sm font-semibold text-slate-700">
                            Uploaded files by folder
                        </h2>
                        <Button
                            size="sm"
                            onClick={() => setPending({ kind: 'quarantine' })}
                            disabled={totals.unused === 0}
                            className="bg-brand text-brand-foreground hover:bg-brand-hover"
                        >
                            <FolderInput className="size-4" />
                            {totals.unused > 0
                                ? `Move ${plural(totals.unused, 'unused file')} aside`
                                : 'Nothing to clean up'}
                        </Button>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[32rem] text-sm">
                            <thead className="bg-slate-50 text-left text-xs text-slate-600">
                                <tr>
                                    <th className="px-4 py-2 font-medium">
                                        Folder
                                    </th>
                                    <th className="px-4 py-2 text-right font-medium">
                                        Files
                                    </th>
                                    <th className="px-4 py-2 text-right font-medium">
                                        Size
                                    </th>
                                    <th className="px-4 py-2 text-right font-medium">
                                        Unused
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {report.folders.map((folder) => (
                                    <tr key={folder.folder}>
                                        <td className="px-4 py-2.5 font-medium text-slate-800">
                                            {folder.folder}
                                        </td>
                                        <td className="px-4 py-2.5 text-right text-slate-600">
                                            {folder.files}
                                        </td>
                                        <td className="px-4 py-2.5 text-right text-slate-600">
                                            {formatBytes(folder.bytes)}
                                        </td>
                                        <td className="px-4 py-2.5 text-right">
                                            {!folder.checked ? (
                                                <span className="text-xs text-slate-600">
                                                    not checked
                                                </span>
                                            ) : folder.unused > 0 ? (
                                                <Badge
                                                    variant="warning"
                                                    size="sm"
                                                >
                                                    {folder.unused} ·{' '}
                                                    {formatBytes(
                                                        folder.unused_bytes,
                                                    )}
                                                </Badge>
                                            ) : (
                                                <span className="text-slate-600">
                                                    0
                                                </span>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {report.unused.length > 0 && (
                        <div className="border-t border-slate-100 px-4 py-3">
                            <button
                                type="button"
                                onClick={() => setShowUnused(!showUnused)}
                                className="flex items-center gap-1 text-xs font-medium text-slate-600 hover:text-slate-900"
                                aria-expanded={showUnused}
                            >
                                {showUnused ? (
                                    <ChevronDown className="size-4" />
                                ) : (
                                    <ChevronRight className="size-4" />
                                )}
                                {showUnused ? 'Hide' : 'Show'} the unused files
                            </button>
                            {showUnused && (
                                <ul className="mt-2 max-h-72 space-y-1 overflow-auto rounded-lg bg-slate-50 p-3 font-mono text-[11px] text-slate-600">
                                    {report.unused.map((file) => (
                                        <li
                                            key={file.path}
                                            className="flex justify-between gap-3"
                                        >
                                            <span className="break-all">
                                                {file.path}
                                            </span>
                                            <span className="shrink-0 text-slate-600">
                                                {formatBytes(file.bytes)}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    )}
                </Card>

                <Card className="overflow-hidden p-0 shadow-sm">
                    <div className="border-b border-slate-100 px-4 py-3">
                        <h2 className="text-sm font-semibold text-slate-700">
                            Files moved aside
                        </h2>
                        <p className="text-xs text-slate-600">
                            Each move makes a batch. Put a batch back if an
                            image is missing; delete it once it's{' '}
                            {purgeAfterDays} days old.
                        </p>
                    </div>
                    {batches.length === 0 ? (
                        <p className="px-4 py-8 text-center text-sm text-slate-600">
                            Nothing has been moved aside.
                        </p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[40rem] text-sm">
                                <thead className="bg-slate-50 text-left text-xs text-slate-600">
                                    <tr>
                                        <th className="px-4 py-2 font-medium">
                                            Batch
                                        </th>
                                        <th className="px-4 py-2 font-medium">
                                            Moved
                                        </th>
                                        <th className="px-4 py-2 text-right font-medium">
                                            Files
                                        </th>
                                        <th className="px-4 py-2 font-medium">
                                            Can be deleted
                                        </th>
                                        <th className="px-4 py-2 text-right font-medium">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {batches.map((batch) => (
                                        <tr key={batch.batch}>
                                            <td className="px-4 py-2.5 font-mono text-xs text-slate-700">
                                                {batch.batch}
                                            </td>
                                            <td className="px-4 py-2.5 whitespace-nowrap text-slate-600">
                                                {formatDate(batch.moved_at)}
                                            </td>
                                            <td className="px-4 py-2.5 text-right whitespace-nowrap text-slate-600">
                                                {batch.files} ·{' '}
                                                {formatBytes(batch.bytes)}
                                            </td>
                                            <td className="px-4 py-2.5 whitespace-nowrap">
                                                {batch.purgeable ? (
                                                    <Badge
                                                        variant="success"
                                                        size="sm"
                                                    >
                                                        Now
                                                    </Badge>
                                                ) : (
                                                    <span className="text-xs text-slate-600">
                                                        From{' '}
                                                        {formatDate(
                                                            batch.purgeable_at,
                                                        )}
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-4 py-2.5">
                                                <div className="flex justify-end gap-2">
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() =>
                                                            setPending({
                                                                kind: 'restore',
                                                                batch,
                                                            })
                                                        }
                                                    >
                                                        <ArchiveRestore className="size-4" />
                                                        Put back
                                                    </Button>
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        disabled={
                                                            !batch.purgeable
                                                        }
                                                        title={
                                                            batch.purgeable
                                                                ? undefined
                                                                : `Available from ${formatDate(batch.purgeable_at)}`
                                                        }
                                                        onClick={() =>
                                                            setPending({
                                                                kind: 'delete',
                                                                batch,
                                                            })
                                                        }
                                                        className="border-red-200 text-red-600 hover:bg-red-50"
                                                    >
                                                        <Trash2 className="size-4" />
                                                        Delete
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </Card>
            </div>
        </>
    );
}
