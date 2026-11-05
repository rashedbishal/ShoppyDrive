import { useSyncExternalStore } from 'react';

function subscribeToTick(callback: () => void): () => void {
    const intervalId = window.setInterval(callback, 1000);

    return () => window.clearInterval(intervalId);
}

/** Whole seconds, so repeated reads within a tick return the same value. */
function getNow(): number {
    return Math.floor(Date.now() / 1000) * 1000;
}

function getServerNow(): null {
    return null;
}

/**
 * The current time, ticking every second. It's null during the server render
 * and the browser's first pass, so both produce identical HTML: reading the
 * clock in each would disagree on the seconds and cause a hydration mismatch.
 */
export function useNow(): number | null {
    return useSyncExternalStore(subscribeToTick, getNow, getServerNow);
}
