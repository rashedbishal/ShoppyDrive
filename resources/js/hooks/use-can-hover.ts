import { useSyncExternalStore } from 'react';

/**
 * IMG-F7: whether the main pointer can hover (a mouse or trackpad). Phones
 * and tablets can't, so product cards there never download the hover photo
 * they could never show. Follows the device live (e.g. a tablet docked to a
 * keyboard with a trackpad).
 */
const mql =
    typeof window === 'undefined' || typeof window.matchMedia !== 'function'
        ? undefined
        : window.matchMedia('(hover: hover) and (pointer: fine)');

function subscribe(callback: () => void): () => void {
    if (!mql) {
        return () => {};
    }

    mql.addEventListener('change', callback);

    return () => {
        mql.removeEventListener('change', callback);
    };
}

function getSnapshot(): boolean {
    return mql?.matches ?? false;
}

function getServerSnapshot(): boolean {
    return false;
}

export function useCanHover(): boolean {
    return useSyncExternalStore(subscribe, getSnapshot, getServerSnapshot);
}
