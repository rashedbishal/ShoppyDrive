import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    Package,
    ShoppingBag,
    Tag,
    Trash2,
} from 'lucide-react';
import React from 'react';

import { Button } from '@/components/ui/button';
import { QuantityStepper } from '@/components/ui/quantity-stepper';
import { groupCartItems } from '@/lib/cart-bundles';
import { formatCurrency, getPrimaryImagePath } from '@/lib/utils';
import { useCartStore } from '@/stores/cartStore';
import type { CartItem as CartItemType } from '@/types';

export default function Cart(): React.JSX.Element {
    const {
        items,
        removeItem,
        updateQuantity,
        updateBundleQuantity,
        removeBundle,
        getSubtotal,
        shippingEstimate,
        tax,
    } = useCartStore();

    // Coupons are applied at checkout only (that's where the order is
    // priced), so this estimate deliberately leaves any coupon out.
    const subtotal = getSubtotal();
    const total = Math.max(0, subtotal + shippingEstimate + tax);

    if (items.length === 0) {
        return (
            <div className="flex items-center justify-center py-20">
                <Head title="Shopping Cart" />
                <div className="w-full max-w-md rounded-3xl border border-[var(--color-border)] bg-white p-12 text-center shadow-sm">
                    <div className="mx-auto mb-6 flex h-24 w-24 items-center justify-center rounded-full bg-[var(--color-surface-alt)] text-[var(--color-text-muted)]">
                        <ShoppingBag size={48} />
                    </div>
                    <h1 className="mb-3 text-2xl font-bold text-[var(--color-text-primary)]">
                        Your cart is empty
                    </h1>
                    <p className="mb-10 text-[var(--color-text-secondary)]">
                        Looks like you haven't added anything to your cart yet.
                        Browse our products and find something you like!
                    </p>
                    <Button
                        asChild
                        className="h-14 w-full rounded-2xl text-lg font-bold shadow-lg"
                    >
                        <Link href={route('products.index')}>
                            Start Shopping
                        </Link>
                    </Button>
                </div>
            </div>
        );
    }

    return (
        <div className="py-12">
            <Head title="Shopping Cart" />

            <div className="mx-auto max-w-6xl px-4">
                <div className="mb-6 flex items-center gap-3">
                    <Link
                        href={route('products.index')}
                        className="rounded-full border border-[var(--color-border)] bg-white p-2 text-[var(--color-text-muted)] transition-colors hover:text-[var(--color-primary)]"
                    >
                        <ArrowLeft size={18} />
                    </Link>
                    <h1 className="text-2xl font-bold text-[var(--color-text-primary)]">
                        Shopping Cart
                    </h1>
                </div>

                <div className="grid grid-cols-1 items-start gap-6 lg:grid-cols-12">
                    {/* Cart Items */}
                    <div className="space-y-3 lg:col-span-8">
                        <div className="overflow-hidden rounded-2xl border border-[var(--color-border)] bg-white shadow-sm">
                            <div className="hidden grid-cols-12 gap-4 border-b border-[var(--color-border)] px-5 py-2.5 text-[11px] font-bold tracking-widest text-[var(--color-text-muted)] uppercase md:grid">
                                <div className="col-span-6">Product</div>
                                <div className="col-span-2 text-center">
                                    Price
                                </div>
                                <div className="col-span-2 text-center">
                                    Quantity
                                </div>
                                <div className="col-span-2 text-right">
                                    Total
                                </div>
                            </div>

                            <div className="divide-y divide-[var(--color-border)]">
                                {groupCartItems(items).map((group) =>
                                    group.bundleId === null ? (
                                        <CartRow
                                            key={group.items[0].item_key}
                                            item={group.items[0]}
                                            onQuantityChange={(val) =>
                                                updateQuantity(
                                                    group.items[0].item_key,
                                                    val,
                                                )
                                            }
                                            onRemove={() =>
                                                removeItem(
                                                    group.items[0].item_key,
                                                )
                                            }
                                        />
                                    ) : (
                                        <div
                                            key={`bundle-${group.bundleId}`}
                                            className="bg-[var(--color-surface-alt)]/40"
                                        >
                                            <div className="flex flex-wrap items-center justify-between gap-3 px-4 pt-3">
                                                <div className="flex items-center gap-1.5 text-xs font-bold tracking-widest text-[var(--color-primary)] uppercase">
                                                    <Package size={14} />
                                                    {group.bundleName} (bundle)
                                                </div>
                                                <div className="flex items-center gap-3">
                                                    <QuantityStepper
                                                        value={
                                                            group.items[0]
                                                                .bundle_unit_quantity
                                                                ? group.items[0]
                                                                      .quantity /
                                                                  group.items[0]
                                                                      .bundle_unit_quantity!
                                                                : 1
                                                        }
                                                        onChange={(val) =>
                                                            updateBundleQuantity(
                                                                group.bundleId!,
                                                                val,
                                                            )
                                                        }
                                                        className="scale-90 rounded-lg"
                                                    />
                                                    <button
                                                        onClick={() =>
                                                            removeBundle(
                                                                group.bundleId!,
                                                            )
                                                        }
                                                        className="flex items-center gap-1 text-xs font-bold text-[var(--color-error)] transition-opacity hover:opacity-80"
                                                    >
                                                        <Trash2 size={13} />
                                                        Remove
                                                    </button>
                                                </div>
                                            </div>
                                            <div className="divide-y divide-[var(--color-border)]/60">
                                                {group.items.map((item) => (
                                                    <CartRow
                                                        key={item.item_key}
                                                        item={item}
                                                    />
                                                ))}
                                            </div>
                                        </div>
                                    ),
                                )}
                            </div>
                        </div>

                        <div className="flex items-center justify-between px-2">
                            <Link
                                href={route('products.index')}
                                className="flex items-center gap-2 text-sm font-bold text-[var(--color-text-muted)] transition-colors hover:text-[var(--color-primary)]"
                            >
                                <ArrowLeft size={16} />
                                Continue Shopping
                            </Link>
                            <span className="text-sm font-medium text-[var(--color-text-muted)]">
                                {items.length} items in your cart
                            </span>
                        </div>
                    </div>

                    {/* Order Summary */}
                    <div className="lg:col-span-4">
                        <div className="sticky top-24 overflow-hidden rounded-2xl border border-[var(--color-border)] bg-white shadow-sm">
                            <div className="px-6 pt-6 pb-5">
                                <h2 className="text-lg font-bold text-[var(--color-text-primary)]">
                                    Order Summary
                                </h2>
                                <p className="mt-0.5 text-xs text-[var(--color-text-muted)]">
                                    Review your totals before checkout.
                                </p>
                            </div>

                            <div className="space-y-6 border-t border-[var(--color-border)] px-6 py-6">
                                {/* Coupons are entered at checkout, where the order is priced. */}
                                <p className="flex items-center gap-2 rounded-xl bg-[var(--color-surface-alt)] px-3 py-2.5 text-xs text-[var(--color-text-secondary)]">
                                    <Tag
                                        size={14}
                                        className="shrink-0 text-[var(--color-primary)]"
                                    />
                                    Have a promo code? You can apply it at
                                    checkout.
                                </p>

                                {/* Totals */}
                                <div className="space-y-2.5 text-sm">
                                    <div className="flex items-center justify-between">
                                        <span className="text-[var(--color-text-secondary)]">
                                            Subtotal
                                        </span>
                                        <span className="font-semibold text-[var(--color-text-primary)]">
                                            {formatCurrency(subtotal)}
                                        </span>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <span className="text-[var(--color-text-secondary)]">
                                            Shipping
                                        </span>
                                        <span className="font-semibold text-[var(--color-text-primary)]">
                                            {shippingEstimate > 0
                                                ? formatCurrency(
                                                      shippingEstimate,
                                                  )
                                                : 'Calculated at checkout'}
                                        </span>
                                    </div>
                                </div>

                                {/* Total */}
                                <div className="rounded-xl border border-[var(--color-border)] bg-[var(--color-surface-alt)] p-4">
                                    <p className="text-xs text-[var(--color-text-muted)]">
                                        Estimated Total
                                    </p>
                                    <p className="mt-1 text-3xl font-bold text-[var(--color-primary)]">
                                        {formatCurrency(total)}
                                    </p>
                                </div>

                                <Button
                                    asChild
                                    size="lg"
                                    className="h-13 w-full rounded-xl text-base font-bold shadow-[var(--color-primary)]/20 shadow-lg"
                                >
                                    <Link href={route('checkout.index')}>
                                        Proceed to Checkout
                                        <ArrowRight size={16} />
                                    </Link>
                                </Button>

                                <div className="flex items-center justify-center gap-3 text-[10px] font-bold tracking-widest text-[var(--color-text-muted)] uppercase">
                                    <span>Secure Payment</span>
                                    <div className="h-1 w-1 rounded-full bg-[var(--color-border)]" />
                                    <span>Easy Returns</span>
                                    <div className="h-1 w-1 rounded-full bg-[var(--color-border)]" />
                                    <span>Genuine Products</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

type CartRowProps = {
    item: CartItemType;
    /** Omitted for a bundle's sub-rows — quantity/remove live on the bundle group header instead. */
    onQuantityChange?: (value: number) => void;
    onRemove?: () => void;
};

function CartRow({
    item,
    onQuantityChange,
    onRemove,
}: CartRowProps): React.JSX.Element {
    return (
        <div className="grid grid-cols-1 items-center gap-3 p-4 md:grid-cols-12">
            {/* Product Info */}
            <div className="flex gap-3 md:col-span-6">
                <Link
                    href={route('product.show', item.product.slug)}
                    className="h-16 w-16 shrink-0 overflow-hidden rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-alt)]"
                >
                    <img
                        src={
                            item.variant?.image ||
                            item.product.primary_image_url ||
                            getPrimaryImagePath(item.product)
                        }
                        alt={item.product.name}
                        className="h-full w-full object-cover"
                    />
                </Link>
                <div className="flex min-w-0 flex-col justify-center">
                    <Link
                        href={route('product.show', item.product.slug)}
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
                                        className="text-[11px] font-bold tracking-wider text-[var(--color-text-muted)] uppercase"
                                    >
                                        {key}: {value}
                                    </span>
                                ),
                            )}
                        </div>
                    )}
                    {onRemove && (
                        <button
                            onClick={onRemove}
                            className="mt-1.5 flex items-center gap-1 text-xs font-bold text-[var(--color-error)] transition-opacity hover:opacity-80"
                        >
                            <Trash2 size={13} />
                            Remove
                        </button>
                    )}
                </div>
            </div>

            {/* Price */}
            <div className="text-center md:col-span-2">
                <span className="mb-1 block text-xs font-bold text-[var(--color-text-muted)] uppercase md:hidden">
                    Price
                </span>
                <span className="text-sm font-bold text-[var(--color-text-primary)]">
                    {formatCurrency(item.price)}
                </span>
            </div>

            {/* Quantity */}
            <div className="flex flex-col items-center md:col-span-2">
                <span className="mb-1 block text-xs font-bold text-[var(--color-text-muted)] uppercase md:hidden">
                    Quantity
                </span>
                {onQuantityChange ? (
                    <QuantityStepper
                        value={item.quantity}
                        onChange={onQuantityChange}
                        max={
                            item.variant?.stock_quantity ??
                            item.product.stock_quantity
                        }
                        className="scale-90 rounded-lg"
                    />
                ) : (
                    <span className="text-sm font-semibold text-[var(--color-text-secondary)]">
                        × {item.quantity}
                    </span>
                )}
            </div>

            {/* Total */}
            <div className="text-right md:col-span-2">
                <span className="mb-1 block text-xs font-bold text-[var(--color-text-muted)] uppercase md:hidden">
                    Total
                </span>
                <span className="text-sm font-bold text-[var(--color-primary)]">
                    {formatCurrency(item.subtotal)}
                </span>
            </div>
        </div>
    );
}
