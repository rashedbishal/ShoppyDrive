import { Head, Link, router } from '@inertiajs/react';
import { ArrowRight, PhoneMissed, Search, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ClearFiltersButton } from '@/components/admin/clear-filters-button';
import { Card } from '@/components/ui/card';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';

type IncompleteOrderItem = {
    product_id: number;
    variant_id: number | null;
    product_name: string;
    sku: string | null;
    price: number;
    quantity: number;
    subtotal: number;
    image: string | null;
    variant_attributes: Record<string, string> | null;
};

type IncompleteOrder = {
    id: number;
    name: string | null;
    phone: string;
    items: IncompleteOrderItem[];
    subtotal: number;
    created_at: string;
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
    incompleteOrders: Paginated<IncompleteOrder>;
    filters: { search?: string };
};

export default function IncompleteOrdersIndex({
    incompleteOrders,
    filters,
}: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [deleteTarget, setDeleteTarget] = useState<IncompleteOrder | null>(
        null,
    );
    const [deleting, setDeleting] = useState(false);
    const [deletingAll, setDeletingAll] = useState(false);
    const [confirmDeleteAll, setConfirmDeleteAll] = useState(false);

    const applySearch = () => {
        if (search === (filters.search ?? '')) {
            return;
        }

        router.get(
            route('admin.orders.incomplete.index'),
            { search },
            { preserveState: true, replace: true },
        );
    };

    const clearFilters = () => {
        setSearch('');
        router.get(
            route('admin.orders.incomplete.index'),
            {},
            { preserveState: true, replace: true },
        );
    };

    const confirmDelete = () => {
        if (!deleteTarget) {
            return;
        }

        setDeleting(true);
        router.delete(
            route('admin.orders.incomplete.destroy', deleteTarget.id),
            {
                onFinish: () => {
                    setDeleting(false);
                    setDeleteTarget(null);
                },
            },
        );
    };

    const handleDeleteAll = () => {
        setDeletingAll(true);
        router.delete(route('admin.orders.incomplete.destroyAll'), {
            onFinish: () => {
                setDeletingAll(false);
                setConfirmDeleteAll(false);
            },
        });
    };

    return (
        <>
            <Head title="Incomplete Orders — Admin" />
            <div className="space-y-5">
                <div className="flex items-center justify-between gap-3">
                    <div className="min-w-0">
                        <h1 className="text-xl font-bold text-slate-900">
                            Incomplete Orders
                        </h1>
                        <p className="text-sm text-slate-500">
                            {incompleteOrders.total} total · phone number
                            entered at checkout, order never placed
                        </p>
                    </div>
                    {incompleteOrders.total > 0 && (
                        <button
                            type="button"
                            onClick={() => setConfirmDeleteAll(true)}
                            className="flex shrink-0 items-center gap-1.5 rounded-lg border border-red-200 px-3 py-2 text-xs font-semibold whitespace-nowrap text-red-600 hover:bg-red-50"
                        >
                            <Trash2 className="size-3.5" />
                            Delete All
                        </button>
                    )}
                </div>

                <Card className="overflow-hidden p-0 shadow-sm">
                    <div className="flex gap-2 border-b border-slate-100 p-4">
                        <div className="relative flex-1">
                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                            <input
                                maxLength={100}
                                type="text"
                                placeholder="Search phone or name…"
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
                        {incompleteOrders.data.length === 0 ? (
                            <div className="flex flex-col items-center gap-2 py-16 text-center">
                                <PhoneMissed className="size-8 text-slate-300" />
                                <p className="text-sm text-slate-400">
                                    No incomplete orders right now.
                                </p>
                            </div>
                        ) : (
                            incompleteOrders.data.map((incomplete) => (
                                <div
                                    key={incomplete.id}
                                    className="admin-row-card flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center"
                                >
                                    <div className="min-w-0 sm:w-48 sm:shrink-0">
                                        <p className="font-mono text-sm font-bold text-slate-800">
                                            {incomplete.phone}
                                        </p>
                                        <p className="text-xs text-slate-400">
                                            {incomplete.name || (
                                                <span className="text-slate-300 italic">
                                                    No name provided
                                                </span>
                                            )}
                                        </p>
                                    </div>

                                    <div className="min-w-0 flex-1 space-y-1">
                                        {incomplete.items.map((item, index) => (
                                            <div
                                                key={index}
                                                className="flex items-center justify-between gap-2 text-xs"
                                            >
                                                <span className="min-w-0 truncate text-slate-700">
                                                    {item.product_name}
                                                    {item.variant_attributes && (
                                                        <span className="text-slate-400">
                                                            {' '}
                                                            (
                                                            {Object.entries(
                                                                item.variant_attributes,
                                                            )
                                                                .map(
                                                                    ([k, v]) =>
                                                                        `${k}: ${v}`,
                                                                )
                                                                .join(', ')}
                                                            )
                                                        </span>
                                                    )}
                                                </span>
                                                <span className="shrink-0 text-slate-400">
                                                    ×{item.quantity}
                                                </span>
                                            </div>
                                        ))}
                                    </div>

                                    <div className="flex items-center justify-between gap-3 sm:contents">
                                        <p className="shrink-0 text-xs text-slate-400 sm:w-28">
                                            {new Date(
                                                incomplete.created_at,
                                            ).toLocaleDateString()}
                                        </p>

                                        <div className="flex shrink-0 items-center gap-1.5">
                                            <Link
                                                href={route(
                                                    'admin.orders.incomplete.convert',
                                                    incomplete.id,
                                                )}
                                                className="flex items-center gap-1.5 rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50"
                                            >
                                                Convert
                                                <ArrowRight className="size-3.5" />
                                            </Link>
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setDeleteTarget(incomplete)
                                                }
                                                className="rounded-lg p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600"
                                                title="Delete"
                                            >
                                                <Trash2 className="size-4" />
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            ))
                        )}
                    </div>

                    {incompleteOrders.last_page > 1 && (
                        <div className="flex items-center justify-between border-t border-slate-100 px-4 py-3">
                            <p className="text-xs text-slate-500">
                                Page {incompleteOrders.current_page} of{' '}
                                {incompleteOrders.last_page}
                            </p>
                            <div className="flex gap-1">
                                {incompleteOrders.links[0]?.url && (
                                    <Link
                                        href={incompleteOrders.links[0].url}
                                        className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs hover:bg-slate-50"
                                    >
                                        Prev
                                    </Link>
                                )}
                                {incompleteOrders.links[
                                    incompleteOrders.links.length - 1
                                ]?.url && (
                                    <Link
                                        href={
                                            incompleteOrders.links[
                                                incompleteOrders.links.length -
                                                    1
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
                title="Delete this incomplete order?"
                description={
                    deleteTarget
                        ? `This will remove the tracked attempt for "${deleteTarget.phone}". This cannot be undone.`
                        : undefined
                }
                confirmLabel="Delete"
                onConfirm={confirmDelete}
                processing={deleting}
            />

            <ConfirmDialog
                open={confirmDeleteAll}
                onOpenChange={setConfirmDeleteAll}
                title="Delete all incomplete orders?"
                description={`This will permanently delete all ${incompleteOrders.total} tracked incomplete order(s). This cannot be undone.`}
                confirmLabel="Delete All"
                onConfirm={handleDeleteAll}
                processing={deletingAll}
            />
        </>
    );
}
