import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type Customer = {
    id: number;
    name: string;
};

type Props = {
    customer: Customer | null;
    onOpenChange: (open: boolean) => void;
};

export function RestoreCustomerDialog({ customer, onOpenChange }: Props) {
    const { data, setData, post, processing, errors, reset, clearErrors } =
        useForm({
            mother_password: '',
        });

    useEffect(() => {
        reset();
        clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [customer?.id]);

    if (!customer) {
        return null;
    }

    const submit = () => {
        if (!data.mother_password || processing) {
            return;
        }

        post(route('admin.customers.restore', customer.id), {
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open onOpenChange={onOpenChange}>
            <DialogContent appearance="premium" className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Restore this customer?</DialogTitle>
                    <DialogDescription>
                        This will put back &quot;{customer.name}&quot;&apos;s
                        original name, phone, avatar and address book, restore
                        their original password (they can sign in with it again)
                        and reconnect any social accounts that were removed —
                        undoing the anonymization.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-1.5">
                    <label
                        htmlFor="restore_mother_password"
                        className="text-sm font-medium text-slate-700"
                    >
                        Mother admin password
                    </label>
                    <input
                        maxLength={255}
                        id="restore_mother_password"
                        type="password"
                        autoFocus
                        autoComplete="off"
                        value={data.mother_password}
                        onChange={(e) =>
                            setData('mother_password', e.target.value)
                        }
                        onKeyDown={(e) => e.key === 'Enter' && submit()}
                        className="h-9 w-full rounded-lg border border-slate-200 px-3 text-sm focus:border-[#c8102e] focus:outline-none"
                        placeholder="Required to confirm this action"
                    />
                    {errors.mother_password && (
                        <p className="text-xs text-red-600">
                            {errors.mother_password}
                        </p>
                    )}
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        disabled={processing || !data.mother_password}
                        onClick={submit}
                        className="bg-[#c8102e] text-white hover:bg-[#a50d25]"
                    >
                        {processing ? 'Restoring…' : 'Restore Customer'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
