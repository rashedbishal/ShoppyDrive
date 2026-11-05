import { toast as showToast } from 'sonner';
import { create } from 'zustand';
import { STORAGE_KEYS } from '@/lib/constants';
import type { FlashToast, UiState, UiStateToast } from '@/types';

type ToastInput = Omit<FlashToast, 'id'> & {
    id?: string;
    duration?: number;
    title?: string;
};

type UiStoreState = UiState & {
    cartOpen: boolean;
    mobileMenuOpen: boolean;
    searchOpen: boolean;
    toasts: UiStateToast[];
    /** Products listed in the "can't delete, in use by N product(s)" flash toast's modal — kept outside the toast's own lifecycle so the modal survives if the toast auto-dismisses. */
    blockedDeleteProducts: { id: number; name: string; slug: string }[] | null;
    showBlockedDeleteProducts: (
        products: { id: number; name: string; slug: string }[],
    ) => void;
    hideBlockedDeleteProducts: () => void;
    openCart: () => void;
    closeCart: () => void;
    toggleCart: () => void;
    openMobileMenu: () => void;
    closeMobileMenu: () => void;
    toggleMobileMenu: () => void;
    openSearch: () => void;
    closeSearch: () => void;
    toggleSearch: () => void;
    addToast: (toast: ToastInput) => string;
    removeToast: (id: string) => void;
    clearToasts: () => void;
};

// The retired product-comparison list was the only thing this store ever saved.
// Drop the copy still sitting in returning visitors' browsers.
if (typeof window !== 'undefined') {
    try {
        window.localStorage.removeItem(STORAGE_KEYS.ui);
    } catch {
        // Storage can be unavailable (e.g. blocked by privacy settings); nothing to clean.
    }
}

function createToastId(): string {
    return `${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
}

export const useUiStore = create<UiStoreState>()((set, get) => ({
    cartOpen: false,
    mobileMenuOpen: false,
    searchOpen: false,
    toasts: [],
    blockedDeleteProducts: null,
    showBlockedDeleteProducts: (products) => {
        set({ blockedDeleteProducts: products });
    },
    hideBlockedDeleteProducts: () => {
        set({ blockedDeleteProducts: null });
    },
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

        showToast[nextToast.type](nextToast.title ?? nextToast.message, {
            id,
            description: nextToast.title ? nextToast.message : undefined,
            duration,
        });

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
}));
