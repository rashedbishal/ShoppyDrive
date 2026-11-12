import { Head, Link, router } from '@inertiajs/react';
import { Edit, Plus, Search, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { AdminThumbnail } from '@/components/admin/admin-thumbnail';
import { BulkActionBar } from '@/components/admin/bulk-action-bar';
import { ClearFiltersButton } from '@/components/admin/clear-filters-button';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { Select } from '@/components/ui/select';
import { useBulkSelect } from '@/hooks/use-bulk-select';
import { clickableRow } from '@/lib/clickable-row';
import {
    destroyMany as bulkDestroyRoute,
    updateStatusMany as bulkStatusRoute,
} from '@/routes/admin/sub-categories';

type Category = {
    id: number;
    name: string;
    slug: string;
    image: string | null;
    sort_order: number;
    is_active: boolean;
    category: { id: number; name: string } | null;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};

type Props = {
    subCategories: Paginated<Category>;
    filters: { search?: string; status?: string };
};

export default function SubCategoriesIndex({ subCategories, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [deleteTarget, setDeleteTarget] = useState<Category | null>(null);
    const [deleting, setDeleting] = useState(false);
    const [bulkDeleting, setBulkDeleting] = useState(false);
    const [bulkConfirmOpen, setBulkConfirmOpen] = useState(false);

    const bulk = useBulkSelect(subCategories.data.map((c) => c.id));

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
            route('admin.sub-categories.index'),
            { ...filters, search, ...overrides },
            { preserveState: true, replace: true },
        );
    };

    const clearFilters = () => {
        setSearch('');
        router.get(
            route('admin.sub-categories.index'),
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
        router.delete(route('admin.sub-categories.destroy', deleteTarget.id), {
            onFinish: () => {
                setDeleting(false);
                setDeleteTarget(null);
            },
        });
    };

    return (
        <>
            <Head title="Sub-categories — Admin" />
            <div className="space-y-5">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-xl font-bold text-slate-900">
                            Sub-categories
                        </h1>
                        <p className="text-sm text-slate-500">
                            {subCategories.total} total
                        </p>
                    </div>
                    <Button
                        asChild
                        size="sm"
                        className="w-full bg-[#c8102e] text-white hover:bg-[#a50d25] sm:w-auto"
                    >
                        <Link href={route('admin.sub-categories.create')}>
                            <Plus className="mr-1.5 size-4" /> Add Sub-category
                        </Link>
                    </Button>
                </div>

                <Card className="overflow-hidden p-0 shadow-sm">
                    <div className="flex flex-wrap gap-2 border-b border-slate-100 p-4">
                        <div className="relative min-w-[200px] flex-1">
                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                            <input
                                maxLength={100}
                                type="text"
                                placeholder="Search sub-categories…"
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
                        label="sub-category(ies) selected"
                    />

                    {subCategories.data.length > 0 && (
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
                        {subCategories.data.length === 0 ? (
                            <p className="py-12 text-center text-sm text-slate-400">
                                No sub-categories found.
                            </p>
                        ) : (
                            subCategories.data.map((cat) => (
                                <div
                                    key={cat.id}
                                    {...clickableRow(
                                        route(
                                            'admin.sub-categories.edit',
                                            cat.id,
                                        ),
                                    )}
                                    className="admin-row-card flex flex-wrap items-center gap-x-3 gap-y-2 px-4 py-3 hover:bg-slate-50/60 sm:flex-nowrap sm:gap-4"
                                >
                                    <Checkbox
                                        checked={bulk.isSelected(cat.id)}
                                        onCheckedChange={() =>
                                            bulk.toggle(cat.id)
                                        }
                                    />
                                    <AdminThumbnail
                                        image={cat.image}
                                        name={cat.name}
                                    />
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-semibold text-slate-800">
                                            {cat.name}
                                        </p>
                                        <p className="text-xs text-slate-400">
                                            {cat.slug}
                                            {cat.category
                                                ? ` · category: ${cat.category.name}`
                                                : ''}
                                        </p>
                                    </div>
                                    <div className="order-last w-full pl-7 sm:order-none sm:w-auto sm:pl-0">
                                        <Badge
                                            variant={
                                                cat.is_active
                                                    ? 'success'
                                                    : 'error'
                                            }
                                            size="sm"
                                        >
                                            {cat.is_active
                                                ? 'Active'
                                                : 'Inactive'}
                                        </Badge>
                                    </div>
                                    <div className="flex items-center gap-1.5">
                                        <Link
                                            href={route(
                                                'admin.sub-categories.edit',
                                                cat.id,
                                            )}
                                            className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                                        >
                                            <Edit className="size-4" />
                                        </Link>
                                        <button
                                            type="button"
                                            onClick={() => setDeleteTarget(cat)}
                                            className="rounded-lg p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600"
                                        >
                                            <Trash2 className="size-4" />
                                        </button>
                                    </div>
                                </div>
                            ))
                        )}
                    </div>

                    {subCategories.last_page > 1 && (
                        <div className="flex items-center justify-between border-t border-slate-100 px-4 py-3">
                            <p className="text-xs text-slate-500">
                                Page {subCategories.current_page} of{' '}
                                {subCategories.last_page}
                            </p>
                            <div className="flex gap-1">
                                {subCategories.links[0]?.url && (
                                    <Link
                                        href={subCategories.links[0].url}
                                        className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs hover:bg-slate-50"
                                    >
                                        Prev
                                    </Link>
                                )}
                                {subCategories.links[
                                    subCategories.links.length - 1
                                ]?.url && (
                                    <Link
                                        href={
                                            subCategories.links[
                                                subCategories.links.length - 1
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
                title="Delete sub-category?"
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
                title="Delete selected sub-categories?"
                description={`This will permanently delete ${bulk.selected.length} sub-category(ies). This cannot be undone.`}
                onConfirm={confirmBulkDelete}
                processing={bulkDeleting}
            />
        </>
    );
}
