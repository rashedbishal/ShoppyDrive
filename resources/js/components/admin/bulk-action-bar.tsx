import { Trash2, X } from 'lucide-react';
import { Button } from '@/components/ui/button';

type BulkActionBarProps = {
    count: number;
    onDelete: () => void;
    onClear: () => void;
    deleting?: boolean;
    label?: string;
};

export function BulkActionBar({ count, onDelete, onClear, deleting = false, label = 'selected' }: BulkActionBarProps): React.JSX.Element | null {
    if (count === 0) {
        return null;
    }

    return (
        <div className="flex items-center justify-between gap-3 border-b border-slate-100 bg-slate-50 px-4 py-2.5">
            <p className="text-sm font-medium text-slate-700">
                {count} {label}
            </p>
            <div className="flex items-center gap-2">
                <Button type="button" size="sm" variant="outline" onClick={onClear}>
                    <X className="mr-1 size-3.5" /> Clear
                </Button>
                <Button
                    type="button"
                    size="sm"
                    disabled={deleting}
                    onClick={onDelete}
                    className="bg-red-600 text-white hover:bg-red-700"
                >
                    <Trash2 className="mr-1 size-3.5" /> {deleting ? 'Deleting…' : 'Delete'}
                </Button>
            </div>
        </div>
    );
}
