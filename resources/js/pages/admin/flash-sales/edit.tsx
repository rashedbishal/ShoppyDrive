import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Select } from '@/components/ui/select';
import { maxLength, required, validate } from '@/lib/validate';

type Product = { id: number; name: string; price: number; sku: string | null };
type FlashSaleItemData = {
    id?: number;
    product_id: string;
    sale_price: string;
    stock_limit: string;
    product?: { id: number; name: string; price: number; sku: string | null };
};
type FlashSale = {
    id: number;
    name: string;
    starts_at: string;
    ends_at: string;
    is_active: boolean;
    items: FlashSaleItemData[];
};

type Props = {
    flashSale: FlashSale;
    products: Product[];
};

const toDatetimeLocal = (str: string) => str.slice(0, 16);

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

export default function FlashSaleEdit({ flashSale, products }: Props) {
    const { data, setData, put, processing, errors, setError, clearErrors } =
        useForm({
            name: flashSale.name,
            starts_at: toDatetimeLocal(flashSale.starts_at),
            ends_at: toDatetimeLocal(flashSale.ends_at),
            is_active: flashSale.is_active,
            items: flashSale.items.map((item) => ({
                product_id: String(item.product_id),
                sale_price: String(item.sale_price),
                stock_limit: String(item.stock_limit),
            })),
        });

    const addItem = () => {
        setData('items', [
            ...data.items,
            { product_id: '', sale_price: '', stock_limit: '10' },
        ]);
    };

    const removeItem = (i: number) => {
        setData(
            'items',
            data.items.filter((_, j) => j !== i),
        );
    };

    const updateItem = (
        i: number,
        key: 'product_id' | 'sale_price' | 'stock_limit',
        value: string,
    ) => {
        const items = [...data.items];
        items[i] = { ...items[i], [key]: value };
        setData('items', items);
    };

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

        if (
            data.items.some(
                (item) =>
                    !item.product_id || !item.sale_price || !item.stock_limit,
            )
        ) {
            clientErrors.items =
                'Every product row needs a product, sale price, and stock limit.';
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
            <Head title={`Edit Flash Sale — Admin`} />
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
                                <CardHeader className="flex flex-row items-center justify-between border-b border-slate-100 pb-4">
                                    <CardTitle className="text-base font-semibold">
                                        Products
                                    </CardTitle>
                                    <button
                                        type="button"
                                        onClick={addItem}
                                        className="flex items-center gap-1 text-xs font-medium text-[#c8102e] hover:underline"
                                    >
                                        <Plus className="size-3.5" /> Add
                                        Product
                                    </button>
                                </CardHeader>
                                <CardContent className="pt-4">
                                    {errors.items && (
                                        <p className="mb-3 text-xs text-red-500">
                                            {errors.items}
                                        </p>
                                    )}
                                    {data.items.length === 0 ? (
                                        <p className="py-4 text-center text-sm text-slate-400">
                                            No products. Click "Add Product"
                                            above.
                                        </p>
                                    ) : (
                                        <div className="space-y-3">
                                            {data.items.map((item, i) => {
                                                const selectedProduct =
                                                    products.find(
                                                        (p) =>
                                                            String(p.id) ===
                                                            item.product_id,
                                                    );
                                                const usedIds = data.items
                                                    .map((it) => it.product_id)
                                                    .filter(Boolean);
                                                const availableProducts =
                                                    products.filter(
                                                        (p) =>
                                                            !usedIds.includes(
                                                                String(p.id),
                                                            ) ||
                                                            String(p.id) ===
                                                                item.product_id,
                                                    );

                                                return (
                                                    <div
                                                        key={i}
                                                        className="flex flex-col gap-3 rounded-lg border border-slate-100 bg-slate-50/50 p-3 sm:flex-row sm:items-end"
                                                    >
                                                        <div className="min-w-0 flex-1">
                                                            <label className="mb-1 block text-xs font-medium text-slate-600">
                                                                Product *
                                                            </label>
                                                            <Select
                                                                selectClassName="h-9 w-full rounded-lg border border-slate-200 px-3 text-sm focus:ring-1 focus:ring-[#c8102e] focus:outline-none"
                                                                value={
                                                                    item.product_id
                                                                }
                                                                onChange={(e) =>
                                                                    updateItem(
                                                                        i,
                                                                        'product_id',
                                                                        e.target
                                                                            .value,
                                                                    )
                                                                }
                                                            >
                                                                <option value="">
                                                                    — Select
                                                                    product —
                                                                </option>
                                                                {availableProducts.map(
                                                                    (p) => (
                                                                        <option
                                                                            key={
                                                                                p.id
                                                                            }
                                                                            value={
                                                                                p.id
                                                                            }
                                                                        >
                                                                            {
                                                                                p.name
                                                                            }{' '}
                                                                            (৳
                                                                            {p.price.toLocaleString()}
                                                                            )
                                                                        </option>
                                                                    ),
                                                                )}
                                                            </Select>
                                                        </div>
                                                        <div className="w-full sm:w-32">
                                                            <label className="mb-1 block text-xs font-medium text-slate-600">
                                                                Sale Price (৳) *
                                                            </label>
                                                            <input
                                                                type="number"
                                                                min="0"
                                                                step="0.01"
                                                                className="h-9 w-full rounded-lg border border-slate-200 px-3 text-sm focus:ring-1 focus:ring-[#c8102e] focus:outline-none"
                                                                value={
                                                                    item.sale_price
                                                                }
                                                                onChange={(e) =>
                                                                    updateItem(
                                                                        i,
                                                                        'sale_price',
                                                                        e.target
                                                                            .value,
                                                                    )
                                                                }
                                                                placeholder={
                                                                    selectedProduct
                                                                        ? String(
                                                                              Math.round(
                                                                                  selectedProduct.price *
                                                                                      0.8,
                                                                              ),
                                                                          )
                                                                        : ''
                                                                }
                                                            />
                                                        </div>
                                                        <div className="w-full sm:w-28">
                                                            <label className="mb-1 block text-xs font-medium text-slate-600">
                                                                Stock Limit *
                                                            </label>
                                                            <input
                                                                type="number"
                                                                min="1"
                                                                className="h-9 w-full rounded-lg border border-slate-200 px-3 text-sm focus:ring-1 focus:ring-[#c8102e] focus:outline-none"
                                                                value={
                                                                    item.stock_limit
                                                                }
                                                                onChange={(e) =>
                                                                    updateItem(
                                                                        i,
                                                                        'stock_limit',
                                                                        e.target
                                                                            .value,
                                                                    )
                                                                }
                                                            />
                                                        </div>
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                removeItem(i)
                                                            }
                                                            className="self-end rounded-lg p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-500 sm:mb-0.5 sm:self-auto"
                                                        >
                                                            <Trash2 className="size-4" />
                                                        </button>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    )}
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
