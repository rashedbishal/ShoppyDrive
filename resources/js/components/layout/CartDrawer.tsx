import { Link, usePage } from '@inertiajs/react';
import { ArrowRight, ShoppingCart, Trash2 } from 'lucide-react';
import { useMemo } from 'react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Drawer } from '@/components/ui/drawer';
import { QuantityStepper } from '@/components/ui/quantity-stepper';
import { EmptyState } from '@/components/ui/empty-state';
import { formatCurrency } from '@/lib/utils';
import { useCartStore } from '@/stores/cartStore';
import { useUiStore } from '@/stores/uiStore';
import type { SharedPageProps } from '@/types';
import cartRoutes from '@/routes/cart';

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

    // Shipping cost from server: based on the user's default address
    const { cart_shipping_cost: cartShippingCost } = usePage<SharedPageProps>().props;
    const isEmpty = items.length === 0;

    const summary = useMemo(() => {
        const subtotal = items.reduce((total, item) => total + item.subtotal, 0);
        const discount = calculateCouponDiscount(
            subtotal,
            coupon?.type ?? null,
            coupon?.discount_amount ?? coupon?.value ?? 0,
        );
        // Use server-resolved shipping cost; fall back to 0 when cart is empty
        const shipping = isEmpty ? 0 : (cartShippingCost ?? 0);
        const total = Math.max(0, subtotal - discount + shipping + tax);

        return {
            subtotal,
            discount,
            shipping,
            total,
            itemCount: items.reduce((count, item) => count + item.quantity, 0),
        };
    }, [coupon, items, cartShippingCost, isEmpty, tax]);

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
                    <div className="space-y-2 rounded-2xl border border-[var(--color-border)] bg-surface p-4">
                        <div className="flex items-center justify-between text-sm">
                            <span className="text-[var(--color-text-secondary)]">Subtotal</span>
                            <span className="font-semibold text-[var(--color-text-primary)]">
                                {formatCurrency(summary.subtotal)}
                            </span>
                        </div>
                        <div className="flex items-center justify-between text-sm">
                            <span className="text-[var(--color-text-secondary)]">Discount</span>
                            <span className="font-semibold text-[var(--color-success)]">
                                -{formatCurrency(summary.discount)}
                            </span>
                        </div>
                        <div className="flex items-center justify-between text-sm">
                            <span className="text-[var(--color-text-secondary)]">Shipping</span>
                            <span className="font-semibold text-[var(--color-text-primary)]">
                                {isEmpty
                                    ? '—'
                                    : cartShippingCost !== null
                                        ? formatCurrency(cartShippingCost)
                                        : <span className="text-xs font-normal text-[var(--color-text-muted)]">At checkout</span>
                                }
                            </span>
                        </div>
                        <div className="flex items-center justify-between text-sm">
                            <span className="text-[var(--color-text-secondary)]">Tax</span>
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

                    <div className="grid gap-3 sm:grid-cols-2 mt-2">
                        {isEmpty ? (
                            <Button
                                variant="outline"
                                fullWidth
                                disabled
                                className="h-12 rounded-xl font-semibold opacity-40 cursor-not-allowed"
                            >
                                View cart
                            </Button>
                        ) : (
                            <Link href={cartRoutes.index.url()} onClick={closeCart} className="block w-full">
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
                                className="h-12 rounded-xl font-bold opacity-40 cursor-not-allowed"
                            >
                                Checkout
                            </Button>
                        ) : (
                            <Link href="/checkout" onClick={closeCart} className="block w-full">
                                <Button
                                    fullWidth
                                    variant="primary"
                                    rightIcon={<ArrowRight className="size-4 transition-transform group-hover:translate-x-1" />}
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
                    {items.map((item) => (
                        <div
                            key={item.item_key}
                            className="flex gap-4 rounded-2xl border border-[var(--color-border)] bg-surface p-4 shadow-[var(--shadow-card)]"
                        >
                            <div className="flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-[var(--color-surface-alt)]">
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
                            </div>

                            <div className="min-w-0 flex-1 space-y-3">
                                <div className="space-y-1">
                                    <p className="truncate text-sm font-semibold text-[var(--color-text-primary)]">
                                        {item.product.name}
                                    </p>
                                    <p className="text-xs text-[var(--color-text-secondary)]">
                                        {item.variant ? `Variant #${item.variant.id}` : 'Standard item'}
                                    </p>
                                </div>

                                <div className="flex items-center justify-between gap-3">
                                    <QuantityStepper
                                        value={item.quantity}
                                        min={1}
                                        max={item.product.stock_quantity > 0 ? item.product.stock_quantity : 99}
                                        onChange={(value) => updateQuantity(item.item_key, value)}
                                    />

                                    <button
                                        type="button"
                                        onClick={() => removeItem(item.item_key)}
                                        aria-label={`Remove ${item.product.name} from cart`}
                                        className="inline-flex size-9 items-center justify-center rounded-lg text-[var(--color-text-secondary)] transition hover:bg-[var(--color-primary-light)] hover:text-[var(--color-primary)]"
                                    >
                                        <Trash2 className="size-4" />
                                    </button>
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
                    ))}
                </div>
            )}
        </Drawer>
    );
}
