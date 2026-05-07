import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, FolderOpen } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ImageUpload } from '@/components/ui/image-upload';

type Category = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    image: string | null;
    is_active: boolean;
    sort_order: number;
    sub_categories_count: number;
    direct_products_count: number;
};

type Props = { category: Category };

export default function CategoryEdit({ category }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        _method: 'PUT',
        name: category.name,
        slug: category.slug,
        description: category.description ?? '',
        image: category.image as File | string | null,
        is_active: category.is_active,
        sort_order: String(category.sort_order),
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('admin.categories.update', category.id), {
            forceFormData: true,
        });
    };

    const Field = ({ label, hint, error, children }: { label: string; hint?: string; error?: string; children: React.ReactNode }) => (
        <div>
            <label className="mb-1 block text-sm font-medium text-slate-700">{label}</label>
            {hint && <p className="mb-1.5 text-xs text-slate-400">{hint}</p>}
            {children}
            {error && <p className="mt-1 text-xs text-red-500">{error}</p>}
        </div>
    );

    const inputClass = (err?: string) =>
        `h-9 w-full rounded-lg border px-3 text-sm focus:outline-none focus:ring-1 ${err ? 'border-red-400 focus:ring-red-400' : 'border-slate-200 focus:ring-[#c8102e]'}`;

    return (
        <>
            <Head title={`Edit ${category.name} — Admin`} />
            <div className="space-y-5">
                <div className="flex items-center gap-3">
                    <Link href={route('admin.categories.index')} className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                        <ArrowLeft className="size-4" />
                    </Link>
                    <div>
                        <h1 className="text-xl font-bold text-slate-900">Edit Category</h1>
                        <p className="text-sm text-slate-500">{category.name}</p>
                    </div>
                </div>

                {/* Stats strip */}
                <div className="flex gap-3">
                    <div className="flex items-center gap-2 rounded-xl border border-slate-100 bg-white px-4 py-2.5 shadow-sm">
                        <FolderOpen className="size-4 text-slate-400" />
                        <span className="text-sm font-semibold text-slate-700">{category.sub_categories_count}</span>
                        <span className="text-xs text-slate-400">sub-{category.sub_categories_count === 1 ? 'category' : 'categories'}</span>
                    </div>
                    <div className="flex items-center gap-2 rounded-xl border border-slate-100 bg-white px-4 py-2.5 shadow-sm">
                        <span className="text-sm font-semibold text-slate-700">{category.direct_products_count}</span>
                        <span className="text-xs text-slate-400">direct products</span>
                    </div>
                    <Link
                        href={`${route('admin.sub-categories.index')}?search=${encodeURIComponent(category.name)}`}
                        className="ml-auto flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-medium text-slate-600 shadow-sm hover:bg-slate-50"
                    >
                        View sub-categories →
                    </Link>
                </div>

                <form onSubmit={handleSubmit} className="max-w-xl">
                    <Card className="shadow-sm">
                        <CardHeader className="border-b border-slate-100 pb-4">
                            <CardTitle className="text-sm font-semibold text-slate-700">Category Details</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 pt-4">
                            <Field label="Name *" error={errors.name}>
                                <input
                                    className={inputClass(errors.name)}
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                />
                            </Field>

                            <Field label="Slug *" hint="Changing the slug will break existing URLs" error={errors.slug}>
                                <input
                                    className={inputClass(errors.slug)}
                                    value={data.slug}
                                    onChange={(e) => setData('slug', e.target.value)}
                                />
                            </Field>

                            <Field label="Description" error={errors.description}>
                                <textarea
                                    rows={3}
                                    className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#c8102e]"
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                />
                            </Field>

                            <Field label="Image" error={errors.image}>
                                <ImageUpload value={data.image} onChange={(file) => setData('image', file)} error={errors.image} previewClassName="h-32 object-contain" />
                            </Field>

                            <Field label="Sort Order" hint="Lower number appears first" error={errors.sort_order}>
                                <input
                                    type="number"
                                    min="0"
                                    className={`${inputClass(errors.sort_order)} max-w-[120px]`}
                                    value={data.sort_order}
                                    onChange={(e) => setData('sort_order', e.target.value)}
                                />
                            </Field>

                            <label className="flex cursor-pointer items-center gap-2.5 text-sm text-slate-700">
                                <input
                                    type="checkbox"
                                    checked={data.is_active}
                                    onChange={(e) => setData('is_active', e.target.checked)}
                                    className="size-4 rounded border-slate-300 accent-[#c8102e]"
                                />
                                Active (visible in storefront)
                            </label>

                            <div className="flex gap-2 pt-2">
                                <Button type="submit" disabled={processing} className="bg-[#c8102e] text-white hover:bg-[#a50d25]">
                                    {processing ? 'Saving…' : 'Save Changes'}
                                </Button>
                                <Link href={route('admin.categories.index')} className="inline-flex items-center rounded-lg border border-slate-200 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">
                                    Cancel
                                </Link>
                            </div>
                        </CardContent>
                    </Card>
                </form>
            </div>
        </>
    );
}
