import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { RouteModal } from '@/components/ui/route-modal';
import { Select } from '@/components/ui/select';
import { maxLength, minValue, required, validate } from '@/lib/validate';

type Coupon = {
    id: number;
    code: string;
    name: string;
    type: 'percent' | 'fixed';
    value: number;
    min_order_amount: number | null;
    max_uses: number | null;
    per_user_limit: number | null;
    starts_at: string | null;
    expires_at: string | null;
    is_active: boolean;
};

type Props = { coupon: Coupon };

const toDatetimeLocal = (str: string | null) => {
    if (!str) {
        return '';
    }

    return str.slice(0, 16);
};

function Field({
    label,
    error,
    children,
}: {
    label: string;
    error?: string;
    children: React.ReactNode;
}) {
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-slate-700">
                {label}
            </label>
            {children}
            {error && <p className="mt-1 text-xs text-red-500">{error}</p>}
        </div>
    );
}

export default function CouponEdit({ coupon }: Props) {
    const { data, setData, put, processing, errors, setError, clearErrors } =
        useForm({
            code: coupon.code,
            name: coupon.name,
            type: coupon.type,
            value: String(coupon.value),
            min_order_amount: coupon.min_order_amount
                ? String(coupon.min_order_amount)
                : '',
            max_uses: coupon.max_uses ? String(coupon.max_uses) : '',
            per_user_limit: coupon.per_user_limit
                ? String(coupon.per_user_limit)
                : '',
            starts_at: toDatetimeLocal(coupon.starts_at),
            expires_at: toDatetimeLocal(coupon.expires_at),
            is_active: coupon.is_active,
        });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        clearErrors();
        const clientErrors: Record<string, string> = validate(data, {
            code: [required(), maxLength(50)],
            name: [required(), maxLength(255)],
            value: [required(), minValue(0)],
            min_order_amount: [minValue(0)],
            max_uses: [minValue(1)],
            per_user_limit: [minValue(1)],
        });

        if (
            data.starts_at &&
            data.expires_at &&
            new Date(data.expires_at) < new Date(data.starts_at)
        ) {
            clientErrors.expires_at =
                'The expiry date must be on or after the start date.';
        }

        if (Object.keys(clientErrors).length > 0) {
            setError(clientErrors);

            return;
        }

        put(route('admin.coupons.update', coupon.id));
    };

    const inputClass = (err?: string) =>
        `h-9 w-full rounded-lg border px-3 text-sm focus:outline-none focus:ring-1 ${err ? 'border-red-400 focus:ring-red-400' : 'border-slate-200 focus:ring-[#c8102e]'}`;

    return (
        <>
            <Head title={`Edit ${coupon.code} — Admin`} />
            <RouteModal
                title="Edit Coupon"
                description={coupon.code}
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
                                    />
                                </Field>
                                <Field label="Name *" error={errors.name}>
                                    <input
                                        className={inputClass(errors.name)}
                                        value={data.name}
                                        onChange={(e) =>
                                            setData('name', e.target.value)
                                        }
                                    />
                                </Field>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <Field label="Type *" error={errors.type}>
                                    <Select
                                        selectClassName="h-9 w-full rounded-lg border border-slate-200 px-3 text-sm focus:ring-1 focus:ring-[#c8102e] focus:outline-none"
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
                                    </Select>
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
                                    {processing ? 'Saving…' : 'Save Changes'}
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
