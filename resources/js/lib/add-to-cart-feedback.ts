import { useUiStore } from '@/stores/uiStore';
import type { Product, ProductVariant } from '@/types';

/** Fired by the cart store whenever a shopper adds an item. */
export const CART_ITEM_ADDED_EVENT = 'cart:item-added';

export type CartItemAddedDetail = {
    product: Product;
    variant?: ProductVariant | null;
};

/** Marks the header cart button the thumbnail flies into. */
const CART_TARGET_SELECTOR = '[data-cart-target]';
const CART_COUNT_SELECTOR = '[data-cart-count]';

/** A click this recent is treated as the one that triggered the add. */
const SOURCE_CLICK_WINDOW_MS = 1500;
const FLIGHT_DURATION_MS = 720;
const FLYER_SIZE_PX = 56;

let lastClickedElement: Element | null = null;
let lastClickedAt = 0;
let isInitialized = false;

function prefersReducedMotion(): boolean {
    return window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
}

function findSourceElement(): HTMLElement | null {
    if (!lastClickedElement || performance.now() - lastClickedAt > SOURCE_CLICK_WINDOW_MS) {
        return null;
    }

    const source = lastClickedElement.closest<HTMLElement>('button, a, [role="button"]');

    return source?.isConnected ? source : null;
}

function findVisibleCartTarget(): HTMLElement | null {
    const targets = document.querySelectorAll<HTMLElement>(CART_TARGET_SELECTOR);

    for (const target of targets) {
        const rect = target.getBoundingClientRect();

        if (rect.width > 0 && rect.height > 0 && rect.bottom > 0 && rect.top < window.innerHeight) {
            return target;
        }
    }

    return null;
}

/** Lucide "package" icon, inlined so the flyer needs no React render. */
const PACKAGE_ICON_SVG =
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="26" height="26" aria-hidden="true">' +
    '<path d="M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73z"/>' +
    '<path d="M12 22V12"/><path d="m3.3 7 7.703 4.734a2 2 0 0 0 1.994 0L20.7 7"/><path d="m7.5 4.27 9 5.15"/>' +
    '</svg>';

/** Tactile "press" on the button that was tapped — composited on top of its own transform. */
function pulseSource(source: HTMLElement): void {
    source.animate(
        [{ transform: 'scale(1)' }, { transform: 'scale(0.94)' }, { transform: 'scale(1)' }],
        { duration: 260, easing: 'cubic-bezier(0.3, 0, 0.2, 1)', composite: 'add' },
    );
}

/** The cart icon "catches" the item, and its count badge pops. */
function bumpCart(target: HTMLElement): void {
    target.animate(
        [
            { transform: 'scale(1)' },
            { transform: 'scale(1.22) rotate(-8deg)', offset: 0.35 },
            { transform: 'scale(0.94) rotate(4deg)', offset: 0.7 },
            { transform: 'scale(1)' },
        ],
        { duration: 480, easing: 'ease-out' },
    );

    target.querySelector<HTMLElement>(CART_COUNT_SELECTOR)?.animate(
        [{ transform: 'scale(1)' }, { transform: 'scale(1.5)', offset: 0.4 }, { transform: 'scale(1)' }],
        { duration: 420, delay: 80, easing: 'cubic-bezier(0.34, 1.56, 0.64, 1)' },
    );
}

function createFlyer(left: number, top: number): { outer: HTMLDivElement; inner: HTMLDivElement } {
    const outer = document.createElement('div');
    outer.setAttribute('aria-hidden', 'true');
    Object.assign(outer.style, {
        position: 'fixed',
        left: `${left}px`,
        top: `${top}px`,
        width: `${FLYER_SIZE_PX}px`,
        height: `${FLYER_SIZE_PX}px`,
        zIndex: '60',
        pointerEvents: 'none',
        willChange: 'transform',
    });

    // Brand-red rounded badge with a white package icon — reads clearly on any background.
    const inner = document.createElement('div');
    inner.innerHTML = PACKAGE_ICON_SVG;
    Object.assign(inner.style, {
        width: '100%',
        height: '100%',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        borderRadius: '16px',
        color: '#fff',
        backgroundColor: 'var(--color-primary)',
        backgroundImage: 'linear-gradient(135deg, rgba(255, 255, 255, 0.22), rgba(255, 255, 255, 0) 60%)',
        boxShadow: '0 12px 24px -8px color-mix(in srgb, var(--color-primary) 60%, transparent), 0 0 0 3px rgba(255, 255, 255, 0.9)',
        willChange: 'transform, opacity',
    });

    outer.appendChild(inner);

    return { outer, inner };
}

/**
 * Fly a product thumbnail from the tapped button into the header cart along
 * a gentle arc: X moves linearly while Y eases in, which curves the path.
 * Only transform/opacity animate, so it all runs on the compositor.
 */
function flyToCart(source: HTMLElement, target: HTMLElement): void {
    const from = source.getBoundingClientRect();
    const to = target.getBoundingClientRect();

    const startX = from.left + from.width / 2 - FLYER_SIZE_PX / 2;
    const startY = from.top + from.height / 2 - FLYER_SIZE_PX / 2;
    const deltaX = to.left + to.width / 2 - FLYER_SIZE_PX / 2 - startX;
    const deltaY = to.top + to.height / 2 - FLYER_SIZE_PX / 2 - startY;

    const { outer, inner } = createFlyer(startX, startY);
    document.body.appendChild(outer);

    outer.animate([{ transform: 'translateX(0)' }, { transform: `translateX(${deltaX}px)` }], {
        duration: FLIGHT_DURATION_MS,
        easing: 'cubic-bezier(0.4, 0, 0.6, 1)',
        fill: 'forwards',
    });

    const flight = inner.animate(
        [
            { transform: 'translateY(0) scale(0.4)', opacity: 0 },
            { transform: 'translateY(0) scale(1.05)', opacity: 1, offset: 0.18 },
            { transform: `translateY(${deltaY}px) scale(0.32)`, opacity: 0.85 },
        ],
        { duration: FLIGHT_DURATION_MS, easing: 'cubic-bezier(0.55, 0, 0.85, 0.35)', fill: 'forwards' },
    );

    const finish = (): void => {
        outer.remove();
        bumpCart(target);
    };

    flight.addEventListener('finish', finish, { once: true });
    flight.addEventListener('cancel', () => outer.remove(), { once: true });
}

function handleItemAdded(): void {
    const source = findSourceElement();

    // Wait a frame so a same-tick `openCart()` from the caller is visible to us.
    requestAnimationFrame(() => {
        if (prefersReducedMotion()) {
            return;
        }

        if (source) {
            pulseSource(source);
        }

        // An opened cart drawer is already the confirmation — don't fly behind it.
        if (useUiStore.getState().cartOpen) {
            return;
        }

        const target = findVisibleCartTarget();

        if (!target) {
            return;
        }

        if (source) {
            flyToCart(source, target);
        } else {
            bumpCart(target);
        }
    });
}

/** Install the global add-to-cart feedback once, in the browser only. */
export function initAddToCartFeedback(): void {
    if (isInitialized || typeof window === 'undefined') {
        return;
    }

    isInitialized = true;

    document.addEventListener(
        'pointerdown',
        (event) => {
            lastClickedElement = event.target instanceof Element ? event.target : null;
            lastClickedAt = performance.now();
        },
        { capture: true, passive: true },
    );

    // Keyboard activation (Enter/Space on a focused button) has no pointerdown.
    document.addEventListener(
        'keydown',
        (event) => {
            if ((event.key === 'Enter' || event.key === ' ') && document.activeElement) {
                lastClickedElement = document.activeElement;
                lastClickedAt = performance.now();
            }
        },
        { capture: true, passive: true },
    );

    window.addEventListener(CART_ITEM_ADDED_EVENT, handleItemAdded);
}
