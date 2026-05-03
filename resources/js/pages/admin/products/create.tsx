import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type Props = {
    categories: { id: number; name: string }[];
    brands: { id: number; name: string }[];
};

export default function ProductCreate({ categories, brands }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        slug: '',
        sku: '',
        short_description: '',
        description: '',
        category_id: '',
        brand_id: '',
        price: '',
        compare_price: '',
        cost_price: '',
        stock_quantity: '0',
        low_stock_threshold: '5',
        is_active: true,
        is_featured: false,
        images: [''],
        meta_title: '',
        meta_description: '',
    });

    const slugify = (name: string) =>
        name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('admin.products.store'));
    };

    const Field = ({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) => (
        <div>
            <label className="mb-1 block text-sm font-medium text-slate-700">{label}</label>
            {children}
            {error && <p className="mt-1 text-xs text-red-500">{error}</p>}
        </div>
    );

    const inputClass = (err?: string) =>
        `h-9 w-full rounded-lg border px-3 text-sm focus:outline-none focus:ring-1 ${err ? 'border-red-400 focus:ring-red-400' : 'border-slate-200 focus:ring-[#c8102e]'}`;

    return (
        <>
            <Head title="Add Product — Admin" />
            <div className="space-y-5">
                <div className="flex items-center gap-3">
                    <Link href={route('admin.products.index')} className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                        <ArrowLeft className="size-4" />
                    </Link>
                    <div>
                        <h1 className="text-xl font-bold text-slate-900">Add Product</h1>
                        <p className="text-sm text-slate-500">Fill in the details below</p>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="grid gap-5 lg:grid-cols-[1fr_320px]">
                    <div className="space-y-5">
                        <Card className="shadow-sm">
                            <CardHeader className="border-b border-slate-100 pb-4">
                                <CardTitle className="text-sm font-semibold text-slate-700">Basic Info</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4 pt-4">
                                <Field label="Product Name *" error={errors.name}>
                                    <input
                                        className={inputClass(errors.name)}
                                        value={data.name}
                                        onChange={(e) => {
                                            setData('name', e.target.value);
                                            if (!data.slug) setData('slug', slugify(e.target.value));
                                        }}
                                    />
                                </Field>
                                <Field label="Slug *" error={errors.slug}>
                                    <input className={inputClass(errors.slug)} value={data.slug} onChange={(e) => setData('slug', e.target.value)} />
                                </Field>
                                <Field label="SKU" error={errors.sku}>
                                    <input className={inputClass(errors.sku)} value={data.sku} onChange={(e) => setData('sku', e.target.value)} />
                                </Field>
                                <Field label="Short Description" error={errors.short_description}>
                                    <textarea
                                        rows={2}
                                        className={`w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-1 ${errors.short_description ? 'border-red-400 focus:ring-red-400' : 'border-slate-200 focus:ring-[#c8102e]'}`}
                                        value={data.short_description}
                                        onChange={(e) => setData('short_description', e.target.value)}
                                    />
                                </Field>
                                <Field label="Description" error={errors.description}>
                                    <textarea
                                        rows={5}
                                        className={`w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-1 ${errors.description ? 'border-red-400 focus:ring-red-400' : 'border-slate-200 focus:ring-[#c8102e]'}`}
                                        value={data.description}
                                        onChange={(e) => setData('description', e.target.value)}
                                    />
                                </Field>
                            </CardContent>
                        </Card>

                        <Card className="shadow-sm">
                            <CardHeader className="border-b border-slate-100 pb-4">
                                <CardTitle className="text-sm font-semibold text-slate-700">Pricing & Stock</CardTitle>
                            </CardHeader>
                            <CardContent className="grid grid-cols-2 gap-4 pt-4 sm:grid-cols-3">
                                <Field label="Price (৳) *" error={errors.price}>
                                    <input type="number" min="0" step="0.01" className={inputClass(errors.price)} value={data.price} onChange={(e) => setData('price', e.target.value)} />
                                </Field>
                                <Field label="Compare Price (৳)" error={errors.compare_price}>
                                    <input type="number" min="0" step="0.01" className={inputClass(errors.compare_price)} value={data.compare_price} onChange={(e) => setData('compare_price', e.target.value)} />
                                </Field>
                                <Field label="Cost Price (৳)" error={errors.cost_price}>
                                    <input type="number" min="0" step="0.01" className={inputClass(errors.cost_price)} value={data.cost_price} onChange={(e) => setData('cost_price', e.target.value)} />
                                </Field>
                                <Field label="Stock Qty *" error={errors.stock_quantity}>
                                    <input type="number" min="0" className={inputClass(errors.stock_quantity)} value={data.stock_quantity} onChange={(e) => setData('stock_quantity', e.target.value)} />
                                </Field>
                                <Field label="Low Stock Alert" error={errors.low_stock_threshold}>
                                    <input type="number" min="0" className={inputClass(errors.low_stock_threshold)} value={data.low_stock_threshold} onChange={(e) => setData('low_stock_threshold', e.target.value)} />
                                </Field>
                            </CardContent>
                        </Card>

                        <Card className="shadow-sm">
                            <CardHeader className="border-b border-slate-100 pb-4">
                                <CardTitle className="text-sm font-semibold text-slate-700">Images (URLs)</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-2 pt-4">
                                {data.images.map((url, i) => (
                                    <div key={i} className="flex gap-2">
                                        <input
                                            type="url"
                                            placeholder={i === 0 ? 'Primary image URL' : `Image ${i + 1} URL`}
                                            className={inputClass(errors[`images.${i}` as keyof typeof errors])}
                                            value={url}
                                            onChange={(e) => {
                                                const imgs = [...data.images];
                                                imgs[i] = e.target.value;
                                                setData('images', imgs);
                                            }}
                                        />
                                        {i > 0 && (
                                            <button type="button" onClick={() => setData('images', data.images.filter((_, j) => j !== i))} className="shrink-0 rounded-lg border border-slate-200 px-2 text-slate-400 hover:border-red-200 hover:text-red-500">×</button>
                                        )}
                                    </div>
                                ))}
                                <button type="button" onClick={() => setData('images', [...data.images, ''])} className="text-xs font-medium text-[#c8102e] hover:underline">+ Add image</button>
                            </CardContent>
                        </Card>

                        <Card className="shadow-sm">
                            <CardHeader className="border-b border-slate-100 pb-4">
                                <CardTitle className="text-sm font-semibold text-slate-700">SEO</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4 pt-4">
                                <Field label="Meta Title" error={errors.meta_title}>
                                    <input className={inputClass(errors.meta_title)} value={data.meta_title} onChange={(e) => setData('meta_title', e.target.value)} />
                                </Field>
                                <Field label="Meta Description" error={errors.meta_description}>
                                    <textarea rows={2} className={`w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-1 border-slate-200 focus:ring-[#c8102e]`} value={data.meta_description} onChange={(e) => setData('meta_description', e.target.value)} />
                                </Field>
                            </CardContent>
                        </Card>
                    </div>

                    <div className="space-y-5">
                        <Card className="shadow-sm">
                            <CardHeader className="border-b border-slate-100 pb-4">
                                <CardTitle className="text-sm font-semibold text-slate-700">Organisation</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4 pt-4">
                                <Field label="Category" error={errors.category_id}>
                                    <select className={`h-9 w-full rounded-lg border px-3 text-sm focus:outline-none focus:ring-1 ${errors.category_id ? 'border-red-400' : 'border-slate-200 focus:ring-[#c8102e]'}`} value={data.category_id} onChange={(e) => setData('category_id', e.target.value)}>
                                        <option value="">— Select —</option>
                                        {categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                                    </select>
                                </Field>
                                <Field label="Brand" error={errors.brand_id}>
                                    <select className="h-9 w-full rounded-lg border border-slate-200 px-3 text-sm focus:outline-none focus:ring-1 focus:ring-[#c8102e]" value={data.brand_id} onChange={(e) => setData('brand_id', e.target.value)}>
                                        <option value="">— None —</option>
                                        {brands.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}
                                    </select>
                                </Field>
                            </CardContent>
                        </Card>

                        <Card className="shadow-sm">
                            <CardHeader className="border-b border-slate-100 pb-4">
                                <CardTitle className="text-sm font-semibold text-slate-700">Visibility</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3 pt-4">
                                <label className="flex items-center gap-2.5 text-sm text-slate-700 cursor-pointer">
                                    <input type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} className="size-4 rounded border-slate-300 accent-[#c8102e]" />
                                    Active (visible in store)
                                </label>
                                <label className="flex items-center gap-2.5 text-sm text-slate-700 cursor-pointer">
                                    <input type="checkbox" checked={data.is_featured} onChange={(e) => setData('is_featured', e.target.checked)} className="size-4 rounded border-slate-300 accent-[#c8102e]" />
                                    Featured
                                </label>
                            </CardContent>
                        </Card>

                        <div className="flex flex-col gap-2">
                            <Button type="submit" disabled={processing} className="w-full bg-[#c8102e] text-white hover:bg-[#a50d25]">
                                {processing ? 'Saving…' : 'Create Product'}
                            </Button>
                            <Link href={route('admin.products.index')} className="text-center text-sm text-slate-500 hover:underline">Cancel</Link>
                        </div>
                    </div>
                </form>
            </div>
        </>
    );
}
