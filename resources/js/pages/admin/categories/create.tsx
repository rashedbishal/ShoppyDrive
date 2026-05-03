import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type Props = {
    parents: { id: number; name: string }[];
};

export default function CategoryCreate({ parents }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        slug: '',
        parent_id: '',
        description: '',
        image: '',
        is_active: true,
        sort_order: '0',
    });

    const slugify = (name: string) =>
        name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('admin.categories.store'));
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
            <Head title="Add Category — Admin" />
            <div className="space-y-5">
                <div className="flex items-center gap-3">
                    <Link href={route('admin.categories.index')} className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                        <ArrowLeft className="size-4" />
                    </Link>
                    <h1 className="text-xl font-bold text-slate-900">Add Category</h1>
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
                                    onChange={(e) => {
                                        setData('name', e.target.value);
                                        if (!data.slug) setData('slug', slugify(e.target.value));
                                    }}
                                />
                            </Field>
                            <Field label="Slug *" error={errors.slug}>
                                <input className={inputClass(errors.slug)} value={data.slug} onChange={(e) => setData('slug', e.target.value)} />
                            </Field>
                            <Field label="Parent Category" error={errors.parent_id}>
                                <select className="h-9 w-full rounded-lg border border-slate-200 px-3 text-sm focus:outline-none focus:ring-1 focus:ring-[#c8102e]" value={data.parent_id} onChange={(e) => setData('parent_id', e.target.value)}>
                                    <option value="">— None (top-level) —</option>
                                    {parents.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                                </select>
                            </Field>
                            <Field label="Description" error={errors.description}>
                                <textarea rows={3} className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#c8102e]" value={data.description} onChange={(e) => setData('description', e.target.value)} />
                            </Field>
                            <Field label="Image URL" error={errors.image}>
                                <input type="url" className={inputClass(errors.image)} value={data.image} onChange={(e) => setData('image', e.target.value)} />
                            </Field>
                            <Field label="Sort Order" error={errors.sort_order}>
                                <input type="number" min="0" className={inputClass(errors.sort_order)} value={data.sort_order} onChange={(e) => setData('sort_order', e.target.value)} />
                            </Field>
                            <label className="flex cursor-pointer items-center gap-2.5 text-sm text-slate-700">
                                <input type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} className="size-4 rounded border-slate-300 accent-[#c8102e]" />
                                Active
                            </label>
                            <div className="flex gap-2 pt-2">
                                <Button type="submit" disabled={processing} className="bg-[#c8102e] text-white hover:bg-[#a50d25]">
                                    {processing ? 'Saving…' : 'Create Category'}
                                </Button>
                                <Link href={route('admin.categories.index')} className="inline-flex items-center rounded-lg border border-slate-200 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Cancel</Link>
                            </div>
                        </CardContent>
                    </Card>
                </form>
            </div>
        </>
    );
}
