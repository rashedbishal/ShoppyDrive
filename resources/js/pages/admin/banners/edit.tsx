import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ImageUpload } from '@/components/ui/image-upload';

type Banner = {
    id: number;
    title: string;
    subtitle: string | null;
    image: string;
    link: string | null;
    sort_order: number;
    is_active: boolean;
    starts_at: string | null;
    expires_at: string | null;
};

type Props = { banner: Banner };

const toDatetimeLocal = (str: string | null) => {
    if (!str) return '';
    return str.slice(0, 16);
};

export default function BannerEdit({ banner }: Props) {
    const { data, setData, put, post, processing, errors } = useForm({
        title: banner.title,
        subtitle: banner.subtitle ?? '',
        image: banner.image as File | string | null,
        link: banner.link ?? '',
        sort_order: String(banner.sort_order),
        is_active: banner.is_active,
        starts_at: toDatetimeLocal(banner.starts_at),
        expires_at: toDatetimeLocal(banner.expires_at),
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (data.image instanceof File) {
            // Inertia will automatically use FormData if there is a File object, but we must use post or put with _method since HTML forms don't support PUT with files correctly in Laravel.
            // Wait, Laravel supports PUT/PATCH with files if spoofed, Inertia handles this automatically starting v1? 
            // Better to use post with _method: 'put' to be safe.
            post(route('admin.banners.update', banner.id), {
                forceFormData: true,
                _method: 'put',
            } as any);
        } else {
            put(route('admin.banners.update', banner.id));
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
            <Head title={`Edit Banner — Admin`} />
            <div className="space-y-5">
                <div className="flex items-center gap-3">
                    <Link href={route('admin.banners.index')} className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                        <ArrowLeft className="size-4" />
                    </Link>
                    <div>
                        <h1 className="text-xl font-bold text-slate-900">Edit Banner</h1>
                        <p className="text-sm text-slate-500">{banner.title}</p>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="max-w-2xl">
                    <Card className="shadow-sm">
                        <CardHeader className="border-b border-slate-100 pb-4">
                            <CardTitle className="text-sm font-semibold text-slate-700">Banner Details</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 pt-4">
                            <Field label="Title *" error={errors.title}>
                                <input className={inputClass(errors.title)} value={data.title} onChange={(e) => setData('title', e.target.value)} />
                            </Field>
                            <Field label="Subtitle" error={errors.subtitle}>
                                <input className={inputClass(errors.subtitle)} value={data.subtitle} onChange={(e) => setData('subtitle', e.target.value)} />
                            </Field>
                            <Field label="Image *" error={errors.image}>
                                <ImageUpload value={data.image} onChange={(file) => setData('image', file)} error={errors.image} previewClassName="h-32 object-cover" />
                            </Field>
                            <Field label="Link URL" error={errors.link}>
                                <input className={inputClass(errors.link)} value={data.link} onChange={(e) => setData('link', e.target.value)} placeholder="/shop or https://..." />
                            </Field>
                            <div className="grid grid-cols-2 gap-4">
                                <Field label="Sort Order" error={errors.sort_order}>
                                    <input type="number" min="0" className={inputClass(errors.sort_order)} value={data.sort_order} onChange={(e) => setData('sort_order', e.target.value)} />
                                </Field>
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <Field label="Starts At" error={errors.starts_at}>
                                    <input type="datetime-local" className={inputClass(errors.starts_at)} value={data.starts_at} onChange={(e) => setData('starts_at', e.target.value)} />
                                </Field>
                                <Field label="Expires At" error={errors.expires_at}>
                                    <input type="datetime-local" className={inputClass(errors.expires_at)} value={data.expires_at} onChange={(e) => setData('expires_at', e.target.value)} />
                                </Field>
                            </div>
                            <label className="flex cursor-pointer items-center gap-2.5 text-sm text-slate-700">
                                <input type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} className="size-4 rounded border-slate-300 accent-[#c8102e]" />
                                Active
                            </label>
                            <div className="flex gap-2 pt-2">
                                <Button type="submit" disabled={processing} className="bg-[#c8102e] text-white hover:bg-[#a50d25]">
                                    {processing ? 'Saving…' : 'Save Changes'}
                                </Button>
                                <Link href={route('admin.banners.index')} className="inline-flex items-center rounded-lg border border-slate-200 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Cancel</Link>
                            </div>
                        </CardContent>
                    </Card>
                </form>
            </div>
        </>
    );
}
