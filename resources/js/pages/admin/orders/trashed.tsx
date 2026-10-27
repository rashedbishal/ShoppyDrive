import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, RotateCcw, Search, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ClearFiltersButton } from '@/components/admin/clear-filters-button';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';

type Order = {
    id: number;
    order_number: string;
    status: string;
    payment_status: string;
    total: number;
    deleted_at: string;
    user: { id: number; name: string; email: string } | null;
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
};

export default function TrashedOrders({ orders, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [restoringId, setRestoringId] = useState<number | null>(null);
    const [deleteTarget, setDeleteTarget] = useState<Order | null>(null);
    const [deleting, setDeleting] = useState(false);
    const { props } = usePage<any>();
    const flash = props.flash as
        | { success?: string; error?: string }
        | undefined;

    const applySearch = () => {
        if (search === (filters.search ?? '')) {
            return;
        }

        router.get(
            route('admin.orders.trashed'),
            { search },
            { preserveState: true, replace: true },
        );
    };

    const clearFilters = () => {
        setSearch('');
        router.get(
            route('admin.orders.trashed'),
            {},
            { preserveState: true, replace: true },
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

    const confirmForceDelete = () => {
        if (!deleteTarget) {
            return;
        }

        setDeleting(true);
        router.delete(route('admin.orders.forceDelete', deleteTarget.id), {
            onFinish: () => {
                setDeleting(false);
                setDeleteTarget(null);
            },
        });
    };

    return (
        <>
            <Head title="Trashed Orders — Admin" />
            <div className="space-y-5">
                <div className="flex items-center gap-3">
                    <Link
                        href={route('admin.orders.index')}
                        className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                    >
                        <ArrowLeft className="size-4" />
                    </Link>
                    <div>
                        <h1 className="text-xl font-bold text-slate-900">
                            Trashed Orders
                        </h1>
                        <p className="text-sm text-slate-500">
                            {orders.total} total · deleted cancelled orders
                        </p>
                    </div>
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
                    <div className="flex gap-2 border-b border-slate-100 p-4">
                        <div className="relative flex-1">
                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                            <input
                                type="text"
                                placeholder="Search order number…"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                onKeyDown={(e) =>
                                    e.key === 'Enter' && applySearch()
                                }
                                className="h-9 w-full rounded-lg border border-slate-200 pr-3 pl-9 text-sm focus:border-[#c8102e] focus:outline-none"
                            />
                        </div>
                        <ClearFiltersButton
                            active={Boolean(search || filters.search)}
                            onClear={clearFilters}
                        />
                    </div>

                    <div className="divide-y divide-slate-50">
                        {orders.data.length === 0 ? (
                            <p className="py-12 text-center text-sm text-slate-400">
                                No trashed orders.
                            </p>
                        ) : (
                            orders.data.map((order) => (
                                <div
                                    key={order.id}
                                    className="flex items-center gap-4 px-4 py-3"
                                >
                                    <div className="min-w-0 flex-1">
                                        <p className="font-mono text-sm font-bold text-slate-800">
                                            {order.order_number}
                                        </p>
                                        <p className="text-xs text-slate-400">
                                            {order.user?.name ?? 'Guest'} ·
                                            Deleted{' '}
                                            {new Date(
                                                order.deleted_at,
                                            ).toLocaleDateString()}
                                        </p>
                                    </div>
                                    <p className="hidden text-sm font-bold text-slate-800 sm:block">
                                        ৳{order.total.toLocaleString()}
                                    </p>
                                    <Badge variant="error" size="sm">
                                        {order.status}
                                    </Badge>
                                    <div className="flex items-center gap-1.5">
                                        <button
                                            type="button"
                                            disabled={restoringId === order.id}
                                            onClick={() => handleRestore(order)}
                                            className="flex items-center gap-1.5 rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 disabled:opacity-50"
                                        >
                                            <RotateCcw className="size-3.5" />
                                            {restoringId === order.id
                                                ? 'Restoring…'
                                                : 'Restore'}
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setDeleteTarget(order)
                                            }
                                            className="rounded-lg p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600"
                                            title="Permanently delete"
                                        >
                                            <Trash2 className="size-4" />
                                        </button>
                                    </div>
                                </div>
                            ))
                        )}
                    </div>

                    {orders.last_page > 1 && (
                        <div className="flex items-center justify-between border-t border-slate-100 px-4 py-3">
                            <p className="text-xs text-slate-500">
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

            <ConfirmDialog
                open={deleteTarget !== null}
                onOpenChange={(open) => !open && setDeleteTarget(null)}
                title="Permanently delete this order?"
                description={
                    deleteTarget
                        ? `This will permanently delete order "${deleteTarget.order_number}" and all its items and history. This cannot be undone.`
                        : undefined
                }
                confirmLabel="Delete Permanently"
                onConfirm={confirmForceDelete}
                processing={deleting}
            />
        </>
    );
}
