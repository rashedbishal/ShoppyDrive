import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { CharCount } from '@/components/ui/char-count';
import { ImageUpload } from '@/components/ui/image-upload';
import { RouteModal } from '@/components/ui/route-modal';
import { useAdminListUrl } from '@/hooks/use-admin-list-url';
import { maxLength, required, validate } from '@/lib/validate';

const slugify = (name: string) =>
    name
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/(^-|-$)/g, '');

function Field({
    label,
    hint,
    error,
    children,
}: {
    label: string;
    hint?: string;
    error?: string;
    children: React.ReactNode;
}) {
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-slate-700">
                {label}
            </label>
            {hint && <p className="mb-1.5 text-xs text-slate-600">{hint}</p>}
            {children}
            {error && <p className="mt-1 text-xs text-red-500">{error}</p>}
        </div>
    );
}

const inputClass = (err?: string) =>
    `h-9 w-full rounded-lg border px-3 text-sm focus:outline-none focus:ring-1 ${err ? 'border-red-400 focus:ring-red-400' : 'border-slate-200 focus:ring-[#c8102e]'}`;

export default function BrandCreate() {
    const listUrl = useAdminListUrl('admin.brands.index');
    const { data, setData, post, processing, errors, setError, clearErrors } =
        useForm({
            name: '',
            slug: '',
            logo: null as File | string | null,
            description: '',
            is_active: true,
        });
    const [slugTouched, setSlugTouched] = useState(false);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        clearErrors();
        const clientErrors = validate(data, {
            name: [required(), maxLength(80)],
            slug: [required(), maxLength(100)],
        });

        if (Object.keys(clientErrors).length > 0) {
            setError(clientErrors);

            return;
        }

        post(route('admin.brands.store'));
    };

    return (
        <>
            <Head title="Add Brand — Admin" />
            <RouteModal
                title="Add Brand"
                returnTo={listUrl}
                className="sm:max-w-xl"
            >
                <form onSubmit={handleSubmit}>
                    <Card className="shadow-sm">
                        <CardHeader className="border-b border-slate-100">
                            <CardTitle className="text-sm font-semibold text-slate-700">
                                Brand Details
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 pt-3">
                            <Field label="Name *" error={errors.name}>
                                <input
                                    maxLength={80}
                                    className={inputClass(errors.name)}
                                    value={data.name}
                                    onChange={(e) => {
                                        const name = e.target.value;

                                        setData((prev) => ({
                                            ...prev,
                                            name,
                                            slug: slugTouched
                                                ? prev.slug
                                                : slugify(name),
                                        }));
                                    }}
                                />
                                <CharCount
                                    value={data.name}
                                    max={80}
                                    className="mt-1 block text-right"
                                />
                            </Field>
                            <Field
                                label="Slug *"
                                hint="Used in the URL — auto-generated from name"
                                error={errors.slug}
                            >
                                <input
                                    maxLength={100}
                                    className={inputClass(errors.slug)}
                                    value={data.slug}
                                    onChange={(e) => {
                                        setSlugTouched(true);
                                        setData('slug', e.target.value);
                                    }}
                                />
                                <CharCount
                                    value={data.slug}
                                    max={100}
                                    className="mt-1 block text-right"
                                />
                            </Field>
                            <Field label="Logo">
                                <ImageUpload
                                    value={data.logo}
                                    onChange={(file) => setData('logo', file)}
                                    error={errors.logo}
                                    previewClassName="h-32 object-contain"
                                    maxSizeMb={1}
                                    hint="Transparent PNG or WebP. Square logos: 500×500 px. Wide or tall logos: 500 px on the long side (e.g. 500×250). Crop away empty space around the logo."
                                />
                            </Field>
                            <Field
                                label="Description"
                                error={errors.description}
                            >
                                <textarea
                                    maxLength={2000}
                                    rows={3}
                                    className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-1 focus:ring-[#c8102e] focus:outline-none"
                                    value={data.description}
                                    onChange={(e) =>
                                        setData('description', e.target.value)
                                    }
                                />
                                <CharCount
                                    value={data.description}
                                    max={2000}
                                    className="mt-1 block text-right"
                                />
                            </Field>
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
                            <div className="flex gap-2 pt-2">
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="bg-[#c8102e] text-white hover:bg-[#a50d25]"
                                >
                                    {processing ? 'Saving…' : 'Create Brand'}
                                </Button>
                                <Link
                                    href={listUrl}
                                    className="inline-flex items-center rounded-lg border border-slate-200 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50"
                                >
                                    Cancel
                                </Link>
                            </div>
                        </CardContent>
                    </Card>
                </form>
            </RouteModal>
        </>
    );
}
