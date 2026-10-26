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
        // Closing without saving shouldn't re-fetch returnTo's props from the
        // server — the list page is already cached from the visit that opened
        // this modal (always via a Link from that page). Going back lets
        // Inertia restore it from that cache instead of issuing a fresh
        // request. If there's no history to go back to (e.g. this route was
        // loaded directly), fall back to a normal visit.
        let wentBack = false;
        const onPopState = () => {
            wentBack = true;
            window.removeEventListener('popstate', onPopState);
        };
        window.addEventListener('popstate', onPopState);
        window.history.back();
        setTimeout(() => {
            window.removeEventListener('popstate', onPopState);
            if (!wentBack) {
                router.visit(returnTo, { preserveScroll: true });
            }
        }, 150);
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
