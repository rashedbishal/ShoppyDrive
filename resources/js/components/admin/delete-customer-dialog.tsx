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
    orders_count: number;
};

type Props = {
    customer: Customer | null;
    onOpenChange: (open: boolean) => void;
};

export function DeleteCustomerDialog({ customer, onOpenChange }: Props) {
    const {
        data,
        setData,
        delete: destroy,
        processing,
        errors,
        reset,
        clearErrors,
    } = useForm({
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

        destroy(route('admin.customers.destroy', customer.id), {
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Delete customer?</DialogTitle>
                    <DialogDescription>
                        This will permanently delete &quot;{customer.name}
                        &quot;&apos;s account. This cannot be undone.
                    </DialogDescription>
                </DialogHeader>

                {customer.orders_count > 0 && (
                    <div className="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-xs text-amber-700">
                        This customer has{' '}
                        <strong>{customer.orders_count}</strong> order
                        {customer.orders_count === 1 ? '' : 's'}. Deleting this
                        customer will also delete all of their orders.
                    </div>
                )}

                <div className="space-y-1.5">
                    <label
                        htmlFor="mother_password"
                        className="text-sm font-medium text-slate-700"
                    >
                        Mother admin password
                    </label>
                    <input
                        id="mother_password"
                        type="password"
                        autoFocus
                        autoComplete="off"
                        value={data.mother_password}
                        onChange={(e) =>
                            setData('mother_password', e.target.value)
                        }
                        onKeyDown={(e) => e.key === 'Enter' && submit()}
                        className="h-9 w-full rounded-lg border border-slate-200 px-3 text-sm focus:border-[#c8102e] focus:outline-none"
                        placeholder="Required to confirm this deletion"
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
                        className="bg-red-600 text-white hover:bg-red-700"
                    >
                        {processing ? 'Deleting…' : 'Delete Customer'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
