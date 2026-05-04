import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ImageUpload } from '@/components/ui/image-upload';

type Brand = {
    id: number;
    name: string;
    slug: string;
    logo: string | null;
    description: string | null;
    is_active: boolean;
};

type Props = { brand: Brand };

export default function BrandEdit({ brand }: Props) {
    const { data, setData, put, post, processing, errors } = useForm({
        name: brand.name,
        slug: brand.slug,
        logo: brand.logo as File | string | null,
        description: brand.description ?? '',
        is_active: brand.is_active,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (data.logo instanceof File) {
            post(route('admin.brands.update', brand.id), {
                forceFormData: true,
                _method: 'put',
            } as any);
        } else {
            put(route('admin.brands.update', brand.id));
        }
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
            <Head title={`Edit ${brand.name} — Admin`} />
            <div className="space-y-5">
                <div className="flex items-center gap-3">
                    <Link href={route('admin.brands.index')} className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                        <ArrowLeft className="size-4" />
                    </Link>
                    <div>
                        <h1 className="text-xl font-bold text-slate-900">Edit Brand</h1>
                        <p className="text-sm text-slate-500">{brand.name}</p>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="max-w-xl">
                    <Card className="shadow-sm">
                        <CardHeader className="border-b border-slate-100 pb-4">
                            <CardTitle className="text-sm font-semibold text-slate-700">Brand Details</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 pt-4">
                            <Field label="Name *" error={errors.name}>
                                <input className={inputClass(errors.name)} value={data.name} onChange={(e) => setData('name', e.target.value)} />
                            </Field>
                            <Field label="Slug *" error={errors.slug}>
                                <input className={inputClass(errors.slug)} value={data.slug} onChange={(e) => setData('slug', e.target.value)} />
                            </Field>
                            <Field label="Logo" error={errors.logo}>
                                <ImageUpload value={data.logo} onChange={(file) => setData('logo', file)} error={errors.logo} previewClassName="h-32 object-contain" />
                            </Field>
                            <Field label="Description" error={errors.description}>
                                <textarea rows={3} className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#c8102e]" value={data.description} onChange={(e) => setData('description', e.target.value)} />
                            </Field>
                            <label className="flex cursor-pointer items-center gap-2.5 text-sm text-slate-700">
                                <input type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} className="size-4 rounded border-slate-300 accent-[#c8102e]" />
                                Active
                            </label>
                            <div className="flex gap-2 pt-2">
                                <Button type="submit" disabled={processing} className="bg-[#c8102e] text-white hover:bg-[#a50d25]">
                                    {processing ? 'Saving…' : 'Save Changes'}
                                </Button>
                                <Link href={route('admin.brands.index')} className="inline-flex items-center rounded-lg border border-slate-200 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Cancel</Link>
                            </div>
                        </CardContent>
                    </Card>
                </form>
            </div>
        </>
    );
}
