import { AnimatePresence } from 'framer-motion';
import { Package, Search, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { AdminFade, AdminReveal } from '@/components/admin/admin-motion';
import { useRowKeys } from '@/hooks/use-row-keys';
import { belowCostWarning } from '@/lib/price-warnings';
import { formatCurrency } from '@/lib/utils';

export type FlashSaleItemData = {
    product_id: string;
    /** Empty string means "the whole product, every variant" — see FlashSaleItem::findActiveFor(). */
    variant_id: string;
    sale_price: string;
    // Denormalized purely for display (so an existing row renders without
    // a fresh search) — the backend only reads product_id/variant_id/
    // sale_price; these extra fields are harmlessly ignored
    // by FlashSaleRequest's rules(), same convention as create-order-modal's
    // OrderLine carrying product_name/variant_attributes alongside the
    // fields CreateOrderRequest actually validates.
    product_name: string;
    product_sku: string | null;
    /** The real stock ceiling for whichever this row targets (the variant's own, or the whole product's) — a UI hint only; the backend validates it authoritatively regardless. */
    available_stock: number;
    variant_label: string | null;
    /**
     * Below-cost warning only (never sent anywhere that reads it). Null when
     * no cost was entered or this admin can't see cost prices — see
     * Admin\FlashSaleController::canSeeCostPrices().
     */
    cost_price: number | null;
};

type SearchVariant = {
    id: number;
    sku: string | null;
    price: number;
    cost_price: number | null;
    stock_quantity: number;
    attributes: Record<string, string> | null;
};

type SearchProduct = {
    id: number;
    name: string;
    sku: string | null;
    price: number;
    /** For the whole product: its costliest variant's cost, or its own. */
    cost_price: number | null;
    available_stock: number;
    image: string | null;
    variants: SearchVariant[];
};

function variantLabel(attributes: Record<string, string> | null): string {
    if (!attributes) {
        return '';
    }

    return Object.entries(attributes)
        .map(([key, value]) => `${key}: ${value}`)
        .join(', ');
}

type Props = {
    items: FlashSaleItemData[];
    onChange: (items: FlashSaleItemData[]) => void;
    errors: Record<string, string>;
};

/**
 * The product-picking section of the flash-sale create/edit forms. Search
 * is required to discover a product's variants (see
 * Admin\FlashSaleController::searchProducts(), same response shape as
 * Admin\IncompleteOrderController's) — a plain dropdown of every product
 * has no room to also list each one's variants. When the selected product
 * has any, a second step offers "the whole product" alongside each
 * specific combination, so an admin can still run one sale across every
 * variant, or target just one, or add several rows to price variants
 * differently — never required to pick a variant just because the product
 * has some.
 */
export function FlashSaleProductsCard({ items, onChange, errors }: Props) {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<SearchProduct[]>([]);
    const [searching, setSearching] = useState(false);
    const [pendingVariantFor, setPendingVariantFor] =
        useState<SearchProduct | null>(null);
    const searchRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const handleClickOutside = (e: MouseEvent) => {
            if (
                searchRef.current &&
                !searchRef.current.contains(e.target as Node)
            ) {
                setResults([]);
                setPendingVariantFor(null);
            }
        };

        document.addEventListener('mousedown', handleClickOutside);

        return () =>
            document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    useEffect(() => {
        const clearResults = (): void => setResults([]);

        if (!query.trim()) {
            clearResults();

            return;
        }

        const controller = new AbortController();
        const timeoutId = setTimeout(async () => {
            setSearching(true);

            try {
                const res = await fetch(
                    route('admin.flash-sales.products.search', { q: query }),
                    {
                        signal: controller.signal,
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    },
                );

                if (res.ok) {
                    setResults(await res.json());
                }
            } catch {
                // aborted or network error
            } finally {
                setSearching(false);
            }
        }, 300);

        return () => {
            clearTimeout(timeoutId);
            controller.abort();
        };
    }, [query]);

    const isDuplicate = (productId: number, variantId: number | null) =>
        items.some(
            (item) =>
                Number(item.product_id) === productId &&
                (item.variant_id === '' ? null : Number(item.variant_id)) ===
                    variantId,
        );

    const addItem = (product: SearchProduct, variant: SearchVariant | null) => {
        if (isDuplicate(product.id, variant?.id ?? null)) {
            setQuery('');
            setResults([]);
            setPendingVariantFor(null);

            return;
        }

        onChange([
            ...items,
            {
                product_id: String(product.id),
                variant_id: variant ? String(variant.id) : '',
                sale_price: '',
                product_name: product.name,
                product_sku: variant?.sku ?? product.sku,
                available_stock: variant
                    ? variant.stock_quantity
                    : product.available_stock,
                variant_label: variant
                    ? variantLabel(variant.attributes) ||
                      variant.sku ||
                      `Variant #${variant.id}`
                    : null,
                cost_price: variant
                    ? (variant.cost_price ?? null)
                    : (product.cost_price ?? null),
            },
        ]);
        setQuery('');
        setResults([]);
        setPendingVariantFor(null);
    };

    const handleSelectProduct = (product: SearchProduct) => {
        if (product.variants.length > 0) {
            setPendingVariantFor(product);

            return;
        }

        addItem(product, null);
    };

    const itemRowKeys = useRowKeys(items.length);

    const removeItem = (index: number) => {
        itemRowKeys.removeKey(index);
        onChange(items.filter((_, i) => i !== index));
    };

    const updateItem = (index: number, patch: Partial<FlashSaleItemData>) => {
        onChange(
            items.map((item, i) =>
                i === index ? { ...item, ...patch } : item,
            ),
        );
    };

    return (
        <div className="space-y-3">
            <div ref={searchRef} className="relative">
                <div className="relative">
                    <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                    <input
                        maxLength={100}
                        type="text"
                        placeholder="Search products to add…"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        className="h-10 w-full rounded-lg border border-slate-200 pr-3 pl-9 text-sm focus:border-[#c8102e] focus:outline-none"
                    />
                </div>

                <AnimatePresence initial={false}>
                    {pendingVariantFor && (
                        <AdminFade
                            key="variant-picker"
                            className="absolute z-10 mt-1 w-full origin-top rounded-lg border border-slate-200 bg-white p-2 shadow-lg"
                        >
                            <p className="mb-1.5 px-1 text-xs font-semibold text-slate-500">
                                Apply the flash sale to…
                            </p>
                            <div className="max-h-64 space-y-0.5 overflow-y-auto">
                                <button
                                    type="button"
                                    onClick={() =>
                                        addItem(pendingVariantFor, null)
                                    }
                                    className="flex w-full items-center justify-between rounded-md px-2 py-1.5 text-left text-sm hover:bg-slate-50"
                                >
                                    <span className="font-medium">
                                        Whole product (all variants)
                                    </span>
                                    <span className="text-xs text-slate-400">
                                        {formatCurrency(
                                            pendingVariantFor.price,
                                        )}{' '}
                                        · stock{' '}
                                        {pendingVariantFor.available_stock}
                                    </span>
                                </button>
                                {pendingVariantFor.variants.map((variant) => (
                                    <button
                                        key={variant.id}
                                        type="button"
                                        onClick={() =>
                                            addItem(pendingVariantFor, variant)
                                        }
                                        className="flex w-full items-center justify-between rounded-md px-2 py-1.5 text-left text-sm hover:bg-slate-50"
                                    >
                                        <span>
                                            {variantLabel(variant.attributes) ||
                                                variant.sku}
                                        </span>
                                        <span className="text-xs text-slate-400">
                                            {formatCurrency(variant.price)} ·
                                            stock {variant.stock_quantity}
                                        </span>
                                    </button>
                                ))}
                            </div>
                        </AdminFade>
                    )}
                </AnimatePresence>

                <AnimatePresence initial={false}>
                    {!pendingVariantFor && results.length > 0 && (
                        <AdminFade
                            key="search-results"
                            className="absolute z-10 mt-1 max-h-64 w-full origin-top space-y-0.5 overflow-y-auto rounded-lg border border-slate-200 bg-white p-2 shadow-lg"
                        >
                            {results.map((product) => (
                                <button
                                    key={product.id}
                                    type="button"
                                    onClick={() => handleSelectProduct(product)}
                                    className="flex w-full items-center justify-between rounded-md px-2 py-1.5 text-left text-sm hover:bg-slate-50"
                                >
                                    <span className="truncate">
                                        {product.name}
                                        {product.variants.length > 0 && (
                                            <span className="ml-1.5 text-xs text-slate-400">
                                                ({product.variants.length}{' '}
                                                variants)
                                            </span>
                                        )}
                                    </span>
                                    <span className="shrink-0 text-xs text-slate-400">
                                        {formatCurrency(product.price)}
                                    </span>
                                </button>
                            ))}
                        </AdminFade>
                    )}
                </AnimatePresence>

                {!pendingVariantFor &&
                    query.trim() !== '' &&
                    !searching &&
                    results.length === 0 && (
                        <div className="absolute z-10 mt-1 w-full rounded-lg border border-slate-200 bg-white p-3 text-center text-xs text-slate-400 shadow-lg">
                            No products found.
                        </div>
                    )}
            </div>

            {errors.items && (
                <p className="text-xs text-red-500">{errors.items}</p>
            )}

            <div className="space-y-2">
                {items.length === 0 ? (
                    <p className="py-4 text-center text-sm text-slate-400">
                        No products added yet. Search above to add one.
                    </p>
                ) : (
                    <AnimatePresence initial={false}>
                        {items.map((item, i) => {
                            const priceError = errors[`items.${i}.sale_price`];
                            const costWarning = belowCostWarning(
                                item.sale_price,
                                item.cost_price,
                            );
                            const productError =
                                errors[`items.${i}.product_id`] ??
                                errors[`items.${i}.variant_id`];

                            return (
                                <AdminReveal key={itemRowKeys.keys[i]}>
                                    <div className="flex flex-col gap-3 rounded-lg border border-slate-100 bg-slate-50/50 p-3 sm:flex-row sm:items-start">
                                        <div className="flex min-w-0 flex-1 items-start gap-3">
                                            <div className="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-400">
                                                <Package className="size-4" />
                                            </div>
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-semibold text-slate-800">
                                                    {item.product_name}
                                                </p>
                                                <p className="truncate text-xs text-slate-400">
                                                    {item.variant_label ??
                                                        'Whole product (all variants)'}
                                                    {item.product_sku
                                                        ? ` · ${item.product_sku}`
                                                        : ''}
                                                </p>
                                                {productError && (
                                                    <p className="mt-1 text-xs text-red-500">
                                                        {productError}
                                                    </p>
                                                )}
                                            </div>
                                        </div>
                                        <div className="w-full sm:w-32">
                                            <label className="mb-1 block text-xs font-medium text-slate-600">
                                                Sale Price (৳) *
                                            </label>
                                            <input
                                                max={99999999.99}
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                className={`h-9 w-full rounded-lg border px-3 text-sm focus:ring-1 focus:outline-none ${priceError ? 'border-red-400 focus:ring-red-400' : 'border-slate-200 focus:ring-[#c8102e]'}`}
                                                value={item.sale_price}
                                                onChange={(e) =>
                                                    updateItem(i, {
                                                        sale_price:
                                                            e.target.value,
                                                    })
                                                }
                                            />
                                            {priceError && (
                                                <p className="mt-1 text-xs text-red-500">
                                                    {priceError}
                                                </p>
                                            )}
                                            {!priceError && costWarning && (
                                                <p className="mt-1 text-xs text-amber-600">
                                                    {costWarning}
                                                </p>
                                            )}
                                        </div>
                                        <div className="w-full sm:w-28">
                                            <p className="mb-1 block text-xs font-medium text-slate-600">
                                                Stock
                                            </p>
                                            <p
                                                className={`flex h-9 items-center text-sm font-medium ${item.available_stock > 0 ? 'text-slate-700' : 'text-red-500'}`}
                                            >
                                                {item.available_stock > 0
                                                    ? `${item.available_stock} available`
                                                    : 'Out of stock'}
                                            </p>
                                        </div>
                                        <button
                                            type="button"
                                            onClick={() => removeItem(i)}
                                            className="self-end rounded-lg p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-500 sm:mt-5 sm:self-auto"
                                        >
                                            <Trash2 className="size-4" />
                                        </button>
                                    </div>
                                </AdminReveal>
                            );
                        })}
                    </AnimatePresence>
                )}
            </div>
        </div>
    );
}
