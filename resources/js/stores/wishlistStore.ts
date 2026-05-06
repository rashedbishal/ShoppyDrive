import { createJSONStorage, persist } from 'zustand/middleware';
import { create } from 'zustand';
import { STORAGE_KEYS } from '@/lib/constants';

type WishlistStoreState = {
    productIds: number[];
    toggleWishlist: (productId: number) => void;
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

function getCsrfToken(): string {
    return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
}

export const useWishlistStore = create<WishlistStoreState>()(
    persist<WishlistStoreState, [], [], WishlistPersistedState>(
        (set, get) => ({
            productIds: [],
            toggleWishlist: (productId) => {
                const isSelected = get().productIds.includes(productId);

                // Optimistic update
                set((state) => ({
                    productIds: isSelected
                        ? state.productIds.filter((id) => id !== productId)
                        : normalizeProductIds([...state.productIds, productId]),
                }));

                // Sync with server (silently fails for guests / network errors)
                fetch(route('wishlist.toggle'), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken(),
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
                            set({ productIds: normalizeProductIds(data.wishlist_ids) });
                        }
                    })
                    .catch(() => {
                        // Guest or network error — local state stays
                    });
            },
            addWishlist: (productId) => {
                set((state) => ({
                    productIds: normalizeProductIds([...state.productIds, productId]),
                }));
            },
            removeWishlist: (productId) => {
                set((state) => ({
                    productIds: state.productIds.filter((id) => id !== productId),
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
