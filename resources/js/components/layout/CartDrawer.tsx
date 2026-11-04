import { Link, usePage } from '@inertiajs/react';
import { ArrowRight, Package, ShoppingCart, Trash2 } from 'lucide-react';
import { useMemo } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Drawer } from '@/components/ui/drawer';
import { EmptyState } from '@/components/ui/empty-state';
import { QuantityStepper } from '@/components/ui/quantity-stepper';
import { groupCartItems } from '@/lib/cart-bundles';
import { formatCurrency } from '@/lib/utils';
import cartRoutes from '@/routes/cart';
import { useCartStore } from '@/stores/cartStore';
import { useUiStore } from '@/stores/uiStore';
import type { CartItem, SharedPageProps } from '@/types';

function calculateCouponDiscount(
    subtotal: number,
    couponType: 'percent' | 'fixed' | null,
    couponValue: number,
): number {
    if (couponType === null) {
        return 0;
    }

    if (couponType === 'percent') {
        return Math.min(subtotal, (subtotal * couponValue) / 100);
    }

    return Math.min(subtotal, couponValue);
}

export default function CartDrawer(): React.JSX.Element {
    const isOpen = useUiStore((state) => state.cartOpen);
    const closeCart = useUiStore((state) => state.closeCart);
    const items = useCartStore((state) => state.items);
    const coupon = useCartStore((state) => state.coupon);
    const tax = useCartStore((state) => state.tax);
    const updateQuantity = useCartStore((state) => state.updateQuantity);
    const removeItem = useCartStore((state) => state.removeItem);
    const updateBundleQuantity = useCartStore(
        (state) => state.updateBundleQuantity,
    );
    const removeBundle = useCartStore((state) => state.removeBundle);

    // Shared props: shipping cost (applies free-delivery rules) and the raw
    // location-based rate (ignores free-delivery rules, used as instant fallback).
    const {
        cart_shipping_cost: cartShippingCost,
        cart_base_shipping_rate: cartBaseRate,
    } = usePage<SharedPageProps>().props;
    const isEmpty = items.length === 0;

    // Client-side free-delivery check.
    // hydrateCart populates fresh product data on page load, so free_delivery_min_qty
    // is always a number or null (never undefined) by the time the user interacts.
    const allItemsFreeDelivery = useMemo(() => {
        if (isEmpty) {
            return false;
        }

        return items.every((item) => {
            const p = item.product;

            if (!p.free_delivery) {
                return false;
            }

            const minQty = p.free_delivery_min_qty ?? null;

            if (minQty !== null) {
                return item.quantity >= minQty;
            }

            return true;
        });
    }, [items, isEmpty]);

    // Fully client-side — no router call needed, no drawer close.
    // allItemsFreeDelivery handles the "charge → free" direction instantly.
    // cartBaseRate (location rate, ignores free-delivery) handles "free → charge" instantly.
    const effectiveShipping = isEmpty
        ? 0
        : allItemsFreeDelivery
          ? 0
          : (cartBaseRate ?? cartShippingCost ?? 0);

    const summary = useMemo(() => {
        const subtotal = items.reduce(
            (total, item) => total + item.subtotal,
            0,
        );
        const discount = calculateCouponDiscount(
            subtotal,
            coupon?.type ?? null,
            coupon?.discount_amount ?? coupon?.value ?? 0,
        );
        const total = Math.max(
            0,
            subtotal - discount + effectiveShipping + tax,
        );

        return {
            subtotal,
            discount,
            shipping: effectiveShipping,
            total,
            itemCount: items.reduce((count, item) => count + item.quantity, 0),
        };
    }, [coupon, items, effectiveShipping, tax]);

    return (
        <Drawer
            isOpen={isOpen}
            onClose={closeCart}
            title={`Your Cart (${summary.itemCount})`}
            description="Review items before checkout."
            side="right"
            size="lg"
            footer={
                <div className="space-y-4">
                    {!isEmpty && (
                        <div className="space-y-2 rounded-2xl border border-[var(--color-border)] bg-surface p-4">
                            <div className="flex items-center justify-between text-sm">
                                <span className="text-[var(--color-text-secondary)]">
                                    Subtotal
                                </span>
                                <span className="font-semibold text-[var(--color-text-primary)]">
                                    {formatCurrency(summary.subtotal)}
                                </span>
                            </div>
                            <div className="flex items-center justify-between text-sm">
                                <span className="text-[var(--color-text-secondary)]">
                                    Discount
                                </span>
                                <span className="font-semibold text-[var(--color-success)]">
                                    -{formatCurrency(summary.discount)}
                                </span>
                            </div>
                            <div className="flex items-start justify-between gap-2 text-sm">
                                <div>
                                    <span className="text-[var(--color-text-secondary)]">
                                        Shipping
                                    </span>
                                    {effectiveShipping === 0 ? (
                                        <p className="mt-0.5 text-[11px] text-[var(--color-success)]">
                                            All items qualify for free delivery
                                        </p>
                                    ) : effectiveShipping > 0 ? (
                                        <p className="mt-0.5 text-[11px] text-[var(--color-text-muted)]">
                                            1+ item requires delivery charge
                                        </p>
                                    ) : null}
                                </div>
                                <span className="shrink-0 font-semibold">
                                    {cartShippingCost === null &&
                                    !allItemsFreeDelivery ? (
                                        <span className="text-xs font-normal text-[var(--color-text-muted)]">
                                            At checkout
                                        </span>
                                    ) : effectiveShipping === 0 ? (
                                        <span className="text-[var(--color-success)]">
                                            Free
                                        </span>
                                    ) : (
                                        <span className="text-[var(--color-text-primary)]">
                                            {formatCurrency(effectiveShipping)}
                                        </span>
                                    )}
                                </span>
                            </div>
                            <div className="flex items-center justify-between text-sm">
                                <span className="text-[var(--color-text-secondary)]">
                                    Tax
                                </span>
                                <span className="font-semibold text-[var(--color-text-primary)]">
                                    {formatCurrency(tax)}
                                </span>
                            </div>
                            <div className="flex items-center justify-between border-t border-[var(--color-border)] pt-3">
                                <span className="text-sm font-semibold text-[var(--color-text-primary)]">
                                    Total
                                </span>
                                <span className="font-mono text-lg font-semibold text-[var(--color-secondary)]">
                                    {formatCurrency(summary.total)}
                                </span>
                            </div>
                        </div>
                    )}

                    <div className="mt-2 grid gap-3 sm:grid-cols-2">
                        {isEmpty ? (
                            <Button
                                variant="outline"
                                fullWidth
                                disabled
                                className="h-12 cursor-not-allowed rounded-xl font-semibold opacity-40"
                            >
                                View cart
                            </Button>
                        ) : (
                            <Link
                                href={cartRoutes.index.url()}
                                prefetch
                                onClick={closeCart}
                                className="block w-full"
                            >
                                <Button
                                    variant="outline"
                                    fullWidth
                                    className="h-12 rounded-xl border border-[var(--color-border)] bg-white font-semibold text-[var(--color-text-primary)] shadow-sm transition-all hover:border-[var(--color-primary)] hover:bg-[var(--color-primary-light)] hover:text-[var(--color-primary)]"
                                >
                                    View cart
                                </Button>
                            </Link>
                        )}
                        {isEmpty ? (
                            <Button
                                fullWidth
                                variant="primary"
                                disabled
                                className="h-12 cursor-not-allowed rounded-xl font-bold opacity-40"
                            >
                                Checkout
                            </Button>
                        ) : (
                            <Link
                                href="/checkout"
                                onClick={closeCart}
                                className="block w-full"
                            >
                                <Button
                                    fullWidth
                                    variant="primary"
                                    rightIcon={
                                        <ArrowRight className="size-4 transition-transform group-hover:translate-x-1" />
                                    }
                                    className="group h-12 rounded-xl font-bold transition-all hover:-translate-y-0.5"
                                >
                                    Checkout
                                </Button>
                            </Link>
                        )}
                    </div>
                </div>
            }
        >
            {items.length === 0 ? (
                <EmptyState
                    icon={ShoppingCart}
                    title="Your cart is empty"
                    description="Add products to see them here and continue to checkout."
                    action={{
                        label: 'Start shopping',
                        href: '/',
                    }}
                />
            ) : (
                <div className="space-y-3">
                    {groupCartItems(items).map((group) =>
                        group.bundleId === null ? (
                            <DrawerCartRow
                                key={group.items[0].item_key}
                                item={group.items[0]}
                                onNavigate={closeCart}
                                onQuantityChange={(value) =>
                                    updateQuantity(
                                        group.items[0].item_key,
                                        value,
                                    )
                                }
                                onRemove={() =>
                                    removeItem(group.items[0].item_key)
                                }
                            />
                        ) : (
                            <div
                                key={`bundle-${group.bundleId}`}
                                className="space-y-2 rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface-alt)]/40 p-3 shadow-[var(--shadow-card)]"
                            >
                                <div className="flex flex-wrap items-center justify-between gap-2 px-1">
                                    <div className="flex items-center gap-1.5 text-xs font-bold tracking-widest text-[var(--color-primary)] uppercase">
                                        <Package size={14} />
                                        {group.bundleName} (bundle)
                                    </div>
                                    <div className="flex items-center gap-3">
                                        <QuantityStepper
                                            value={
                                                group.items[0]
                                                    .bundle_unit_quantity
                                                    ? group.items[0].quantity /
                                                      group.items[0]
                                                          .bundle_unit_quantity!
                                                    : 1
                                            }
                                            onChange={(value) =>
                                                updateBundleQuantity(
                                                    group.bundleId!,
                                                    value,
                                                )
                                            }
                                            className="scale-90 rounded-lg"
                                        />
                                        <button
                                            type="button"
                                            onClick={() =>
                                                removeBundle(group.bundleId!)
                                            }
                                            aria-label={`Remove ${group.bundleName} bundle from cart`}
                                            className="inline-flex size-9 items-center justify-center rounded-lg text-[var(--color-text-secondary)] transition hover:bg-[var(--color-primary-light)] hover:text-[var(--color-error)]"
                                        >
                                            <Trash2 className="size-4" />
                                        </button>
                                    </div>
                                </div>
                                <div className="space-y-2">
                                    {group.items.map((item) => (
                                        <DrawerCartRow
                                            key={item.item_key}
                                            item={item}
                                            onNavigate={closeCart}
                                            compact
                                        />
                                    ))}
                                </div>
                            </div>
                        ),
                    )}
                </div>
            )}
        </Drawer>
    );
}

type DrawerCartRowProps = {
    item: CartItem;
    /** Closes the drawer when the product image/name is clicked through to its page. */
    onNavigate?: () => void;
    /** Omitted for a bundle's sub-rows — quantity/remove live on the bundle group header instead. */
    onQuantityChange?: (value: number) => void;
    onRemove?: () => void;
    /** Tighter padding/sizing for a bundle's sub-rows nested under the group header. */
    compact?: boolean;
};

function DrawerCartRow({
    item,
    onNavigate,
    onQuantityChange,
    onRemove,
    compact = false,
}: DrawerCartRowProps): React.JSX.Element {
    return (
        <div
            className={
                compact
                    ? 'flex gap-3 rounded-xl bg-surface p-3'
                    : 'flex gap-4 rounded-2xl border border-[var(--color-border)] bg-surface p-4 shadow-[var(--shadow-card)]'
            }
        >
            <Link
                href={route('product.show', item.product.slug)}
                onClick={onNavigate}
                className={
                    compact
                        ? 'flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-[var(--color-surface-alt)]'
                        : 'flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-[var(--color-surface-alt)]'
                }
            >
                {item.product.primary_image_url ? (
                    <img
                        src={item.product.primary_image_url}
                        alt={item.product.name}
                        className="size-full object-cover"
                        loading="lazy"
                    />
                ) : (
                    <ShoppingCart className="size-6 text-[var(--color-text-muted)]" />
                )}
            </Link>

            <div className="min-w-0 flex-1 space-y-2">
                <div className="space-y-1">
                    <Link
                        href={route('product.show', item.product.slug)}
                        onClick={onNavigate}
                        className="block truncate text-sm font-semibold text-[var(--color-text-primary)] transition-colors hover:text-[var(--color-primary)]"
                    >
                        {item.product.name}
                    </Link>
                    {item.variant && (
                        <p className="text-xs font-medium tracking-wide text-[var(--color-text-secondary)] uppercase">
                            {Object.entries(item.variant.attributes)
                                .map(([key, value]) => `${key}: ${value}`)
                                .join(' | ')}
                        </p>
                    )}
                </div>

                <div className="flex items-center justify-between gap-3">
                    {onQuantityChange ? (
                        <QuantityStepper
                            value={item.quantity}
                            min={1}
                            max={
                                (item.variant?.stock_quantity ??
                                    item.product.stock_quantity) > 0
                                    ? (item.variant?.stock_quantity ??
                                      item.product.stock_quantity)
                                    : 99
                            }
                            onChange={onQuantityChange}
                        />
                    ) : (
                        <span className="text-sm font-semibold text-[var(--color-text-secondary)]">
                            × {item.quantity}
                        </span>
                    )}

                    {onRemove && (
                        <button
                            type="button"
                            onClick={onRemove}
                            aria-label={`Remove ${item.product.name} from cart`}
                            className="inline-flex size-9 items-center justify-center rounded-lg text-[var(--color-text-secondary)] transition hover:bg-[var(--color-primary-light)] hover:text-[var(--color-primary)]"
                        >
                            <Trash2 className="size-4" />
                        </button>
                    )}
                </div>

                <div className="flex items-center justify-between">
                    <Badge variant="secondary" size="sm">
                        {formatCurrency(item.price)}
                    </Badge>
                    <span className="font-mono text-sm font-semibold text-[var(--color-text-primary)]">
                        {formatCurrency(item.subtotal)}
                    </span>
                </div>
            </div>
        </div>
    );
}
