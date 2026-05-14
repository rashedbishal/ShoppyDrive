import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type Product = { id: number; name: string; price: number };
type Props = { products: Product[] };

export default function LandingPageCreate({ products }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        title: '',
        slug: '',
        product_id: '',
        is_active: true,
    });

    const inputClass = 'h-9 w-full rounded-lg border border-slate-200 px-3 text-sm focus:outline-none focus:ring-1 focus:ring-[#c8102e]';

    const autoSlug = (title: string) =>
        title.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');

    return (
        <>
            <Head title="New Landing Page — Admin" />
            <div className="max-w-lg space-y-5">
                <div className="flex items-center gap-3">
                    <Link href={route('admin.landing-pages.index')} className="text-slate-400 hover:text-slate-700 transition-colors">
                        <ArrowLeft className="size-5" />
                    </Link>
                    <h1 className="text-xl font-bold text-slate-900">New Landing Page</h1>
                </div>

                <Card className="shadow-sm">
                    <CardHeader className="border-b border-slate-100 pb-4">
                        <CardTitle className="text-sm font-semibold text-slate-700">Basic Info</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4 pt-4">
                        <form onSubmit={(e) => { e.preventDefault(); post(route('admin.landing-pages.store')); }} className="space-y-4">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-slate-700">Page Title</label>
                                <input
                                    className={inputClass}
                                    value={data.title}
                                    onChange={(e) => {
                                        setData('title', e.target.value);
                                        if (!data.slug) setData('slug', autoSlug(e.target.value));
                                    }}
                                    placeholder="e.g. Special Offer on Product X"
                                />
                                {errors.title && <p className="mt-1 text-xs text-red-600">{errors.title}</p>}
                            </div>

                            <div>
                                <label className="mb-1 block text-sm font-medium text-slate-700">URL Slug</label>
                                <div className="flex items-center rounded-lg border border-slate-200 focus-within:ring-1 focus-within:ring-[#c8102e] overflow-hidden">
                                    <span className="bg-slate-50 px-3 text-sm text-slate-400 border-r border-slate-200 h-9 flex items-center shrink-0">/lp/</span>
                                    <input
                                        className="flex-1 h-9 px-3 text-sm focus:outline-none font-mono"
                                        value={data.slug}
                                        onChange={(e) => setData('slug', e.target.value.toLowerCase().replace(/[^a-z0-9\-]/g, ''))}
                                        placeholder="my-product-offer"
                                    />
                                </div>
                                {errors.slug && <p className="mt-1 text-xs text-red-600">{errors.slug}</p>}
                                <p className="mt-1 text-xs text-slate-400">Only lowercase letters, numbers, and hyphens.</p>
                            </div>

                            <div>
                                <label className="mb-1 block text-sm font-medium text-slate-700">Product</label>
                                <select
                                    className={inputClass}
                                    value={data.product_id}
                                    onChange={(e) => setData('product_id', e.target.value)}
                                >
                                    <option value="">Select a product…</option>
                                    {products.map((p) => (
                                        <option key={p.id} value={p.id}>{p.name} — ৳{p.price.toLocaleString()}</option>
                                    ))}
                                </select>
                                {errors.product_id && <p className="mt-1 text-xs text-red-600">{errors.product_id}</p>}
                            </div>

                            <div className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    id="is_active"
                                    checked={data.is_active}
                                    onChange={(e) => setData('is_active', e.target.checked)}
                                    className="rounded border-slate-300"
                                />
                                <label htmlFor="is_active" className="text-sm font-medium text-slate-700">Active (publicly accessible)</label>
                            </div>

                            <div className="flex gap-2 pt-2">
                                <Button type="submit" disabled={processing} className="bg-[#c8102e] text-white hover:bg-[#a50d25]">
                                    {processing ? 'Creating…' : 'Create & Edit Sections'}
                                </Button>
                                <Link href={route('admin.landing-pages.index')}>
                                    <Button type="button" variant="outline">Cancel</Button>
                                </Link>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
