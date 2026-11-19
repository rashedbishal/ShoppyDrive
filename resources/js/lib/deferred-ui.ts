import { lazy, useEffect, useState } from 'react';
import type { ComponentType, LazyExoticComponent } from 'react';

/**
 * DISC-73: parts of the storefront nobody sees when a page first appears
 * (the cart drawer, mobile menu, chat button, AI assistant and newsletter
 * thank-you modal) are split into their own files. Production renders
 * pages in the browser, so every line of JavaScript loaded up front delays
 * the first paint; these now download in the background once the page is
 * idle, or on first use if that comes sooner.
 */

type Preloadable<P extends object> = LazyExoticComponent<ComponentType<P>> & {
    preload: () => void;
};

/** React.lazy() plus a preload() that starts the download early. */
function lazyWithPreload<P extends object>(
    factory: () => Promise<{ default: ComponentType<P> }>,
): Preloadable<P> {
    let pending: Promise<{ default: ComponentType<P> }> | null = null;
    const load = () => {
        pending ??= factory().catch((error: unknown) => {
            // A failed download (e.g. a flaky connection) can be retried.
            pending = null;

            throw error;
        });

        return pending;
    };
    const component = lazy(load) as Preloadable<P>;
    component.preload = () => {
        load().catch(() => {
            // The real render retries and surfaces any error there.
        });
    };

    return component;
}

export const LazyCartDrawer = lazyWithPreload(
    () => import('@/components/layout/CartDrawer'),
);

export const LazyMobileMenu = lazyWithPreload(
    () => import('@/components/layout/MobileMenu'),
);

export const LazySocialChatWidget = lazyWithPreload(
    () => import('@/components/layout/SocialChatWidget'),
);

export const LazyAiAssistantPanel = lazyWithPreload(
    () => import('@/components/layout/AiAssistantPanel'),
);

export const LazyNewsletterThankYouModal = lazyWithPreload(
    () => import('@/components/shared/NewsletterThankYouModal'),
);

/** Runs `callback` once the page has loaded and the browser is idle. */
function whenIdle(callback: () => void): () => void {
    let idleHandle: number | null = null;
    let timeoutHandle: ReturnType<typeof setTimeout> | null = null;

    const schedule = () => {
        if ('requestIdleCallback' in window) {
            idleHandle = window.requestIdleCallback(callback, {
                timeout: 3000,
            });
        } else {
            timeoutHandle = setTimeout(callback, 1500);
        }
    };

    if (document.readyState === 'complete') {
        schedule();
    } else {
        window.addEventListener('load', schedule, { once: true });
    }

    return () => {
        window.removeEventListener('load', schedule);

        if (idleHandle !== null && 'cancelIdleCallback' in window) {
            window.cancelIdleCallback(idleHandle);
        }

        if (timeoutHandle !== null) {
            clearTimeout(timeoutHandle);
        }
    };
}

/** False until the page has loaded and the browser is idle, then true. */
export function useBrowserIdle(): boolean {
    const [isIdle, setIsIdle] = useState(false);

    useEffect(() => whenIdle(() => setIsIdle(true)), []);

    return isIdle;
}

/** Starts downloading every deferred part once the browser is idle. */
export function usePreloadDeferredUi(): void {
    useEffect(
        () =>
            whenIdle(() => {
                LazyCartDrawer.preload();
                LazyMobileMenu.preload();
                LazySocialChatWidget.preload();
                LazyNewsletterThankYouModal.preload();
            }),
        [],
    );
}

/**
 * True from the first time `isOpen` is true, and stays true, so a deferred
 * panel is mounted on first use and then kept (its state and close animation
 * work exactly as before).
 */
export function useHasOpened(isOpen: boolean): boolean {
    const [hasOpened, setHasOpened] = useState(isOpen);

    if (isOpen && !hasOpened) {
        setHasOpened(true);
    }

    return hasOpened || isOpen;
}
