import { Head, Link, router, usePage } from '@inertiajs/react';
import { Edit, Plus, Search, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { BulkActionBar } from '@/components/admin/bulk-action-bar';
import { ClearFiltersButton } from '@/components/admin/clear-filters-button';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { Select } from '@/components/ui/select';
import { useBulkSelect } from '@/hooks/use-bulk-select';
import {
    destroyMany as bulkDestroyRoute,
    updateStatusMany as bulkStatusRoute,
} from '@/routes/admin/brands';

type Brand = {
    id: number;
    name: string;
    slug: string;
    logo: string | null;
    is_active: boolean;
    products_count: number;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};

type Props = {
    brands: Paginated<Brand>;
    filters: { search?: string; status?: string };
};

export default function BrandsIndex({ brands, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [deleteTarget, setDeleteTarget] = useState<Brand | null>(null);
    const [deleting, setDeleting] = useState(false);
    const [bulkDeleting, setBulkDeleting] = useState(false);
    const [bulkConfirmOpen, setBulkConfirmOpen] = useState(false);
    const { props } = usePage<any>();
    const flash = props.flash as { success?: string } | undefined;

    const bulk = useBulkSelect(brands.data.map((b) => b.id));

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

    const applyFilters = (overrides: Record<string, string>) => {
        if (
            Object.keys(overrides).length === 0 &&
            search === (filters.search ?? '')
        ) {
            return;
        }

        router.get(
            route('admin.brands.index'),
            { ...filters, search, ...overrides },
            { preserveState: true, replace: true },
        );
    };

    const clearFilters = () => {
        setSearch('');
        router.get(
            route('admin.brands.index'),
            {},
            { preserveState: true, replace: true },
        );
    };

    const hasActiveFilters = Boolean(
        search || filters.search || filters.status,
    );

    const confirmDelete = () => {
        if (!deleteTarget) {
            return;
        }

        setDeleting(true);
        router.delete(route('admin.brands.destroy', deleteTarget.id), {
            onFinish: () => {
                setDeleting(false);
                setDeleteTarget(null);
            },
        });
    };

    return (
        <>
            <Head title="Brands — Admin" />
            <div className="space-y-5">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-bold text-slate-900">
                            Brands
                        </h1>
                        <p className="text-sm text-slate-500">
                            {brands.total} total
                        </p>
                    </div>
                    <Button
                        asChild
                        size="sm"
                        className="bg-[#c8102e] text-white hover:bg-[#a50d25]"
                    >
                        <Link href={route('admin.brands.create')}>
                            <Plus className="mr-1.5 size-4" /> Add Brand
                        </Link>
                    </Button>
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
                                placeholder="Search brands…"
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
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </Select>
                        <ClearFiltersButton
                            active={hasActiveFilters}
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
                        label="brand(s) selected"
                    />

                    {brands.data.length > 0 && (
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
                        {brands.data.length === 0 ? (
                            <p className="py-12 text-center text-sm text-slate-400">
                                No brands found.
                            </p>
                        ) : (
                            brands.data.map((brand) => (
                                <div
                                    key={brand.id}
                                    className="flex items-center gap-4 px-4 py-3 hover:bg-slate-50/60"
                                >
                                    <Checkbox
                                        checked={bulk.isSelected(brand.id)}
                                        onCheckedChange={() =>
                                            bulk.toggle(brand.id)
                                        }
                                    />
                                    {brand.logo ? (
                                        <img
                                            src={brand.logo}
                                            alt={brand.name}
                                            className="size-9 rounded-lg border border-slate-100 bg-white object-contain p-1"
                                        />
                                    ) : (
                                        <div className="flex size-9 items-center justify-center rounded-lg bg-slate-100 text-xs font-bold text-slate-400">
                                            {brand.name
                                                .slice(0, 2)
                                                .toUpperCase()}
                                        </div>
                                    )}
                                    <div className="min-w-0 flex-1">
                                        <p className="text-sm font-semibold text-slate-800">
                                            {brand.name}
                                        </p>
                                        <p className="text-xs text-slate-400">
                                            {brand.slug} ·{' '}
                                            {brand.products_count} products
                                        </p>
                                    </div>
                                    <Badge
                                        variant={
                                            brand.is_active
                                                ? 'success'
                                                : 'error'
                                        }
                                        size="sm"
                                    >
                                        {brand.is_active
                                            ? 'Active'
                                            : 'Inactive'}
                                    </Badge>
                                    <div className="flex items-center gap-1.5">
                                        <Link
                                            href={route(
                                                'admin.brands.edit',
                                                brand.id,
                                            )}
                                            className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                                        >
                                            <Edit className="size-4" />
                                        </Link>
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setDeleteTarget(brand)
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

                    {brands.last_page > 1 && (
                        <div className="flex items-center justify-between border-t border-slate-100 px-4 py-3">
                            <p className="text-xs text-slate-500">
                                Page {brands.current_page} of {brands.last_page}
                            </p>
                            <div className="flex gap-1">
                                {brands.links[0]?.url && (
                                    <Link
                                        href={brands.links[0].url}
                                        className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs hover:bg-slate-50"
                                    >
                                        Prev
                                    </Link>
                                )}
                                {brands.links[brands.links.length - 1]?.url && (
                                    <Link
                                        href={
                                            brands.links[
                                                brands.links.length - 1
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
                title="Delete brand?"
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
                title="Delete selected brands?"
                description={`This will permanently delete ${bulk.selected.length} brand(s). This cannot be undone.`}
                onConfirm={confirmBulkDelete}
                processing={bulkDeleting}
            />
        </>
    );
}
