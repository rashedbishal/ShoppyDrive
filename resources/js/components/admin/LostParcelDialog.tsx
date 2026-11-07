import { useForm } from '@inertiajs/react';
import { PackageX } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';

type Props = {
    open: boolean;
    onClose: () => void;
    orderId: number;
    orderNumber: string;
};

/**
 * FE-004: the mother admin's record that the courier confirmed a parcel lost.
 * The server enforces everything; this only collects the note and password.
 */
export default function LostParcelDialog({
    open,
    onClose,
    orderId,
    orderNumber,
}: Props) {
    const form = useForm({ note: '', password: '' });

    const close = () => {
        form.reset();
        form.clearErrors();
        onClose();
    };

    return (
        <Dialog open={open} onOpenChange={(value) => !value && close()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <PackageX className="size-4" />
                        Confirm parcel lost
                    </DialogTitle>
                    <DialogDescription>
                        For order {orderNumber}. This doesn&apos;t refund or
                        change the order. It only stops stock being added back
                        when you later cancel or refund it, because the goods
                        are gone.
                    </DialogDescription>
                </DialogHeader>
                <form
                    className="space-y-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(
                            route('admin.orders.confirmLostParcel', orderId),
                            {
                                preserveScroll: true,
                                onSuccess: close,
                                onFinish: () => form.reset('password'),
                            },
                        );
                    }}
                >
                    <div>
                        <label
                            htmlFor="lost-note"
                            className="mb-1 block text-sm font-medium"
                        >
                            What did the courier confirm?
                        </label>
                        <textarea
                            id="lost-note"
                            rows={3}
                            className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                            value={form.data.note}
                            onChange={(event) =>
                                form.setData('note', event.target.value)
                            }
                        />
                        {form.errors.note && (
                            <p className="mt-1 text-xs text-red-500">
                                {form.errors.note}
                            </p>
                        )}
                    </div>
                    <div>
                        <label
                            htmlFor="lost-password"
                            className="mb-1 block text-sm font-medium"
                        >
                            Your password
                        </label>
                        <Input
                            id="lost-password"
                            type="password"
                            autoComplete="current-password"
                            value={form.data.password}
                            onChange={(event) =>
                                form.setData('password', event.target.value)
                            }
                        />
                        {form.errors.password && (
                            <p className="mt-1 text-xs text-red-500">
                                {form.errors.password}
                            </p>
                        )}
                    </div>
                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="outline" onClick={close}>
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={
                                form.processing ||
                                !form.data.note ||
                                !form.data.password
                            }
                        >
                            {form.processing ? 'Saving…' : 'Confirm lost'}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
