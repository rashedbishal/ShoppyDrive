import { useCallback, useEffect, useRef } from 'react';

type Range = [number, number];

/**
 * Decides when a price-range filter actually reloads the product list.
 *
 * While a slider handle is held, nothing is sent: the latest value is only
 * remembered, and applied once when the pointer is released anywhere on the
 * page. A slow drag used to send a request on every pause (or every ৳10
 * step), enough to hit the `product-browsing` rate limit (HTTP 429).
 * Typing and keyboard nudges are debounced instead, and keyboard callers
 * apply immediately on key-up via `flush()`.
 */
export function usePriceRangeCommit(
    onCommit: (lo: number, hi: number) => void,
    delayMs = 350,
): {
    /** Remember a value; sent after `delayMs` unless a handle is held. */
    schedule: (lo: number, hi: number) => void;
    /** Send the remembered value now, if there is one. */
    flush: () => void;
    /** Send this value now, discarding anything remembered. */
    commitNow: (lo: number, hi: number) => void;
    /** Call on a handle's pointer-down: holds sending until release. */
    startDrag: () => void;
} {
    const onCommitRef = useRef(onCommit);
    useEffect(() => {
        onCommitRef.current = onCommit;
    }, [onCommit]);

    const timeoutRef = useRef<ReturnType<typeof setTimeout> | undefined>(
        undefined,
    );
    const pendingRef = useRef<Range | undefined>(undefined);
    const isDraggingRef = useRef(false);
    const removeDragListenersRef = useRef<(() => void) | undefined>(undefined);

    const flush = useCallback((): void => {
        clearTimeout(timeoutRef.current);

        if (pendingRef.current) {
            const [lo, hi] = pendingRef.current;
            pendingRef.current = undefined;
            onCommitRef.current(lo, hi);
        }
    }, []);

    const schedule = useCallback(
        (lo: number, hi: number): void => {
            pendingRef.current = [lo, hi];
            clearTimeout(timeoutRef.current);

            if (!isDraggingRef.current) {
                timeoutRef.current = setTimeout(flush, delayMs);
            }
        },
        [delayMs, flush],
    );

    const commitNow = useCallback(
        (lo: number, hi: number): void => {
            pendingRef.current = [lo, hi];
            flush();
        },
        [flush],
    );

    const startDrag = useCallback((): void => {
        if (isDraggingRef.current) {
            return;
        }

        isDraggingRef.current = true;
        clearTimeout(timeoutRef.current);

        // Listened for on window, not the handle: a mouse released outside
        // the slider never fires pointerup on the handle itself. `blur`
        // covers switching windows mid-drag, when no pointerup arrives.
        const endDrag = (): void => {
            removeDragListeners();
            flush();
        };
        const removeDragListeners = (): void => {
            window.removeEventListener('pointerup', endDrag);
            window.removeEventListener('pointercancel', endDrag);
            window.removeEventListener('blur', endDrag);
            isDraggingRef.current = false;
            removeDragListenersRef.current = undefined;
        };

        window.addEventListener('pointerup', endDrag);
        window.addEventListener('pointercancel', endDrag);
        window.addEventListener('blur', endDrag);
        removeDragListenersRef.current = removeDragListeners;
    }, [flush]);

    useEffect(
        () => () => {
            clearTimeout(timeoutRef.current);
            removeDragListenersRef.current?.();
        },
        [],
    );

    return { schedule, flush, commitNow, startDrag };
}
