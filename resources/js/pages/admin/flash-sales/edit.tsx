import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { FlashSaleProductsCard } from '@/components/admin/flash-sale-products-card';
import type { FlashSaleItemData } from '@/components/admin/flash-sale-products-card';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { CharCount } from '@/components/ui/char-count';
import { useAdminListUrl } from '@/hooks/use-admin-list-url';
import {
    STORABLE_DATETIME_MAX,
    STORABLE_DATETIME_MIN,
} from '@/lib/storable-date';
import { maxLength, required, validate } from '@/lib/validate';

type FlashSaleItemProduct = {
    id: number;
    name: string;
    price: number;
    sku: string | null;
    stock_quantity: number;
    available_stock: number;
};

type FlashSaleItemVariant = {
    id: number;
    sku: string | null;
    price: number;
    stock_quantity: number;
    attributes: Record<string, string> | null;
};

type FlashSaleItemRaw = {
    id: number;
    product_id: number;
    variant_id: number | null;
    sale_price: number;
    product: FlashSaleItemProduct | null;
    variant: FlashSaleItemVariant | null;
    /** Only sent to admins who may see cost prices; null otherwise. */
    cost_price: number | null;
};

type FlashSale = {
    id: number;
    name: string;
    /** Bangladesh time, formatted for the date inputs. */
    starts_at_input: string;
    ends_at_input: string;
    is_active: boolean;
    items: FlashSaleItemRaw[];
};

type Props = { flashSale: FlashSale };

function variantLabel(attributes: Record<string, string> | null): string {
    if (!attributes) {
        return '';
    }

    return Object.entries(attributes)
        .map(([key, value]) => `${key}: ${value}`)
        .join(', ');
}

function toItemData(item: FlashSaleItemRaw): FlashSaleItemData {
    return {
        product_id: String(item.product_id),
        variant_id: item.variant_id !== null ? String(item.variant_id) : '',
        sale_price: String(item.sale_price),
        product_name: item.product?.name ?? '',
        product_sku: item.variant?.sku ?? item.product?.sku ?? null,
        available_stock:
            item.variant?.stock_quantity ?? item.product?.available_stock ?? 0,
        variant_label: item.variant
            ? variantLabel(item.variant.attributes) ||
              item.variant.sku ||
              `Variant #${item.variant.id}`
            : null,
        cost_price: item.cost_price ?? null,
    };
}

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

export default function FlashSaleEdit({ flashSale }: Props) {
    const listUrl = useAdminListUrl('admin.flash-sales.index');
    const { data, setData, put, processing, errors, setError, clearErrors } =
        useForm({
            name: flashSale.name,
            starts_at: flashSale.starts_at_input,
            ends_at: flashSale.ends_at_input,
            is_active: flashSale.is_active,
            items: flashSale.items.map(toItemData),
        });

    const getClientErrors = (): Record<string, string> => {
        const clientErrors: Record<string, string> = validate(data, {
            name: [required(), maxLength(100)],
            starts_at: [required()],
            ends_at: [required()],
        });

        if (
            data.starts_at &&
            data.ends_at &&
            new Date(data.ends_at) <= new Date(data.starts_at)
        ) {
            clientErrors.ends_at = 'The end date must be after the start date.';
        }

        if (data.items.some((item) => !item.product_id || !item.sale_price)) {
            clientErrors.items = 'Every product row needs a sale price.';
        }

        return clientErrors;
    };

    const isFormValid = Object.keys(getClientErrors()).length === 0;

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        clearErrors();
        const clientErrors = getClientErrors();

        if (Object.keys(clientErrors).length > 0) {
            setError(clientErrors);

            return;
        }

        put(route('admin.flash-sales.update', flashSale.id));
    };

    const inputClass = (err?: string) =>
        `h-9 w-full rounded-lg border px-3 text-sm focus:outline-none focus:ring-1 ${err ? 'border-red-400 focus:ring-red-400' : 'border-slate-200 focus:ring-[#c8102e]'}`;

    return (
        <>
            <Head title="Edit Flash Sale — Admin" />
            <div className="space-y-5">
                <div className="flex items-center gap-4">
                    <Button
                        asChild
                        variant="ghost"
                        size="icon"
                        className="rounded-full"
                    >
                        <Link href={listUrl}>
                            <ArrowLeft className="size-5" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900">
                            Edit Flash Sale
                        </h1>
                        <p className="text-sm text-slate-600">
                            {flashSale.name}
                        </p>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="space-y-5">
                    <div className="grid grid-cols-1 gap-5 lg:grid-cols-[minmax(0,1fr)_320px]">
                        <div className="space-y-5">
                            <Card>
                                <CardHeader className="border-b border-slate-100">
                                    <CardTitle className="text-base font-semibold">
                                        Products
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="pt-3">
                                    <FlashSaleProductsCard
                                        items={data.items}
                                        onChange={(items) =>
                                            setData('items', items)
                                        }
                                        errors={
                                            errors as Record<string, string>
                                        }
                                    />
                                </CardContent>
                            </Card>
                        </div>

                        <div className="space-y-5">
                            <Card>
                                <CardHeader className="border-b border-slate-100">
                                    <CardTitle className="text-base font-semibold">
                                        Flash Sale Details
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4 pt-3">
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
                                    <Field
                                        label="Starts At * (Bangladesh time)"
                                        error={errors.starts_at}
                                    >
                                        <input
                                            type="datetime-local"
                                            min={STORABLE_DATETIME_MIN}
                                            max={STORABLE_DATETIME_MAX}
                                            className={inputClass(
                                                errors.starts_at,
                                            )}
                                            value={data.starts_at}
                                            onChange={(e) =>
                                                setData(
                                                    'starts_at',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </Field>
                                    <Field
                                        label="Ends At * (Bangladesh time)"
                                        error={errors.ends_at}
                                    >
                                        <input
                                            type="datetime-local"
                                            min={STORABLE_DATETIME_MIN}
                                            max={STORABLE_DATETIME_MAX}
                                            className={inputClass(
                                                errors.ends_at,
                                            )}
                                            value={data.ends_at}
                                            onChange={(e) =>
                                                setData(
                                                    'ends_at',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </Field>
                                    <label className="flex cursor-pointer items-center gap-2.5 text-sm text-slate-700">
                                        <input
                                            type="checkbox"
                                            checked={data.is_active}
                                            onChange={(e) =>
                                                setData(
                                                    'is_active',
                                                    e.target.checked,
                                                )
                                            }
                                            className="size-4 rounded border-slate-300 accent-[#c8102e]"
                                        />
                                        Active
                                    </label>
                                </CardContent>
                            </Card>
                        </div>
                    </div>

                    <div className="flex items-center gap-3">
                        <Button
                            type="submit"
                            disabled={processing || !isFormValid}
                            className="bg-[#c8102e] text-white hover:bg-[#a50d25]"
                        >
                            {processing ? 'Saving…' : 'Save Changes'}
                        </Button>
                        <Button
                            asChild
                            variant="ghost"
                            className="text-slate-600"
                        >
                            <Link href={listUrl}>Cancel</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}
