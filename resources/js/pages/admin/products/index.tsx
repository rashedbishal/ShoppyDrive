import { Head, Link, router } from '@inertiajs/react';
import { Baby, Edit, Plus, Search, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { ClearFiltersButton } from '@/components/admin/clear-filters-button';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { Select } from '@/components/ui/select';
import { useLiveSearch } from '@/hooks/use-live-search';
import { clickableRow } from '@/lib/clickable-row';
type Product = {
    id: number;
    name: string;
    sku: string | null;
    price: number;
    stock_quantity: number;
    available_stock: number;
    is_active: boolean;
    is_featured: boolean;
    cry_baby_enabled: boolean;
    category: { id: number; name: string } | null;
    brand: { id: number; name: string } | null;
    primary_image_url: string | null;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};

type Props = {
    products: Paginated<Product>;
    filters: { search?: string; status?: string; category_id?: string };
    categories: { id: number; name: string }[];
    cryBabyEnabledCount: number;
};

export default function ProductsIndex({
    products,
    filters,
    categories,
    cryBabyEnabledCount,
}: Props) {
    // Prefetching covers the form's data but not its code, which is otherwise
    // only downloaded on the first click. A failure here is harmless: the
    // click just downloads it as usual.
    useEffect(() => {
        import('./create').catch(() => {});
    }, []);

    const [search, setSearch] = useState(filters.search ?? '');
    const [deleteTarget, setDeleteTarget] = useState<{
        id: number;
        name: string;
    } | null>(null);
    const [deleting, setDeleting] = useState(false);
    const [cryBabyBulkTarget, setCryBabyBulkTarget] = useState<boolean | null>(
        null,
    );
    const [updatingCryBaby, setUpdatingCryBaby] = useState(false);

    const applyFilters = (overrides: Record<string, string>) => {
        if (
            Object.keys(overrides).length === 0 &&
            search === (filters.search ?? '')
        ) {
            return;
        }

        router.get(
            route('admin.products.index'),
            { ...filters, search, ...overrides },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const clearFilters = () => {
        setSearch('');
        router.get(
            route('admin.products.index'),
            {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const hasActiveFilters = Boolean(
        search || filters.search || filters.status || filters.category_id,
    );

    const confirmDelete = () => {
        if (!deleteTarget) {
            return;
        }

        setDeleting(true);
        router.delete(route('admin.products.destroy', deleteTarget.id), {
            onFinish: () => {
                setDeleting(false);
                setDeleteTarget(null);
            },
        });
    };

    const confirmCryBabyBulkUpdate = () => {
        if (cryBabyBulkTarget === null) {
            return;
        }

        setUpdatingCryBaby(true);
        router.patch(
            route('admin.products.cry-baby.update-all'),
            { enabled: cryBabyBulkTarget },
            {
                preserveScroll: true,
                onFinish: () => {
                    setUpdatingCryBaby(false);
                    setCryBabyBulkTarget(null);
                },
            },
        );
    };

    // Results follow the search box as you type (Enter / the Search button still apply at once).
    useLiveSearch(search, filters.search ?? '', () => applyFilters({}));

    return (
        <>
            <Head title="Products — Admin" />

            <div className="space-y-5">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-xl font-bold text-slate-900">
                            Products
                        </h1>
                        <p className="text-sm text-slate-600">
                            {products.total} total
                        </p>
                    </div>
                    <Button
                        asChild
                        size="sm"
                        className="w-full bg-brand text-brand-foreground hover:bg-brand-hover sm:w-auto"
                    >
                        <Link
                            href={route('admin.products.create')}
                            prefetch={['mount', 'hover']}
                            cacheFor="1m"
                        >
                            <Plus className="mr-1.5 size-4" /> Add Product
                        </Link>
                    </Button>
                </div>

                <Card className="flex flex-col gap-3 p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-start gap-3">
                        <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                            <Baby className="size-4" />
                        </span>
                        <div>
                            <p className="text-sm font-semibold text-slate-800">
                                Crying baby easter egg
                            </p>
                            <p className="text-xs text-slate-600">
                                On for {cryBabyEnabledCount}{' '}
                                {cryBabyEnabledCount === 1
                                    ? 'product'
                                    : 'products'}
                                . Toggle per product from its edit page.
                            </p>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() => setCryBabyBulkTarget(true)}
                        >
                            Turn on for all
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() => setCryBabyBulkTarget(false)}
                        >
                            Turn off for all
                        </Button>
                    </div>
                </Card>

                <Card className="overflow-hidden p-0 shadow-sm">
                    <div className="flex flex-wrap gap-2 border-b border-slate-100 p-4">
                        <div className="relative w-full flex-none sm:w-auto sm:min-w-[200px] sm:flex-1">
                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                            <input
                                maxLength={100}
                                type="text"
                                placeholder="Search products…"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                onKeyDown={(e) =>
                                    e.key === 'Enter' && applyFilters({})
                                }
                                className="h-9 w-full rounded-lg border border-slate-200 pr-3 pl-9 text-sm focus:border-brand focus:outline-none"
                            />
                        </div>
                        <Select
                            value={filters.status ?? ''}
                            onChange={(e) =>
                                applyFilters({ status: e.target.value })
                            }
                            containerClassName="min-w-0 flex-1 sm:min-w-40 sm:flex-none"
                            selectClassName="h-9 rounded-lg border border-slate-200 px-3 text-sm focus:outline-none"
                        >
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </Select>
                        <Select
                            value={filters.category_id ?? ''}
                            onChange={(e) =>
                                applyFilters({ category_id: e.target.value })
                            }
                            containerClassName="min-w-0 flex-1 sm:min-w-40 sm:flex-none"
                            selectClassName="h-9 rounded-lg border border-slate-200 px-3 text-sm focus:outline-none"
                        >
                            <option value="">All Categories</option>
                            {categories.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.name}
                                </option>
                            ))}
                        </Select>
                        <ClearFiltersButton
                            active={hasActiveFilters}
                            onClear={clearFilters}
                        />
                    </div>

                    <div className="divide-y divide-slate-50">
                        {products.data.length === 0 ? (
                            <p className="py-12 text-center text-sm text-slate-600">
                                No products found.
                            </p>
                        ) : (
                            products.data.map((p) => (
                                <div
                                    key={p.id}
                                    {...clickableRow(
                                        route('admin.products.edit', p.id),
                                    )}
                                    className="admin-row-card flex flex-wrap items-center gap-x-3 gap-y-2 px-4 py-3 hover:bg-slate-50/60 sm:flex-nowrap sm:gap-4"
                                >
                                    <img
                                        src={
                                            p.primary_image_url ??
                                            '/placeholder.svg'
                                        }
                                        alt=""
                                        className="size-10 shrink-0 rounded-lg border border-slate-100 object-cover"
                                    />
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-semibold text-slate-800">
                                            {p.name}
                                        </p>
                                        <p className="text-xs text-slate-600">
                                            {p.sku ?? '—'} ·{' '}
                                            {p.category?.name ?? 'No category'}
                                        </p>
                                    </div>
                                    <div className="order-last flex w-full items-center justify-between gap-3 sm:order-none sm:w-auto sm:gap-4">
                                        <div className="flex items-baseline gap-2 sm:block sm:text-right">
                                            <p className="text-sm font-bold text-slate-800">
                                                ৳{p.price.toLocaleString()}
                                            </p>
                                            <p className="text-xs text-slate-600">
                                                Stock: {p.available_stock}
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            {p.cry_baby_enabled && (
                                                <span
                                                    title="Crying baby easter egg is on"
                                                    className="flex size-6 items-center justify-center rounded-full bg-amber-50 text-amber-600"
                                                >
                                                    <Baby className="size-3.5" />
                                                </span>
                                            )}
                                            <Badge
                                                variant={
                                                    p.is_active
                                                        ? 'success'
                                                        : 'error'
                                                }
                                                size="sm"
                                            >
                                                {p.is_active
                                                    ? 'Active'
                                                    : 'Inactive'}
                                            </Badge>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-1.5">
                                        <Link
                                            href={route(
                                                'admin.products.edit',
                                                p.id,
                                            )}
                                            className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                                        >
                                            <Edit className="size-4" />
                                        </Link>
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setDeleteTarget({
                                                    id: p.id,
                                                    name: p.name,
                                                })
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

                    {products.last_page > 1 && (
                        <div className="flex items-center justify-between border-t border-slate-100 px-4 py-3">
                            <p className="text-xs text-slate-600">
                                Page {products.current_page} of{' '}
                                {products.last_page}
                            </p>
                            <div className="flex gap-1">
                                {products.links[0]?.url && (
                                    <Link
                                        href={products.links[0].url}
                                        className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs hover:bg-slate-50"
                                    >
                                        Prev
                                    </Link>
                                )}
                                {products.links[products.links.length - 1]
                                    ?.url && (
                                    <Link
                                        href={
                                            products.links[
                                                products.links.length - 1
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
                title="Delete product?"
                description={
                    deleteTarget
                        ? `This will permanently delete "${deleteTarget.name}". This cannot be undone.`
                        : undefined
                }
                onConfirm={confirmDelete}
                processing={deleting}
            />

            <ConfirmDialog
                open={cryBabyBulkTarget !== null}
                onOpenChange={(open) => !open && setCryBabyBulkTarget(null)}
                title={
                    cryBabyBulkTarget
                        ? 'Turn on crying baby for all products?'
                        : 'Turn off crying baby for all products?'
                }
                description={
                    cryBabyBulkTarget
                        ? 'Every product page will show the crying-baby GIF beside Add to Cart. You can still turn it off per product afterwards.'
                        : 'The crying-baby GIF will be removed from every product page. You can still turn it on per product afterwards.'
                }
                confirmLabel={
                    cryBabyBulkTarget ? 'Turn on for all' : 'Turn off for all'
                }
                processingLabel={
                    cryBabyBulkTarget ? 'Turning on…' : 'Turning off…'
                }
                onConfirm={confirmCryBabyBulkUpdate}
                processing={updatingCryBaby}
            />
        </>
    );
}
