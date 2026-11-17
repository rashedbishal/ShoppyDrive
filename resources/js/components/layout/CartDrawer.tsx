import { Link, router, usePage } from '@inertiajs/react';
import { ArrowRight, Package, ShoppingCart, Trash2, Truck } from 'lucide-react';
import { useMemo } from 'react';
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
    const freeDeliveryThreshold = settings.free_delivery_threshold ?? 0;

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
                <div className="space-y-3">
                    {/* Free-delivery nudge: same comparison as isDeliveryFree (pre-discount subtotal vs. the threshold), so it can never disagree with the shipping line. */}
                    {!isEmpty &&
                    !summary.deliveryFree &&
                    freeDeliveryThreshold > 0 ? (
                        <div className="space-y-1.5 rounded-xl bg-[var(--color-primary-light)] px-3 py-2.5">
                            <p className="flex items-center gap-1.5 text-xs font-medium text-[var(--color-text-primary)]">
                                <Truck className="size-3.5 shrink-0 text-[var(--color-primary)]" />
                                <span>
                                    Add{' '}
                                    <span className="font-bold text-[var(--color-primary)]">
                                        {formatCurrency(
                                            freeDeliveryThreshold -
                                                summary.subtotal,
                                        )}
                                    </span>{' '}
                                    more for free delivery
                                </span>
                            </p>
                            <div className="h-1.5 overflow-hidden rounded-full bg-white">
                                <div
                                    className="h-full rounded-full bg-[var(--color-primary)] transition-all"
                                    style={{
                                        width: `${Math.min(100, (summary.subtotal / freeDeliveryThreshold) * 100)}%`,
                                    }}
                                />
                            </div>
                        </div>
                    ) : null}

                    {!isEmpty && (
                        <div className="space-y-2 rounded-xl border border-[var(--color-border)] bg-surface p-3.5">
                            <div className="flex items-center justify-between text-sm">
                                <span className="text-[var(--color-text-secondary)]">
                                    Subtotal
                                </span>
                                <span className="font-semibold text-[var(--color-text-primary)]">
                                    {formatCurrency(summary.subtotal)}
                                </span>
                            </div>
                            {/* Discount and tax only when they apply (tax is currently always 0). */}
                            {summary.discount > 0 ? (
                                <div className="flex items-center justify-between text-sm">
                                    <span className="text-[var(--color-text-secondary)]">
                                        Discount
                                    </span>
                                    <span className="font-semibold text-[var(--color-success)]">
                                        -{formatCurrency(summary.discount)}
                                    </span>
                                </div>
                            ) : null}
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
                                        <p className="mt-0.5 text-[11px] text-[var(--color-text-secondary)]">
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
                            {tax > 0 ? (
                                <div className="flex items-center justify-between text-sm">
                                    <span className="text-[var(--color-text-secondary)]">
                                        Tax
                                    </span>
                                    <span className="font-semibold text-[var(--color-text-primary)]">
                                        {formatCurrency(tax)}
                                    </span>
                                </div>
                            ) : null}
                            <div className="flex items-center justify-between border-t border-[var(--color-border)] pt-2.5">
                                <span className="text-sm font-semibold text-[var(--color-text-primary)]">
                                    Total
                                </span>
                                <span className="text-lg font-bold text-[var(--color-secondary)]">
                                    {formatCurrency(summary.total)}
                                </span>
                            </div>
                        </div>
                    )}

                    <div className="grid gap-2.5 sm:grid-cols-2">
                        {isEmpty ? (
                            <Button
                                variant="outline"
                                fullWidth
                                disabled
                                className="h-11 cursor-not-allowed rounded-xl font-semibold opacity-40"
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
                                    className="h-11 rounded-xl border border-[var(--color-border)] bg-white font-semibold text-[var(--color-text-primary)] shadow-sm transition-all hover:border-[var(--color-primary)] hover:bg-[var(--color-primary-light)] hover:text-[var(--color-primary)]"
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
                                className="h-11 cursor-not-allowed rounded-xl font-bold opacity-40"
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
                                    className="group h-11 rounded-xl font-bold transition-all hover:-translate-y-0.5"
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
                        // Close the panel first, so it never stays open over the page
                        // the visitor is already on.
                        onClick: () => {
                            closeCart();

                            if (window.location.pathname !== '/products') {
                                router.visit('/products');
                            }
                        },
                    }}
                />
            ) : (
                // One divided list, like the /cart page (owner request 2026-11-15), instead of a bordered card per item.
                <div className="-mt-1 divide-y divide-[var(--color-border)]">
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
                                className="py-3"
                            >
                                <div className="rounded-xl border border-[var(--color-border)] bg-[var(--color-surface-alt)]/40 px-3">
                                    <div className="flex flex-wrap items-center justify-between gap-2 border-b border-[var(--color-border)]/60 py-2.5">
                                        <div className="flex min-w-0 items-center gap-1.5 text-xs font-bold tracking-widest text-[var(--color-primary)] uppercase">
                                            <Package
                                                size={14}
                                                className="shrink-0"
                                            />
                                            <span className="truncate">
                                                {group.bundleName} (bundle)
                                            </span>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <QuantityStepper
                                                size="sm"
                                                value={
                                                    group.items[0]
                                                        .bundle_unit_quantity
                                                        ? group.items[0]
                                                              .quantity /
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
                                            />
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    removeBundle(
                                                        group.bundleId!,
                                                    )
                                                }
                                                aria-label={`Remove ${group.bundleName} bundle from cart`}
                                                className="flex items-center gap-1 text-xs font-bold text-[var(--color-error)] transition-opacity hover:opacity-80"
                                            >
                                                <Trash2 size={13} />
                                                Remove
                                            </button>
                                        </div>
                                    </div>
                                    <div className="divide-y divide-[var(--color-border)]/60">
                                        {group.items.map((item) => (
                                            <DrawerCartRow
                                                key={item.item_key}
                                                item={item}
                                                onNavigate={closeCart}
                                            />
                                        ))}
                                    </div>
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
};

/**
 * One cart line in the side panel, in the /cart page row's style: 64px
 * bordered image, bold two-line name, variant, the quantity, and the line
 * total with the unit price and a "Remove" link under it.
 * The row reads the panel's own width (container query): wide enough
 * (≥ 28rem), the quantity sits in its own column; narrower (phones), it
 * moves under the name so nothing is squeezed.
 */
function DrawerCartRow({
    item,
    onNavigate,
    onQuantityChange,
    onRemove,
}: DrawerCartRowProps): React.JSX.Element {
    // Same order as the cart page: a cart from the server has no primary_image_url.
    const imageUrl =
        item.variant?.image ||
        item.product.primary_image_url ||
        getPrimaryImagePath(item.product);
    const stock = item.variant?.stock_quantity ?? item.product.stock_quantity;

    return (
        <div className="@container py-3">
            {/* One row like the /cart page: product | quantity | total. A phone-width panel just shrinks the image and gaps. */}
            <div className="grid grid-cols-[minmax(0,1fr)_auto_auto] items-center gap-x-2 @md:gap-x-3">
                <div className="flex min-w-0 gap-2.5 @md:gap-3">
                    <Link
                        href={route('product.show', item.product.slug)}
                        onClick={onNavigate}
                        className="flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-alt)] @md:size-16"
                    >
                        {imageUrl ? (
                            <img
                                src={imageUrl}
                                alt={item.product.name}
                                className="size-full object-cover"
                                loading="lazy"
                            />
                        ) : (
                            <ShoppingCart className="size-5 text-[var(--color-text-muted)]" />
                        )}
                    </Link>
                    <div className="flex min-w-0 flex-col justify-center">
                        <Link
                            href={route('product.show', item.product.slug)}
                            onClick={onNavigate}
                            className="line-clamp-2 text-sm font-bold text-[var(--color-text-primary)] transition-colors hover:text-[var(--color-primary)]"
                        >
                            {item.product.name}
                        </Link>
                        {item.variant && (
                            <div className="mt-0.5 flex flex-wrap gap-x-2 gap-y-0.5">
                                {Object.entries(item.variant.attributes).map(
                                    ([key, value]) => (
                                        <span
                                            key={key}
                                            className="text-[11px] font-bold tracking-wider text-[var(--color-text-secondary)] uppercase"
                                        >
                                            {key}: {value}
                                        </span>
                                    ),
                                )}
                            </div>
                        )}
                    </div>
                </div>

                <div>
                    {onQuantityChange ? (
                        <QuantityStepper
                            size="sm"
                            value={item.quantity}
                            min={1}
                            max={stock > 0 ? stock : 99}
                            onChange={onQuantityChange}
                        />
                    ) : (
                        <span className="text-sm font-semibold text-[var(--color-text-secondary)]">
                            × {item.quantity}
                        </span>
                    )}
                </div>

                <div className="flex flex-col items-end text-right">
                    <p className="text-sm font-bold whitespace-nowrap text-[var(--color-primary)]">
                        {formatCurrency(item.subtotal)}
                    </p>
                    <p className="text-[11px] whitespace-nowrap text-[var(--color-text-secondary)]">
                        {formatCurrency(item.price)} each
                    </p>
                    {onRemove && (
                        <button
                            type="button"
                            onClick={onRemove}
                            aria-label={`Remove ${item.product.name} from cart`}
                            className="mt-1.5 flex w-fit items-center gap-1 text-xs font-bold text-[var(--color-error)] transition-opacity hover:opacity-80"
                        >
                            <Trash2 size={13} />
                            Remove
                        </button>
                    )}
                </div>
            </div>
        </div>
    );
}
