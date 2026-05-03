import { createJSONStorage, persist } from 'zustand/middleware';
import { create } from 'zustand';
import { STORAGE_KEYS } from '@/lib/constants';
import type { FlashToast, UiState, UiStateToast, Product } from '@/types';

type ToastInput = Omit<FlashToast, 'id'> & {
    id?: string;
    duration?: number;
    title?: string;
};

type UiStoreState = Omit<UiState, 'compareItems'> & {
    compareItems: Product[];
    cartOpen: boolean;
    mobileMenuOpen: boolean;
    searchOpen: boolean;
    toasts: UiStateToast[];
    openCart: () => void;
    closeCart: () => void;
    toggleCart: () => void;
    openMobileMenu: () => void;
    closeMobileMenu: () => void;
    toggleMobileMenu: () => void;
    openSearch: () => void;
    closeSearch: () => void;
    toggleSearch: () => void;
    addCompareItem: (product: Product) => void;
    removeCompareItem: (productId: number) => void;
    clearCompareItems: () => void;
    hasCompareItem: (productId: number) => boolean;
    addToast: (toast: ToastInput) => string;
    removeToast: (id: string) => void;
    clearToasts: () => void;
};

type UiPersistedState = Pick<UiStoreState, 'compareItems'>;

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

const storage = createJSONStorage<UiPersistedState>(() =>
    typeof window === 'undefined' ? memoryStorage() : window.localStorage,
);

function createToastId(): string {
    return `${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
}

export const useUiStore = create<UiStoreState>()(
    persist<UiStoreState, [], [], UiPersistedState>(
        (set, get) => ({
            cartOpen: false,
            mobileMenuOpen: false,
            searchOpen: false,
            compareItems: [],
            toasts: [],
            openCart: () => {
                set({ cartOpen: true });
            },
            closeCart: () => {
                set({ cartOpen: false });
            },
            toggleCart: () => {
                set((state) => ({ cartOpen: !state.cartOpen }));
            },
            openMobileMenu: () => {
                set({ mobileMenuOpen: true });
            },
            closeMobileMenu: () => {
                set({ mobileMenuOpen: false });
            },
            toggleMobileMenu: () => {
                set((state) => ({ mobileMenuOpen: !state.mobileMenuOpen }));
            },
            openSearch: () => {
                set({ searchOpen: true });
            },
            closeSearch: () => {
                set({ searchOpen: false });
            },
            toggleSearch: () => {
                set((state) => ({ searchOpen: !state.searchOpen }));
            },
            addCompareItem: (product) => {
                set((state) => {
                    if (state.compareItems.some(p => p.id === product.id)) {
                        return state;
                    }
                    if (state.compareItems.length >= 3) {
                        return state;
                    }

                    return {
                        compareItems: [...state.compareItems, product],
                    };
                });
            },
            removeCompareItem: (productId) => {
                set((state) => ({
                    compareItems: state.compareItems.filter((p) => p.id !== productId),
                }));
            },
            clearCompareItems: () => {
                set({ compareItems: [] });
            },
            hasCompareItem: (productId) => {
                return get().compareItems.some(p => p.id === productId);
            },
            addToast: (toast) => {
                const id = toast.id ?? createToastId();
                const duration = toast.duration ?? 4000;
                const nextToast: UiStateToast = {
                    id,
                    type: toast.type,
                    message: toast.message,
                    title: toast.title,
                    duration,
                };

                set((state) => ({
                    toasts: [...state.toasts, nextToast],
                }));

                globalThis.setTimeout(() => {
                    get().removeToast(id);
                }, duration);

                return id;
            },
            removeToast: (id) => {
                set((state) => ({
                    toasts: state.toasts.filter((toast) => toast.id !== id),
                }));
            },
            clearToasts: () => {
                set({ toasts: [] });
            },
        }),
        {
            name: STORAGE_KEYS.ui,
            storage,
            partialize: (state) => ({
                compareItems: state.compareItems,
            }),
        },
    ),
);

