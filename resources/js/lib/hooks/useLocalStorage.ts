import { useCallback, useEffect, useState, type Dispatch, type SetStateAction } from 'react';

function resolveInitialValue<T>(initialValue: T): T {
    return initialValue;
}

export function useLocalStorage<T>(
    key: string,
    initialValue: T,
): [T, Dispatch<SetStateAction<T>>] {
    const [storedValue, setStoredValue] = useState<T>(() => {
        if (typeof window === 'undefined') {
            return resolveInitialValue(initialValue);
        }

        try {
            const item = window.localStorage.getItem(key);

            if (item === null) {
                return resolveInitialValue(initialValue);
            }

            return JSON.parse(item) as T;
        } catch {
            return resolveInitialValue(initialValue);
        }
    });

    const setValue: Dispatch<SetStateAction<T>> = useCallback(
        (value) => {
            setStoredValue((current) => {
                const nextValue = value instanceof Function ? value(current) : value;

                if (typeof window !== 'undefined') {
                    window.localStorage.setItem(key, JSON.stringify(nextValue));
                }

                return nextValue;
            });
        },
        [key],
    );

    useEffect(() => {
        if (typeof window === 'undefined') {
            return;
        }

        try {
            window.localStorage.setItem(key, JSON.stringify(storedValue));
        } catch {
            // Ignore quota and serialization errors in the client hook.
        }
    }, [key, storedValue]);

    return [storedValue, setValue];
}

