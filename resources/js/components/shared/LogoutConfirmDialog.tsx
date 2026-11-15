import { ConfirmDialog } from '@/components/ui/confirm-dialog';

type LogoutConfirmDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onConfirm: () => void;
    processing?: boolean;
};

/**
 * Shared "are you sure?" prompt shown before signing out, for both customers
 * and admins.
 */
export function LogoutConfirmDialog({
    open,
    onOpenChange,
    onConfirm,
    processing = false,
}: LogoutConfirmDialogProps): React.JSX.Element {
    return (
        <ConfirmDialog
            open={open}
            onOpenChange={onOpenChange}
            title="Log out?"
            description="Are you sure you want to log out? You will need to sign in again to continue."
            confirmLabel="Log out"
            processingLabel="Logging out…"
            onConfirm={onConfirm}
            processing={processing}
        />
    );
}
