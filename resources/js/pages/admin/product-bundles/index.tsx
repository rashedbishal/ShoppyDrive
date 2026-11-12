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
import { useLiveSearch } from '@/hooks/use-live-search';
import { clickableRow } from '@/lib/clickable-row';
import {
    destroyMany as bulkDestroyRoute,
    updateStatusMany as bulkStatusRoute,
} from '@/routes/admin/product-bundles';

type ProductBundle = {
    id: number;
    name: string;
    image: string | null;
    discount_type: 'fixed_price' | 'free_delivery';
    bundle_price: number | null;
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
    bundles: Paginated<ProductBundle>;
    filters: { search?: string };
};

export default function ProductBundlesIndex({ bundles, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [deleteTarget, setDeleteTarget] = useState<ProductBundle | null>(
        null,
    );
    const [deleting, setDeleting] = useState(false);
    const [bulkDeleting, setBulkDeleting] = useState(false);
    const [bulkConfirmOpen, setBulkConfirmOpen] = useState(false);

    const bulk = useBulkSelect(bundles.data.map((b) => b.id));

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
            route('admin.product-bundles.index'),
            { search },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const clearFilters = () => {
        setSearch('');
        router.get(
            route('admin.product-bundles.index'),
            {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const confirmDelete = () => {
        if (!deleteTarget) {
            return;
        }

        setDeleting(true);
        router.delete(route('admin.product-bundles.destroy', deleteTarget.id), {
            onFinish: () => {
                setDeleting(false);
                setDeleteTarget(null);
            },
        });
    };

    // Results follow the search box as you type (Enter / the Search button still apply at once).
    useLiveSearch(search, filters.search ?? '', () => applySearch());

    return (
        <>
            <Head title="Bundles — Admin" />
            <div className="space-y-5">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-xl font-bold text-slate-900">
                            Bundles
                        </h1>
                        <p className="text-sm text-slate-500">
                            {bundles.total} total
                        </p>
                    </div>
                    <Button
                        asChild
                        size="sm"
                        className="w-full bg-[#c8102e] text-white hover:bg-[#a50d25] sm:w-auto"
                    >
                        <Link href={route('admin.product-bundles.create')}>
                            <Plus className="mr-1.5 size-4" /> Add Bundle
                        </Link>
                    </Button>
                </div>

                <Card className="overflow-hidden p-0 shadow-sm">
                    <div className="flex gap-2 border-b border-slate-100 p-4">
                        <div className="relative flex-1">
                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                            <input
                                maxLength={100}
                                type="text"
                                placeholder="Search bundles…"
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
                        label="bundle(s) selected"
                    />

                    {bundles.data.length > 0 && (
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
                        {bundles.data.length === 0 ? (
                            <p className="py-12 text-center text-sm text-slate-400">
                                No bundles found.
                            </p>
                        ) : (
                            bundles.data.map((bundle) => (
                                <div
                                    key={bundle.id}
                                    {...clickableRow(
                                        route(
                                            'admin.product-bundles.edit',
                                            bundle.id,
                                        ),
                                    )}
                                    className="admin-row-card flex flex-wrap items-center gap-x-3 gap-y-2 px-4 py-3 hover:bg-slate-50/60 sm:flex-nowrap sm:gap-4"
                                >
                                    <Checkbox
                                        checked={bulk.isSelected(bundle.id)}
                                        onCheckedChange={() =>
                                            bulk.toggle(bundle.id)
                                        }
                                    />
                                    <img
                                        src={bundle.image ?? '/placeholder.svg'}
                                        alt=""
                                        className="size-10 shrink-0 rounded-lg border border-slate-100 object-cover"
                                    />
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-semibold text-slate-800">
                                            {bundle.name}
                                        </p>
                                        <p className="text-xs text-slate-400">
                                            {bundle.discount_type ===
                                            'fixed_price'
                                                ? `Bundle price: ৳${bundle.bundle_price?.toLocaleString() ?? '—'}`
                                                : 'Free delivery'}
                                        </p>
                                    </div>
                                    <div className="order-last flex w-full items-center gap-2 pl-7 sm:order-none sm:w-auto sm:gap-4 sm:pl-0">
                                        <p className="mr-auto text-xs text-slate-500 sm:mr-0">
                                            {bundle.items_count} products
                                        </p>
                                        <Badge
                                            variant={
                                                bundle.is_active
                                                    ? 'success'
                                                    : 'error'
                                            }
                                            size="sm"
                                        >
                                            {bundle.is_active
                                                ? 'Active'
                                                : 'Inactive'}
                                        </Badge>
                                    </div>
                                    <div className="flex items-center gap-1.5">
                                        <Link
                                            href={route(
                                                'admin.product-bundles.edit',
                                                bundle.id,
                                            )}
                                            className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                                        >
                                            <Edit className="size-4" />
                                        </Link>
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setDeleteTarget(bundle)
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

                    {bundles.last_page > 1 && (
                        <div className="flex items-center justify-between border-t border-slate-100 px-4 py-3">
                            <p className="text-xs text-slate-500">
                                Page {bundles.current_page} of{' '}
                                {bundles.last_page}
                            </p>
                            <div className="flex gap-1">
                                {bundles.links[0]?.url && (
                                    <Link
                                        href={bundles.links[0].url}
                                        className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs hover:bg-slate-50"
                                    >
                                        Prev
                                    </Link>
                                )}
                                {bundles.links[bundles.links.length - 1]
                                    ?.url && (
                                    <Link
                                        href={
                                            bundles.links[
                                                bundles.links.length - 1
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
                title="Delete bundle?"
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
                title="Delete selected bundles?"
                description={`This will permanently delete ${bulk.selected.length} bundle(s). This cannot be undone.`}
                onConfirm={confirmBulkDelete}
                processing={bulkDeleting}
            />
        </>
    );
}
