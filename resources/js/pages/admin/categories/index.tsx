import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Edit,
    FolderOpen,
    GripVertical,
    Plus,
    Search,
    Trash2,
} from 'lucide-react';
import { useRef, useState } from 'react';
import { BulkActionBar } from '@/components/admin/bulk-action-bar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { useBulkSelect } from '@/hooks/use-bulk-select';
import {
    destroyMany as bulkDestroyRoute,
    updateStatusMany as bulkStatusRoute,
} from '@/routes/admin/categories';
import { resolveCategoryColor } from '@/lib/category-colors';
import { CategoryIcon } from '@/lib/category-icons';
import { cn } from '@/lib/utils';

type ParentCategory = {
    id: number;
    name: string;
    slug: string;
    icon: string | null;
    color: string | null;
    is_active: boolean;
    sub_categories_count: number;
    direct_products_count: number;
};

type Props = {
    categories: ParentCategory[];
    filters: { search?: string };
};

export default function CategoriesIndex({ categories, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [optimisticOrder, setOptimisticOrder] = useState<
        ParentCategory[] | null
    >(null);
    const [draggingId, setDraggingId] = useState<number | null>(null);
    const dragIndexRef = useRef<number | null>(null);
    const [deleteTarget, setDeleteTarget] = useState<ParentCategory | null>(
        null,
    );
    const [deleting, setDeleting] = useState(false);
    const [bulkDeleting, setBulkDeleting] = useState(false);
    const [bulkConfirmOpen, setBulkConfirmOpen] = useState(false);
    const { props } = usePage<any>();
    const flash = props.flash as { success?: string } | undefined;
    const canReorder = search.trim() === '';
    const items = optimisticOrder ?? categories;

    const bulk = useBulkSelect(items.map((c) => c.id));

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
        router.get(
            route('admin.categories.index'),
            { search },
            { preserveState: true, replace: true },
        );
    };

    const confirmDelete = () => {
        if (!deleteTarget) {
            return;
        }

        setDeleting(true);
        router.delete(route('admin.categories.destroy', deleteTarget.id), {
            onFinish: () => {
                setDeleting(false);
                setDeleteTarget(null);
            },
        });
    };

    const handleDrop = (dropIndex: number) => {
        const dragIndex = dragIndexRef.current;
        dragIndexRef.current = null;
        setDraggingId(null);

        if (dragIndex === null || dragIndex === dropIndex) {
            return;
        }

        const reordered = [...items];
        const [moved] = reordered.splice(dragIndex, 1);
        reordered.splice(dropIndex, 0, moved);

        setOptimisticOrder(reordered);

        router.post(
            route('admin.categories.reorder'),
            { ids: reordered.map((cat) => cat.id) },
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => setOptimisticOrder(null),
            },
        );
    };

    return (
        <>
            <Head title="Categories — Admin" />
            <div className="space-y-5">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-bold text-slate-900">
                            Categories
                        </h1>
                        <p className="text-sm text-slate-500">
                            {items.length} top-level categories
                        </p>
                    </div>
                    <Button
                        asChild
                        size="sm"
                        className="bg-[#c8102e] text-white hover:bg-[#a50d25]"
                    >
                        <Link href={route('admin.categories.create')}>
                            <Plus className="mr-1.5 size-4" /> Add Category
                        </Link>
                    </Button>
                </div>

                {flash?.success && (
                    <div className="rounded-lg bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-700">
                        {flash.success}
                    </div>
                )}

                {/* Info banner */}
                <div className="rounded-xl border border-blue-100 bg-blue-50/60 px-4 py-3 text-xs text-blue-700">
                    Categories are top-level groupings (e.g. "Electronics").
                    Sub-categories are managed under{' '}
                    <Link
                        href={route('admin.sub-categories.index')}
                        className="font-semibold underline underline-offset-2"
                    >
                        Sub-categories
                    </Link>
                    . Drag the handle on the left to reorder how categories
                    appear on the storefront.
                </div>

                <Card className="overflow-hidden p-0 shadow-sm">
                    <div className="flex gap-2 border-b border-slate-100 p-4">
                        <div className="relative flex-1">
                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                            <input
                                type="text"
                                placeholder="Search categories…"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                onKeyDown={(e) =>
                                    e.key === 'Enter' && applySearch()
                                }
                                className="h-9 w-full rounded-lg border border-slate-200 pr-3 pl-9 text-sm focus:border-[#c8102e] focus:outline-none"
                            />
                        </div>
                        {!canReorder && (
                            <p className="flex items-center text-xs text-slate-400">
                                Clear search to drag-and-drop reorder
                            </p>
                        )}
                    </div>

                    <BulkActionBar
                        count={bulk.selected.length}
                        statusUrl={bulkStatusRoute.url()}
                        selectedIds={bulk.selected}
                        onClear={bulk.clear}
                        onDelete={() => setBulkConfirmOpen(true)}
                        deleting={bulkDeleting}
                        label="category(ies) selected"
                    />

                    {items.length > 0 && (
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
                        {items.length === 0 ? (
                            <div className="flex flex-col items-center gap-2 py-14">
                                <FolderOpen className="size-10 text-slate-200" />
                                <p className="text-sm text-slate-400">
                                    No categories found.
                                </p>
                            </div>
                        ) : (
                            items.map((cat, index) => {
                                const color = resolveCategoryColor(
                                    cat.color,
                                    index,
                                );

                                return (
                                    <div
                                        key={cat.id}
                                        draggable={canReorder}
                                        onDragStart={() => {
                                            dragIndexRef.current = index;
                                            setDraggingId(cat.id);
                                        }}
                                        onDragOver={(e) => e.preventDefault()}
                                        onDrop={() => handleDrop(index)}
                                        onDragEnd={() => {
                                            dragIndexRef.current = null;
                                            setDraggingId(null);
                                        }}
                                        className={cn(
                                            'flex items-center gap-4 px-4 py-3 hover:bg-slate-50/60',
                                            draggingId === cat.id &&
                                                'opacity-40',
                                        )}
                                    >
                                        {canReorder ? (
                                            <GripVertical className="size-4 shrink-0 cursor-grab text-slate-300 active:cursor-grabbing" />
                                        ) : (
                                            <span className="size-4 shrink-0" />
                                        )}
                                        <Checkbox
                                            checked={bulk.isSelected(cat.id)}
                                            onCheckedChange={() =>
                                                bulk.toggle(cat.id)
                                            }
                                        />
                                        <div
                                            style={
                                                {
                                                    '--category-color': color,
                                                } as React.CSSProperties
                                            }
                                            className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-[color-mix(in_srgb,var(--category-color)_12%,white)] text-[var(--category-color)]"
                                        >
                                            <CategoryIcon
                                                icon={cat.icon}
                                                className="size-4"
                                            />
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <p className="text-sm font-semibold text-slate-800">
                                                {cat.name}
                                            </p>
                                            <p className="text-xs text-slate-400">
                                                {cat.slug}
                                            </p>
                                        </div>
                                        <div className="hidden flex-col items-end gap-0.5 sm:flex">
                                            <p className="text-xs font-medium text-slate-600">
                                                {cat.sub_categories_count} sub-
                                                {cat.sub_categories_count === 1
                                                    ? 'category'
                                                    : 'categories'}
                                            </p>
                                            <p className="text-xs text-slate-400">
                                                {cat.direct_products_count}{' '}
                                                direct products
                                            </p>
                                        </div>
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
                                        <div className="flex items-center gap-1.5">
                                            <Link
                                                href={route(
                                                    'admin.categories.edit',
                                                    cat.id,
                                                )}
                                                className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                                            >
                                                <Edit className="size-4" />
                                            </Link>
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setDeleteTarget(cat)
                                                }
                                                className="rounded-lg p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600"
                                            >
                                                <Trash2 className="size-4" />
                                            </button>
                                        </div>
                                    </div>
                                );
                            })
                        )}
                    </div>
                </Card>
            </div>

            <ConfirmDialog
                open={deleteTarget !== null}
                onOpenChange={(open) => !open && setDeleteTarget(null)}
                title="Delete category?"
                description={
                    deleteTarget
                        ? deleteTarget.sub_categories_count > 0
                            ? `"${deleteTarget.name}" has ${deleteTarget.sub_categories_count} sub-categor${deleteTarget.sub_categories_count === 1 ? 'y' : 'ies'}. Deleting it will also remove them.`
                            : `This will permanently delete "${deleteTarget.name}". This cannot be undone.`
                        : undefined
                }
                onConfirm={confirmDelete}
                processing={deleting}
            />

            <ConfirmDialog
                open={bulkConfirmOpen}
                onOpenChange={setBulkConfirmOpen}
                title="Delete selected categories?"
                description={`This will permanently delete ${bulk.selected.length} category(ies), including their sub-categories. This cannot be undone.`}
                onConfirm={confirmBulkDelete}
                processing={bulkDeleting}
            />
        </>
    );
}
