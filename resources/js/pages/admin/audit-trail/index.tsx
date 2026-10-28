import { Head, router, usePage } from '@inertiajs/react';
import {
    History,
    LogIn,
    LogOut,
    Pencil,
    Plus,
    ShieldAlert,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Pagination } from '@/components/ui/pagination';
import { Select } from '@/components/ui/select';
import type { PaginatedData } from '@/types';

type Causer = {
    name: string;
    type: 'Admin' | 'Customer' | 'System';
};

type ActivityEntry = {
    id: number;
    log_name: string | null;
    log_name_label: string;
    event: string | null;
    description: string;
    causer: Causer;
    subject_type: string | null;
    created_at: string;
};

type Props = {
    activities: PaginatedData<ActivityEntry>;
    filters: { log_name?: string | null };
    logNameOptions: Record<string, string>;
    canClear: boolean;
};

const EVENT_BADGES: Record<
    string,
    {
        variant: 'success' | 'info' | 'error' | 'warning' | 'default';
        icon: React.ElementType;
    }
> = {
    created: { variant: 'success', icon: Plus },
    updated: { variant: 'info', icon: Pencil },
    deleted: { variant: 'error', icon: Trash2 },
    restored: { variant: 'warning', icon: Plus },
    login: { variant: 'default', icon: LogIn },
    logout: { variant: 'default', icon: LogOut },
    cleared: { variant: 'error', icon: ShieldAlert },
};

function ClearLogsModal({ onClose }: { onClose: () => void }) {
    const { props } = usePage<any>();
    const errors = (props.errors ?? {}) as Record<string, string>;
    const [password, setPassword] = useState('');
    const [processing, setProcessing] = useState(false);

    const handleConfirm = () => {
        setProcessing(true);
        router.delete(route('admin.audit-trail.destroy'), {
            data: { password },
            preserveScroll: true,
            onSuccess: () => onClose(),
            onError: () => setProcessing(false),
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center">
            <div className="absolute inset-0 bg-black/40" onClick={onClose} />
            <div className="relative z-10 w-full max-w-md rounded-xl bg-white p-6 shadow-2xl">
                <div className="mb-4 flex items-start gap-3">
                    <div className="flex size-10 shrink-0 items-center justify-center rounded-full bg-red-100">
                        <ShieldAlert className="size-5 text-red-600" />
                    </div>
                    <div>
                        <h2 className="text-base font-semibold text-slate-900">
                            Clear the entire audit trail?
                        </h2>
                        <p className="mt-0.5 text-sm text-slate-500">
                            This permanently deletes every log entry — admin
                            activity and order activity alike. This cannot be
                            undone. Enter your password to confirm.
                        </p>
                    </div>
                </div>

                <div className="mb-4">
                    <label className="mb-1 block text-sm font-medium text-slate-700">
                        Your Password
                    </label>
                    <input
                        type="password"
                        autoFocus
                        value={password}
                        onChange={(e) => setPassword(e.target.value)}
                        onKeyDown={(e) =>
                            e.key === 'Enter' && !processing && handleConfirm()
                        }
                        placeholder="Enter your admin password"
                        className="h-9 w-full rounded-lg border border-slate-200 px-3 text-sm focus:border-red-400 focus:ring-1 focus:ring-red-400 focus:outline-none"
                    />
                    {errors.password && (
                        <p className="mt-1 text-xs font-medium text-red-600">
                            {errors.password}
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
                        {processing ? 'Clearing…' : 'Clear All Logs'}
                    </Button>
                </div>
            </div>
        </div>
    );
}

export default function AuditTrailIndex({
    activities,
    filters,
    logNameOptions,
    canClear,
}: Props) {
    const { props } = usePage<any>();
    const flash = props.flash as
        | { success?: string; error?: string }
        | undefined;
    const [clearing, setClearing] = useState(false);

    const applyFilter = (logName: string) => {
        router.get(
            route('admin.audit-trail.index'),
            logName ? { log_name: logName } : {},
            { preserveState: true, replace: true },
        );
    };

    const handlePageChange = (page: number) =>
        router.get(
            route('admin.audit-trail.index'),
            { ...filters, page },
            { preserveState: true, replace: true },
        );

    return (
        <>
            <Head title="Audit Trail — Admin" />

            {clearing && <ClearLogsModal onClose={() => setClearing(false)} />}

            <div className="space-y-5">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="flex items-center gap-2 text-xl font-bold text-slate-900">
                            <History className="size-5 text-slate-400" />
                            Audit Trail
                        </h1>
                        <p className="text-sm text-slate-500">
                            {activities.total} total entries
                        </p>
                    </div>
                    {canClear && (
                        <Button
                            variant="outline"
                            onClick={() => setClearing(true)}
                            className="border-red-200 text-red-600 hover:bg-red-50"
                        >
                            <Trash2 className="size-4" />
                            Clear All Logs
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
                    <div className="flex flex-wrap gap-2 border-b border-slate-100 p-4">
                        <Select
                            value={filters.log_name ?? ''}
                            onChange={(e) => applyFilter(e.target.value)}
                            containerClassName="min-w-48"
                            selectClassName="h-9 rounded-lg border border-slate-200 px-3 text-sm focus:outline-none"
                        >
                            <option value="">All Activity</option>
                            {Object.entries(logNameOptions).map(
                                ([value, label]) => (
                                    <option key={value} value={value}>
                                        {label}
                                    </option>
                                ),
                            )}
                        </Select>
                    </div>

                    <div className="divide-y divide-slate-50">
                        {activities.data.length === 0 ? (
                            <div className="py-16 text-center">
                                <History className="mx-auto mb-3 size-10 text-slate-300" />
                                <p className="text-sm text-slate-400">
                                    No activity logged yet.
                                </p>
                            </div>
                        ) : (
                            activities.data.map((item) => {
                                const badge = (item.event &&
                                    EVENT_BADGES[item.event]) || {
                                    variant: 'default' as const,
                                    icon: History,
                                };
                                const Icon = badge.icon;

                                return (
                                    <div
                                        key={item.id}
                                        className="flex items-start gap-3 px-4 py-3 hover:bg-slate-50/60"
                                    >
                                        <Badge
                                            variant={badge.variant}
                                            size="sm"
                                            className="mt-0.5 shrink-0"
                                        >
                                            <Icon className="size-3" />
                                            {item.event ?? 'event'}
                                        </Badge>
                                        <div className="min-w-0 flex-1">
                                            <p className="text-sm text-slate-700">
                                                {item.description}
                                            </p>
                                            <p className="mt-0.5 text-xs text-slate-400">
                                                {item.causer.name}
                                                {item.causer.type !==
                                                    'System' &&
                                                    ` (${item.causer.type})`}
                                                {' · '}
                                                {item.log_name_label}
                                            </p>
                                        </div>
                                        <p className="shrink-0 text-xs text-slate-400">
                                            {new Date(
                                                item.created_at,
                                            ).toLocaleString('en-BD', {
                                                dateStyle: 'medium',
                                                timeStyle: 'short',
                                            })}
                                        </p>
                                    </div>
                                );
                            })
                        )}
                    </div>

                    {activities.last_page > 1 && (
                        <div className="border-t border-slate-100 px-4 py-4">
                            <div className="flex flex-col items-center gap-2">
                                <Pagination
                                    data={activities}
                                    onPageChange={handlePageChange}
                                />
                                <p className="text-xs text-slate-400">
                                    Showing {activities.from}–{activities.to} of{' '}
                                    {activities.total}
                                </p>
                            </div>
                        </div>
                    )}
                </Card>
            </div>
        </>
    );
}
