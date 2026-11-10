import { Head, router, usePage } from '@inertiajs/react';
import { RotateCcw, Trash2, TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { Pagination } from '@/components/ui/pagination';
import type { PaginatedData } from '@/types';

type FailedJob = {
    uuid: string;
    queue: string;
    connection: string;
    job: string;
    max_tries: number | null;
    failed_at: string;
    error_type: string;
    error: string;
    can_retry: boolean;
    retry_warning: string | null;
};

type Props = {
    failedJobs: PaginatedData<FailedJob>;
    canRetry: boolean;
    canDelete: boolean;
};

type PendingAction =
    | { type: 'retry' | 'delete'; job: FailedJob }
    | { type: 'retry-all' };

export default function FailedJobsIndex({
    failedJobs,
    canRetry,
    canDelete,
}: Props) {
    const { props } = usePage<any>();
    const flash = props.flash as
        | { success?: string; error?: string }
        | undefined;
    const [pending, setPending] = useState<PendingAction | null>(null);
    const [processing, setProcessing] = useState(false);

    const handleConfirm = () => {
        if (!pending) {
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

        if (pending.type === 'retry') {
            router.post(
                route('admin.failed-jobs.retry', pending.job.uuid),
                { confirmed: true },
                options,
            );
        } else if (pending.type === 'retry-all') {
            router.post(
                route('admin.failed-jobs.retryAll'),
                { confirmed: true },
                options,
            );
        } else {
            router.delete(
                route('admin.failed-jobs.destroy', pending.job.uuid),
                {
                    ...options,
                    data: { confirmed: true },
                },
            );
        }
    };

    return (
        <>
            <Head title="Failed Jobs — Admin" />

            <ConfirmDialog
                open={pending !== null}
                onOpenChange={(open) => !open && setPending(null)}
                title={
                    pending?.type === 'retry'
                        ? 'Retry this job?'
                        : pending?.type === 'retry-all'
                          ? 'Retry every retryable failed job?'
                          : 'Delete this record?'
                }
                description={
                    pending?.type === 'retry'
                        ? (pending.job.retry_warning ??
                          `${pending.job.job} will run again. If the email reached the mail server before failing, the customer may receive it twice.`)
                        : pending?.type === 'retry-all'
                          ? 'Every currently retryable failed job will run again. If any of their emails reached the mail server before failing, those customers may receive it twice.'
                          : 'The record is removed from this list. The job will not run again.'
                }
                confirmLabel={pending?.type === 'delete' ? 'Delete' : 'Retry'}
                onConfirm={handleConfirm}
                processing={processing}
            />

            <div className="space-y-5">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="flex items-center gap-2 text-xl font-bold text-slate-900">
                            <TriangleAlert className="size-5 text-slate-400" />
                            Failed Jobs
                        </h1>
                        <p className="text-sm text-slate-500">
                            {failedJobs.total} background{' '}
                            {failedJobs.total === 1 ? 'job' : 'jobs'} failed
                            after every retry
                        </p>
                    </div>
                    {canRetry && failedJobs.total > 0 && (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setPending({ type: 'retry-all' })}
                        >
                            <RotateCcw className="size-4" />
                            Retry All
                        </Button>
                    )}
                </div>

                {flash?.success && (
                    <div className="rounded-lg bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-700">
                        {flash.success}
                    </div>
                )}
                {flash?.error && (
                    <div className="rounded-lg bg-red-50 px-4 py-2.5 text-sm font-medium text-red-700">
                        {flash.error}
                    </div>
                )}

                <Card className="overflow-hidden p-0 shadow-sm">
                    {failedJobs.data.length === 0 ? (
                        <p className="p-10 text-center text-sm text-slate-500">
                            No failed jobs.
                        </p>
                    ) : (
                        <ul className="divide-y divide-slate-100">
                            {failedJobs.data.map((job) => (
                                <li
                                    key={job.uuid}
                                    className="admin-row-card flex flex-wrap items-start justify-between gap-3 p-4"
                                >
                                    <div className="min-w-0 flex-1 space-y-1">
                                        <p className="flex flex-wrap items-center gap-2 text-sm font-semibold text-slate-800">
                                            {job.job}
                                            <Badge variant="error">
                                                {job.error_type}
                                            </Badge>
                                            {!job.can_retry && (
                                                <Badge variant="default">
                                                    Retry not available
                                                </Badge>
                                            )}
                                        </p>
                                        <p className="text-sm break-words text-slate-600">
                                            {job.error ||
                                                'No error details available.'}
                                        </p>
                                        <p className="text-xs break-all text-slate-400">
                                            {new Date(
                                                job.failed_at,
                                            ).toLocaleString()}
                                            {' · '}
                                            {job.connection}/{job.queue}
                                            {job.max_tries !== null &&
                                                ` · max tries ${job.max_tries}`}
                                            {' · '}
                                            {job.uuid}
                                        </p>
                                    </div>
                                    <div className="flex gap-2">
                                        {canRetry && job.can_retry && (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    setPending({
                                                        type: 'retry',
                                                        job,
                                                    })
                                                }
                                            >
                                                <RotateCcw className="size-4" />
                                                Retry
                                            </Button>
                                        )}
                                        {canDelete && (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                className="border-red-200 text-red-600 hover:bg-red-50"
                                                onClick={() =>
                                                    setPending({
                                                        type: 'delete',
                                                        job,
                                                    })
                                                }
                                            >
                                                <Trash2 className="size-4" />
                                                Delete
                                            </Button>
                                        )}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>

                <Pagination
                    data={failedJobs}
                    onPageChange={(page) =>
                        router.get(
                            route('admin.failed-jobs.index'),
                            { page },
                            { preserveState: true, replace: true },
                        )
                    }
                />
            </div>
        </>
    );
}
