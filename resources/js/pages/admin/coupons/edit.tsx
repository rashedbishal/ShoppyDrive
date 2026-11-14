import { Head, Link, useForm } from '@inertiajs/react';
import { CouponTargetsCard } from '@/components/admin/CouponTargetsCard';
import type { CouponTargetProduct } from '@/components/admin/CouponTargetsCard';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { CharCount } from '@/components/ui/char-count';
import { RouteModal } from '@/components/ui/route-modal';
import { Select } from '@/components/ui/select';
import { useAdminListUrl } from '@/hooks/use-admin-list-url';
import {
    STORABLE_DATETIME_MAX,
    STORABLE_DATETIME_MIN,
} from '@/lib/storable-date';
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
    /** Bangladesh time, formatted for the date inputs. */
    starts_at_input: string;
    expires_at_input: string;
    applies_to: 'all' | 'selected';
    target_category_ids: number[];
    target_sub_category_ids: number[];
    target_products: CouponTargetProduct[];
    is_active: boolean;
};

type TargetOptions = {
    categories: { id: number; name: string }[];
    subCategories: { id: number; name: string; category_id: number }[];
};

type Props = TargetOptions & { coupon: Coupon };

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

export default function CouponEdit({
    coupon,
    categories,
    subCategories,
}: Props) {
    const listUrl = useAdminListUrl('admin.coupons.index');
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
            starts_at: coupon.starts_at_input,
            expires_at: coupon.expires_at_input,
            is_active: coupon.is_active,
            applies_to: coupon.applies_to,
            target_category_ids: coupon.target_category_ids,
            target_sub_category_ids: coupon.target_sub_category_ids,
            target_products: coupon.target_products,
        });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        clearErrors();
        const clientErrors: Record<string, string> = validate(data, {
            code: [required(), maxLength(30)],
            name: [required(), maxLength(100)],
            value: [required(), minValue(0)],
            min_order_amount: [minValue(0)],
            max_uses: [minValue(1)],
            per_user_limit: [minValue(1)],
        });

        if (
            data.applies_to === 'selected' &&
            data.target_category_ids.length === 0 &&
            data.target_sub_category_ids.length === 0 &&
            data.target_products.length === 0
        ) {
            clientErrors.applies_to =
                'Select at least one category, subcategory or product.';
        }

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
        `h-9 w-full rounded-lg border px-3 text-sm focus:outline-none focus:ring-1 ${err ? 'border-red-400 focus:ring-red-400' : 'border-slate-200 focus:ring-brand'}`;

    return (
        <>
            <Head title={`Edit ${coupon.code} — Admin`} />
            <RouteModal
                title="Edit Coupon"
                description={coupon.code}
                returnTo={listUrl}
                className="sm:max-w-2xl"
            >
                <form onSubmit={handleSubmit}>
                    <Card className="shadow-sm">
                        <CardHeader className="border-b border-slate-100">
                            <CardTitle className="text-sm font-semibold text-slate-700">
                                Coupon Details
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 pt-3">
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <Field label="Code *" error={errors.code}>
                                    <input
                                        maxLength={30}
                                        className={`${inputClass(errors.code)} font-mono uppercase`}
                                        value={data.code}
                                        onChange={(e) =>
                                            setData(
                                                'code',
                                                e.target.value.toUpperCase(),
                                            )
                                        }
                                    />
                                    <CharCount
                                        value={data.code}
                                        max={30}
                                        className="mt-1 block text-right"
                                    />
                                </Field>
                                <Field label="Name *" error={errors.name}>
                                    <input
                                        maxLength={100}
                                        className={inputClass(errors.name)}
                                        value={data.name}
                                        onChange={(e) =>
                                            setData('name', e.target.value)
                                        }
                                    />
                                    <CharCount
                                        value={data.name}
                                        max={100}
                                        className="mt-1 block text-right"
                                    />
                                </Field>
                            </div>

                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <Field label="Type *" error={errors.type}>
                                    <Select
                                        selectClassName="h-9 w-full rounded-lg border border-slate-200 px-3 text-sm focus:ring-1 focus:ring-brand focus:outline-none"
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
                                        max={
                                            data.type === 'percent'
                                                ? 100
                                                : 99999999.99
                                        }
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

                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <Field
                                    label="Min Order (৳)"
                                    error={errors.min_order_amount}
                                >
                                    <input
                                        max={99999999.99}
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
                                        max={9999999}
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

                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <Field
                                    label="Per User Limit"
                                    error={errors.per_user_limit}
                                >
                                    <input
                                        max={1000}
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

                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <Field
                                    label="Starts At (Bangladesh time)"
                                    error={errors.starts_at}
                                >
                                    <input
                                        type="datetime-local"
                                        min={STORABLE_DATETIME_MIN}
                                        max={STORABLE_DATETIME_MAX}
                                        className={inputClass(errors.starts_at)}
                                        value={data.starts_at}
                                        onChange={(e) =>
                                            setData('starts_at', e.target.value)
                                        }
                                    />
                                </Field>
                                <Field
                                    label="Expires At (Bangladesh time)"
                                    error={errors.expires_at}
                                >
                                    <input
                                        type="datetime-local"
                                        min={STORABLE_DATETIME_MIN}
                                        max={STORABLE_DATETIME_MAX}
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
                                    className="size-4 rounded border-slate-300 accent-brand"
                                />
                                Active
                            </label>

                            <CouponTargetsCard
                                value={data}
                                onChange={(patch) =>
                                    setData((prev) => ({ ...prev, ...patch }))
                                }
                                categories={categories}
                                subCategories={subCategories}
                                errors={errors as Record<string, string>}
                            />
                            <div className="flex gap-2 pt-2">
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="bg-brand text-brand-foreground hover:bg-brand-hover"
                                >
                                    {processing ? 'Saving…' : 'Save Changes'}
                                </Button>
                                <Link
                                    href={listUrl}
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
