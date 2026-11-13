import { Equal, Minus, Package, Plus, ShoppingBag, Truck } from 'lucide-react';
import { Fragment, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { trackMetaProductEvent } from '@/lib/meta-tracking';
import { formatCurrency, getPrimaryImagePath } from '@/lib/utils';
import { useCartStore } from '@/stores/cartStore';
import { useUiStore } from '@/stores/uiStore';
import type { Product, ProductVariant } from '@/types';

export type BundleItem = {
    id: number;
    product_id: number;
    variant_id: number | null;
    quantity: number;
    product: Product;
    variant: ProductVariant | null;
};

export type ProductBundle = {
    id: number;
    name: string;
    description: string | null;
    image: string | null;
    discount_type: 'fixed_price' | 'free_delivery';
    bundle_price: number | null;
    /** Complete sets current stock can fill, from `BundleService::availableSets()`. */
    available_sets: number;
    items: BundleItem[];
};

const MAX_VISIBLE_THUMBS = 3;

/** A component's regular catalog price, ignoring any flash sale — mirrors `BundleService::basePriceFor()` on the backend. */
function basePrice(item: BundleItem): number {
    return item.variant?.price ?? item.product.price;
}

function thumbUrl(item: BundleItem): string {
    return (
        item.variant?.image ||
        item.product.primary_image_url ||
        getPrimaryImagePath(item.product) ||
        '/placeholder.svg'
    );
}

/**
 * Prorates `bundle.bundle_price` across its components the same way
 * `BundleService::unitPriceFor()` does server-side, so the price shown here
 * and the price `CartManager::resolvePrice()` settles on at sync/checkout
 * never disagree.
 */
function proratedUnitPrice(bundle: ProductBundle, item: BundleItem): number {
    if (
        bundle.discount_type !== 'fixed_price' ||
        bundle.bundle_price === null
    ) {
        return basePrice(item);
    }

    const referenceTotal = bundle.items.reduce(
        (sum, i) => sum + basePrice(i) * i.quantity,
        0,
    );

    if (referenceTotal <= 0) {
        return basePrice(item);
    }

    return (
        Math.round(
            ((bundle.bundle_price * basePrice(item)) / referenceTotal) * 100,
        ) / 100
    );
}

/**
 * A bundle reads as "these products, combined, equal this deal" — so the
 * card is built around a literal equation strip (thumbnail + thumbnail + …
 * = price/truck) instead of a generic product-card layout, to make the
 * combo mechanic legible at a glance rather than looking like one more
 * plain product tile.
 */
export function BundleCard({
    bundle,
}: {
    bundle: ProductBundle;
}): React.JSX.Element {
    const [multiplier, setMultiplier] = useState(1);
    const addItem = useCartStore((state) => state.addItem);
    const openCart = useUiStore((state) => state.openCart);
    const addToast = useUiStore((state) => state.addToast);

    const isOutOfStock = bundle.available_sets < 1;
    const isFixedPrice = bundle.discount_type === 'fixed_price';
    const originalTotal = bundle.items.reduce(
        (sum, item) => sum + basePrice(item) * item.quantity,
        0,
    );
    const bundleTotal = isFixedPrice
        ? (bundle.bundle_price ?? 0)
        : originalTotal;
    const savings = isFixedPrice ? Math.max(0, originalTotal - bundleTotal) : 0;

    const visibleItems = bundle.items.slice(0, MAX_VISIBLE_THUMBS);
    const extraCount = bundle.items.length - visibleItems.length;

    const handleAdd = () => {
        bundle.items.forEach((item) => {
            addItem({
                product: item.product,
                variant: item.variant,
                quantity: item.quantity * multiplier,
                price: proratedUnitPrice(bundle, item),
                bundleId: bundle.id,
                bundleUnitQuantity: item.quantity,
                skipMetaTracking: true,
            });
        });

        trackMetaProductEvent(
            'AddToCart',
            bundle.items.map((item) => ({
                productId: Number(item.product.id),
                variantId: item.variant ? Number(item.variant.id) : null,
                bundleId: Number(bundle.id),
                name: item.product.name,
                price: proratedUnitPrice(bundle, item),
                quantity: item.quantity * multiplier,
            })),
        );

        openCart();
        addToast({
            type: 'success',
            title: 'Bundle added',
            message: `${bundle.name} was added to your cart.`,
        });
    };

    return (
        <Card className="group relative overflow-hidden border-[var(--color-border)] bg-gradient-to-br from-[var(--color-surface)] to-[var(--color-accent-light)] shadow-[var(--shadow-card)] transition-shadow duration-300 hover:shadow-[var(--shadow-card-hover)]">
            {isFixedPrice && savings > 0 && (
                <div className="absolute top-4 right-4 z-10 rotate-3 rounded-full bg-[var(--color-accent)] px-3 py-1 text-xs font-bold text-white shadow-[var(--shadow-dropdown)]">
                    Save {formatCurrency(savings)}
                </div>
            )}

            <CardContent className="space-y-4 p-5">
                <div className="flex items-center gap-2">
                    <span className="flex size-7 shrink-0 items-center justify-center rounded-lg bg-[var(--color-primary-light)] text-[var(--color-primary)]">
                        <Package className="size-4" />
                    </span>
                    <span className="text-xs font-bold tracking-[0.18em] text-[var(--color-primary)] uppercase">
                        Bundle
                    </span>
                </div>

                <div className="space-y-1">
                    <h3 className="font-display text-lg font-semibold text-[var(--color-text-primary)]">
                        {bundle.name}
                    </h3>
                    {bundle.description && (
                        <p className="line-clamp-2 text-sm text-[var(--color-text-secondary)]">
                            {bundle.description}
                        </p>
                    )}
                </div>

                {/* The "equation": component thumbnails joined by + signs, resolving to the deal. */}
                <div className="flex items-center gap-2 overflow-x-auto rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)]/70 p-3">
                    {visibleItems.map((item, index) => (
                        <Fragment key={item.id}>
                            {index > 0 && (
                                <Plus className="size-3.5 shrink-0 text-[var(--color-text-muted)]" />
                            )}
                            <div className="relative shrink-0">
                                <div className="size-12 overflow-hidden rounded-xl border-2 border-[var(--color-surface)] shadow-[var(--shadow-card)] ring-1 ring-[var(--color-border)]">
                                    <img
                                        src={thumbUrl(item)}
                                        alt={item.product.name}
                                        className="size-full object-cover"
                                    />
                                </div>
                                {item.quantity > 1 && (
                                    <span className="absolute -right-1.5 -bottom-1.5 flex size-5 items-center justify-center rounded-full bg-[var(--color-primary)] text-[10px] font-bold text-white ring-2 ring-[var(--color-surface)]">
                                        {item.quantity}
                                    </span>
                                )}
                            </div>
                        </Fragment>
                    ))}
                    {extraCount > 0 && (
                        <span className="shrink-0 text-xs font-semibold text-[var(--color-text-secondary)]">
                            +{extraCount}
                        </span>
                    )}
                    <Equal className="size-3.5 shrink-0 text-[var(--color-text-muted)]" />
                    {isFixedPrice ? (
                        <span className="flex shrink-0 items-baseline gap-1.5">
                            <span className="text-base font-bold text-[var(--color-text-primary)]">
                                {formatCurrency(bundleTotal)}
                            </span>
                            {savings > 0 && (
                                <span className="text-xs text-[var(--color-text-muted)] line-through">
                                    {formatCurrency(originalTotal)}
                                </span>
                            )}
                        </span>
                    ) : (
                        <span className="flex shrink-0 items-center gap-1 rounded-full bg-[var(--color-success)]/10 px-2.5 py-1 text-xs font-bold text-[var(--color-success)]">
                            <Truck className="size-3.5" />
                            Free delivery
                        </span>
                    )}
                </div>

                <div className="flex items-center justify-between gap-3">
                    <div className="flex items-center gap-2 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] px-2 py-1">
                        <button
                            type="button"
                            onClick={() =>
                                setMultiplier((m) => Math.max(1, m - 1))
                            }
                            className="flex size-6 items-center justify-center rounded text-[var(--color-text-secondary)] hover:bg-[var(--color-surface-alt)]"
                        >
                            <Minus className="size-3.5" />
                        </button>
                        <span className="w-6 text-center text-sm font-medium text-[var(--color-text-primary)]">
                            {multiplier}
                        </span>
                        <button
                            type="button"
                            onClick={() => setMultiplier((m) => m + 1)}
                            disabled={multiplier >= bundle.available_sets}
                            className="flex size-6 items-center justify-center rounded text-[var(--color-text-secondary)] hover:bg-[var(--color-surface-alt)] disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            <Plus className="size-3.5" />
                        </button>
                    </div>
                    <Button
                        onClick={handleAdd}
                        disabled={isOutOfStock}
                        className="h-10 flex-1"
                    >
                        <ShoppingBag className="mr-2 size-4" />
                        {isOutOfStock ? 'Out of Stock' : 'Add Bundle'}
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}
