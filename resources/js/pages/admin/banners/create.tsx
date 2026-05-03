import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

export default function BannerCreate() {
    const { data, setData, post, processing, errors } = useForm({
        title: '',
        subtitle: '',
        image: '',
        link: '',
        sort_order: '0',
        is_active: true,
        starts_at: '',
        expires_at: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('admin.banners.store'));
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
            <Head title="Add Banner — Admin" />
            <div className="space-y-5">
                <div className="flex items-center gap-3">
                    <Link href={route('admin.banners.index')} className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                        <ArrowLeft className="size-4" />
                    </Link>
                    <h1 className="text-xl font-bold text-slate-900">Add Banner</h1>
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
                            <Field label="Image URL *" error={errors.image}>
                                <input type="url" className={inputClass(errors.image)} value={data.image} onChange={(e) => setData('image', e.target.value)} />
                                {data.image && <img src={data.image} alt="Preview" className="mt-2 h-24 w-full rounded-lg object-cover border border-slate-100" onError={(e) => { (e.target as HTMLImageElement).style.display = 'none'; }} />}
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
                                    {processing ? 'Saving…' : 'Create Banner'}
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
