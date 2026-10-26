import { Head, Link, router, usePage, usePoll } from '@inertiajs/react';
import { MessageSquare, Search, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ClearFiltersButton } from '@/components/admin/clear-filters-button';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Pagination } from '@/components/ui/pagination';
import { Select } from '@/components/ui/select';
import type { PaginatedData } from '@/types';

type ReceivedSms = {
    id: number;
    sender: string;
    provider: 'bkash' | 'nagad' | 'unknown';
    content: string;
    received_at: string;
    parsed_transaction_id: string | null;
    parsed_amount: number | null;
    is_matched: boolean;
    matched_order: { id: number; order_number: string } | null;
    created_at: string;
};

type Props = {
    sms: PaginatedData<ReceivedSms>;
    filters: { search?: string; matched?: string };
};

function DeleteModal({
    sms,
    onClose,
}: {
    sms: ReceivedSms;
    onClose: () => void;
}) {
    const { props } = usePage<any>();
    const errors = (props.errors ?? {}) as Record<string, string>;
    const [password, setPassword] = useState('');
    const [processing, setProcessing] = useState(false);

    const handleConfirm = () => {
        setProcessing(true);
        router.delete(route('admin.received-sms.destroy', sms.id), {
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
                        <Trash2 className="size-5 text-red-600" />
                    </div>
                    <div>
                        <h2 className="text-base font-semibold text-slate-900">
                            Delete SMS
                        </h2>
                        <p className="mt-0.5 text-sm text-slate-500">
                            This action cannot be undone. Enter your admin
                            password to confirm.
                        </p>
                    </div>
                </div>

                <div className="mb-1 line-clamp-2 rounded-lg bg-slate-50 px-3 py-2.5 text-xs text-slate-600">
                    {sms.content}
                </div>
                <p className="mb-4 text-[11px] text-slate-400">
                    From: {sms.sender}
                </p>

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
                        {processing ? 'Deleting…' : 'Delete SMS'}
                    </Button>
                </div>
            </div>
        </div>
    );
}

export default function ReceivedSmsIndex({ sms, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const { props } = usePage<any>();
    const flash = props.flash as { success?: string } | undefined;
    const [deletingItem, setDeletingItem] = useState<ReceivedSms | null>(null);

    usePoll(5000, { only: ['sms'], preserveScroll: true });

    const applyFilters = (overrides: Record<string, string | number>) => {
        router.get(
            route('admin.received-sms.index'),
            { ...filters, search, ...overrides },
            { preserveState: true, replace: true },
        );
    };

    const handlePageChange = (page: number) => applyFilters({ page });

    const clearFilters = () => {
        setSearch('');
        router.get(
            route('admin.received-sms.index'),
            {},
            { preserveState: true, replace: true },
        );
    };

    const hasActiveFilters = Boolean(
        search || filters.search || filters.matched,
    );

    return (
        <>
            <Head title="Received SMS — Admin" />

            {deletingItem && (
                <DeleteModal
                    sms={deletingItem}
                    onClose={() => setDeletingItem(null)}
                />
            )}

            <div className="space-y-5">
                <div className="flex items-center justify-between">
                    <div>
                        <div className="flex items-center gap-2.5">
                            <h1 className="text-xl font-bold text-slate-900">
                                Received SMS
                            </h1>
                            <span className="flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-600">
                                <span className="relative flex size-1.5">
                                    <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75" />
                                    <span className="relative inline-flex size-1.5 rounded-full bg-emerald-500" />
                                </span>
                                Live
                            </span>
                        </div>
                        <p className="text-sm text-slate-500">
                            {sms.total} total · updates every 5 seconds
                        </p>
                    </div>
                </div>

                {flash?.success && (
                    <div className="rounded-lg bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-700">
                        {flash.success}
                    </div>
                )}

                <Card className="overflow-hidden p-0 shadow-sm">
                    <div className="flex flex-wrap gap-2 border-b border-slate-100 p-4">
                        <div className="relative min-w-[200px] flex-1">
                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                            <input
                                type="text"
                                placeholder="Search TrxID or sender…"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                onKeyDown={(e) =>
                                    e.key === 'Enter' && applyFilters({})
                                }
                                className="h-9 w-full rounded-lg border border-slate-200 pr-3 pl-9 text-sm focus:border-[#c8102e] focus:outline-none"
                            />
                        </div>
                        <Select
                            value={filters.matched ?? ''}
                            onChange={(e) =>
                                applyFilters({ matched: e.target.value })
                            }
                            containerClassName="min-w-40"
                            selectClassName="h-9 rounded-lg border border-slate-200 px-3 text-sm focus:outline-none"
                        >
                            <option value="">All</option>
                            <option value="yes">Matched</option>
                            <option value="no">Unmatched</option>
                        </Select>
                        <ClearFiltersButton
                            active={hasActiveFilters}
                            onClear={clearFilters}
                        />
                    </div>

                    <div className="divide-y divide-slate-50">
                        {sms.data.length === 0 ? (
                            <div className="py-16 text-center">
                                <MessageSquare className="mx-auto mb-3 size-10 text-slate-300" />
                                <p className="text-sm text-slate-400">
                                    No SMS received yet.
                                </p>
                            </div>
                        ) : (
                            sms.data.map((item) => (
                                <div
                                    key={item.id}
                                    className="px-4 py-3 hover:bg-slate-50/60"
                                >
                                    <div className="flex items-start gap-4">
                                        <div className="min-w-0 flex-1 space-y-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                {item.provider !==
                                                    'unknown' && (
                                                    <span
                                                        className={`rounded-full px-2 py-0.5 text-[11px] font-bold ${
                                                            item.provider ===
                                                            'bkash'
                                                                ? 'bg-pink-100 text-pink-700'
                                                                : 'bg-orange-100 text-orange-700'
                                                        }`}
                                                    >
                                                        {item.provider ===
                                                        'bkash'
                                                            ? 'Bkash'
                                                            : 'Nagad'}
                                                    </span>
                                                )}
                                                <span className="font-mono text-sm font-bold text-slate-800">
                                                    {item.parsed_transaction_id ?? (
                                                        <span className="font-normal text-slate-400">
                                                            No TrxID parsed
                                                        </span>
                                                    )}
                                                </span>
                                                {item.parsed_amount !==
                                                    null && (
                                                    <span className="text-sm font-semibold text-slate-700">
                                                        ৳
                                                        {item.parsed_amount.toLocaleString()}
                                                    </span>
                                                )}
                                                <Badge
                                                    variant={
                                                        item.is_matched
                                                            ? 'success'
                                                            : 'warning'
                                                    }
                                                    size="sm"
                                                >
                                                    {item.is_matched
                                                        ? 'Matched'
                                                        : 'Pending'}
                                                </Badge>
                                            </div>
                                            <p className="truncate text-xs text-slate-400">
                                                From: {item.sender}
                                            </p>
                                            <p className="line-clamp-2 text-xs leading-relaxed text-slate-500">
                                                {item.content}
                                            </p>
                                            {item.matched_order && (
                                                <Link
                                                    href={route(
                                                        'admin.orders.show',
                                                        item.matched_order.id,
                                                    )}
                                                    className="inline-flex items-center text-xs font-semibold text-[#c8102e] hover:underline"
                                                >
                                                    Order:{' '}
                                                    {
                                                        item.matched_order
                                                            .order_number
                                                    }
                                                </Link>
                                            )}
                                        </div>
                                        <div className="flex shrink-0 items-center gap-3">
                                            <p className="text-xs text-slate-400">
                                                {new Date(
                                                    item.received_at,
                                                ).toLocaleString('en-BD', {
                                                    dateStyle: 'medium',
                                                    timeStyle: 'short',
                                                })}
                                            </p>
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setDeletingItem(item)
                                                }
                                                title="Delete SMS"
                                                className="rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-red-50 hover:text-red-500"
                                            >
                                                <Trash2 className="size-4" />
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            ))
                        )}
                    </div>

                    {sms.last_page > 1 && (
                        <div className="border-t border-slate-100 px-4 py-4">
                            <div className="flex flex-col items-center gap-2">
                                <Pagination
                                    data={sms}
                                    onPageChange={handlePageChange}
                                />
                                <p className="text-xs text-slate-400">
                                    Showing {sms.from}–{sms.to} of {sms.total}
                                </p>
                            </div>
                        </div>
                    )}
                </Card>
            </div>
        </>
    );
}
