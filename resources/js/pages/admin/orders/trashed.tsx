import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, RotateCcw, Search, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ClearFiltersButton } from '@/components/admin/clear-filters-button';
import PermanentOrderDeleteDialog from '@/components/admin/PermanentOrderDeleteDialog';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { useAdminListUrl } from '@/hooks/use-admin-list-url';
import { useLiveSearch } from '@/hooks/use-live-search';

type Order = {
    id: number;
    order_number: string;
    status: string;
    payment_status: string;
    total: number;
    deleted_at: string;
    user: { id: number; name: string; email: string } | null;
    /** Mother admin only: why permanent deletion is unavailable (empty = allowed). */
    deletion_blockers?: { code: string; message: string }[];
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};

type Props = {
    orders: Paginated<Order>;
    filters: { search?: string };
    canPermanentlyDelete: boolean;
    pendingPermanentDeletion: { order_id: number; expires_at: number } | null;
};

export default function TrashedOrders({
    orders,
    filters,
    canPermanentlyDelete,
    pendingPermanentDeletion,
}: Props) {
    const listUrl = useAdminListUrl('admin.orders.index');
    const [search, setSearch] = useState(filters.search ?? '');
    const [restoringId, setRestoringId] = useState<number | null>(null);
    const [deleteTarget, setDeleteTarget] = useState<Order | null>(null);

    const applySearch = () => {
        if (search === (filters.search ?? '')) {
            return;
        }

        router.get(
            route('admin.orders.trashed'),
            { search },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const clearFilters = () => {
        setSearch('');
        router.get(
            route('admin.orders.trashed'),
            {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const handleRestore = (order: Order) => {
        setRestoringId(order.id);
        router.patch(
            route('admin.orders.restore', order.id),
            {},
            {
                onFinish: () => setRestoringId(null),
            },
        );
    };

    // Results follow the search box as you type (Enter / the Search button still apply at once).
    useLiveSearch(search, filters.search ?? '', () => applySearch());

    return (
        <>
            <Head title="Trashed Orders — Admin" />
            <div className="space-y-5">
                <div className="flex items-center gap-3">
                    <Link
                        href={listUrl}
                        className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                    >
                        <ArrowLeft className="size-4" />
                    </Link>
                    <div>
                        <h1 className="text-xl font-bold text-slate-900">
                            Trashed Orders
                        </h1>
                        <p className="text-sm text-slate-600">
                            {orders.total} total · deleted cancelled orders
                        </p>
                    </div>
                </div>

                <Card className="overflow-hidden p-0 shadow-sm">
                    <div className="flex gap-2 border-b border-slate-100 p-4">
                        <div className="relative flex-1">
                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                            <input
                                maxLength={100}
                                type="text"
                                placeholder="Search order number…"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                onKeyDown={(e) =>
                                    e.key === 'Enter' && applySearch()
                                }
                                className="h-9 w-full rounded-lg border border-slate-200 pr-3 pl-9 text-sm focus:border-brand focus:outline-none"
                            />
                        </div>
                        <ClearFiltersButton
                            active={Boolean(search || filters.search)}
                            onClear={clearFilters}
                        />
                    </div>

                    <div className="divide-y divide-slate-50">
                        {orders.data.length === 0 ? (
                            <p className="py-12 text-center text-sm text-slate-600">
                                No trashed orders.
                            </p>
                        ) : (
                            orders.data.map((order) => (
                                <div
                                    key={order.id}
                                    className="admin-row-card flex flex-wrap items-center gap-x-3 gap-y-2 px-4 py-3 sm:flex-nowrap sm:gap-4"
                                >
                                    <div className="w-full min-w-0 sm:w-auto sm:flex-1">
                                        <p className="font-mono text-sm font-bold text-slate-800">
                                            {order.order_number}
                                        </p>
                                        <p className="text-xs text-slate-600">
                                            {order.user?.name ?? 'Guest'} ·
                                            Deleted{' '}
                                            {new Date(
                                                order.deleted_at,
                                            ).toLocaleDateString()}
                                        </p>
                                    </div>
                                    <div className="order-last flex w-full items-center gap-2 sm:order-none sm:w-auto sm:gap-4">
                                        <p className="mr-auto text-sm font-bold text-slate-800 sm:mr-0">
                                            ৳{order.total.toLocaleString()}
                                        </p>
                                        <Badge variant="error" size="sm">
                                            {order.status}
                                        </Badge>
                                        <div className="ml-auto flex items-center gap-1.5 sm:ml-0">
                                            <button
                                                type="button"
                                                disabled={
                                                    restoringId === order.id
                                                }
                                                onClick={() =>
                                                    handleRestore(order)
                                                }
                                                className="flex items-center gap-1.5 rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 disabled:opacity-50"
                                            >
                                                <RotateCcw className="size-3.5" />
                                                {restoringId === order.id
                                                    ? 'Restoring…'
                                                    : 'Restore'}
                                            </button>
                                            {canPermanentlyDelete &&
                                                ((order.deletion_blockers ?? [])
                                                    .length === 0 ? (
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            setDeleteTarget(
                                                                order,
                                                            )
                                                        }
                                                        className="rounded-lg p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600"
                                                        title="Permanently delete"
                                                    >
                                                        <Trash2 className="size-4" />
                                                    </button>
                                                ) : (
                                                    <span
                                                        className="cursor-help rounded-lg p-1.5 text-slate-300"
                                                        title={`Can't be permanently deleted: ${(
                                                            order.deletion_blockers ??
                                                            []
                                                        )
                                                            .map(
                                                                (b) =>
                                                                    b.message,
                                                            )
                                                            .join(' ')}`}
                                                    >
                                                        <Trash2 className="size-4" />
                                                    </span>
                                                ))}
                                        </div>
                                    </div>
                                </div>
                            ))
                        )}
                    </div>

                    {orders.last_page > 1 && (
                        <div className="flex items-center justify-between border-t border-slate-100 px-4 py-3">
                            <p className="text-xs text-slate-600">
                                Page {orders.current_page} of {orders.last_page}
                            </p>
                            <div className="flex gap-1">
                                {orders.links[0]?.url && (
                                    <Link
                                        href={orders.links[0].url}
                                        className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs hover:bg-slate-50"
                                    >
                                        Prev
                                    </Link>
                                )}
                                {orders.links[orders.links.length - 1]?.url && (
                                    <Link
                                        href={
                                            orders.links[
                                                orders.links.length - 1
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

            {canPermanentlyDelete && (
                <PermanentOrderDeleteDialog
                    order={deleteTarget}
                    confirmedForThisOrder={
                        deleteTarget !== null &&
                        pendingPermanentDeletion?.order_id === deleteTarget.id
                    }
                    onClose={() => setDeleteTarget(null)}
                />
            )}
        </>
    );
}
