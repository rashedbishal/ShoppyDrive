import { Head, Link, router, useForm, usePoll } from '@inertiajs/react';
import {
    Boxes,
    Minus,
    PackageCheck,
    PackageX,
    Plus,
    Search,
    TriangleAlert,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { ClearFiltersButton } from '@/components/admin/clear-filters-button';
import { productInputClass } from '@/components/admin/product-form-field';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { CharCount } from '@/components/ui/char-count';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Select } from '@/components/ui/select';
import { useLiveSearch } from '@/hooks/use-live-search';

type Variant = {
    id: number;
    sku: string | null;
    price: number;
    stock_quantity: number;
    low_stock_threshold: number | null;
    label: string;
};

type StockProduct = {
    id: number;
    name: string;
    sku: string | null;
    price: number;
    is_active: boolean;
    stock_quantity: number;
    low_stock_threshold: number | null;
    category: string | null;
    sub_category: string | null;
    image: string | null;
    variants: Variant[];
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};

type Props = {
    products: Paginated<StockProduct>;
    filters: {
        search?: string;
        category_id?: string;
        sub_category_id?: string;
    };
    categories: { id: number; name: string }[];
    subCategories: { id: number; name: string; category_id: number }[];
    canAdjust: boolean;
};

/** One table row: a product without variants, or one variant of a product. */
type StockRow = {
    key: string;
    productId: number;
    variantId: number | null;
    name: string;
    variantLabel: string | null;
    sku: string | null;
    price: number;
    isActive: boolean;
    stock: number;
    threshold: number | null;
    image: string | null;
    category: string | null;
};

type Direction = 'add' | 'remove';

type Target = {
    productId: number;
    variantId: number | null;
    direction: Direction;
};

/** How often the page re-reads stock (owner decision: polling, every 5 seconds). */
const POLL_MS = 5000;

function toRows(products: StockProduct[]): StockRow[] {
    return products.flatMap((p): StockRow[] => {
        const base = {
            productId: p.id,
            name: p.name,
            isActive: p.is_active,
            image: p.image,
            category:
                [p.category, p.sub_category].filter(Boolean).join(' › ') ||
                null,
        };

        if (p.variants.length === 0) {
            return [
                {
                    ...base,
                    key: `p-${p.id}`,
                    variantId: null,
                    variantLabel: null,
                    sku: p.sku,
                    price: p.price,
                    stock: p.stock_quantity,
                    threshold: p.low_stock_threshold,
                },
            ];
        }

        return p.variants.map((v) => ({
            ...base,
            key: `v-${v.id}`,
            variantId: v.id,
            variantLabel: v.label,
            sku: v.sku ?? p.sku,
            price: v.price,
            stock: v.stock_quantity,
            threshold: v.low_stock_threshold,
        }));
    });
}

function stockTone(row: StockRow): 'out' | 'low' | 'ok' {
    if (row.stock <= 0) {
        return 'out';
    }

    if (row.threshold !== null && row.stock <= row.threshold) {
        return 'low';
    }

    return 'ok';
}

function StockBadge({ row }: { row: StockRow }) {
    const tone = stockTone(row);
    const styles = {
        out: 'bg-red-50 text-red-700 ring-red-200',
        low: 'bg-amber-50 text-amber-700 ring-amber-200',
        ok: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    }[tone];
    const label = { out: 'Out of stock', low: 'Low stock', ok: 'In stock' }[
        tone
    ];

    return (
        <div className="flex flex-col items-start gap-0.5 sm:items-end">
            <span
                className={`inline-flex min-w-12 justify-center rounded-lg px-2.5 py-1 text-base font-bold tabular-nums ring-1 ${styles}`}
            >
                {row.stock.toLocaleString()}
            </span>
            <span className="text-[11px] text-slate-500">{label}</span>
        </div>
    );
}

type ShownAdjustment = { target: Target; row: StockRow };

function AdjustDialog({
    target,
    row,
    onClose,
}: {
    target: Target | null;
    row: StockRow | null;
    onClose: () => void;
}) {
    const open = target !== null && row !== null;

    // Keep showing the last row while the dialog animates closed (clearing it
    // with `target` emptied the dialog mid-animation, so only its title
    // flashed), and give every opening a fresh form via `key`, so nothing
    // typed last time survives even when it is reopened while still closing.
    const [shown, setShown] = useState<ShownAdjustment | null>(null);
    const [opening, setOpening] = useState(0);

    if (open && (shown?.target !== target || shown?.row !== row)) {
        if (shown?.target !== target) {
            setOpening((count) => count + 1);
        }

        setShown({ target, row });
    }

    return (
        <AdjustDialogBody
            key={opening}
            open={open}
            shown={shown}
            onClose={onClose}
        />
    );
}

function AdjustDialogBody({
    open,
    shown,
    onClose,
}: {
    open: boolean;
    shown: ShownAdjustment | null;
    onClose: () => void;
}) {
    const form = useForm({
        product_id: 0,
        variant_id: null as number | null,
        direction: 'add' as Direction,
        quantity: '',
        reason: '',
        password: '',
    });

    const direction = shown?.target.direction ?? 'add';
    const quantity = Number.parseInt(form.data.quantity, 10);
    const validQuantity = Number.isInteger(quantity) && quantity > 0;
    const newStock = shown
        ? shown.row.stock +
          (direction === 'add' ? 1 : -1) * (validQuantity ? quantity : 0)
        : 0;
    const tooMuch =
        direction === 'remove' &&
        validQuantity &&
        shown !== null &&
        quantity > shown.row.stock;

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (!open || !shown || !validQuantity || tooMuch) {
            return;
        }

        const { productId, variantId } = shown.target;

        form.transform((data) => ({
            ...data,
            product_id: productId,
            variant_id: variantId,
            direction: shown.target.direction,
        }));
        form.post(route('admin.stock.adjust'), {
            preserveScroll: true,
            onSuccess: onClose,
            onFinish: () => form.reset('password'),
        });
    };

    const isAdd = direction === 'add';
    const shownRow = shown?.row ?? null;

    return (
        <Dialog open={open} onOpenChange={(value) => !value && onClose()}>
            <DialogContent appearance="premium" className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        {isAdd ? (
                            <PackageCheck className="size-4 text-emerald-600" />
                        ) : (
                            <PackageX className="size-4 text-red-600" />
                        )}
                        {isAdd ? 'Add stock' : 'Remove stock'}
                    </DialogTitle>
                    <DialogDescription>
                        {shownRow?.name}
                        {shownRow?.variantLabel
                            ? ` — ${shownRow.variantLabel}`
                            : ''}
                        {shownRow?.sku ? ` · SKU ${shownRow.sku}` : ''}
                    </DialogDescription>
                </DialogHeader>

                {shownRow && (
                    <form className="space-y-4" onSubmit={submit}>
                        <div className="grid grid-cols-2 gap-3 rounded-xl bg-slate-50 p-3 text-center">
                            <div>
                                <p className="text-[11px] font-medium tracking-wide text-slate-500 uppercase">
                                    Current stock
                                </p>
                                <p className="text-2xl font-bold text-slate-800 tabular-nums">
                                    {shownRow.stock.toLocaleString()}
                                </p>
                            </div>
                            <div>
                                <p className="text-[11px] font-medium tracking-wide text-slate-500 uppercase">
                                    After this change
                                </p>
                                <p
                                    className={`text-2xl font-bold tabular-nums ${
                                        tooMuch
                                            ? 'text-red-600'
                                            : isAdd
                                              ? 'text-emerald-600'
                                              : 'text-slate-800'
                                    }`}
                                >
                                    {validQuantity
                                        ? newStock.toLocaleString()
                                        : '—'}
                                </p>
                            </div>
                        </div>

                        <div>
                            <label
                                htmlFor="stock-quantity"
                                className="mb-1 block text-sm font-medium text-slate-700"
                            >
                                Quantity to {isAdd ? 'add' : 'remove'}
                            </label>
                            <input
                                className={productInputClass(
                                    form.errors.quantity ??
                                        (tooMuch ? 'too much' : undefined),
                                )}
                                id="stock-quantity"
                                type="number"
                                inputMode="numeric"
                                min={1}
                                max={isAdd ? undefined : shownRow.stock}
                                step={1}
                                autoFocus
                                value={form.data.quantity}
                                onChange={(event) =>
                                    form.setData('quantity', event.target.value)
                                }
                            />
                            {tooMuch && (
                                <p className="mt-1 text-xs text-red-500">
                                    You can&apos;t remove more than the current
                                    stock ({shownRow.stock}).
                                </p>
                            )}
                            {form.errors.quantity && (
                                <p className="mt-1 text-xs text-red-500">
                                    {form.errors.quantity}
                                </p>
                            )}
                        </div>

                        <div>
                            <label
                                htmlFor="stock-reason"
                                className="mb-1 block text-sm font-medium text-slate-700"
                            >
                                Reason{' '}
                                <span className="font-normal text-slate-500">
                                    (optional)
                                </span>
                            </label>
                            <input
                                className={productInputClass()}
                                id="stock-reason"
                                maxLength={255}
                                placeholder={
                                    isAdd
                                        ? 'e.g. New shipment received'
                                        : 'e.g. Damaged items'
                                }
                                value={form.data.reason}
                                onChange={(event) =>
                                    form.setData('reason', event.target.value)
                                }
                            />
                            <CharCount
                                value={form.data.reason}
                                max={255}
                                className="mt-1 block text-right"
                            />
                            {form.errors.reason && (
                                <p className="mt-1 text-xs text-red-500">
                                    {form.errors.reason}
                                </p>
                            )}
                        </div>

                        <div>
                            <label
                                htmlFor="stock-password"
                                className="mb-1 block text-sm font-medium text-slate-700"
                            >
                                Your password
                            </label>
                            <input
                                className={productInputClass(
                                    form.errors.password,
                                )}
                                id="stock-password"
                                type="password"
                                maxLength={255}
                                autoComplete="current-password"
                                value={form.data.password}
                                onChange={(event) =>
                                    form.setData('password', event.target.value)
                                }
                            />
                            {form.errors.password && (
                                <p className="mt-1 text-xs text-red-500">
                                    {form.errors.password}
                                </p>
                            )}
                        </div>

                        <div className="sm:flex-shownRow flex flex-col-reverse gap-2 sm:justify-end">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={onClose}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={
                                    form.processing ||
                                    !validQuantity ||
                                    tooMuch ||
                                    form.data.password === ''
                                }
                                className={
                                    isAdd
                                        ? 'bg-emerald-600 text-white hover:bg-emerald-700'
                                        : 'bg-brand text-brand-foreground hover:bg-brand-hover'
                                }
                            >
                                {form.processing
                                    ? 'Saving…'
                                    : isAdd
                                      ? 'Add stock'
                                      : 'Remove stock'}
                            </Button>
                        </div>
                    </form>
                )}
            </DialogContent>
        </Dialog>
    );
}

export default function StockIndex({
    products,
    filters,
    categories,
    subCategories,
    canAdjust,
}: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [target, setTarget] = useState<Target | null>(null);

    // Owner decision: stock changed anywhere (orders, cancellations, the
    // product edit form, another admin) shows here without a page refresh.
    usePoll(POLL_MS, { only: ['products'] });

    const rows = useMemo(() => toRows(products.data), [products.data]);
    const targetRow = target
        ? (rows.find(
              (r) =>
                  r.productId === target.productId &&
                  r.variantId === target.variantId,
          ) ?? null)
        : null;

    const visibleSubCategories = filters.category_id
        ? subCategories.filter(
              (s) => String(s.category_id) === filters.category_id,
          )
        : subCategories;

    const applyFilters = (overrides: Record<string, string>) => {
        if (
            Object.keys(overrides).length === 0 &&
            search === (filters.search ?? '')
        ) {
            return;
        }

        router.get(
            route('admin.stock.index'),
            { ...filters, search, ...overrides },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const clearFilters = () => {
        setSearch('');
        router.get(
            route('admin.stock.index'),
            {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const hasActiveFilters = Boolean(
        search ||
        filters.search ||
        filters.category_id ||
        filters.sub_category_id,
    );

    useLiveSearch(search, filters.search ?? '', () => applyFilters({}));

    const lowCount = rows.filter((r) => stockTone(r) === 'low').length;
    const outCount = rows.filter((r) => stockTone(r) === 'out').length;

    const actionButtons = (row: StockRow) =>
        canAdjust && (
            <div className="flex gap-2">
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    className="flex-1 border-emerald-200 text-emerald-700 hover:bg-emerald-50 sm:flex-none"
                    onClick={() =>
                        setTarget({
                            productId: row.productId,
                            variantId: row.variantId,
                            direction: 'add',
                        })
                    }
                >
                    <Plus className="mr-1 size-3.5" /> Add
                </Button>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    disabled={row.stock <= 0}
                    className="flex-1 border-red-200 text-red-700 hover:bg-red-50 sm:flex-none"
                    onClick={() =>
                        setTarget({
                            productId: row.productId,
                            variantId: row.variantId,
                            direction: 'remove',
                        })
                    }
                >
                    <Minus className="mr-1 size-3.5" /> Remove
                </Button>
            </div>
        );

    return (
        <>
            <Head title="Stock Management — Admin" />

            <div className="space-y-5">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="flex items-center gap-2 text-xl font-bold text-slate-900">
                            <Boxes className="size-5 text-brand" /> Stock
                            Management
                        </h1>
                        <p className="text-sm text-slate-600">
                            Add or remove stock for each product and variant.
                            Changes need your password and are recorded in the
                            Audit Trail. Stock refreshes automatically.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2 text-xs">
                        <span className="rounded-full bg-slate-100 px-3 py-1 font-medium text-slate-700">
                            {products.total} products
                        </span>
                        {lowCount > 0 && (
                            <span className="inline-flex items-center gap-1 rounded-full bg-amber-50 px-3 py-1 font-medium text-amber-700">
                                <TriangleAlert className="size-3" /> {lowCount}{' '}
                                low on this page
                            </span>
                        )}
                        {outCount > 0 && (
                            <span className="rounded-full bg-red-50 px-3 py-1 font-medium text-red-700">
                                {outCount} out of stock on this page
                            </span>
                        )}
                    </div>
                </div>

                <Card className="overflow-hidden p-0 shadow-sm">
                    <div className="flex flex-wrap gap-2 border-b border-slate-100 p-4">
                        <div className="relative w-full flex-none sm:w-auto sm:min-w-[220px] sm:flex-1">
                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                            <input
                                maxLength={100}
                                type="text"
                                placeholder="Search by name, SKU or price…"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                onKeyDown={(e) =>
                                    e.key === 'Enter' && applyFilters({})
                                }
                                className="h-9 w-full rounded-lg border border-slate-200 pr-3 pl-9 text-sm focus:border-brand focus:outline-none"
                            />
                        </div>
                        <Select
                            value={filters.category_id ?? ''}
                            onChange={(e) =>
                                applyFilters({
                                    category_id: e.target.value,
                                    sub_category_id: '',
                                })
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
                        <Select
                            value={filters.sub_category_id ?? ''}
                            onChange={(e) =>
                                applyFilters({
                                    sub_category_id: e.target.value,
                                })
                            }
                            containerClassName="min-w-0 flex-1 sm:min-w-40 sm:flex-none"
                            selectClassName="h-9 rounded-lg border border-slate-200 px-3 text-sm focus:outline-none"
                        >
                            <option value="">All Sub-categories</option>
                            {visibleSubCategories.map((s) => (
                                <option key={s.id} value={s.id}>
                                    {s.name}
                                </option>
                            ))}
                        </Select>
                        <ClearFiltersButton
                            active={hasActiveFilters}
                            onClear={clearFilters}
                        />
                    </div>

                    {rows.length === 0 ? (
                        <p className="py-12 text-center text-sm text-slate-600">
                            No products found.
                        </p>
                    ) : (
                        <>
                            {/* Desktop table */}
                            <div className="hidden md:block">
                                <table className="w-full text-sm">
                                    <thead className="bg-slate-50 text-left text-[11px] font-semibold tracking-wide text-slate-500 uppercase">
                                        <tr>
                                            <th className="px-4 py-2.5">
                                                Product
                                            </th>
                                            <th className="px-4 py-2.5">
                                                Status
                                            </th>
                                            <th className="px-4 py-2.5 text-right">
                                                Current stock
                                            </th>
                                            {canAdjust && (
                                                <th className="px-4 py-2.5 text-right">
                                                    Adjust
                                                </th>
                                            )}
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {rows.map((row) => (
                                            <tr
                                                key={row.key}
                                                className="hover:bg-slate-50/60"
                                            >
                                                <td className="px-4 py-3">
                                                    <div className="flex items-center gap-3">
                                                        <img
                                                            src={
                                                                row.image ??
                                                                '/placeholder.svg'
                                                            }
                                                            alt=""
                                                            className="size-11 shrink-0 rounded-lg border border-slate-100 object-cover"
                                                        />
                                                        <div className="min-w-0">
                                                            <Link
                                                                href={route(
                                                                    'admin.products.edit',
                                                                    row.productId,
                                                                )}
                                                                className="block truncate font-semibold text-slate-800 hover:text-brand"
                                                            >
                                                                {row.name}
                                                            </Link>
                                                            {row.variantLabel && (
                                                                <span className="mt-0.5 inline-block rounded-md bg-slate-100 px-1.5 py-0.5 text-xs font-medium text-slate-700">
                                                                    {
                                                                        row.variantLabel
                                                                    }
                                                                </span>
                                                            )}
                                                            <p className="mt-0.5 truncate text-xs text-slate-500">
                                                                {row.sku
                                                                    ? `SKU ${row.sku} · `
                                                                    : ''}
                                                                ৳
                                                                {row.price.toLocaleString()}
                                                                {row.category
                                                                    ? ` · ${row.category}`
                                                                    : ''}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <Badge
                                                        variant={
                                                            row.isActive
                                                                ? 'success'
                                                                : 'warning'
                                                        }
                                                        size="sm"
                                                    >
                                                        {row.isActive
                                                            ? 'Active'
                                                            : 'Inactive'}
                                                    </Badge>
                                                </td>
                                                <td className="px-4 py-3 text-right">
                                                    <StockBadge row={row} />
                                                </td>
                                                {canAdjust && (
                                                    <td className="px-4 py-3">
                                                        <div className="flex justify-end">
                                                            {actionButtons(row)}
                                                        </div>
                                                    </td>
                                                )}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            {/* Mobile cards */}
                            <div className="divide-y divide-slate-100 md:hidden">
                                {rows.map((row) => (
                                    <div
                                        key={row.key}
                                        className="space-y-3 p-4"
                                    >
                                        <div className="flex items-start gap-3">
                                            <img
                                                src={
                                                    row.image ??
                                                    '/placeholder.svg'
                                                }
                                                alt=""
                                                className="size-12 shrink-0 rounded-lg border border-slate-100 object-cover"
                                            />
                                            <div className="min-w-0 flex-1">
                                                <Link
                                                    href={route(
                                                        'admin.products.edit',
                                                        row.productId,
                                                    )}
                                                    className="block font-semibold text-slate-800"
                                                >
                                                    {row.name}
                                                </Link>
                                                {row.variantLabel && (
                                                    <span className="mt-0.5 inline-block rounded-md bg-slate-100 px-1.5 py-0.5 text-xs font-medium text-slate-700">
                                                        {row.variantLabel}
                                                    </span>
                                                )}
                                                <p className="mt-0.5 text-xs text-slate-500">
                                                    {row.sku
                                                        ? `SKU ${row.sku} · `
                                                        : ''}
                                                    ৳
                                                    {row.price.toLocaleString()}
                                                </p>
                                                <div className="mt-1.5">
                                                    <Badge
                                                        variant={
                                                            row.isActive
                                                                ? 'success'
                                                                : 'warning'
                                                        }
                                                        size="sm"
                                                    >
                                                        {row.isActive
                                                            ? 'Active'
                                                            : 'Inactive'}
                                                    </Badge>
                                                </div>
                                            </div>
                                            <StockBadge row={row} />
                                        </div>
                                        {actionButtons(row)}
                                    </div>
                                ))}
                            </div>
                        </>
                    )}

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
                                        preserveScroll
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
                                        preserveScroll
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

            <AdjustDialog
                target={target}
                row={targetRow}
                onClose={() => setTarget(null)}
            />
        </>
    );
}
