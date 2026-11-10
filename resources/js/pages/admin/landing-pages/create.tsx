import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { RouteModal } from '@/components/ui/route-modal';
import { Select } from '@/components/ui/select';
import { maxLength, pattern, required, validate } from '@/lib/validate';

type Product = { id: number; name: string; price: number };
type Props = { products: Product[] };

export default function LandingPageCreate({ products }: Props) {
    const { data, setData, post, processing, errors, setError, clearErrors } =
        useForm({
            title: '',
            slug: '',
            product_id: '',
            is_active: true,
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
                                    title: [required(), maxLength(255)],
                                    slug: [
                                        required(),
                                        maxLength(255),
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
                                        /sentinel/
                                    </span>
                                    <input
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
                                <Select
                                    selectClassName={inputClass}
                                    value={data.product_id}
                                    onChange={(e) =>
                                        setData('product_id', e.target.value)
                                    }
                                >
                                    <option value="">Select a product…</option>
                                    {products.map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {p.name} — ৳
                                            {p.price.toLocaleString()}
                                        </option>
                                    ))}
                                </Select>
                                {errors.product_id && (
                                    <p className="mt-1 text-xs text-red-600">
                                        {errors.product_id}
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
                                    {processing
                                        ? 'Creating…'
                                        : 'Create & Edit Sections'}
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
