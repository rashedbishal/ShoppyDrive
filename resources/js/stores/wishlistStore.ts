import { create } from 'zustand';
import { createJSONStorage, persist } from 'zustand/middleware';
import { STORAGE_KEYS } from '@/lib/constants';
import { trackMetaProductEvent } from '@/lib/meta-tracking';

type WishlistTrackedProduct = { name: string; price: number };

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
    isWishlisted: (productId: number) => boolean;
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
                    body: JSON.stringify({ product_id: productId }),
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
            isWishlisted: (productId) => {
                return get().productIds.includes(productId);
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
