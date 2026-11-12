import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { CharCount } from '@/components/ui/char-count';
import { ComboboxField } from '@/components/ui/combobox';
import { RouteModal } from '@/components/ui/route-modal';
import { maxLength, pattern, required, validate } from '@/lib/validate';

type Product = {
    id: number;
    name: string;
    price: number;
    is_active: boolean;
};
type Props = { products: Product[] };

export default function LandingPageCreate({ products }: Props) {
    const { data, setData, post, processing, errors, setError, clearErrors } =
        useForm({
            title: '',
            slug: '',
            product_id: '',
            is_active: true,
            max_order_quantity: 100,
        });
    const [slugTouched, setSlugTouched] = useState(false);

    const inputClass =
        'h-9 w-full rounded-lg border border-slate-200 px-3 text-sm focus:outline-none focus:ring-1 focus:ring-[#c8102e]';

    const autoSlug = (title: string) =>
        title
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-|-$/g, '');

    return (
        <>
            <Head title="New Landing Page — Admin" />
            <RouteModal
                title="New Landing Page"
                returnTo={route('admin.landing-pages.index')}
                className="sm:max-w-lg"
            >
                <Card className="shadow-sm">
                    <CardHeader className="border-b border-slate-100">
                        <CardTitle className="text-sm font-semibold text-slate-700">
                            Basic Info
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4 pt-3">
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();

                                clearErrors();
                                const clientErrors = validate(data, {
                                    title: [required(), maxLength(100)],
                                    slug: [
                                        required(),
                                        maxLength(100),
                                        pattern(
                                            /^[a-z0-9-]+$/,
                                            'Only lowercase letters, numbers, and hyphens are allowed.',
                                        ),
                                    ],
                                    product_id: [required()],
                                });

                                if (Object.keys(clientErrors).length > 0) {
                                    setError(clientErrors);

                                    return;
                                }

                                post(route('admin.landing-pages.store'));
                            }}
                            className="space-y-4"
                        >
                            <div>
                                <label className="mb-1 block text-sm font-medium text-slate-700">
                                    Page Title
                                </label>
                                <input
                                    maxLength={100}
                                    className={inputClass}
                                    value={data.title}
                                    onChange={(e) => {
                                        const title = e.target.value;

                                        setData((prev) => ({
                                            ...prev,
                                            title,
                                            slug: slugTouched
                                                ? prev.slug
                                                : autoSlug(title),
                                        }));
                                    }}
                                    placeholder="e.g. Special Offer on Product X"
                                />
                                <CharCount
                                    value={data.title}
                                    max={100}
                                    className="mt-1 block text-right"
                                />

                                {errors.title && (
                                    <p className="mt-1 text-xs text-red-600">
                                        {errors.title}
                                    </p>
                                )}
                            </div>

                            <div>
                                <label className="mb-1 block text-sm font-medium text-slate-700">
                                    URL Slug
                                </label>
                                <div className="flex items-center overflow-hidden rounded-lg border border-slate-200 focus-within:ring-1 focus-within:ring-[#c8102e]">
                                    <span className="flex h-9 shrink-0 items-center border-r border-slate-200 bg-slate-50 px-3 text-sm text-slate-400">
                                        /lp/
                                    </span>
                                    <input
                                        maxLength={100}
                                        className="h-9 flex-1 px-3 font-mono text-sm focus:outline-none"
                                        value={data.slug}
                                        onChange={(e) => {
                                            setSlugTouched(true);
                                            setData(
                                                'slug',
                                                e.target.value
                                                    .toLowerCase()
                                                    .replace(/[^a-z0-9-]/g, ''),
                                            );
                                        }}
                                        placeholder="my-product-offer"
                                    />
                                </div>
                                <CharCount
                                    value={data.slug}
                                    max={100}
                                    className="mt-1 block text-right"
                                />
                                {errors.slug && (
                                    <p className="mt-1 text-xs text-red-600">
                                        {errors.slug}
                                    </p>
                                )}
                                <p className="mt-1 text-xs text-slate-400">
                                    Only lowercase letters, numbers, and
                                    hyphens.
                                </p>
                            </div>

                            <div>
                                <label className="mb-1 block text-sm font-medium text-slate-700">
                                    Product
                                </label>
                                <ComboboxField
                                    value={data.product_id}
                                    onChange={(value) =>
                                        setData('product_id', value)
                                    }
                                    options={products.map((p) => ({
                                        value: String(p.id),
                                        label: `${p.name} — ৳${p.price.toLocaleString()}${p.is_active ? '' : ' (inactive)'}`,
                                    }))}
                                    placeholder="Search and select a product…"
                                    error={errors.product_id}
                                    inputClassName="h-9"
                                />
                                {errors.product_id && (
                                    <p className="mt-1 text-xs text-red-600">
                                        {errors.product_id}
                                    </p>
                                )}
                            </div>

                            {/* Most units one customer can order at once (all variants together) */}
                            <div>
                                <label className="mb-1 block text-sm font-medium text-slate-700">
                                    Most items per order
                                </label>
                                <input
                                    type="number"
                                    min={1}
                                    max={100}
                                    className={inputClass}
                                    value={data.max_order_quantity}
                                    onChange={(e) =>
                                        setData(
                                            'max_order_quantity',
                                            Number(e.target.value) || 1,
                                        )
                                    }
                                />
                                <p className="mt-1 text-xs text-slate-400">
                                    The most units a customer can buy in one
                                    order from this page, all options together
                                    (1 to 100). Cash-on-delivery orders are also
                                    limited per phone number by the shop's
                                    pending-order rule.
                                </p>
                                {errors.max_order_quantity && (
                                    <p className="mt-1 text-xs text-red-600">
                                        {errors.max_order_quantity}
                                    </p>
                                )}
                            </div>

                            <div className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    id="is_active"
                                    checked={data.is_active}
                                    onChange={(e) =>
                                        setData('is_active', e.target.checked)
                                    }
                                    className="rounded border-slate-300"
                                />
                                <label
                                    htmlFor="is_active"
                                    className="text-sm font-medium text-slate-700"
                                >
                                    Active (publicly accessible)
                                </label>
                            </div>

                            <div className="flex gap-2 pt-2">
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="bg-[#c8102e] text-white hover:bg-[#a50d25]"
                                >
                                    {processing ? 'Creating…' : 'Create Page'}
                                </Button>
                                <Link href={route('admin.landing-pages.index')}>
                                    <Button type="button" variant="outline">
                                        Cancel
                                    </Button>
                                </Link>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </RouteModal>
        </>
    );
}
