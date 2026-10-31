import { Head, Link, router } from '@inertiajs/react';
import { Check, Search, X } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { Select } from '@/components/ui/select';

type ReturnStatus = 'pending' | 'approved' | 'rejected' | 'refunded';

type ReturnRequest = {
    id: number;
    status: ReturnStatus;
    reason: string;
    admin_note: string | null;
    refund_amount: number | null;
    refund_method: string | null;
    created_at: string;
    items: Array<{
        order_item_id: number;
        product_id: number;
        quantity: number;
        reason?: string;
    }>;
    user: { id: number; name: string; email: string } | null;
    order: { id: number; order_number: string; total: number } | null;
    /** PAY-RETURN-001: server-computed maximum for an approved return (null otherwise). */
    max_refund: number | null;
    refund_blocked_reasons: string[];
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};

type Props = {
    returns: Paginated<ReturnRequest>;
    filters: { search?: string; status?: string };
};

const statusVariant: Record<
    ReturnStatus,
    'warning' | 'success' | 'error' | 'info'
> = {
    pending: 'warning',
    approved: 'success',
    rejected: 'error',
    refunded: 'info',
};

export default function ReturnsIndex({ returns, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');

    const applyFilters = (overrides: Record<string, string>) => {
        router.get(
            route('admin.returns.index'),
            { ...filters, search, ...overrides },
            { preserveState: true, replace: true },
        );
    };

    const handleApprove = (id: number) => {
        if (!confirm('Approve this return request?')) {
            return;
        }

        router.patch(route('admin.returns.approve', id));
    };

    const handleReject = (id: number) => {
        const admin_note =
            prompt(
                'Optional note to the customer explaining why this was rejected:',
            ) ?? '';
        router.patch(route('admin.returns.reject', id), { admin_note });
    };

    const handleMarkRefunded = (id: number) => {
        const request = returns.data.find((r) => r.id === id);

        if (request && request.refund_blocked_reasons.length > 0) {
            alert(
                `No refund can be recorded yet:\n\n${request.refund_blocked_reasons.join('\n')}`,
            );

            return;
        }

        const refund_amount = prompt(
            `Refund amount actually sent to the customer (at most ৳${request?.max_refund ?? 0} — the discounted value of the returned items; shipping is not refundable):`,
        );

        if (!refund_amount) {
            return;
        }

        const refund_method = prompt(
            'How was it sent (e.g. bKash, bank transfer)?',
        );

        if (!refund_method) {
            return;
        }

        router.patch(route('admin.returns.markRefunded', id), {
            refund_amount,
            refund_method,
        });
    };

    return (
        <>
            <Head title="Returns — Admin" />
            <div className="space-y-5">
                <div>
                    <h1 className="text-xl font-bold text-slate-900">
                        Return Requests
                    </h1>
                    <p className="text-sm text-slate-500">
                        {returns.total} total
                    </p>
                </div>

                <Card className="overflow-hidden p-0 shadow-sm">
                    <div className="flex flex-wrap gap-2 border-b border-slate-100 p-4">
                        <div className="relative min-w-[200px] flex-1">
                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                            <input
                                type="text"
                                placeholder="Search by order number or customer…"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                onKeyDown={(e) =>
                                    e.key === 'Enter' && applyFilters({})
                                }
                                className="h-9 w-full rounded-lg border border-slate-200 pr-3 pl-9 text-sm focus:border-[#c8102e] focus:outline-none"
                            />
                        </div>
                        <Select
                            value={filters.status ?? ''}
                            onChange={(e) =>
                                applyFilters({ status: e.target.value })
                            }
                            containerClassName="min-w-40"
                            selectClassName="h-9 rounded-lg border border-slate-200 px-3 text-sm focus:outline-none"
                        >
                            <option value="">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                            <option value="refunded">Refunded</option>
                        </Select>
                    </div>

                    <div className="divide-y divide-slate-50">
                        {returns.data.length === 0 ? (
                            <p className="py-12 text-center text-sm text-slate-400">
                                No return requests found.
                            </p>
                        ) : (
                            returns.data.map((r) => (
                                <div
                                    key={r.id}
                                    className="px-4 py-3 hover:bg-slate-50/60"
                                >
                                    <div className="flex items-start gap-3">
                                        <div className="min-w-0 flex-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="text-sm font-semibold text-slate-800">
                                                    {r.order?.order_number ??
                                                        'Unknown order'}
                                                </span>
                                                <Badge
                                                    variant={
                                                        statusVariant[r.status]
                                                    }
                                                    size="sm"
                                                >
                                                    {r.status
                                                        .charAt(0)
                                                        .toUpperCase() +
                                                        r.status.slice(1)}
                                                </Badge>
                                                <span className="text-xs text-slate-400">
                                                    {r.items.length} item
                                                    {r.items.length === 1
                                                        ? ''
                                                        : 's'}
                                                </span>
                                            </div>
                                            <p className="mt-1 line-clamp-2 text-xs text-slate-600">
                                                {r.reason}
                                            </p>
                                            <p className="mt-1 text-xs text-slate-400">
                                                {r.user?.name ?? 'Unknown'} ·{' '}
                                                {new Date(
                                                    r.created_at,
                                                ).toLocaleDateString()}
                                            </p>
                                            {r.admin_note && (
                                                <p className="mt-1 text-xs text-slate-500 italic">
                                                    Note: {r.admin_note}
                                                </p>
                                            )}
                                            {r.status === 'refunded' && (
                                                <p className="mt-1 text-xs text-emerald-600">
                                                    Refunded {r.refund_amount}{' '}
                                                    via {r.refund_method}
                                                </p>
                                            )}
                                        </div>
                                        <div className="flex shrink-0 items-center gap-1">
                                            {r.status === 'pending' && (
                                                <>
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            handleApprove(r.id)
                                                        }
                                                        title="Approve"
                                                        className="rounded-lg p-1.5 text-slate-400 hover:bg-emerald-50 hover:text-emerald-600"
                                                    >
                                                        <Check className="size-4" />
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            handleReject(r.id)
                                                        }
                                                        title="Reject"
                                                        className="rounded-lg p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600"
                                                    >
                                                        <X className="size-4" />
                                                    </button>
                                                </>
                                            )}
                                            {r.status === 'approved' && (
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        handleMarkRefunded(r.id)
                                                    }
                                                    className="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-50"
                                                >
                                                    Mark Refunded
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            ))
                        )}
                    </div>

                    {returns.last_page > 1 && (
                        <div className="flex items-center justify-between border-t border-slate-100 px-4 py-3">
                            <p className="text-xs text-slate-500">
                                Page {returns.current_page} of{' '}
                                {returns.last_page}
                            </p>
                            <div className="flex gap-1">
                                {returns.links[0]?.url && (
                                    <Link
                                        href={returns.links[0].url}
                                        className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs hover:bg-slate-50"
                                    >
                                        Prev
                                    </Link>
                                )}
                                {returns.links[returns.links.length - 1]
                                    ?.url && (
                                    <Link
                                        href={
                                            returns.links[
                                                returns.links.length - 1
                                            ].url!
                                        }
                                        className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs hover:bg-slate-50"
                                    >
                                        Next
                                    </Link>
                                )}
                            </div>
                        </div>
                    )}
                </Card>
            </div>
        </>
    );
}
