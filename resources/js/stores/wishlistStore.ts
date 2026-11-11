import { create } from 'zustand';
import { createJSONStorage, persist } from 'zustand/middleware';
import { STORAGE_KEYS } from '@/lib/constants';
import { trackMetaProductEvent } from '@/lib/meta-tracking';

type WishlistTrackedProduct = { name: string; price: number };

/**
 * In a render selector, read `state.productIds` directly (e.g.
 * `(state) => state.productIds.includes(id)`), never a method that calls
 * `get()`: during hydration zustand hands selectors the store's initial
 * state so the first render matches the server's, and `get()` would read
 * the browser's saved wishlist instead (hydration mismatch).
 */
type WishlistStoreState = {
    productIds: number[];
    /** `product` is only used to report AddToWishlist to Meta when this toggle adds it. */
    toggleWishlist: (
        productId: number,
        product?: WishlistTrackedProduct,
    ) => void;
    addWishlist: (productId: number) => void;
    removeWishlist: (productId: number) => void;
    clearWishlist: () => void;
    setWishlist: (productIds: number[]) => void;
};

type WishlistPersistedState = Pick<WishlistStoreState, 'productIds'>;

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

const storage = createJSONStorage<WishlistPersistedState>(() =>
    typeof window === 'undefined' ? memoryStorage() : window.localStorage,
);

function normalizeProductIds(productIds: number[]): number[] {
    return Array.from(new Set(productIds));
}

/**
 * Cross-device sync (app.tsx polls the account's wishlist ids): a change made
 * here must never be undone by a poll answer that left before it reached the
 * server. `changeSeq` counts heart clicks; `writesInFlight` counts saves not
 * yet answered. A poll answer is applied only if neither moved meanwhile.
 */
let wishlistWritesInFlight = 0;
let wishlistChangeSeq = 0;

export function wishlistLocalChangeState(): {
    writesInFlight: number;
    changeSeq: number;
} {
    return {
        writesInFlight: wishlistWritesInFlight,
        changeSeq: wishlistChangeSeq,
    };
}

function getXsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

export const useWishlistStore = create<WishlistStoreState>()(
    persist<WishlistStoreState, [], [], WishlistPersistedState>(
        (set, get) => ({
            productIds: [],
            toggleWishlist: (productId, product) => {
                const isSelected = get().productIds.includes(productId);
                const reportAdded = (): void => {
                    if (isSelected || !product) {
                        return;
                    }

                    trackMetaProductEvent('AddToWishlist', [
                        {
                            productId,
                            name: product.name,
                            price: product.price,
                            quantity: 1,
                        },
                    ]);
                };

                wishlistChangeSeq += 1;
                wishlistWritesInFlight += 1;

                // Optimistic update
                set((state) => ({
                    productIds: isSelected
                        ? state.productIds.filter((id) => id !== productId)
                        : normalizeProductIds([...state.productIds, productId]),
                }));

                // Sync with server (silently fails for guests / network errors)
                fetch(route('wishlist.toggle'), {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-XSRF-TOKEN': getXsrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    // The state chosen on this screen, not "flip it": a device
                    // showing an older wishlist can't add back a product
                    // another device already removed (or the reverse).
                    body: JSON.stringify({
                        product_id: productId,
                        wishlisted: !isSelected,
                    }),
                })
                    .then((res) => {
                        if (res.ok) {
                            return res.json();
                        }
                    })
                    .then((data) => {
                        if (data?.wishlist_ids) {
                            set({
                                productIds: normalizeProductIds(
                                    data.wishlist_ids,
                                ),
                            });
                        }

                        // Reported once the add has stuck: confirmed by the
                        // server, or kept locally (guest wishlist).
                        if (
                            !data?.wishlist_ids ||
                            normalizeProductIds(data.wishlist_ids).includes(
                                productId,
                            )
                        ) {
                            reportAdded();
                        }
                    })
                    .catch(() => {
                        // Guest or network error — local state stays
                        reportAdded();
                    })
                    .finally(() => {
                        wishlistWritesInFlight -= 1;
                    });
            },
            addWishlist: (productId) => {
                set((state) => ({
                    productIds: normalizeProductIds([
                        ...state.productIds,
                        productId,
                    ]),
                }));
            },
            removeWishlist: (productId) => {
                set((state) => ({
                    productIds: state.productIds.filter(
                        (id) => id !== productId,
                    ),
                }));
            },
            clearWishlist: () => {
                set({ productIds: [] });
            },
            setWishlist: (productIds) => {
                set({ productIds: normalizeProductIds(productIds) });
            },
        }),
        {
            name: STORAGE_KEYS.wishlist,
            storage,
            partialize: (state) => ({
                productIds: state.productIds,
            }),
        },
    ),
);
