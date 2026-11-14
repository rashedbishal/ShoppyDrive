import { AnimatePresence } from 'framer-motion';
import { Package, Search, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { AdminReveal } from '@/components/admin/admin-motion';
import { cn } from '@/lib/utils';

export type CouponTargetProduct = {
    product_id: number;
    variant_id: number | null;
    /** Display only: the backend reads just product_id and variant_id. */
    product_name: string;
    variant_label: string | null;
};

export type CouponTargetsValue = {
    applies_to: 'all' | 'selected';
    target_category_ids: number[];
    target_sub_category_ids: number[];
    target_products: CouponTargetProduct[];
};

type SearchProduct = {
    id: number;
    name: string;
    variants: { id: number; label: string }[];
};

type Props = {
    value: CouponTargetsValue;
    onChange: (patch: Partial<CouponTargetsValue>) => void;
    categories: { id: number; name: string }[];
    subCategories: { id: number; name: string; category_id: number }[];
    errors: Record<string, string>;
};

const pillClass = (active: boolean, disabled = false) =>
    cn(
        'rounded-full border px-3 py-1.5 text-xs font-medium transition-colors',
        active
            ? 'border-brand bg-brand/10 text-brand'
            : 'border-slate-200 text-slate-600 hover:border-slate-300',
        disabled && 'cursor-not-allowed opacity-40 hover:border-slate-200',
    );

/**
 * Chooses what a coupon covers: everything, or any mix of categories,
 * subcategories, whole products and specific variants. Something can't be
 * picked twice, or picked when a broader selection already covers it.
 * CouponRequest enforces the same rules.
 */
export function CouponTargetsCard({
    value,
    onChange,
    categories,
    subCategories,
    errors,
}: Props): React.JSX.Element {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<SearchProduct[]>([]);
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
        if (!query.trim()) {
            // eslint-disable-next-line react-hooks/set-state-in-effect -- clearing results for an empty query
            setResults([]);

            return;
        }

        const controller = new AbortController();
        const timeoutId = setTimeout(async () => {
            try {
                const res = await fetch(
                    route('admin.coupons.products.search', { q: query }),
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
            }
        }, 300);

        return () => {
            clearTimeout(timeoutId);
            controller.abort();
        };
    }, [query]);

    const rowsFor = (productId: number) =>
        value.target_products.filter((row) => row.product_id === productId);
    const hasWhole = (productId: number) =>
        rowsFor(productId).some((row) => row.variant_id === null);
    const hasVariant = (productId: number, variantId: number) =>
        rowsFor(productId).some((row) => row.variant_id === variantId);

    const addProduct = (
        product: SearchProduct,
        variant: SearchProduct['variants'][number] | null,
    ) => {
        onChange({
            target_products: [
                ...value.target_products,
                {
                    product_id: product.id,
                    variant_id: variant?.id ?? null,
                    product_name: product.name,
                    variant_label: variant?.label ?? null,
                },
            ],
        });
        setQuery('');
        setResults([]);
        setPendingVariantFor(null);
    };

    const toggleCategory = (id: number) => {
        const selected = value.target_category_ids.includes(id);
        const covered = subCategories
            .filter((sub) => sub.category_id === id)
            .map((sub) => sub.id);

        onChange({
            target_category_ids: selected
                ? value.target_category_ids.filter((c) => c !== id)
                : [...value.target_category_ids, id],
            // A selected category already covers its subcategories.
            target_sub_category_ids: selected
                ? value.target_sub_category_ids
                : value.target_sub_category_ids.filter(
                      (s) => !covered.includes(s),
                  ),
        });
    };

    const toggleSubCategory = (id: number) => {
        onChange({
            target_sub_category_ids: value.target_sub_category_ids.includes(id)
                ? value.target_sub_category_ids.filter((s) => s !== id)
                : [...value.target_sub_category_ids, id],
        });
    };

    const fieldError = Object.entries(errors).find(([key]) =>
        key.startsWith('target_'),
    )?.[1];

    return (
        <div className="space-y-3 rounded-xl border border-slate-200 p-4">
            <div>
                <p className="mb-2 text-sm font-medium text-slate-700">
                    Applies To
                </p>
                <div className="flex flex-wrap gap-4">
                    {(
                        [
                            ['all', 'All products'],
                            ['selected', 'Selected items only'],
                        ] as const
                    ).map(([option, label]) => (
                        <label
                            key={option}
                            className="flex items-center gap-2 text-sm text-slate-700"
                        >
                            <input
                                type="radio"
                                name="applies_to"
                                checked={value.applies_to === option}
                                onChange={() =>
                                    onChange({ applies_to: option })
                                }
                                className="size-4 accent-brand"
                            />
                            {label}
                        </label>
                    ))}
                </div>
                {errors.applies_to && (
                    <p className="mt-1 text-xs text-red-500">
                        {errors.applies_to}
                    </p>
                )}
            </div>

            <AnimatePresence initial={false}>
                {value.applies_to === 'selected' && (
                    <AdminReveal key="coupon-targets">
                        <div className="space-y-4 border-t border-slate-100 pt-3">
                            <p className="text-xs text-slate-600">
                                Mix categories, subcategories and products. The
                                discount and minimum order count only these
                                items. Anything matching more than one is still
                                discounted once.
                            </p>

                            <div>
                                <p className="mb-1.5 text-xs font-semibold tracking-wide text-slate-600 uppercase">
                                    Categories
                                </p>
                                <div className="flex max-h-40 flex-wrap gap-2 overflow-y-auto">
                                    {categories.map((category) => (
                                        <button
                                            key={category.id}
                                            type="button"
                                            aria-pressed={value.target_category_ids.includes(
                                                category.id,
                                            )}
                                            onClick={() =>
                                                toggleCategory(category.id)
                                            }
                                            className={pillClass(
                                                value.target_category_ids.includes(
                                                    category.id,
                                                ),
                                            )}
                                        >
                                            {category.name}
                                        </button>
                                    ))}
                                    {categories.length === 0 && (
                                        <p className="text-xs text-slate-600">
                                            No categories yet.
                                        </p>
                                    )}
                                </div>
                            </div>

                            <div>
                                <p className="mb-1.5 text-xs font-semibold tracking-wide text-slate-600 uppercase">
                                    Subcategories
                                </p>
                                <div className="flex max-h-40 flex-wrap gap-2 overflow-y-auto">
                                    {subCategories.map((sub) => {
                                        const covered =
                                            value.target_category_ids.includes(
                                                sub.category_id,
                                            );

                                        return (
                                            <button
                                                key={sub.id}
                                                type="button"
                                                disabled={covered}
                                                aria-pressed={value.target_sub_category_ids.includes(
                                                    sub.id,
                                                )}
                                                title={
                                                    covered
                                                        ? 'Already covered by its selected category'
                                                        : undefined
                                                }
                                                onClick={() =>
                                                    toggleSubCategory(sub.id)
                                                }
                                                className={pillClass(
                                                    value.target_sub_category_ids.includes(
                                                        sub.id,
                                                    ),
                                                    covered,
                                                )}
                                            >
                                                {sub.name}
                                            </button>
                                        );
                                    })}
                                    {subCategories.length === 0 && (
                                        <p className="text-xs text-slate-600">
                                            No subcategories yet.
                                        </p>
                                    )}
                                </div>
                            </div>

                            <div>
                                <p className="mb-1.5 text-xs font-semibold tracking-wide text-slate-600 uppercase">
                                    Products
                                </p>
                                <div ref={searchRef} className="relative">
                                    <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                                    <input
                                        maxLength={100}
                                        type="text"
                                        placeholder="Search products to add…"
                                        value={query}
                                        onChange={(e) => {
                                            setQuery(e.target.value);
                                            setPendingVariantFor(null);
                                        }}
                                        className="h-9 w-full rounded-lg border border-slate-200 pr-3 pl-9 text-sm focus:ring-1 focus:ring-brand focus:outline-none"
                                    />
                                    {(results.length > 0 ||
                                        pendingVariantFor) && (
                                        <div className="absolute z-10 mt-1 max-h-64 w-full space-y-0.5 overflow-y-auto rounded-lg border border-slate-200 bg-white p-1 shadow-lg">
                                            {pendingVariantFor ? (
                                                <>
                                                    <button
                                                        type="button"
                                                        disabled={
                                                            rowsFor(
                                                                pendingVariantFor.id,
                                                            ).length > 0
                                                        }
                                                        onClick={() =>
                                                            addProduct(
                                                                pendingVariantFor,
                                                                null,
                                                            )
                                                        }
                                                        className="flex w-full justify-between rounded-md px-3 py-2 text-left text-sm hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                                                    >
                                                        Whole product (all
                                                        variants)
                                                        {rowsFor(
                                                            pendingVariantFor.id,
                                                        ).length > 0 && (
                                                            <span className="text-xs text-slate-600">
                                                                {hasWhole(
                                                                    pendingVariantFor.id,
                                                                )
                                                                    ? 'Added'
                                                                    : 'Remove its variants first'}
                                                            </span>
                                                        )}
                                                    </button>
                                                    {pendingVariantFor.variants.map(
                                                        (variant) => {
                                                            const taken =
                                                                hasWhole(
                                                                    pendingVariantFor.id,
                                                                ) ||
                                                                hasVariant(
                                                                    pendingVariantFor.id,
                                                                    variant.id,
                                                                );

                                                            return (
                                                                <button
                                                                    key={
                                                                        variant.id
                                                                    }
                                                                    type="button"
                                                                    disabled={
                                                                        taken
                                                                    }
                                                                    onClick={() =>
                                                                        addProduct(
                                                                            pendingVariantFor,
                                                                            variant,
                                                                        )
                                                                    }
                                                                    className="flex w-full justify-between rounded-md px-3 py-2 text-left text-sm hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                                                                >
                                                                    {
                                                                        variant.label
                                                                    }
                                                                    {taken && (
                                                                        <span className="text-xs text-slate-600">
                                                                            {hasWhole(
                                                                                pendingVariantFor.id,
                                                                            )
                                                                                ? 'Covered by whole product'
                                                                                : 'Added'}
                                                                        </span>
                                                                    )}
                                                                </button>
                                                            );
                                                        },
                                                    )}
                                                </>
                                            ) : (
                                                results.map((product) => {
                                                    const added =
                                                        product.variants
                                                            .length === 0 &&
                                                        hasWhole(product.id);

                                                    return (
                                                        <button
                                                            key={product.id}
                                                            type="button"
                                                            disabled={added}
                                                            onClick={() =>
                                                                product.variants
                                                                    .length > 0
                                                                    ? setPendingVariantFor(
                                                                          product,
                                                                      )
                                                                    : addProduct(
                                                                          product,
                                                                          null,
                                                                      )
                                                            }
                                                            className="flex w-full justify-between rounded-md px-3 py-2 text-left text-sm hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                                                        >
                                                            {product.name}
                                                            {added && (
                                                                <span className="text-xs text-slate-600">
                                                                    Added
                                                                </span>
                                                            )}
                                                        </button>
                                                    );
                                                })
                                            )}
                                        </div>
                                    )}
                                </div>

                                <div className="mt-2 space-y-1.5">
                                    <AnimatePresence initial={false}>
                                        {value.target_products.map((row) => (
                                            <AdminReveal
                                                key={`${row.product_id}-${row.variant_id ?? 'whole'}`}
                                            >
                                                <div className="flex items-center gap-2 rounded-lg border border-slate-100 bg-slate-50 px-3 py-2">
                                                    <Package className="size-4 shrink-0 text-slate-400" />
                                                    <div className="min-w-0 flex-1">
                                                        <p className="truncate text-sm font-medium text-slate-800">
                                                            {row.product_name}
                                                        </p>
                                                        <p className="truncate text-xs text-slate-600">
                                                            {row.variant_label ??
                                                                'Whole product'}
                                                        </p>
                                                    </div>
                                                    <button
                                                        type="button"
                                                        aria-label={`Remove ${row.product_name}`}
                                                        onClick={() =>
                                                            onChange({
                                                                target_products:
                                                                    value.target_products.filter(
                                                                        (
                                                                            other,
                                                                        ) =>
                                                                            other !==
                                                                            row,
                                                                    ),
                                                            })
                                                        }
                                                        className="rounded-lg p-1 text-slate-400 hover:text-red-500"
                                                    >
                                                        <Trash2 className="size-4" />
                                                    </button>
                                                </div>
                                            </AdminReveal>
                                        ))}
                                    </AnimatePresence>
                                </div>
                            </div>

                            {fieldError && (
                                <p className="text-xs text-red-500">
                                    {fieldError}
                                </p>
                            )}
                        </div>
                    </AdminReveal>
                )}
            </AnimatePresence>
        </div>
    );
}
