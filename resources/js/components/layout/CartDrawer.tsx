import { Link, usePage } from '@inertiajs/react';
import { ArrowRight, Package, ShoppingCart, Trash2 } from 'lucide-react';
import { useMemo } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Drawer } from '@/components/ui/drawer';
import { EmptyState } from '@/components/ui/empty-state';
import { QuantityStepper } from '@/components/ui/quantity-stepper';
import { groupCartItems } from '@/lib/cart-bundles';
import { getCouponDiscount } from '@/lib/coupon';
import { isDeliveryFree } from '@/lib/delivery';
import { formatCurrency, getPrimaryImagePath } from '@/lib/utils';
import cartRoutes from '@/routes/cart';
import { useCartStore } from '@/stores/cartStore';
import { useUiStore } from '@/stores/uiStore';
import type { CartItem, SharedPageProps } from '@/types';

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

    // The location rate for a saved default address. Without one, the charge
    // isn't known yet, so the wider rate is shown as an estimate.
    const {
        cart_base_shipping_rate: cartBaseRate,
        settings,
        active_bundle_recipes: bundleRecipes,
    } = usePage<SharedPageProps>().props;
    const isEmpty = items.length === 0;
    const isEstimate = cartBaseRate === null || cartBaseRate === undefined;

    const summary = useMemo(() => {
        const subtotal = items.reduce(
            (total, item) => total + item.subtotal,
            0,
        );
        const discount = coupon ? getCouponDiscount(coupon, items) : 0;
        const deliveryFree = isDeliveryFree(items, {
            subtotal,
            threshold: settings.free_delivery_threshold ?? 0,
            bundleRecipes,
        });
        const shipping =
            isEmpty || deliveryFree
                ? 0
                : (cartBaseRate ?? settings.shipping_rate_outside_dhaka ?? 120);

        return {
            subtotal,
            discount,
            shipping,
            deliveryFree,
            total: Math.max(0, subtotal - discount + shipping + tax),
            itemCount: items.reduce((count, item) => count + item.quantity, 0),
        };
    }, [
        bundleRecipes,
        cartBaseRate,
        coupon,
        isEmpty,
        items,
        settings.free_delivery_threshold,
        settings.shipping_rate_outside_dhaka,
        tax,
    ]);

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
                                    {summary.deliveryFree ? (
                                        <p className="mt-0.5 text-[11px] text-[var(--color-success)]">
                                            This order gets free delivery
                                        </p>
                                    ) : isEstimate ? (
                                        <p className="mt-0.5 text-[11px] text-[var(--color-text-muted)]">
                                            Estimate. Set by your address at
                                            checkout
                                        </p>
                                    ) : null}
                                </div>
                                <span className="shrink-0 font-semibold">
                                    {summary.deliveryFree ? (
                                        <span className="text-[var(--color-success)]">
                                            Free
                                        </span>
                                    ) : (
                                        <span className="text-[var(--color-text-primary)]">
                                            {formatCurrency(summary.shipping)}
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
                        // Same destination as the empty Cart page's button,
                        // and the drawer closes so the products are visible.
                        label: 'Start shopping',
                        href: route('products.index'),
                        onClick: closeCart,
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
    // Same order as the cart page: a cart from the server has no primary_image_url.
    const imageUrl =
        item.variant?.image ||
        item.product.primary_image_url ||
        getPrimaryImagePath(item.product);

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
                {imageUrl ? (
                    <img
                        src={imageUrl}
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
