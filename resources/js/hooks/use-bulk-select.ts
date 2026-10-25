import { useMemo, useState } from 'react';

export function useBulkSelect(ids: number[]) {
    const [selected, setSelected] = useState<number[]>([]);

    const idSet = useMemo(() => new Set(ids), [ids]);
    const selectedOnPage = useMemo(() => selected.filter((id) => idSet.has(id)), [selected, idSet]);
    const allSelected = ids.length > 0 && selectedOnPage.length === ids.length;

    const toggle = (id: number) => {
        setSelected((prev) => (prev.includes(id) ? prev.filter((i) => i !== id) : [...prev, id]));
    };

    const toggleAll = () => {
        setSelected((prev) => (allSelected ? prev.filter((id) => !idSet.has(id)) : [...prev.filter((id) => !idSet.has(id)), ...ids]));
    };

    const clear = () => setSelected([]);

    const isSelected = (id: number) => selected.includes(id);

    return { selected, isSelected, toggle, toggleAll, allSelected, clear };
}
