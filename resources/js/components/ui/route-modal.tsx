import { router } from '@inertiajs/react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { cn } from '@/lib/utils';

type RouteModalProps = {
    title: string;
    description?: string;
    returnTo: string;
    className?: string;
    children: React.ReactNode;
};

/**
 * Renders an Inertia create/edit page as a modal dialog instead of a full page.
 * Closing it (X, Escape, backdrop click) navigates back to `returnTo` — the
 * page's own routing, data loading, and validation are otherwise unchanged.
 */
export function RouteModal({
    title,
    description,
    returnTo,
    className,
    children,
}: RouteModalProps): React.JSX.Element {
    const close = () => {
        router.visit(returnTo, { preserveScroll: true });
    };

    return (
        <Dialog
            open
            onOpenChange={(open) => {
                if (!open) {
                    close();
                }
            }}
        >
            <DialogContent className={cn('sm:max-w-lg', className)}>
                {/*
                 * Scrolling lives here, not on DialogContent itself — DialogContent
                 * is the dialog's transform origin, so anything clipped by an
                 * `overflow` set directly on it would also clip absolutely
                 * positioned popups (e.g. a `Select`'s open list) rendered
                 * anywhere underneath, no matter how they're portaled. See
                 * `useDialogPortalTarget` in dialog.tsx.
                 */}
                <div className="max-h-[calc(85vh-3rem)] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>{title}</DialogTitle>
                        {description && (
                            <DialogDescription>
                                {description}
                            </DialogDescription>
                        )}
                    </DialogHeader>
                    {children}
                </div>
            </DialogContent>
        </Dialog>
    );
}
