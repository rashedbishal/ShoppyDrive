import { Head, Link, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { RouteModal } from '@/components/ui/route-modal';
import { Select } from '@/components/ui/select';
import { maxLength, required, validate } from '@/lib/validate';

type Product = { id: number; name: string; price: number; sku: string | null };

type Props = { products: Product[] };

type SaleItem = { product_id: string; sale_price: string; stock_limit: string };

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

export default function FlashSaleCreate({ products }: Props) {
    const { data, setData, post, processing, errors, setError, clearErrors } =
        useForm({
            name: '',
            starts_at: '',
            ends_at: '',
            is_active: false,
            items: [] as SaleItem[],
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

    const updateItem = (i: number, key: keyof SaleItem, value: string) => {
        const items = [...data.items];
        items[i] = { ...items[i], [key]: value };
        setData('items', items);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        clearErrors();
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

        if (Object.keys(clientErrors).length > 0) {
            setError(clientErrors);

            return;
        }

        post(route('admin.flash-sales.store'));
    };

    const inputClass = (err?: string) =>
        `h-9 w-full rounded-lg border px-3 text-sm focus:outline-none focus:ring-1 ${err ? 'border-red-400 focus:ring-red-400' : 'border-slate-200 focus:ring-[#c8102e]'}`;

    return (
        <>
            <Head title="Add Flash Sale — Admin" />
            <RouteModal
                title="Add Flash Sale"
                returnTo={route('admin.flash-sales.index')}
                className="sm:max-w-2xl"
            >
                <form onSubmit={handleSubmit} className="space-y-5">
                    <Card className="shadow-sm">
                        <CardHeader className="border-b border-slate-100 pb-4">
                            <CardTitle className="text-sm font-semibold text-slate-700">
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
                                    placeholder="e.g. Summer Mega Sale"
                                />
                            </Field>
                            <div className="grid grid-cols-2 gap-4">
                                <Field
                                    label="Starts At *"
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
                                <Field label="Ends At *" error={errors.ends_at}>
                                    <input
                                        type="datetime-local"
                                        className={inputClass(errors.ends_at)}
                                        value={data.ends_at}
                                        onChange={(e) =>
                                            setData('ends_at', e.target.value)
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
                        </CardContent>
                    </Card>

                    <Card className="shadow-sm">
                        <CardHeader className="flex flex-row items-center justify-between border-b border-slate-100 pb-4">
                            <CardTitle className="text-sm font-semibold text-slate-700">
                                Products
                            </CardTitle>
                            <button
                                type="button"
                                onClick={addItem}
                                className="flex items-center gap-1 text-xs font-medium text-[#c8102e] hover:underline"
                            >
                                <Plus className="size-3.5" /> Add Product
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
                                    No products added yet. Click "Add Product"
                                    above.
                                </p>
                            ) : (
                                <div className="space-y-3">
                                    {data.items.map((item, i) => {
                                        const selectedProduct = products.find(
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
                                                className="flex items-end gap-3 rounded-lg border border-slate-100 bg-slate-50/50 p-3"
                                            >
                                                <div className="flex-1">
                                                    <label className="mb-1 block text-xs font-medium text-slate-600">
                                                        Product *
                                                    </label>
                                                    <Select
                                                        selectClassName="h-9 w-full rounded-lg border border-slate-200 px-3 text-sm focus:ring-1 focus:ring-[#c8102e] focus:outline-none"
                                                        value={item.product_id}
                                                        onChange={(e) =>
                                                            updateItem(
                                                                i,
                                                                'product_id',
                                                                e.target.value,
                                                            )
                                                        }
                                                    >
                                                        <option value="">
                                                            — Select product —
                                                        </option>
                                                        {availableProducts.map(
                                                            (p) => (
                                                                <option
                                                                    key={p.id}
                                                                    value={p.id}
                                                                >
                                                                    {p.name} (৳
                                                                    {p.price.toLocaleString()}
                                                                    )
                                                                </option>
                                                            ),
                                                        )}
                                                    </Select>
                                                </div>
                                                <div className="w-32">
                                                    <label className="mb-1 block text-xs font-medium text-slate-600">
                                                        Sale Price (৳) *
                                                    </label>
                                                    <input
                                                        type="number"
                                                        min="0"
                                                        step="0.01"
                                                        className="h-9 w-full rounded-lg border border-slate-200 px-3 text-sm focus:ring-1 focus:ring-[#c8102e] focus:outline-none"
                                                        value={item.sale_price}
                                                        onChange={(e) =>
                                                            updateItem(
                                                                i,
                                                                'sale_price',
                                                                e.target.value,
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
                                                <div className="w-28">
                                                    <label className="mb-1 block text-xs font-medium text-slate-600">
                                                        Stock Limit *
                                                    </label>
                                                    <input
                                                        type="number"
                                                        min="1"
                                                        className="h-9 w-full rounded-lg border border-slate-200 px-3 text-sm focus:ring-1 focus:ring-[#c8102e] focus:outline-none"
                                                        value={item.stock_limit}
                                                        onChange={(e) =>
                                                            updateItem(
                                                                i,
                                                                'stock_limit',
                                                                e.target.value,
                                                            )
                                                        }
                                                    />
                                                </div>
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        removeItem(i)
                                                    }
                                                    className="mb-0.5 rounded-lg p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-500"
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

                    <div className="flex gap-2">
                        <Button
                            type="submit"
                            disabled={processing}
                            className="bg-[#c8102e] text-white hover:bg-[#a50d25]"
                        >
                            {processing ? 'Saving…' : 'Create Flash Sale'}
                        </Button>
                        <Link
                            href={route('admin.flash-sales.index')}
                            className="inline-flex items-center rounded-lg border border-slate-200 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50"
                        >
                            Cancel
                        </Link>
                    </div>
                </form>
            </RouteModal>
        </>
    );
}
