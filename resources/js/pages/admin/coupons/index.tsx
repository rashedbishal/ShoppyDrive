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
import { clickableRow } from '@/lib/clickable-row';
import {
    destroyMany as bulkDestroyRoute,
    updateStatusMany as bulkStatusRoute,
} from '@/routes/admin/coupons';

type Coupon = {
    id: number;
    code: string;
    name: string;
    type: 'percent' | 'fixed';
    value: number;
    used_count: number;
    applies_to: 'all' | 'selected';
    max_uses: number | null;
    is_active: boolean;
    expires_at: string | null;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};

type Props = {
    coupons: Paginated<Coupon>;
    filters: { search?: string };
};

export default function CouponsIndex({ coupons, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [deleteTarget, setDeleteTarget] = useState<Coupon | null>(null);
    const [deleting, setDeleting] = useState(false);
    const [bulkDeleting, setBulkDeleting] = useState(false);
    const [bulkConfirmOpen, setBulkConfirmOpen] = useState(false);

    const bulk = useBulkSelect(coupons.data.map((c) => c.id));

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
            route('admin.coupons.index'),
            { search },
            { preserveState: true, replace: true },
        );
    };

    const clearFilters = () => {
        setSearch('');
        router.get(
            route('admin.coupons.index'),
            {},
            { preserveState: true, replace: true },
        );
    };

    const confirmDelete = () => {
        if (!deleteTarget) {
            return;
        }

        setDeleting(true);
        router.delete(route('admin.coupons.destroy', deleteTarget.id), {
            onFinish: () => {
                setDeleting(false);
                setDeleteTarget(null);
            },
        });
    };

    return (
        <>
            <Head title="Coupons — Admin" />
            <div className="space-y-5">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-xl font-bold text-slate-900">
                            Coupons
                        </h1>
                        <p className="text-sm text-slate-500">
                            {coupons.total} total
                        </p>
                    </div>
                    <Button
                        asChild
                        size="sm"
                        className="w-full bg-[#c8102e] text-white hover:bg-[#a50d25] sm:w-auto"
                    >
                        <Link href={route('admin.coupons.create')}>
                            <Plus className="mr-1.5 size-4" /> Add Coupon
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
                                placeholder="Search by code or name…"
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
                        label="coupon(s) selected"
                    />

                    {coupons.data.length > 0 && (
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
                        {coupons.data.length === 0 ? (
                            <p className="py-12 text-center text-sm text-slate-400">
                                No coupons found.
                            </p>
                        ) : (
                            coupons.data.map((coupon) => (
                                <div
                                    key={coupon.id}
                                    {...clickableRow(
                                        route('admin.coupons.edit', coupon.id),
                                    )}
                                    className="admin-row-card flex flex-wrap items-center gap-x-3 gap-y-2 px-4 py-3 hover:bg-slate-50/60 sm:flex-nowrap sm:gap-4"
                                >
                                    <Checkbox
                                        checked={bulk.isSelected(coupon.id)}
                                        onCheckedChange={() =>
                                            bulk.toggle(coupon.id)
                                        }
                                    />
                                    <div className="min-w-0 flex-1">
                                        <p
                                            className="truncate font-mono text-sm font-bold text-slate-800"
                                            title={coupon.code}
                                        >
                                            {coupon.code}
                                        </p>
                                        <p
                                            className="truncate text-xs text-slate-400"
                                            title={coupon.name}
                                        >
                                            {coupon.name}
                                        </p>
                                    </div>
                                    <div className="order-last flex w-full items-center justify-between gap-x-3 gap-y-1 pl-7 sm:order-none sm:w-auto sm:gap-4 sm:pl-0">
                                        <div className="text-left sm:text-right">
                                            <p className="text-sm font-semibold text-slate-800">
                                                {coupon.type === 'percent'
                                                    ? `${coupon.value}%`
                                                    : `৳${coupon.value.toLocaleString()}`}
                                            </p>
                                            <p className="text-xs text-slate-400">
                                                {coupon.applies_to ===
                                                'selected'
                                                    ? 'Selected items · '
                                                    : ''}
                                                {coupon.used_count} used
                                                {coupon.max_uses
                                                    ? ` / ${coupon.max_uses}`
                                                    : ''}
                                            </p>
                                        </div>
                                        <div className="text-right">
                                            {coupon.expires_at && (
                                                <p className="text-xs text-slate-400">
                                                    Exp:{' '}
                                                    {new Date(
                                                        coupon.expires_at,
                                                    ).toLocaleDateString()}
                                                </p>
                                            )}
                                        </div>
                                        <Badge
                                            variant={
                                                coupon.is_active
                                                    ? 'success'
                                                    : 'error'
                                            }
                                            size="sm"
                                        >
                                            {coupon.is_active
                                                ? 'Active'
                                                : 'Inactive'}
                                        </Badge>
                                    </div>
                                    <div className="flex items-center gap-1.5">
                                        <Link
                                            href={route(
                                                'admin.coupons.edit',
                                                coupon.id,
                                            )}
                                            className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                                        >
                                            <Edit className="size-4" />
                                        </Link>
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setDeleteTarget(coupon)
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

                    {coupons.last_page > 1 && (
                        <div className="flex items-center justify-between border-t border-slate-100 px-4 py-3">
                            <p className="text-xs text-slate-500">
                                Page {coupons.current_page} of{' '}
                                {coupons.last_page}
                            </p>
                            <div className="flex gap-1">
                                {coupons.links[0]?.url && (
                                    <Link
                                        href={coupons.links[0].url}
                                        className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs hover:bg-slate-50"
                                    >
                                        Prev
                                    </Link>
                                )}
                                {coupons.links[coupons.links.length - 1]
                                    ?.url && (
                                    <Link
                                        href={
                                            coupons.links[
                                                coupons.links.length - 1
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
                title="Delete coupon?"
                description={
                    deleteTarget
                        ? `This will permanently delete coupon "${deleteTarget.code}". This cannot be undone.`
                        : undefined
                }
                onConfirm={confirmDelete}
                processing={deleting}
            />

            <ConfirmDialog
                open={bulkConfirmOpen}
                onOpenChange={setBulkConfirmOpen}
                title="Delete selected coupons?"
                description={`This will permanently delete ${bulk.selected.length} coupon(s). This cannot be undone.`}
                onConfirm={confirmBulkDelete}
                processing={bulkDeleting}
            />
        </>
    );
}
