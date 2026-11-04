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
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 bg-slate-50 px-4 py-2.5">
                        <p className="text-sm font-medium text-slate-700">
                            {count} {label}
                        </p>
                        <div className="flex flex-wrap items-center gap-2">
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                onClick={onClear}
                                disabled={busy}
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
                                        className="bg-emerald-600 text-white hover:bg-emerald-700"
                                    >
                                        <Eye className="mr-1 size-3.5" />{' '}
                                        {updatingTo === true
                                            ? 'Updating…'
                                            : activateLabel}
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        disabled={busy}
                                        onClick={() => updateStatus(false)}
                                        className="bg-slate-600 text-white hover:bg-slate-700"
                                    >
                                        <EyeOff className="mr-1 size-3.5" />{' '}
                                        {updatingTo === false
                                            ? 'Updating…'
                                            : deactivateLabel}
                                    </Button>
                                </>
                            )}
                            <Button
                                type="button"
                                size="sm"
                                disabled={busy}
                                onClick={onDelete}
                                className="bg-red-600 text-white hover:bg-red-700"
                            >
                                <Trash2 className="mr-1 size-3.5" />{' '}
                                {deleting ? 'Deleting…' : 'Delete'}
                            </Button>
                        </div>
                    </div>
                </AdminReveal>
            )}
        </AnimatePresence>
    );
}
