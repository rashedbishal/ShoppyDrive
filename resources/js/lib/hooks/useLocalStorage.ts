import { useCallback, useSyncExternalStore } from 'react';
import type { Dispatch, SetStateAction } from 'react';

const listeners = new Map<string, Set<() => void>>();
const cache = new Map<string, { raw: string | null; value: unknown }>();

function subscribe(key: string, callback: () => void): () => void {
    if (!listeners.has(key)) {
        listeners.set(key, new Set());
    }

    listeners.get(key)!.add(callback);

    const onStorage = (event: StorageEvent): void => {
        if (event.key === key) {
            callback();
        }
    };
    window.addEventListener('storage', onStorage);

    return () => {
        listeners.get(key)?.delete(callback);
        window.removeEventListener('storage', onStorage);
    };
}

function notify(key: string): void {
    listeners.get(key)?.forEach((callback) => callback());
}

// Caches by the raw stored string so repeated reads return the same parsed
// reference when nothing changed, which `useSyncExternalStore` requires to
// avoid tearing/re-render loops.
function readValue<T>(key: string, initialValue: T): T {
    let raw: string | null;

    try {
        raw = window.localStorage.getItem(key);
    } catch {
        raw = null;
    }

    const cached = cache.get(key);

    if (cached && cached.raw === raw) {
        return cached.value as T;
    }

    let value: T;

    if (raw === null) {
        value = initialValue;
    } else {
        try {
            value = JSON.parse(raw) as T;
        } catch {
            value = initialValue;
        }
    }

    cache.set(key, { raw, value });

    return value;
}

// `getServerSnapshot` always returns `initialValue`, so SSR and the client's
// first hydration pass agree; the real stored value is only read via
// `getSnapshot`, which only runs on the client after hydration.
export function useLocalStorage<T>(
    key: string,
    initialValue: T,
): [T, Dispatch<SetStateAction<T>>] {
    const subscribeFn = useCallback(
        (callback: () => void) => subscribe(key, callback),
        [key],
    );
    const getSnapshot = useCallback(
        () => readValue(key, initialValue),
        [key, initialValue],
    );
    const getServerSnapshot = useCallback(() => initialValue, [initialValue]);

    const storedValue = useSyncExternalStore(
        subscribeFn,
        getSnapshot,
        getServerSnapshot,
    );

    const setValue: Dispatch<SetStateAction<T>> = useCallback(
        (value) => {
            const current = readValue(key, initialValue);
            const nextValue =
                value instanceof Function ? value(current) : value;

            try {
                window.localStorage.setItem(key, JSON.stringify(nextValue));
            } catch {
                // Ignore quota and serialization errors in the client hook.
            }

            notify(key);
        },
        [key, initialValue],
    );

    return [storedValue, setValue];
}
