import { useState } from 'react';

let nextRowKey = 0;

const createRowKeys = (count: number): number[] =>
    Array.from({ length: count }, () => nextRowKey++);

/**
 * UI-only identities for rows that have no id of their own, so removing a row
 * animates that row and not whichever one was last. Keys never reach the form
 * data. Call `removeKey(index)` alongside removing the row; rows added or
 * dropped any other way are picked up from `rowCount`.
 */
export function useRowKeys(rowCount: number): {
    keys: number[];
    removeKey: (index: number) => void;
} {
    const [keys, setKeys] = useState(() => createRowKeys(rowCount));

    if (keys.length !== rowCount) {
        setKeys(
            keys.length < rowCount
                ? [...keys, ...createRowKeys(rowCount - keys.length)]
                : keys.slice(0, rowCount),
        );
    }

    return {
        keys,
        removeKey: (index) =>
            setKeys((current) => current.filter((_, i) => i !== index)),
    };
}
