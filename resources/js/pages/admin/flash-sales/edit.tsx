import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { FlashSaleProductsCard } from '@/components/admin/flash-sale-products-card';
import type { FlashSaleItemData } from '@/components/admin/flash-sale-products-card';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
};

type FlashSale = {
    id: number;
    name: string;
    starts_at: string;
    ends_at: string;
    is_active: boolean;
    items: FlashSaleItemRaw[];
};

type Props = { flashSale: FlashSale };

const toDatetimeLocal = (str: string) => str.slice(0, 16);

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
    const { data, setData, put, processing, errors, setError, clearErrors } =
        useForm({
            name: flashSale.name,
            starts_at: toDatetimeLocal(flashSale.starts_at),
            ends_at: toDatetimeLocal(flashSale.ends_at),
            is_active: flashSale.is_active,
            items: flashSale.items.map(toItemData),
        });

    const getClientErrors = (): Record<string, string> => {
        const clientErrors: Record<string, string> = validate(data, {
            name: [required(), maxLength(255)],
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
            <div className="space-y-6">
                <div className="flex items-center gap-4">
                    <Button
                        asChild
                        variant="ghost"
                        size="icon"
                        className="rounded-full"
                    >
                        <Link href={route('admin.flash-sales.index')}>
                            <ArrowLeft className="size-5" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900">
                            Edit Flash Sale
                        </h1>
                        <p className="text-sm text-slate-500">
                            {flashSale.name}
                        </p>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="space-y-5">
                    <div className="grid gap-5 lg:grid-cols-[1fr_320px]">
                        <div className="space-y-5">
                            <Card>
                                <CardHeader className="border-b border-slate-100 pb-4">
                                    <CardTitle className="text-base font-semibold">
                                        Products
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="pt-4">
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
                                <CardHeader className="border-b border-slate-100 pb-4">
                                    <CardTitle className="text-base font-semibold">
                                        Flash Sale Details
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4 pt-4">
                                    <Field label="Name *" error={errors.name}>
                                        <input
                                            className={inputClass(errors.name)}
                                            value={data.name}
                                            onChange={(e) =>
                                                setData('name', e.target.value)
                                            }
                                        />
                                    </Field>
                                    <Field
                                        label="Starts At *"
                                        error={errors.starts_at}
                                    >
                                        <input
                                            type="datetime-local"
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
                                        label="Ends At *"
                                        error={errors.ends_at}
                                    >
                                        <input
                                            type="datetime-local"
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
                            className="text-slate-500"
                        >
                            <Link href={route('admin.flash-sales.index')}>
                                Cancel
                            </Link>
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}
