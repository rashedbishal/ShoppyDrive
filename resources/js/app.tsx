import { createInertiaApp, router } from '@inertiajs/react';
import React from 'react';
import { route as ziggyRoute } from 'ziggy-js';
import PageLoadingOverlay from '@/components/shared/PageLoadingOverlay';
import { Toaster } from '@/components/ui/sonner';

import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import { initializeSiteTheme } from '@/hooks/use-theme';
import AdminLayout from '@/layouts/admin-layout';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { initAddToCartFeedback } from '@/lib/add-to-cart-feedback';
import { APP_NAME } from '@/lib/constants';
import { STORAGE_KEYS } from '@/lib/constants';
import { useCartStore } from '@/stores/cartStore';
import { useWishlistStore } from '@/stores/wishlistStore';
import type { BundleRecipe, Cart, SharedPageProps } from '@/types';
import { Ziggy as ziggyConfig } from './ziggy';

declare global {
    var route: typeof ziggyRoute;
    var Ziggy: typeof ziggyConfig;

    interface Window {
        React: typeof React;
    }
}

// Ziggy's route() needs its route/URL config available to resolve on the server —
// there's no `Ziggy` global there like the @routes Blade directive sets up in the
// browser. This app reuses app.tsx for SSR (no separate ssr.tsx), so initializing
// these globals here once covers every existing `route(...)` call — both the bare
// global form and `import { route } from 'ziggy-js'` (which falls back to reading
// the global `Ziggy` itself when no config is passed) — in both environments.
//
// In the browser, the @routes Blade directive's default output is
// `<script>const Ziggy={...live route data...};{route.umd.js source}</script>`
// — NOT `window.Ziggy = ...`. A top-level `const`/`let` in a classic script
// creates a lexical binding shared across every script/module in the document,
// not a `window` property, so `window.Ziggy` is always undefined here and must
// never be checked — that bug silently fell back to the `./ziggy` snapshot
// below on every single page load, no matter how current the live server's
// route list was. Reading the bare `Ziggy` identifier (exactly how ziggy-js's
// own bundled code resolves it — see its `"undefined"!=typeof Ziggy?Ziggy:...`
// check) is what actually picks up the live data; the `./ziggy` snapshot is
// now only ever used server-side during SSR, where no such script ran.
globalThis.Ziggy = typeof Ziggy !== 'undefined' && Ziggy ? Ziggy : ziggyConfig;
// Defaults every `route(...)` call (none in this app pass `absolute`
// explicitly) to a relative path instead of Ziggy's own default of an
// absolute URL built from the server's `APP_URL`. An absolute URL there
// can point at a different host than the one actually serving the page
// (e.g. `www.` vs bare domain), which the browser then blocks as a
// cross-origin request on any XHR-driven call like a login form POST.
globalThis.route = ((
    name: string | undefined,
    params?: unknown,
    absolute?: boolean,
    config?: typeof ziggyConfig,
) =>
    // @ts-expect-error — delegating to Ziggy's overloaded signature via a single concrete implementation
    ziggyRoute(
        name,
        params,
        absolute ?? false,
        config ?? globalThis.Ziggy,
    )) as typeof ziggyRoute;

const appName = APP_NAME;

const LAST_USER_KEY = `${STORAGE_KEYS.cart}.last_user`;

function readInitialUserId(): number | null {
    try {
        const raw = document.getElementById('app')?.dataset.page;

        if (raw) {
            return JSON.parse(raw)?.props?.auth?.user?.id ?? null;
        }
    } catch {}

    return null;
}

function readInitialCart(): Cart | null {
    try {
        const raw = document.getElementById('app')?.dataset.page;

        if (raw) {
            return JSON.parse(raw)?.props?.cart ?? null;
        }
    } catch {}

    return null;
}

function readInitialBundleRecipes(): BundleRecipe[] {
    try {
        const raw = document.getElementById('app')?.dataset.page;

        if (raw) {
            return JSON.parse(raw)?.props?.active_bundle_recipes ?? [];
        }
    } catch {}

    return [];
}

let prevAuthUserId: number | null = null;
let activeAuthUserId: number | null = null;
let isHydratingCart = false;
let cartSyncInFlight = false;
let pendingSync: boolean | null = null;
let lastSyncedSignature = '';

function applyLogout(): void {
    localStorage.removeItem(LAST_USER_KEY);
    localStorage.removeItem(STORAGE_KEYS.cart);
    useCartStore.getState().clearCart();
    useWishlistStore.getState().clearWishlist();
    prevAuthUserId = null;
    activeAuthUserId = null;
    lastSyncedSignature = '';
}

function cartSignature(cart: Cart | null): string {
    if (cart === null) {
        return '';
    }

    return cart.items
        .map((item) => `${item.item_key}:${item.quantity}`)
        .sort()
        .join('|');
}

function getCookie(name: string): string | null {
    const value = document.cookie
        .split('; ')
        .find((cookie) => cookie.startsWith(`${name}=`));

    return value
        ? decodeURIComponent(value.split('=').slice(1).join('='))
        : null;
}

function hydrateCart(cart: Cart | null): void {
    isHydratingCart = true;

    if (cart === null) {
        useCartStore.getState().clearCart();
        lastSyncedSignature = '';
    } else {
        useCartStore.getState().hydrateCart(cart);
        lastSyncedSignature = cartSignature(cart);
    }

    isHydratingCart = false;
}

async function syncAuthenticatedCart(merge: boolean): Promise<void> {
    if (activeAuthUserId === null || cartSyncInFlight) {
        pendingSync = pendingSync === null ? merge : pendingSync || merge;

        return;
    }

    cartSyncInFlight = true;

    try {
        const state = useCartStore.getState();
        const response = await fetch(route('cart.sync'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': getCookie('XSRF-TOKEN') ?? '',
            },
            body: JSON.stringify({
                merge,
                items: state.items.map((item) => ({
                    product_id: item.product.id,
                    variant_id: item.variant?.id ?? null,
                    quantity: item.quantity,
                    bundle_id: item.bundle_id ?? null,
                    bundle_unit_quantity: item.bundle_unit_quantity ?? null,
                })),
            }),
        });

        if (!response.ok) {
            throw new Error(`Cart sync failed with status ${response.status}`);
        }

        const payload = (await response.json()) as { cart: Cart | null };
        hydrateCart(payload.cart);
        localStorage.setItem(LAST_USER_KEY, String(activeAuthUserId));
    } finally {
        cartSyncInFlight = false;

        if (pendingSync !== null) {
            const nextMerge = pendingSync;
            pendingSync = null;
            void syncAuthenticatedCart(nextMerge);
        }
    }
}

function applyLogin(
    userId: number,
    cart: Cart | null,
    mergeGuestCart: boolean,
): void {
    activeAuthUserId = userId;
    localStorage.setItem(LAST_USER_KEY, String(userId));

    if (mergeGuestCart && useCartStore.getState().items.length > 0) {
        void syncAuthenticatedCart(true);
    } else {
        hydrateCart(cart);
    }

    prevAuthUserId = userId;
}

if (typeof window !== 'undefined') {
    // Exposed purely so browser tech-detection tools (e.g. Wappalyzer) can
    // fingerprint React; the production bundle otherwise keeps React scoped
    // to the module and never attaches it to `window`.
    window.React = React;

    const initialUserId = readInitialUserId();
    const initialCart = readInitialCart();
    useCartStore.getState().setBundleRecipes(readInitialBundleRecipes());
    const storedLastUser = localStorage.getItem(LAST_USER_KEY);

    if (storedLastUser !== null && initialUserId === null) {
        applyLogout();
    } else if (initialUserId !== null) {
        applyLogin(initialUserId, initialCart, false);
    }
}

useCartStore.subscribe((state) => {
    if (activeAuthUserId === null || isHydratingCart) {
        return;
    }

    const signature = state.items
        .map((item) => `${item.item_key}:${item.quantity}`)
        .sort()
        .join('|');

    if (signature === lastSyncedSignature) {
        return;
    }

    lastSyncedSignature = signature;
    void syncAuthenticatedCart(false);
});

router.on('navigate', (event) => {
    const props = event.detail.page.props as SharedPageProps;

    // Inertia preserves the original document between visits, so Blade only
    // writes this attribute on the initial load. Keep it in sync with the
    // server-resolved CMS theme after every subsequent visit as well.
    document.documentElement.dataset.theme = props.theme;
    const currentUserId = props.auth?.user?.id ?? null;
    const stored = localStorage.getItem(LAST_USER_KEY);
    const currentCart = props.cart ?? null;
    useCartStore.getState().setBundleRecipes(props.active_bundle_recipes ?? []);

    if (stored !== null && currentUserId === null) {
        applyLogout();
    } else if (currentUserId !== null && currentUserId !== prevAuthUserId) {
        // If transitioning from guest → logged-in, snapshot local wishlist for merge
        if (prevAuthUserId === null) {
            const guestIds = useWishlistStore.getState().productIds;

            if (guestIds.length > 0) {
                localStorage.setItem(
                    'shoppydrive.wishlist.pending_merge',
                    JSON.stringify(guestIds),
                );
            }
        }

        applyLogin(currentUserId, currentCart, prevAuthUserId === null);
    } else if (currentUserId !== null) {
        activeAuthUserId = currentUserId;
        hydrateCart(currentCart);
        prevAuthUserId = currentUserId;
    } else {
        prevAuthUserId = null;
    }
});

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
                return null;
            case name.startsWith('auth/'):
                return null;
            case name.startsWith('landing/'):
                return null;
            case name.startsWith('errors/'):
                return null;
            case name.startsWith('admin/'):
                return AdminLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
                <PageLoadingOverlay />
            </TooltipProvider>
        );
    },
    progress: false,
});

// This will set light / dark mode on load...
initializeTheme();
initializeSiteTheme();

// Fly-to-cart animation + cart icon bump whenever an item is added.
initAddToCartFeedback();
