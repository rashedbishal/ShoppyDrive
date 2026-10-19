import { AnimatePresence, motion } from 'framer-motion';
import { useCallback, useEffect, useRef, useState } from 'react';
import { useCartStore } from '@/stores/cartStore';
import { useUiStore } from '@/stores/uiStore';
import type { CartItem, Id } from '@/types';

const GIF_URL = '/images/easter-eggs/cry-baby.gif';

/** One full loop of the GIF (351 frames × 40ms) — both "reach, told no, cry" beats. */
const GIF_LOOP_MS = 14_040;
const FADE_MS = 300;
const IDLE_TRIGGER_MS = 60_000;
const IDLE_PREFETCH_LEAD_MS = 15_000;
/** Let the cart drawer finish sliding away before the baby shows up. */
const CART_CLOSE_DELAY_MS = 450;

/**
 * Geometry of the source GIF, in its own pixels (200×200 canvas, transparent
 * background). The baby reaches to its left with the fingertip landing around
 * FINGERTIP; its body ends at BODY_RIGHT. Measured from the actual frames.
 */
const GIF_CANVAS = 200;
const FINGERTIP = { x: 80, y: 74 };
const BODY_RIGHT = 179;
const FINGERTIP_TO_BODY_RIGHT = BODY_RIGHT - FINGERTIP.x;

/** How far the baby may stand past the button's right edge (into the card padding). */
const OVERHANG_PX = 12;
/** Minimum gap kept between the baby and the button's label. */
const LABEL_CLEARANCE_PX = 6;
const MIN_SCALE = 0.42;
const MAX_SCALE = 0.62;
/** Where the hand lands, as a fraction of the button's height from its top. */
const FINGERTIP_HEIGHT_RATIO = 0.42;

type Placement = { left: number; top: number; size: number };
type Playback = { id: number; src: string; isObjectUrl: boolean };

type CryBabyEasterEggProps = {
    productId: Id;
    enabled: boolean;
};

/** Marks the button the baby reaches for; it must be a sibling of this component. */
const TARGET_SELECTOR = '[data-cry-baby-target]';

let gifBlobPromise: Promise<Blob> | null = null;

/** Fetch the GIF once per page session; each play gets its own object URL so it restarts at frame 0. */
function loadGifBlob(): Promise<Blob> {
    gifBlobPromise ??= fetch(GIF_URL)
        .then((response) => {
            if (!response.ok) {
                throw new Error(`Failed to load ${GIF_URL}`);
            }

            return response.blob();
        })
        .catch((error: unknown) => {
            gifBlobPromise = null;

            throw error;
        });

    return gifBlobPromise;
}

function prefetchGif(): void {
    loadGifBlob().catch(() => {});
}

function isProductInCart(items: CartItem[], productId: Id): boolean {
    return items.some((item) => item.product?.id === productId);
}

function prefersReducedMotion(): boolean {
    return window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
}

/**
 * Size and place the GIF so the baby's fingertip rests on the button's right
 * end, scaled down on narrow buttons so it never covers the label.
 */
function measurePlacement(button: HTMLButtonElement, layer: HTMLElement): Placement | null {
    const buttonRect = button.getBoundingClientRect();
    const layerRect = layer.getBoundingClientRect();

    if (buttonRect.width === 0 || buttonRect.height === 0) {
        return null;
    }

    const labelRange = document.createRange();
    labelRange.selectNodeContents(button);
    const labelRect = labelRange.getBoundingClientRect();
    const labelRight = labelRect.width > 0 ? labelRect.right - buttonRect.left : buttonRect.width / 2;

    const availableWidth = buttonRect.width + OVERHANG_PX - labelRight - LABEL_CLEARANCE_PX;
    const scale = Math.min(MAX_SCALE, Math.max(MIN_SCALE, availableWidth / FINGERTIP_TO_BODY_RIGHT));

    const fingertipX = buttonRect.right - layerRect.left + OVERHANG_PX - FINGERTIP_TO_BODY_RIGHT * scale;
    const fingertipY = buttonRect.top - layerRect.top + buttonRect.height * FINGERTIP_HEIGHT_RATIO;

    return {
        left: fingertipX - FINGERTIP.x * scale,
        top: fingertipY - FINGERTIP.y * scale,
        size: GIF_CANVAS * scale,
    };
}

/**
 * Product-page easter egg: a crying-baby GIF reaching for the Add to Cart
 * button when the shopper lingers for a minute without adding the product,
 * or adds it, removes it, and closes the cart drawer. Purely decorative —
 * it never intercepts clicks. Render it inside a `relative` container whose
 * Add to Cart button carries the `data-cry-baby-target` attribute.
 */
export function CryBabyEasterEgg({ productId, enabled }: CryBabyEasterEggProps): React.JSX.Element | null {
    const layerRef = useRef<HTMLSpanElement>(null);
    const [placement, setPlacement] = useState<Placement | null>(null);
    const [playback, setPlayback] = useState<Playback | null>(null);
    const playbackRef = useRef<Playback | null>(null);
    const isStartingRef = useRef(false);
    const isMountedRef = useRef(true);

    useEffect(() => {
        isMountedRef.current = true;

        return () => {
            isMountedRef.current = false;

            if (playbackRef.current?.isObjectUrl) {
                URL.revokeObjectURL(playbackRef.current.src);
            }
        };
    }, []);

    const stop = useCallback((): void => {
        const current = playbackRef.current;

        if (!current) {
            return;
        }

        playbackRef.current = null;
        setPlayback(null);

        // Free the object URL only once the fade-out no longer needs it.
        if (current.isObjectUrl) {
            setTimeout(() => URL.revokeObjectURL(current.src), FADE_MS + 200);
        }
    }, []);

    const startPlayback = useCallback(async (): Promise<void> => {
        if (isStartingRef.current || playbackRef.current || prefersReducedMotion()) {
            return;
        }

        isStartingRef.current = true;

        let next: Playback;

        try {
            const blob = await loadGifBlob();
            next = { id: Date.now(), src: URL.createObjectURL(blob), isObjectUrl: true };
        } catch {
            next = { id: Date.now(), src: `${GIF_URL}?play=${Date.now()}`, isObjectUrl: false };
        }

        isStartingRef.current = false;

        // The shopper may have added the product (or left) while the GIF was loading.
        if (!isMountedRef.current || isProductInCart(useCartStore.getState().items, productId)) {
            if (next.isObjectUrl) {
                URL.revokeObjectURL(next.src);
            }

            return;
        }

        playbackRef.current = next;
        setPlayback(next);
    }, [productId]);

    // Triggers: idle for a minute, or add → remove → close the cart drawer.
    useEffect(() => {
        if (!enabled) {
            return;
        }

        const timers: ReturnType<typeof setTimeout>[] = [];
        let pendingVisibilityListener: (() => void) | null = null;
        let wasInCart = isProductInCart(useCartStore.getState().items, productId);

        const requestPlay = (): void => {
            if (!document.hidden) {
                void startPlayback();

                return;
            }

            // Hold it until the shopper is actually looking at the page again.
            if (!pendingVisibilityListener) {
                pendingVisibilityListener = () => {
                    if (document.hidden) {
                        return;
                    }

                    document.removeEventListener('visibilitychange', pendingVisibilityListener!);
                    pendingVisibilityListener = null;
                    void startPlayback();
                };
                document.addEventListener('visibilitychange', pendingVisibilityListener);
            }
        };

        const unsubscribeCart = useCartStore.subscribe((state) => {
            if (isProductInCart(state.items, productId)) {
                if (!wasInCart) {
                    prefetchGif();
                }

                wasInCart = true;
                stop();
            }
        });

        const unsubscribeUi = useUiStore.subscribe((state, previousState) => {
            const didCloseCart = previousState.cartOpen && !state.cartOpen;

            if (didCloseCart && wasInCart && !isProductInCart(useCartStore.getState().items, productId)) {
                wasInCart = false;
                timers.push(setTimeout(requestPlay, CART_CLOSE_DELAY_MS));
            }
        });

        timers.push(setTimeout(prefetchGif, IDLE_TRIGGER_MS - IDLE_PREFETCH_LEAD_MS));
        timers.push(
            setTimeout(() => {
                if (!wasInCart && !isProductInCart(useCartStore.getState().items, productId)) {
                    requestPlay();
                }
            }, IDLE_TRIGGER_MS),
        );

        return () => {
            unsubscribeCart();
            unsubscribeUi();
            timers.forEach(clearTimeout);

            if (pendingVisibilityListener) {
                document.removeEventListener('visibilitychange', pendingVisibilityListener);
            }

            stop();
        };
    }, [enabled, productId, startPlayback, stop]);

    // Show for exactly one loop of the GIF, fading out over its last moments.
    useEffect(() => {
        if (!playback) {
            return;
        }

        const timer = setTimeout(stop, GIF_LOOP_MS - FADE_MS);

        return () => clearTimeout(timer);
    }, [playback, stop]);

    // Keep the baby glued to the button through resizes and layout shifts.
    useEffect(() => {
        const layer = layerRef.current;
        const button = layer?.parentElement?.querySelector<HTMLButtonElement>(TARGET_SELECTOR);

        if (!enabled || !layer || !button) {
            return;
        }

        const update = (): void => setPlacement(measurePlacement(button, layer));
        const observer = new ResizeObserver(update);
        observer.observe(button);
        observer.observe(layer);
        window.addEventListener('resize', update);

        return () => {
            observer.disconnect();
            window.removeEventListener('resize', update);
        };
    }, [enabled]);

    if (!enabled) {
        return null;
    }

    return (
        <span ref={layerRef} aria-hidden="true" className="pointer-events-none absolute inset-0 z-20">
            <AnimatePresence>
                {playback && placement ? (
                    <motion.img
                        key={playback.id}
                        src={playback.src}
                        alt=""
                        draggable={false}
                        className="absolute max-w-none select-none"
                        style={{
                            left: placement.left,
                            top: placement.top,
                            width: placement.size,
                            height: placement.size,
                        }}
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        exit={{ opacity: 0 }}
                        transition={{ duration: FADE_MS / 1000, ease: 'easeOut' }}
                    />
                ) : null}
            </AnimatePresence>
        </span>
    );
}
