import { X } from 'lucide-react';

type ClearFiltersButtonProps = {
    active: boolean;
    onClear: () => void;
};

export function ClearFiltersButton({
    active,
    onClear,
}: ClearFiltersButtonProps): React.JSX.Element | null {
    if (!active) {
        return null;
    }

    return (
        <button
            type="button"
            onClick={onClear}
            className="flex h-9 shrink-0 items-center gap-1 rounded-lg border border-slate-200 px-3 text-sm text-slate-600 hover:bg-slate-50 hover:text-slate-700"
        >
            <X className="size-3.5" />
            Clear filters
        </button>
    );
}
