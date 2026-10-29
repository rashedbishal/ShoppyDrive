import { Head, Link, router } from '@inertiajs/react';
import { Edit, Plus, Search, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { BulkActionBar } from '@/components/admin/bulk-action-bar';
import { ClearFiltersButton } from '@/components/admin/clear-filters-button';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { useBulkSelect } from '@/hooks/use-bulk-select';
import {
    destroyMany as bulkDestroyRoute,
    updateStatusMany as bulkStatusRoute,
} from '@/routes/admin/flash-sales';

type FlashSale = {
    id: number;
    name: string;
    starts_at: string;
    ends_at: string;
    is_active: boolean;
    items_count: number;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};

type Props = {
    flashSales: Paginated<FlashSale>;
    filters: { search?: string };
};

export default function FlashSalesIndex({ flashSales, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [deleteTarget, setDeleteTarget] = useState<FlashSale | null>(null);
    const [deleting, setDeleting] = useState(false);
    const [bulkDeleting, setBulkDeleting] = useState(false);
    const [bulkConfirmOpen, setBulkConfirmOpen] = useState(false);

    const bulk = useBulkSelect(flashSales.data.map((s) => s.id));

    const confirmBulkDelete = () => {
        setBulkDeleting(true);
        router.delete(bulkDestroyRoute.url(), {
            data: { ids: bulk.selected },
            onFinish: () => {
                setBulkDeleting(false);
                setBulkConfirmOpen(false);
                bulk.clear();
            },
        });
    };

    const applySearch = () => {
        if (search === (filters.search ?? '')) {
            return;
        }

        router.get(
            route('admin.flash-sales.index'),
            { search },
            { preserveState: true, replace: true },
        );
    };

    const clearFilters = () => {
        setSearch('');
        router.get(
            route('admin.flash-sales.index'),
            {},
            { preserveState: true, replace: true },
        );
    };

    const confirmDelete = () => {
        if (!deleteTarget) {
            return;
        }

        setDeleting(true);
        router.delete(route('admin.flash-sales.destroy', deleteTarget.id), {
            onFinish: () => {
                setDeleting(false);
                setDeleteTarget(null);
            },
        });
    };

    const isLive = (sale: FlashSale) => {
        // eslint-disable-next-line react-hooks/purity -- live status is intentionally evaluated against the current time on each render
        const now = Date.now();

        return (
            sale.is_active &&
            new Date(sale.starts_at).getTime() <= now &&
            new Date(sale.ends_at).getTime() >= now
        );
    };

    return (
        <>
            <Head title="Flash Sales — Admin" />
            <div className="space-y-5">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-bold text-slate-900">
                            Flash Sales
                        </h1>
                        <p className="text-sm text-slate-500">
                            {flashSales.total} total
                        </p>
                    </div>
                    <Button
                        asChild
                        size="sm"
                        className="bg-[#c8102e] text-white hover:bg-[#a50d25]"
                    >
                        <Link href={route('admin.flash-sales.create')}>
                            <Plus className="mr-1.5 size-4" /> Add Flash Sale
                        </Link>
                    </Button>
                </div>

                <Card className="overflow-hidden p-0 shadow-sm">
                    <div className="flex gap-2 border-b border-slate-100 p-4">
                        <div className="relative flex-1">
                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                            <input
                                type="text"
                                placeholder="Search flash sales…"
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

                    <BulkActionBar
                        count={bulk.selected.length}
                        statusUrl={bulkStatusRoute.url()}
                        selectedIds={bulk.selected}
                        onClear={bulk.clear}
                        onDelete={() => setBulkConfirmOpen(true)}
                        deleting={bulkDeleting}
                        label="flash sale(s) selected"
                    />

                    {flashSales.data.length > 0 && (
                        <div className="flex items-center gap-3 border-b border-slate-100 px-4 py-2">
                            <Checkbox
                                checked={bulk.allSelected}
                                onCheckedChange={bulk.toggleAll}
                            />
                            <span className="text-xs font-medium text-slate-500">
                                Select all
                            </span>
                        </div>
                    )}

                    <div className="divide-y divide-slate-50">
                        {flashSales.data.length === 0 ? (
                            <p className="py-12 text-center text-sm text-slate-400">
                                No flash sales found.
                            </p>
                        ) : (
                            flashSales.data.map((sale) => (
                                <div
                                    key={sale.id}
                                    className="flex items-center gap-4 px-4 py-3 hover:bg-slate-50/60"
                                >
                                    <Checkbox
                                        checked={bulk.isSelected(sale.id)}
                                        onCheckedChange={() =>
                                            bulk.toggle(sale.id)
                                        }
                                    />
                                    <div className="min-w-0 flex-1">
                                        <p className="text-sm font-semibold text-slate-800">
                                            {sale.name}
                                        </p>
                                        <p className="text-xs text-slate-400">
                                            {new Date(
                                                sale.starts_at,
                                            ).toLocaleString()}{' '}
                                            →{' '}
                                            {new Date(
                                                sale.ends_at,
                                            ).toLocaleString()}
                                        </p>
                                    </div>
                                    <p className="hidden text-xs text-slate-500 sm:block">
                                        {sale.items_count} products
                                    </p>
                                    {isLive(sale) && (
                                        <Badge variant="success" size="sm">
                                            Live
                                        </Badge>
                                    )}
                                    <Badge
                                        variant={
                                            sale.is_active ? 'success' : 'error'
                                        }
                                        size="sm"
                                    >
                                        {sale.is_active ? 'Active' : 'Inactive'}
                                    </Badge>
                                    <div className="flex items-center gap-1.5">
                                        <Link
                                            href={route(
                                                'admin.flash-sales.edit',
                                                sale.id,
                                            )}
                                            className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                                        >
                                            <Edit className="size-4" />
                                        </Link>
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setDeleteTarget(sale)
                                            }
                                            className="rounded-lg p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600"
                                        >
                                            <Trash2 className="size-4" />
                                        </button>
                                    </div>
                                </div>
                            ))
                        )}
                    </div>

                    {flashSales.last_page > 1 && (
                        <div className="flex items-center justify-between border-t border-slate-100 px-4 py-3">
                            <p className="text-xs text-slate-500">
                                Page {flashSales.current_page} of{' '}
                                {flashSales.last_page}
                            </p>
                            <div className="flex gap-1">
                                {flashSales.links[0]?.url && (
                                    <Link
                                        href={flashSales.links[0].url}
                                        className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs hover:bg-slate-50"
                                    >
                                        Prev
                                    </Link>
                                )}
                                {flashSales.links[flashSales.links.length - 1]
                                    ?.url && (
                                    <Link
                                        href={
                                            flashSales.links[
                                                flashSales.links.length - 1
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
                title="Delete flash sale?"
                description={
                    deleteTarget
                        ? `This will permanently delete "${deleteTarget.name}". This cannot be undone.`
                        : undefined
                }
                onConfirm={confirmDelete}
                processing={deleting}
            />

            <ConfirmDialog
                open={bulkConfirmOpen}
                onOpenChange={setBulkConfirmOpen}
                title="Delete selected flash sales?"
                description={`This will permanently delete ${bulk.selected.length} flash sale(s). This cannot be undone.`}
                onConfirm={confirmBulkDelete}
                processing={bulkDeleting}
            />
        </>
    );
}
