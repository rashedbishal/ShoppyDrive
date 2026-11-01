import { Head, Link } from '@inertiajs/react';
import {
    GitCompare,
    Trash2,
    ShoppingCart,
    Star,
    Check,
    X,
    ArrowLeft,
    Plus,
} from 'lucide-react';
import React from 'react';

import { Button } from '@/components/ui/button';
import { formatCurrency } from '@/lib/utils';
import { useCartStore } from '@/stores/cartStore';
import { useUiStore } from '@/stores/uiStore';
import type { Product } from '@/types';

interface CompareProps {
    products: Product[];
}

export default function Compare({
    products = [],
}: CompareProps): React.JSX.Element {
    const addItem = useCartStore((state) => state.addItem);
    const removeCompareItem = useUiStore((state) => state.removeCompareItem);
    const clearCompareItems = useUiStore((state) => state.clearCompareItems);

    const attributes = Array.from(
        new Set(products.flatMap((p) => p.attributes.map((a) => a.name))),
    );

    const renderPrice = (product: Product) => {
        const currentPrice =
            product.in_flash_sale && product.flash_sale_price
                ? product.flash_sale_price
                : product.price;

        return (
            <div className="flex flex-col items-center">
                <span className="text-xl font-bold text-[var(--color-primary)]">
                    {formatCurrency(currentPrice)}
                </span>
                {product.compare_price &&
                    product.compare_price > currentPrice && (
                        <span className="text-xs text-[var(--color-text-muted)] line-through">
                            {formatCurrency(product.compare_price)}
                        </span>
                    )}
            </div>
        );
    };

    if (products.length === 0) {
        return (
            <div className="flex min-h-screen items-center justify-center bg-[var(--color-surface-alt)] px-4 py-20">
                <Head title="Compare Products" />
                <div className="w-full max-w-md rounded-3xl border border-[var(--color-border)] bg-white p-12 text-center shadow-sm">
                    <div className="mx-auto mb-6 flex h-24 w-24 items-center justify-center rounded-full bg-[var(--color-surface-alt)] text-[var(--color-text-muted)]">
                        <GitCompare size={48} />
                    </div>
                    <h1 className="mb-3 text-2xl font-bold text-[var(--color-text-primary)]">
                        No items to compare
                    </h1>
                    <p className="mb-10 text-[var(--color-text-secondary)]">
                        Add up to 3 products to the comparison list to see their
                        differences side-by-side.
                    </p>
                    <Button
                        asChild
                        className="h-14 w-full rounded-2xl text-lg font-bold shadow-lg"
                    >
                        <Link href={route('products.index')}>
                            Explore Products
                        </Link>
                    </Button>
                </div>
            </div>
        );
    }

    return (
        <div className="min-h-screen bg-[var(--color-surface-alt)] py-12">
            <Head title="Compare Products" />

            <div className="container mx-auto px-4 md:max-w-7xl">
                <div className="mb-12 flex flex-col justify-between gap-6 md:flex-row md:items-center">
                    <div className="flex items-center gap-4">
                        <Link
                            href={route('products.index')}
                            className="rounded-full border border-[var(--color-border)] bg-white p-2 text-[var(--color-text-muted)] transition-colors hover:text-[var(--color-primary)]"
                        >
                            <ArrowLeft size={18} />
                        </Link>
                        <div>
                            <h1 className="mb-1 font-serif text-3xl font-bold text-[var(--color-text-primary)]">
                                Compare Products
                            </h1>
                            <p className="text-[var(--color-text-secondary)]">
                                Showing side-by-side comparison of{' '}
                                {products.length} items.
                            </p>
                        </div>
                    </div>

                    <button
                        onClick={clearCompareItems}
                        className="flex items-center gap-2 text-sm font-bold text-[var(--color-error)] hover:underline"
                    >
                        <Trash2 size={16} />
                        Clear Comparison
                    </button>
                </div>

                <div className="overflow-hidden rounded-3xl border border-[var(--color-border)] bg-white shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full border-collapse">
                            <tbody>
                                {/* Product Images & Basic Info */}
                                <tr>
                                    <td className="w-64 border-b border-[var(--color-border)] bg-[var(--color-surface-alt)]/30 p-8">
                                        <h3 className="text-xs font-bold tracking-widest text-[var(--color-text-muted)] uppercase">
                                            Product Information
                                        </h3>
                                    </td>
                                    {products.map((product) => (
                                        <td
                                            key={product.id}
                                            className="group relative min-w-[280px] border-b border-l border-[var(--color-border)] p-8 text-center"
                                        >
                                            <button
                                                onClick={() =>
                                                    removeCompareItem(
                                                        product.id,
                                                    )
                                                }
                                                className="absolute top-4 right-4 rounded-full bg-red-50 p-1.5 text-[var(--color-error)] opacity-0 transition-opacity group-hover:opacity-100"
                                                title="Remove"
                                            >
                                                <X size={14} strokeWidth={3} />
                                            </button>

                                            <div className="mx-auto mb-6 h-40 w-40 overflow-hidden rounded-2xl bg-[var(--color-surface-alt)]">
                                                <img
                                                    src={
                                                        product.primary_image_url ||
                                                        product.images[0]?.path
                                                    }
                                                    alt={product.name}
                                                    className="h-full w-full object-cover"
                                                />
                                            </div>
                                            <p className="mb-1 text-[10px] font-bold tracking-widest text-[var(--color-primary)] uppercase">
                                                {product.brand?.name ||
                                                    'GENUINE'}
                                            </p>
                                            <Link
                                                href={route(
                                                    'product.show',
                                                    product.slug,
                                                )}
                                                className="mb-4 line-clamp-2 h-12 text-base font-bold text-[var(--color-text-primary)] transition-colors hover:text-[var(--color-primary)]"
                                            >
                                                {product.name}
                                            </Link>

                                            <Button
                                                onClick={() => {
                                                    const defaultVariant =
                                                        (
                                                            product.variants ??
                                                            []
                                                        ).find(
                                                            (variant) =>
                                                                variant.is_default,
                                                        ) ?? null;

                                                    addItem({
                                                        product,
                                                        quantity: 1,
                                                        variant: defaultVariant,
                                                    });
                                                }}
                                                className="h-11 w-full rounded-xl font-bold"
                                            >
                                                <ShoppingCart
                                                    size={16}
                                                    className="mr-2"
                                                />
                                                Add to Cart
                                            </Button>
                                        </td>
                                    ))}
                                    {/* Fill empty spots */}
                                    {[...Array(3 - products.length)].map(
                                        (_, i) => (
                                            <td
                                                key={i}
                                                className="min-w-[280px] border-b border-l border-[var(--color-border)] bg-[var(--color-surface-alt)]/10 p-8"
                                            >
                                                <div className="flex h-full flex-col items-center justify-center py-20 text-[var(--color-text-muted)]">
                                                    <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-2xl border-2 border-dashed border-current opacity-30">
                                                        <Plus size={24} />
                                                    </div>
                                                    <p className="text-xs font-bold tracking-widest uppercase opacity-50">
                                                        Add Product
                                                    </p>
                                                </div>
                                            </td>
                                        ),
                                    )}
                                </tr>

                                {/* Price Row */}
                                <tr className="transition-colors hover:bg-[var(--color-surface-alt)]/30">
                                    <td className="bg-[var(--color-surface-alt)]/30 px-8 py-5 text-sm font-bold text-[var(--color-text-secondary)]">
                                        Price
                                    </td>
                                    {products.map((p) => (
                                        <td
                                            key={p.id}
                                            className="border-l border-[var(--color-border)] px-8 py-5 text-center font-bold"
                                        >
                                            {renderPrice(p)}
                                        </td>
                                    ))}
                                    {[...Array(3 - products.length)].map(
                                        (_, i) => (
                                            <td
                                                key={i}
                                                className="border-l border-[var(--color-border)]"
                                            ></td>
                                        ),
                                    )}
                                </tr>

                                {/* Rating Row */}
                                <tr className="transition-colors hover:bg-[var(--color-surface-alt)]/30">
                                    <td className="bg-[var(--color-surface-alt)]/30 px-8 py-5 text-sm font-bold text-[var(--color-text-secondary)]">
                                        Customer Rating
                                    </td>
                                    {products.map((p) => (
                                        <td
                                            key={p.id}
                                            className="border-l border-[var(--color-border)] px-8 py-5 text-center"
                                        >
                                            <div className="flex items-center justify-center gap-1.5">
                                                <Star
                                                    size={16}
                                                    className="fill-[var(--color-accent)] text-[var(--color-accent)]"
                                                />
                                                <span className="font-bold text-[var(--color-text-primary)]">
                                                    {p.average_rating?.toFixed(
                                                        1,
                                                    ) || '5.0'}
                                                </span>
                                                <span className="text-[10px] font-medium text-[var(--color-text-muted)]">
                                                    ({p.reviews_count || 0})
                                                </span>
                                            </div>
                                        </td>
                                    ))}
                                    {[...Array(3 - products.length)].map(
                                        (_, i) => (
                                            <td
                                                key={i}
                                                className="border-l border-[var(--color-border)]"
                                            ></td>
                                        ),
                                    )}
                                </tr>

                                {/* Stock Status */}
                                <tr className="transition-colors hover:bg-[var(--color-surface-alt)]/30">
                                    <td className="bg-[var(--color-surface-alt)]/30 px-8 py-5 text-sm font-bold text-[var(--color-text-secondary)]">
                                        Availability
                                    </td>
                                    {products.map((p) => (
                                        <td
                                            key={p.id}
                                            className="border-l border-[var(--color-border)] px-8 py-5 text-center"
                                        >
                                            {(p.available_stock ??
                                                p.stock_quantity) > 0 ? (
                                                <div className="inline-flex items-center gap-1.5 text-xs font-bold tracking-widest text-[var(--color-success)] uppercase">
                                                    <Check
                                                        size={14}
                                                        strokeWidth={3}
                                                    />
                                                    In Stock
                                                </div>
                                            ) : (
                                                <div className="inline-flex items-center gap-1.5 text-xs font-bold tracking-widest text-[var(--color-error)] uppercase">
                                                    <X
                                                        size={14}
                                                        strokeWidth={3}
                                                    />
                                                    Out of Stock
                                                </div>
                                            )}
                                        </td>
                                    ))}
                                    {[...Array(3 - products.length)].map(
                                        (_, i) => (
                                            <td
                                                key={i}
                                                className="border-l border-[var(--color-border)]"
                                            ></td>
                                        ),
                                    )}
                                </tr>

                                {/* Category */}
                                <tr className="transition-colors hover:bg-[var(--color-surface-alt)]/30">
                                    <td className="bg-[var(--color-surface-alt)]/30 px-8 py-5 text-sm font-bold text-[var(--color-text-secondary)]">
                                        Category
                                    </td>
                                    {products.map((p) => (
                                        <td
                                            key={p.id}
                                            className="border-l border-[var(--color-border)] px-8 py-5 text-center text-sm font-medium text-[var(--color-text-primary)]"
                                        >
                                            {p.category.name}
                                        </td>
                                    ))}
                                    {[...Array(3 - products.length)].map(
                                        (_, i) => (
                                            <td
                                                key={i}
                                                className="border-l border-[var(--color-border)]"
                                            ></td>
                                        ),
                                    )}
                                </tr>

                                {/* Attributes */}
                                {attributes.map((attrName) => (
                                    <tr
                                        key={attrName}
                                        className="transition-colors hover:bg-[var(--color-surface-alt)]/30"
                                    >
                                        <td className="bg-[var(--color-surface-alt)]/30 px-8 py-5 text-sm font-bold text-[var(--color-text-secondary)]">
                                            {attrName}
                                        </td>
                                        {products.map((p) => {
                                            const attr = p.attributes.find(
                                                (a) => a.name === attrName,
                                            );

                                            return (
                                                <td
                                                    key={p.id}
                                                    className="border-l border-[var(--color-border)] px-8 py-5 text-center text-sm font-medium"
                                                >
                                                    {attr ? (
                                                        attr.values
                                                            .map((v) => v.value)
                                                            .join(', ')
                                                    ) : (
                                                        <span className="text-[var(--color-text-muted)]">
                                                            —
                                                        </span>
                                                    )}
                                                </td>
                                            );
                                        })}
                                        {[...Array(3 - products.length)].map(
                                            (_, i) => (
                                                <td
                                                    key={i}
                                                    className="border-l border-[var(--color-border)]"
                                                ></td>
                                            ),
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    );
}
