import { create } from 'zustand';
import { createJSONStorage, persist } from 'zustand/middleware';
import { CART_ITEM_ADDED_EVENT } from '@/lib/add-to-cart-feedback';
import type { CartItemAddedDetail } from '@/lib/add-to-cart-feedback';
import { autoAssembleBundles } from '@/lib/cart-bundles';
import { STORAGE_KEYS } from '@/lib/constants';
import { getCouponEligibleSubtotal } from '@/lib/coupon';
import { trackMetaProductEvent } from '@/lib/meta-tracking';
import type { MetaTrackedItem } from '@/lib/meta-tracking';
import { formatCurrency } from '@/lib/utils';
import { useUiStore } from '@/stores/uiStore';
import type {
    BundleRecipe,
    Cart,
    CartItem,
    Coupon,
    Product,
    ProductVariant,
} from '@/types';

type CartPayload = {
    product: Product;
    quantity?: number;
    variant?: ProductVariant | null;
    price?: number;
    bundleId?: number | null;
    bundleUnitQuantity?: number | null;
    /** The caller reports AddToCart itself (BundleCard: one event for the whole bundle). */
    skipMetaTracking?: boolean;
};

type CartStoreState = {
    items: CartItem[];
    coupon: Coupon | null;
    shippingEstimate: number;
    tax: number;
    /** Active bundle recipes, kept in sync with the `active_bundle_recipes` shared prop by app.tsx — not persisted, it's catalog data, not cart state. */
    bundleRecipes: BundleRecipe[];
    setBundleRecipes: (recipes: BundleRecipe[]) => void;
    addItem: (payload: CartPayload) => void;
    removeItem: (itemKey: string) => void;
    updateQuantity: (itemKey: string, quantity: number) => void;
    updateBundleQuantity: (bundleId: number, multiplier: number) => void;
    removeBundle: (bundleId: number) => void;
    clearCart: () => void;
    mergeGuestCart: (items: CartItem[]) => void;
    applyCoupon: (coupon: Coupon | null) => void;
    setShippingEstimate: (amount: number) => void;
    setTax: (amount: number) => void;
    hydrateCart: (cart: Cart) => void;
    getItemCount: () => number;
    getSubtotal: () => number;
    getDiscount: () => number;
    getTotal: () => number;
};

type CartPersistedState = Pick<
    CartStoreState,
    'items' | 'coupon' | 'shippingEstimate' | 'tax'
>;

type StorageLike = {
    getItem: (name: string) => string | null;
    setItem: (name: string, value: string) => void;
    removeItem: (name: string) => void;
};

const memoryStorage = (): StorageLike => {
    const store = new Map<string, string>();

    return {
        getItem: (name) => store.get(name) ?? null,
        setItem: (name, value) => {
            store.set(name, value);
        },
        removeItem: (name) => {
            store.delete(name);
        },
    };
};

const storage = createJSONStorage<CartPersistedState>(() =>
    typeof window === 'undefined' ? memoryStorage() : window.localStorage,
);

function createItemKey(
    product: Product,
    variant?: ProductVariant | null,
    bundleId?: number | null,
): string {
    const key = variant ? `variant:${variant.id}` : `product:${product.id}`;

    return bundleId ? `bundle:${bundleId}:${key}` : key;
}

function createItemId(): number {
    return Date.now() + Math.floor(Math.random() * 1000000);
}

/** A cart line as reported to Meta (RemoveFromCart). */
function metaItemFromCartItem(item: CartItem): MetaTrackedItem {
    return {
        productId: Number(item.product.id),
        variantId: item.variant ? Number(item.variant.id) : null,
        bundleId: item.bundle_id != null ? Number(item.bundle_id) : null,
        name: item.product.name,
        price: item.price,
        quantity: item.quantity,
    };
}

function getItemPrice(
    product: Product,
    variant?: ProductVariant | null,
    fallback?: number,
): number {
    if (typeof fallback === 'number') {
        return fallback;
    }

    // Mirrors FlashSaleItem::findActiveFor()'s precedence (and
    // ProductController::show()'s per-variant resolution): a flash-sale
    // item targeting this exact variant wins over a whole-product one,
    // which in turn wins over either's own regular catalog price. Checking
    // only the product-level flash price used to always win here whenever
    // a variant was selected, silently ignoring a variant-specific sale
    // (or picking up the product-wide one even once a more specific
    // variant override existed).
    if (
        variant?.in_flash_sale &&
        typeof variant.flash_sale_price === 'number'
    ) {
        return variant.flash_sale_price;
    }

    if (product.in_flash_sale && typeof product.flash_sale_price === 'number') {
        return product.flash_sale_price;
    }

    if (typeof variant?.price === 'number') {
        return variant.price;
    }

    return product.price;
}

/**
 * Runs the client-side bundle auto-assemble pass and surfaces a toast for
 * each recipe that was newly formed/extended this call, so adding the last
 * product that completes a bundle recipe gives the same instant feedback
 * as explicitly clicking "Add bundle to cart".
 */
function applyAutoAssemble(
    items: CartItem[],
    recipes: BundleRecipe[],
): CartItem[] {
    if (recipes.length === 0) {
        return items;
    }

    const { items: nextItems, formed } = autoAssembleBundles(items, recipes);

    for (const recipe of formed) {
        useUiStore.getState().addToast({
            type: 'success',
            title: 'Bundle unlocked',
            message: `${recipe.name} applied — you're getting the bundle price.`,
        });
    }

    return nextItems;
}

function getCouponDiscount(coupon: Coupon | null, subtotal: number): number {
    if (coupon === null) {
        return 0;
    }

    if (typeof coupon.discount_amount === 'number') {
        return Math.min(coupon.discount_amount, subtotal);
    }

    if (coupon.type === 'percent') {
        return Math.min(subtotal, (subtotal * coupon.value) / 100);
    }

    return Math.min(coupon.value, subtotal);
}

export const useCartStore = create<CartStoreState>()(
    persist<CartStoreState, [], [], CartPersistedState>(
        (set, get) => ({
            items: [],
            coupon: null,
            shippingEstimate: 0,
            tax: 0,
            bundleRecipes: [],
            setBundleRecipes: (recipes) => {
                set({ bundleRecipes: recipes });
            },
            addItem: (payload) => {
                const itemKey = createItemKey(
                    payload.product,
                    payload.variant ?? null,
                    payload.bundleId ?? null,
                );
                const quantity = Math.max(1, payload.quantity ?? 1);
                const price = getItemPrice(
                    payload.product,
                    payload.variant ?? null,
                    payload.price,
                );

                set((state) => {
                    const existingItem = state.items.find(
                        (item) => item.item_key === itemKey,
                    );

                    let nextItems: CartItem[];

                    if (existingItem !== undefined) {
                        const nextQuantity = existingItem.quantity + quantity;

                        nextItems = state.items.map((item) =>
                            item.item_key === itemKey
                                ? {
                                      ...item,
                                      quantity: nextQuantity,
                                      subtotal: price * nextQuantity,
                                      formatted_price: formatCurrency(price),
                                      formatted_subtotal: formatCurrency(
                                          price * nextQuantity,
                                      ),
                                  }
                                : item,
                        );
                    } else {
                        const nextItem: CartItem = {
                            id: createItemId(),
                            item_key: itemKey,
                            product: payload.product,
                            variant: payload.variant ?? null,
                            bundle_id: payload.bundleId ?? null,
                            bundle_unit_quantity:
                                payload.bundleUnitQuantity ?? null,
                            quantity,
                            price,
                            formatted_price: formatCurrency(price),
                            subtotal: price * quantity,
                            formatted_subtotal: formatCurrency(
                                price * quantity,
                            ),
                        };

                        nextItems = [...state.items, nextItem];
                    }

                    // Only plain (non-bundle) adds can newly complete a
                    // bundle recipe — skip the pass entirely for an
                    // explicit bundle add, which is already correctly
                    // priced/tagged by the caller (`BundleCard`).
                    if (payload.bundleId == null) {
                        nextItems = applyAutoAssemble(
                            nextItems,
                            state.bundleRecipes,
                        );
                    }

                    return { items: nextItems };
                });

                if (!payload.skipMetaTracking) {
                    trackMetaProductEvent('AddToCart', [
                        {
                            productId: Number(payload.product.id),
                            variantId: payload.variant
                                ? Number(payload.variant.id)
                                : null,
                            bundleId: payload.bundleId ?? null,
                            name: payload.product.name,
                            price,
                            quantity,
                        },
                    ]);
                }

                // Let UI feedback (fly-to-cart animation) react without the store knowing about it.
                if (typeof window !== 'undefined') {
                    window.dispatchEvent(
                        new CustomEvent<CartItemAddedDetail>(
                            CART_ITEM_ADDED_EVENT,
                            {
                                detail: {
                                    product: payload.product,
                                    variant: payload.variant ?? null,
                                    quantity,
                                },
                            },
                        ),
                    );
                }
            },
            removeItem: (itemKey) => {
                const removed = get().items.filter(
                    (item) => item.item_key === itemKey,
                );

                set((state) => ({
                    items: state.items.filter(
                        (item) => item.item_key !== itemKey,
                    ),
                }));

                trackMetaProductEvent(
                    'RemoveFromCart',
                    removed.map(metaItemFromCartItem),
                );
            },
            updateQuantity: (itemKey, quantity) => {
                if (quantity <= 0) {
                    get().removeItem(itemKey);

                    return;
                }

                set((state) => {
                    const nextItems = state.items.map((item) =>
                        item.item_key === itemKey
                            ? {
                                  ...item,
                                  quantity,
                                  subtotal: item.price * quantity,
                                  formatted_subtotal: formatCurrency(
                                      item.price * quantity,
                                  ),
                              }
                            : item,
                    );

                    return {
                        items: applyAutoAssemble(
                            nextItems,
                            state.bundleRecipes,
                        ),
                    };
                });
            },
            updateBundleQuantity: (bundleId, multiplier) => {
                const nextMultiplier = Math.max(1, multiplier);

                set((state) => ({
                    items: state.items.map((item) => {
                        if (
                            item.bundle_id !== bundleId ||
                            !item.bundle_unit_quantity
                        ) {
                            return item;
                        }

                        const quantity =
                            item.bundle_unit_quantity * nextMultiplier;

                        return {
                            ...item,
                            quantity,
                            subtotal: item.price * quantity,
                            formatted_subtotal: formatCurrency(
                                item.price * quantity,
                            ),
                        };
                    }),
                }));
            },
            removeBundle: (bundleId) => {
                const removed = get().items.filter(
                    (item) => item.bundle_id === bundleId,
                );

                set((state) => ({
                    items: state.items.filter(
                        (item) => item.bundle_id !== bundleId,
                    ),
                }));

                trackMetaProductEvent(
                    'RemoveFromCart',
                    removed.map(metaItemFromCartItem),
                );
            },
            clearCart: () => {
                set({
                    items: [],
                    coupon: null,
                    shippingEstimate: 0,
                    tax: 0,
                });
            },
            mergeGuestCart: (items) => {
                set((state) => {
                    const merged = [...state.items];

                    for (const item of items) {
                        const existingItem = merged.find(
                            (current) => current.item_key === item.item_key,
                        );

                        if (existingItem === undefined) {
                            merged.push(item);
                            continue;
                        }

                        const quantity = existingItem.quantity + item.quantity;
                        const price = item.price;

                        merged.splice(merged.indexOf(existingItem), 1, {
                            ...existingItem,
                            quantity,
                            price,
                            formatted_price: formatCurrency(price),
                            subtotal: price * quantity,
                            formatted_subtotal: formatCurrency(
                                price * quantity,
                            ),
                        });
                    }

                    return {
                        items: merged,
                    };
                });
            },
            applyCoupon: (coupon) => {
                set({ coupon });
            },
            setShippingEstimate: (amount) => {
                set({ shippingEstimate: Math.max(0, amount) });
            },
            setTax: (amount) => {
                set({ tax: Math.max(0, amount) });
            },
            hydrateCart: (cart) => {
                set({
                    items: cart.items,
                    coupon: cart.coupon ?? null,
                    shippingEstimate: cart.shipping_estimate,
                    tax: cart.tax,
                });
            },
            getItemCount: () => {
                return get().items.reduce(
                    (total, item) => total + item.quantity,
                    0,
                );
            },
            getSubtotal: () => {
                return get().items.reduce(
                    (total, item) => total + item.subtotal,
                    0,
                );
            },
            getDiscount: () => {
                const coupon = get().coupon;

                return getCouponDiscount(
                    coupon,
                    coupon ? getCouponEligibleSubtotal(coupon, get().items) : 0,
                );
            },
            getTotal: () => {
                const subtotal = get().getSubtotal();
                const discount = get().getDiscount();
                const shippingEstimate = get().shippingEstimate;
                const tax = get().tax;

                return Math.max(
                    0,
                    subtotal - discount + shippingEstimate + tax,
                );
            },
        }),
        {
            name: STORAGE_KEYS.cart,
            storage,
            partialize: (state) => ({
                items: state.items,
                coupon: state.coupon,
                shippingEstimate: state.shippingEstimate,
                tax: state.tax,
            }),
        },
    ),
);
