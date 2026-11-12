import { router } from '@inertiajs/react';
import { AnimatePresence } from 'framer-motion';
import { Eye, EyeOff, Trash2, X } from 'lucide-react';
import { useState } from 'react';
import { AdminReveal } from '@/components/admin/admin-motion';
import { Button } from '@/components/ui/button';

type BulkActionBarProps = {
    count: number;
    onDelete: () => void;
    onClear: () => void;
    deleting?: boolean;
    label?: string;
    /** When provided, Activate/Deactivate buttons are shown and PATCH `{ ids, active }` to this URL. */
    statusUrl?: string;
    selectedIds?: number[];
    activateLabel?: string;
    deactivateLabel?: string;
};

export function BulkActionBar({
    count,
    onDelete,
    onClear,
    deleting = false,
    label = 'selected',
    statusUrl,
    selectedIds = [],
    activateLabel = 'Activate',
    deactivateLabel = 'Deactivate',
}: BulkActionBarProps): React.JSX.Element {
    const [updatingTo, setUpdatingTo] = useState<boolean | null>(null);

    const busy = deleting || updatingTo !== null;

    const updateStatus = (active: boolean) => {
        if (!statusUrl) {
            return;
        }

        setUpdatingTo(active);
        router.patch(
            statusUrl,
            { ids: selectedIds, active },
            {
                preserveScroll: true,
                onSuccess: () => onClear(),
                onFinish: () => setUpdatingTo(null),
            },
        );
    };

    return (
        <AnimatePresence initial={false}>
            {count > 0 && (
                <AdminReveal key="bulk-actions">
                    {/* Phone: count + Clear on one row, the actions as equal-width buttons under it. From `sm` up everything sits on one row. */}
                    <div className="flex flex-col gap-2.5 border-b border-slate-100 bg-slate-50 px-4 py-2.5 sm:flex-row sm:items-center sm:justify-between sm:gap-3">
                        <div className="flex items-center justify-between gap-3">
                            <p className="min-w-0 text-sm font-medium text-slate-700">
                                {count} {label}
                            </p>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                onClick={onClear}
                                disabled={busy}
                                className="shrink-0 sm:hidden"
                            >
                                <X className="mr-1 size-3.5" /> Clear
                            </Button>
                        </div>
                        <div className="grid auto-cols-fr grid-flow-col gap-2 sm:flex sm:flex-wrap sm:items-center">
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                onClick={onClear}
                                disabled={busy}
                                className="hidden sm:inline-flex"
                            >
                                <X className="mr-1 size-3.5" /> Clear
                            </Button>
                            {statusUrl && (
                                <>
                                    <Button
                                        type="button"
                                        size="sm"
                                        disabled={busy}
                                        onClick={() => updateStatus(true)}
                                        className="min-w-0 bg-emerald-600 px-2 text-xs whitespace-nowrap text-white hover:bg-emerald-700 sm:px-3 sm:text-sm"
                                    >
                                        <Eye className="mr-1 size-3 shrink-0 sm:size-3.5" />
                                        <span className="truncate">
                                            {updatingTo === true
                                                ? 'Updating…'
                                                : activateLabel}
                                        </span>
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        disabled={busy}
                                        onClick={() => updateStatus(false)}
                                        className="min-w-0 bg-slate-600 px-2 text-xs whitespace-nowrap text-white hover:bg-slate-700 sm:px-3 sm:text-sm"
                                    >
                                        <EyeOff className="mr-1 size-3 shrink-0 sm:size-3.5" />
                                        <span className="truncate">
                                            {updatingTo === false
                                                ? 'Updating…'
                                                : deactivateLabel}
                                        </span>
                                    </Button>
                                </>
                            )}
                            <Button
                                type="button"
                                size="sm"
                                disabled={busy}
                                onClick={onDelete}
                                className="min-w-0 bg-red-600 px-2 text-xs whitespace-nowrap text-white hover:bg-red-700 sm:px-3 sm:text-sm"
                            >
                                <Trash2 className="mr-1 size-3 shrink-0 sm:size-3.5" />
                                <span className="truncate">
                                    {deleting ? 'Deleting…' : 'Delete'}
                                </span>
                            </Button>
                        </div>
                    </div>
                </AdminReveal>
            )}
        </AnimatePresence>
    );
}
