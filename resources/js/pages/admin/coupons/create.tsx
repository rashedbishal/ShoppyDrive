import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { RouteModal } from '@/components/ui/route-modal';

export default function CouponCreate() {
    const { data, setData, post, processing, errors } = useForm({
        code: '',
        name: '',
        type: 'percent' as 'percent' | 'fixed',
        value: '',
        min_order_amount: '',
        max_uses: '',
        per_user_limit: '',
        starts_at: '',
        expires_at: '',
        is_active: true,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('admin.coupons.store'));
    };

    const Field = ({
        label,
        error,
        children,
    }: {
        label: string;
        error?: string;
        children: React.ReactNode;
    }) => (
        <div>
            <label className="mb-1 block text-sm font-medium text-slate-700">
                {label}
            </label>
            {children}
            {error && <p className="mt-1 text-xs text-red-500">{error}</p>}
        </div>
    );

    const inputClass = (err?: string) =>
        `h-9 w-full rounded-lg border px-3 text-sm focus:outline-none focus:ring-1 ${err ? 'border-red-400 focus:ring-red-400' : 'border-slate-200 focus:ring-[#c8102e]'}`;

    return (
        <>
            <Head title="Add Coupon — Admin" />
            <RouteModal
                title="Add Coupon"
                returnTo={route('admin.coupons.index')}
                className="sm:max-w-2xl"
            >
                <form onSubmit={handleSubmit}>
                    <Card className="shadow-sm">
                        <CardHeader className="border-b border-slate-100 pb-4">
                            <CardTitle className="text-sm font-semibold text-slate-700">
                                Coupon Details
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 pt-4">
                            <div className="grid grid-cols-2 gap-4">
                                <Field label="Code *" error={errors.code}>
                                    <input
                                        className={`${inputClass(errors.code)} font-mono uppercase`}
                                        value={data.code}
                                        onChange={(e) =>
                                            setData(
                                                'code',
                                                e.target.value.toUpperCase(),
                                            )
                                        }
                                        placeholder="e.g. SAVE20"
                                    />
                                </Field>
                                <Field label="Name *" error={errors.name}>
                                    <input
                                        className={inputClass(errors.name)}
                                        value={data.name}
                                        onChange={(e) =>
                                            setData('name', e.target.value)
                                        }
                                        placeholder="e.g. 20% Off All Items"
                                    />
                                </Field>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <Field label="Type *" error={errors.type}>
                                    <select
                                        className="h-9 w-full rounded-lg border border-slate-200 px-3 text-sm focus:ring-1 focus:ring-[#c8102e] focus:outline-none"
                                        value={data.type}
                                        onChange={(e) =>
                                            setData(
                                                'type',
                                                e.target.value as
                                                    | 'percent'
                                                    | 'fixed',
                                            )
                                        }
                                    >
                                        <option value="percent">
                                            Percentage (%)
                                        </option>
                                        <option value="fixed">
                                            Fixed Amount (৳)
                                        </option>
                                    </select>
                                </Field>
                                <Field
                                    label={
                                        data.type === 'percent'
                                            ? 'Discount % *'
                                            : 'Discount Amount (৳) *'
                                    }
                                    error={errors.value}
                                >
                                    <input
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        className={inputClass(errors.value)}
                                        value={data.value}
                                        onChange={(e) =>
                                            setData('value', e.target.value)
                                        }
                                    />
                                </Field>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <Field
                                    label="Min Order (৳)"
                                    error={errors.min_order_amount}
                                >
                                    <input
                                        type="number"
                                        min="0"
                                        className={inputClass(
                                            errors.min_order_amount,
                                        )}
                                        value={data.min_order_amount}
                                        onChange={(e) =>
                                            setData(
                                                'min_order_amount',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                <Field label="Max Uses" error={errors.max_uses}>
                                    <input
                                        type="number"
                                        min="1"
                                        className={inputClass(errors.max_uses)}
                                        value={data.max_uses}
                                        onChange={(e) =>
                                            setData('max_uses', e.target.value)
                                        }
                                        placeholder="Unlimited"
                                    />
                                </Field>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <Field
                                    label="Per User Limit"
                                    error={errors.per_user_limit}
                                >
                                    <input
                                        type="number"
                                        min="1"
                                        className={inputClass(
                                            errors.per_user_limit,
                                        )}
                                        value={data.per_user_limit}
                                        onChange={(e) =>
                                            setData(
                                                'per_user_limit',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Unlimited"
                                    />
                                </Field>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <Field
                                    label="Starts At"
                                    error={errors.starts_at}
                                >
                                    <input
                                        type="datetime-local"
                                        className={inputClass(errors.starts_at)}
                                        value={data.starts_at}
                                        onChange={(e) =>
                                            setData('starts_at', e.target.value)
                                        }
                                    />
                                </Field>
                                <Field
                                    label="Expires At"
                                    error={errors.expires_at}
                                >
                                    <input
                                        type="datetime-local"
                                        className={inputClass(
                                            errors.expires_at,
                                        )}
                                        value={data.expires_at}
                                        onChange={(e) =>
                                            setData(
                                                'expires_at',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </Field>
                            </div>

                            <label className="flex cursor-pointer items-center gap-2.5 text-sm text-slate-700">
                                <input
                                    type="checkbox"
                                    checked={data.is_active}
                                    onChange={(e) =>
                                        setData('is_active', e.target.checked)
                                    }
                                    className="size-4 rounded border-slate-300 accent-[#c8102e]"
                                />
                                Active
                            </label>

                            <div className="flex gap-2 pt-2">
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="bg-[#c8102e] text-white hover:bg-[#a50d25]"
                                >
                                    {processing ? 'Saving…' : 'Create Coupon'}
                                </Button>
                                <Link
                                    href={route('admin.coupons.index')}
                                    className="inline-flex items-center rounded-lg border border-slate-200 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50"
                                >
                                    Cancel
                                </Link>
                            </div>
                        </CardContent>
                    </Card>
                </form>
            </RouteModal>
        </>
    );
}
