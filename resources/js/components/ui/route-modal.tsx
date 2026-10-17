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
            <DialogContent
                className={cn(
                    'max-h-[85vh] overflow-y-auto sm:max-w-lg',
                    className,
                )}
            >
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    {description && (
                        <DialogDescription>{description}</DialogDescription>
                    )}
                </DialogHeader>
                {children}
            </DialogContent>
        </Dialog>
    );
}
