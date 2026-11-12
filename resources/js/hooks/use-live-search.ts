import { useEffect, useRef } from 'react';

/** How long typing must pause before the results update. */
export const LIVE_SEARCH_DELAY_MS = 350;

/**
 * Runs `apply` shortly after the visitor stops typing, so a list's results
 * follow the search box without pressing Enter or a Search button (both still
 * work and apply at once). Nothing runs while `value` already matches what the
 * list is showing (`applied`) — including right after the results arrive.
 */
export function useLiveSearch(
    value: string,
    applied: string,
    apply: () => void,
    delay: number = LIVE_SEARCH_DELAY_MS,
): void {
    const applyRef = useRef(apply);

    useEffect(() => {
        applyRef.current = apply;
    });

    useEffect(() => {
        if (value.trim() === applied.trim()) {
            return;
        }

        const timer = window.setTimeout(() => applyRef.current(), delay);

        return () => window.clearTimeout(timer);
    }, [value, applied, delay]);
}
