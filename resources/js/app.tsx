import { createInertiaApp, router } from '@inertiajs/react';
import type { ResolvedComponent } from '@inertiajs/react';
import React from 'react';
import { toast } from 'sonner';
import { route as ziggyRoute } from 'ziggy-js';
import ErrorBoundary from '@/components/error-boundary';
import PageLoadingOverlay from '@/components/shared/PageLoadingOverlay';
import { Toaster } from '@/components/ui/sonner';

import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import { initializeSiteTheme } from '@/hooks/use-theme';
import AppLayout from '@/layouts/app-layout';
import { initAddToCartFeedback } from '@/lib/add-to-cart-feedback';
import { APP_NAME } from '@/lib/constants';
import { STORAGE_KEYS } from '@/lib/constants';
import { takePurchasedLines } from '@/lib/purchased-cart-lines';
import type { PurchasedCartLine } from '@/lib/purchased-cart-lines';
import { formatCurrency } from '@/lib/utils';
import { useCartStore } from '@/stores/cartStore';
import { useWishlistStore } from '@/stores/wishlistStore';
import type { BundleRecipe, Cart, CartItem, SharedPageProps } from '@/types';
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
    // Delegates to Ziggy's overloaded signature through one concrete call.
    (ziggyRoute as (...args: unknown[]) => unknown)(
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
    } catch {
        // Missing or malformed page data: fall through to the default below.
    }

    return null;
}

/**
 * FE-005: whether the server-rendered page carried shared props at all. A
 * 500/503 error page doesn't (AppServiceProvider::configureErrorPages()
 * flushes them), so there `auth` is absent rather than `{ user: null }`, and
 * absent must never be read as "the customer logged out". Unreadable page
 * data still counts as carrying auth, which keeps the previous behaviour
 * for that case.
 */
function initialPageCarriesAuth(): boolean {
    try {
        const raw = document.getElementById('app')?.dataset.page;

        if (raw) {
            const props = JSON.parse(raw)?.props;

            return !(props && typeof props === 'object' && !('auth' in props));
        }
    } catch {
        // Missing or malformed page data: fall through to the default below.
    }

    return true;
}

function readInitialCart(): Cart | null {
    try {
        const raw = document.getElementById('app')?.dataset.page;

        if (raw) {
            return JSON.parse(raw)?.props?.cart ?? null;
        }
    } catch {
        // Missing or malformed page data: fall through to the default below.
    }

    return null;
}

function readInitialComponent(): string | null {
    try {
        const raw = document.getElementById('app')?.dataset.page;

        if (raw) {
            return JSON.parse(raw)?.component ?? null;
        }
    } catch {
        // Missing or malformed page data: fall through to the default below.
    }

    return null;
}

function readInitialBundleRecipes(): BundleRecipe[] {
    try {
        const raw = document.getElementById('app')?.dataset.page;

        if (raw) {
            return JSON.parse(raw)?.props?.active_bundle_recipes ?? [];
        }
    } catch {
        // Missing or malformed page data: fall through to the default below.
    }

    return [];
}

let prevAuthUserId: number | null = null;
let activeAuthUserId: number | null = null;
let isHydratingCart = false;
let cartSyncInFlight = false;
let pendingSync: boolean | null = null;
let lastSyncedSignature = '';

/**
 * Cross-device cart sync. A logged-in save carries the server cart version
 * its items were based on; the server refuses (409) a save made from an
 * older copy, and this tab then re-applies its own change on top of the
 * current cart (rebaseOntoServerCart) and saves again. Separately, a
 * visible logged-in page checks the server every CART_POLL_INTERVAL_MS, and
 * when the tab regains focus, so another device's change shows up without
 * a page load. Same-browser tabs still sync instantly via the `storage`
 * event below.
 */
const EMPTY_CART_VERSION = 'empty';
const CART_POLL_INTERVAL_MS = 15000;
const MAX_CART_CONFLICT_RETRIES = 3;
const CART_UPDATED_ELSEWHERE_MESSAGE =
    'Your cart was changed on another device. Please check it.';
let cartConflictRetries = 0;
let cartPollInFlight = false;
let currentPageComponent: string | null = null;

/**
 * FE-011: a failed cart save used to be silent, and the signature was
 * already marked as synced, so the next page's server cart quietly dropped
 * the item. `cartSyncDirty` means local changes the server hasn't
 * confirmed yet. While it is set, a page visit re-sends the cart instead of
 * overwriting it. Only full-cart replace syncs are retried: they are
 * idempotent, whereas the one-time login merge adds quantities, so
 * replaying it after a lost response could double them.
 */
let cartSyncDirty = false;
let cartSyncRetryAttempt = 0;
let cartSyncRetryTimer: ReturnType<typeof setTimeout> | null = null;
let cartSyncFailureShown = false;
const CART_SYNC_RETRY_DELAYS_MS = [2000, 5000, 10000];
const CART_SYNC_FAILED_MESSAGE =
    "We couldn't save your cart. Check your internet connection; we'll keep trying.";

class RetryableCartSyncError extends Error {}

function cartSyncFailed(merge: boolean, retryable: boolean): void {
    if (cartSyncRetryTimer !== null) {
        clearTimeout(cartSyncRetryTimer);
        cartSyncRetryTimer = null;
    }

    if (
        retryable &&
        !merge &&
        cartSyncRetryAttempt < CART_SYNC_RETRY_DELAYS_MS.length
    ) {
        const delay = CART_SYNC_RETRY_DELAYS_MS[cartSyncRetryAttempt];
        cartSyncRetryAttempt += 1;
        cartSyncRetryTimer = setTimeout(() => {
            cartSyncRetryTimer = null;
            void syncAuthenticatedCart(false);
        }, delay);

        return;
    }

    if (!retryable) {
        // A permanent refusal (e.g. the session ended): the server's cart
        // is the truth, as before.
        cartSyncDirty = false;
    }

    if (!cartSyncFailureShown) {
        cartSyncFailureShown = true;
        toast.error(CART_SYNC_FAILED_MESSAGE);
    }
}

function applyLogout(): void {
    localStorage.removeItem(LAST_USER_KEY);
    localStorage.removeItem(STORAGE_KEYS.cart);
    useCartStore.getState().clearCart();
    useWishlistStore.getState().clearWishlist();
    prevAuthUserId = null;
    activeAuthUserId = null;
    lastSyncedSignature = '';

    // FE-011: nothing left to save for a logged-out user.
    cartSyncDirty = false;
    cartSyncRetryAttempt = 0;
    cartSyncFailureShown = false;

    if (cartSyncRetryTimer !== null) {
        clearTimeout(cartSyncRetryTimer);
        cartSyncRetryTimer = null;
    }
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
        // No server cart is the same as an empty one (CartManager::EMPTY_VERSION).
        useCartStore.getState().setServerSnapshot(EMPTY_CART_VERSION, []);
        lastSyncedSignature = '';
    } else {
        useCartStore.getState().hydrateCart(cart);
        lastSyncedSignature = cartSignature(cart);
    }

    isHydratingCart = false;
}

/**
 * hydrateCart() for a change that came from elsewhere (another device), not
 * a page visit. A coupon applied on the Cart page lives only in this
 * browser, so it is kept unless the server cart carries its own.
 */
function hydrateCartFromElsewhere(cart: Cart | null): void {
    const localCoupon = useCartStore.getState().coupon;

    hydrateCart(cart);

    if (localCoupon !== null && (cart?.coupon ?? null) === null) {
        useCartStore.getState().applyCoupon(localCoupon);
    }
}

function signatureOfItems(items: CartItem[]): string {
    return items
        .map((item) => `${item.item_key}:${item.quantity}`)
        .sort()
        .join('|');
}

/**
 * Applies this tab's own unsaved changes on top of the server's current
 * cart. A change is measured against `serverBase`, the server copy this tab
 * last had: a line removed here is removed; a line added here, or whose
 * quantity changed here, takes this tab's quantity. Every other line is
 * the server's, including lines another device added or removed. Returns
 * the merged items, or null when they equal the server's (nothing to save).
 */
function rebaseOntoServerCart(serverCart: Cart | null): CartItem[] | null {
    const { items: localItems, serverBase } = useCartStore.getState();
    const serverItems = serverCart?.items ?? [];
    const merged = new Map(serverItems.map((item) => [item.item_key, item]));
    const localKeys = new Set(localItems.map((item) => item.item_key));

    for (const key of Object.keys(serverBase)) {
        if (!localKeys.has(key)) {
            merged.delete(key);
        }
    }

    for (const item of localItems) {
        if (serverBase[item.item_key] === item.quantity) {
            continue;
        }

        const existing = merged.get(item.item_key);

        merged.set(
            item.item_key,
            existing === undefined
                ? item
                : {
                      ...existing,
                      quantity: item.quantity,
                      bundle_unit_quantity: item.bundle_unit_quantity,
                      subtotal: existing.price * item.quantity,
                      formatted_subtotal: formatCurrency(
                          existing.price * item.quantity,
                      ),
                  },
        );
    }

    const mergedItems = [...merged.values()];

    return signatureOfItems(mergedItems) === signatureOfItems(serverItems)
        ? null
        : mergedItems;
}

/**
 * The server refused a save because the cart changed elsewhere since this
 * tab's copy. Take the current cart, re-apply this tab's change and save
 * again (the `finally` in syncAuthenticatedCart() sends it). If it keeps
 * conflicting, show the server's cart rather than loop.
 */
function handleCartConflict(serverCart: Cart | null): void {
    const canRetry = cartConflictRetries < MAX_CART_CONFLICT_RETRIES;
    // Read before hydrateCart() replaces this tab's items and base.
    const mergedItems = canRetry ? rebaseOntoServerCart(serverCart) : null;

    hydrateCartFromElsewhere(serverCart);

    if (mergedItems === null) {
        cartSyncDirty = false;
        cartConflictRetries = 0;

        if (!canRetry) {
            toast.info(CART_UPDATED_ELSEWHERE_MESSAGE);
        }

        return;
    }

    cartConflictRetries += 1;
    isHydratingCart = true;
    useCartStore.setState({ items: mergedItems });
    isHydratingCart = false;
    lastSyncedSignature = signatureOfItems(mergedItems);
    cartSyncDirty = true;
    pendingSync = pendingSync === null ? false : pendingSync;
}

/**
 * Pages where the cart must not change underneath the customer: checkout
 * and payment (the order is built from what the page shows), order pages,
 * landing-page funnels (their own basket) and admin.
 */
function isCartPollingPaused(component: string | null): boolean {
    return (
        component === null ||
        ['checkout/', 'order/', 'landing/', 'admin/', 'errors/'].some(
            (prefix) => component.startsWith(prefix),
        )
    );
}

/**
 * Picks up a change made to this customer's cart on another device. Never
 * overwrites a change made here that is unsaved or being saved: that save
 * carries its base version, and a conflict is merged instead.
 */
async function pollCartState(): Promise<void> {
    if (
        activeAuthUserId === null ||
        document.visibilityState !== 'visible' ||
        isCartPollingPaused(currentPageComponent) ||
        cartPollInFlight ||
        cartSyncInFlight ||
        cartSyncDirty
    ) {
        return;
    }

    const userId = activeAuthUserId;
    const versionBefore = useCartStore.getState().serverVersion;
    cartPollInFlight = true;

    try {
        const response = await fetch(
            route(
                'cart.state',
                versionBefore === null ? {} : { version: versionBefore },
            ),
            {
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        );

        if (!response.ok) {
            return;
        }

        const payload = (await response.json()) as {
            changed: boolean;
            cart?: Cart | null;
        };

        // Anything that happened here while waiting wins over this answer.
        if (
            !payload.changed ||
            activeAuthUserId !== userId ||
            cartSyncInFlight ||
            cartSyncDirty ||
            isCartPollingPaused(currentPageComponent) ||
            useCartStore.getState().serverVersion !== versionBefore
        ) {
            return;
        }

        hydrateCartFromElsewhere(payload.cart ?? null);
    } catch {
        // Offline or similar: the next check tries again.
    } finally {
        cartPollInFlight = false;
    }
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
                // Unknown (null) keeps the old unconditional save.
                ...(merge || state.serverVersion === null
                    ? {}
                    : { base_version: state.serverVersion }),
                items: state.items.map((item) => ({
                    product_id: item.product.id,
                    variant_id: item.variant?.id ?? null,
                    quantity: item.quantity,
                    bundle_id: item.bundle_id ?? null,
                    bundle_unit_quantity: item.bundle_unit_quantity ?? null,
                })),
            }),
        });

        if (response.status === 409) {
            const conflict = (await response.json()) as { cart: Cart | null };
            handleCartConflict(conflict.cart);

            return;
        }

        if (!response.ok) {
            const temporary =
                response.status >= 500 ||
                response.status === 408 ||
                response.status === 429;

            throw temporary
                ? new RetryableCartSyncError(String(response.status))
                : new Error(`Cart sync failed with status ${response.status}`);
        }

        const payload = (await response.json()) as { cart: Cart | null };
        cartSyncDirty = false;
        cartSyncRetryAttempt = 0;
        cartConflictRetries = 0;

        if (cartSyncFailureShown) {
            cartSyncFailureShown = false;
            toast.success('Your cart is saved.');
        }

        hydrateCart(payload.cart);
        localStorage.setItem(LAST_USER_KEY, String(activeAuthUserId));
    } catch (error) {
        // fetch() rejects with a TypeError when the network is unreachable.
        cartSyncFailed(
            merge,
            error instanceof RetryableCartSyncError ||
                error instanceof TypeError,
        );
    } finally {
        cartSyncInFlight = false;

        if (pendingSync !== null) {
            const nextMerge = pendingSync;
            pendingSync = null;
            void syncAuthenticatedCart(nextMerge);
        }
    }
}

/**
 * Saves the guest's wishlist so AppLayout merges it into the account once
 * the logged-in page renders.
 */
function snapshotGuestWishlistForMerge(): void {
    const guestIds = useWishlistStore.getState().productIds;

    if (guestIds.length > 0) {
        localStorage.setItem(
            'shoppydrive.wishlist.pending_merge',
            JSON.stringify(guestIds),
        );
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

    // FE-005: a full page load that lands on a 500/503 error page has no
    // shared props, so it says nothing about who is logged in. Leave the
    // cart, wishlist and login marker exactly as they were, like the
    // navigate handler below does for the same page rendered via a visit.
    if (initialPageCarriesAuth()) {
        const initialUserId = readInitialUserId();
        const initialCart = readInitialCart();
        useCartStore.getState().setBundleRecipes(readInitialBundleRecipes());
        const storedLastUser = localStorage.getItem(LAST_USER_KEY);

        if (storedLastUser !== null && initialUserId === null) {
            applyLogout();
        } else if (initialUserId !== null) {
            // FE-008: Google/Facebook login returns as a full page load, not an
            // Inertia visit, so the navigate handler's guest-to-login merge below
            // never ran and the guest's cart and wishlist were overwritten.
            // No account active in this browser before this load (logout clears
            // LAST_USER_KEY together with the cart) means whatever is in the
            // local cart and wishlist belongs to a guest who just logged in.
            // A logged-in reload keeps LAST_USER_KEY, so it never merges, which
            // matters because the server merge adds quantities together.
            const guestJustLoggedIn = storedLastUser === null;

            if (guestJustLoggedIn) {
                snapshotGuestWishlistForMerge();
            }

            applyLogin(initialUserId, initialCart, guestJustLoggedIn);
        }
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
    cartSyncDirty = true;
    cartSyncRetryAttempt = 0;
    void syncAuthenticatedCart(false);
});

/**
 * After an order: remove only the cart lines that order bought (remembered
 * per tab by Checkout.tsx), keeping anything added meanwhile in another tab.
 * A logged-in cart with nothing unsaved is simply replaced with the
 * server's, which already removed exactly those lines (BIZ-019); local
 * removal is only for a guest's browser-only cart, or a logged-in cart with
 * changes not yet saved (which then saves the result).
 */
function removeItemsBoughtByOrder(
    purchasedLines: PurchasedCartLine[],
    serverCart: Cart | null,
): void {
    if (activeAuthUserId !== null && !cartSyncDirty) {
        hydrateCart(serverCart);

        return;
    }

    // Pick up whatever another tab saved before removing, so its items
    // aren't overwritten by this tab's older copy.
    void Promise.resolve(useCartStore.persist.rehydrate()).then(() => {
        useCartStore.getState().removePurchasedLines(purchasedLines);

        if (activeAuthUserId !== null) {
            // FE-011: the unsaved changes, minus what was just bought.
            cartSyncRetryAttempt = 0;
            void syncAuthenticatedCart(false);
        }
    });
}

// Cross-device sync: check the server cart regularly while the page is
// visible, and straight away when the customer comes back to the tab.
if (typeof window !== 'undefined') {
    currentPageComponent = readInitialComponent();
    setInterval(() => void pollCartState(), CART_POLL_INTERVAL_MS);
    window.addEventListener('focus', () => void pollCartState());
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            void pollCartState();
        }
    });
}

// Another tab changed the cart: show the same cart here. Not re-saved to
// the server from this tab, because the tab that changed it already did.
if (typeof window !== 'undefined') {
    window.addEventListener('storage', (event) => {
        if (event.key !== STORAGE_KEYS.cart || event.newValue === null) {
            return;
        }

        isHydratingCart = true;

        void Promise.resolve(useCartStore.persist.rehydrate()).finally(() => {
            lastSyncedSignature = useCartStore
                .getState()
                .items.map((item) => `${item.item_key}:${item.quantity}`)
                .sort()
                .join('|');
            isHydratingCart = false;
        });
    });
}

router.on('navigate', (event) => {
    const props = event.detail.page.props as SharedPageProps;
    currentPageComponent = event.detail.page.component;

    // Inertia preserves the original document between visits, so Blade only
    // writes this attribute on the initial load. Keep it in sync with the
    // server-resolved CMS theme after every subsequent visit as well.
    document.documentElement.dataset.theme = props.theme as string | undefined;

    // FE-005: a 500/503 response skips shared props entirely
    // (AppServiceProvider::configureErrorPages() calls Inertia::flushShared()
    // for server errors — the database or shared props may be exactly
    // what's failing), so `auth` isn't merely `{ user: null }` there, it's
    // missing from `props` altogether. `props.auth?.user?.id ?? null`
    // can't tell "the server confirmed you're a guest" apart from "the
    // server couldn't tell us anything" — treating the latter as a real
    // logout cleared a logged-in customer's cart on every server error.
    // Only a response that actually included `auth` gets to decide this.
    if (!('auth' in props)) {
        return;
    }

    const currentUserId = props.auth?.user?.id ?? null;
    const stored = localStorage.getItem(LAST_USER_KEY);
    const currentCart = props.cart ?? null;
    const purchasedLines =
        event.detail.page.component === 'order/OrderConfirmation' &&
        !(props as { keepCart?: boolean }).keepCart
            ? takePurchasedLines()
            : null;
    useCartStore.getState().setBundleRecipes(props.active_bundle_recipes ?? []);

    if (stored !== null && currentUserId === null) {
        applyLogout();
    } else if (currentUserId !== null && currentUserId !== prevAuthUserId) {
        // If transitioning from guest → logged-in, snapshot local wishlist for merge
        if (prevAuthUserId === null) {
            snapshotGuestWishlistForMerge();
        }

        applyLogin(currentUserId, currentCart, prevAuthUserId === null);
    } else if (currentUserId !== null) {
        activeAuthUserId = currentUserId;

        if (purchasedLines !== null) {
            removeItemsBoughtByOrder(purchasedLines, currentCart);
        } else if (cartSyncDirty) {
            // FE-011: keep the unsaved items and try again now.
            cartSyncRetryAttempt = 0;
            void syncAuthenticatedCart(false);
        } else {
            hydrateCart(currentCart);
        }

        prevAuthUserId = currentUserId;
    } else {
        prevAuthUserId = null;

        if (purchasedLines !== null) {
            removeItemsBoughtByOrder(purchasedLines, currentCart);
        }
    }
});

const storefrontPages = import.meta.glob<{ default: ResolvedComponent }>([
    './pages/**/*.tsx',
    '!./pages/admin/**',
]);

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: async (name) => {
        if (name.startsWith('admin/')) {
            const { resolveAdminPage } = await import('@/lib/admin-pages');

            return resolveAdminPage(name);
        }

        const page = await storefrontPages[`./pages/${name}.tsx`]?.();

        if (!page) {
            throw new Error(`Page not found: ${name}`);
        }

        return page.default;
    },
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
                // Set on the page itself by resolveAdminPage().
                return null;
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {/* FE-010: a crashing page shows a message, not a white screen. */}
                <ErrorBoundary>{app}</ErrorBoundary>
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
