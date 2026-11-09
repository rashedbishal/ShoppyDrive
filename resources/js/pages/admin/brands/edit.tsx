import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ImageUpload } from '@/components/ui/image-upload';
import { RouteModal } from '@/components/ui/route-modal';
import { maxLength, required, validate } from '@/lib/validate';

type Brand = {
    id: number;
    name: string;
    slug: string;
    logo: string | null;
    description: string | null;
    is_active: boolean;
};

type Props = { brand: Brand };

function Field({
    label,
    error,
    children,
}: {
    label: string;
    error?: string;
    children: React.ReactNode;
}) {
    return (
        <div>
            <label className="mb-1 block text-sm font-medium text-slate-700">
                {label}
            </label>
            {children}
            {error && <p className="mt-1 text-xs text-red-500">{error}</p>}
        </div>
    );
}

export default function BrandEdit({ brand }: Props) {
    const { data, setData, post, processing, errors, setError, clearErrors } =
        useForm({
            _method: 'PUT',
            name: brand.name,
            slug: brand.slug,
            logo: brand.logo as File | string | null,
            description: brand.description ?? '',
            is_active: brand.is_active,
        });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        clearErrors();
        const clientErrors = validate(data, {
            name: [required(), maxLength(255)],
            slug: [required(), maxLength(255)],
        });

        if (Object.keys(clientErrors).length > 0) {
            setError(clientErrors);

            return;
        }

        post(route('admin.brands.update', brand.id), {
            forceFormData: true,
        });
    };

    const inputClass = (err?: string) =>
        `h-9 w-full rounded-lg border px-3 text-sm focus:outline-none focus:ring-1 ${err ? 'border-red-400 focus:ring-red-400' : 'border-slate-200 focus:ring-[#c8102e]'}`;

    return (
        <>
            <Head title={`Edit ${brand.name} — Admin`} />
            <RouteModal
                title="Edit Brand"
                description={brand.name}
                returnTo={route('admin.brands.index')}
                className="sm:max-w-xl"
            >
                <form onSubmit={handleSubmit}>
                    <Card className="shadow-sm">
                        <CardHeader className="border-b border-slate-100 pb-4">
                            <CardTitle className="text-sm font-semibold text-slate-700">
                                Brand Details
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 pt-4">
                            <Field label="Name *" error={errors.name}>
                                <input
                                    className={inputClass(errors.name)}
                                    value={data.name}
                                    onChange={(e) =>
                                        setData('name', e.target.value)
                                    }
                                />
                            </Field>
                            <Field label="Slug *" error={errors.slug}>
                                <input
                                    className={inputClass(errors.slug)}
                                    value={data.slug}
                                    onChange={(e) =>
                                        setData('slug', e.target.value)
                                    }
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
                                    rows={3}
                                    className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-1 focus:ring-[#c8102e] focus:outline-none"
                                    value={data.description}
                                    onChange={(e) =>
                                        setData('description', e.target.value)
                                    }
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
                                    {processing ? 'Saving…' : 'Save Changes'}
                                </Button>
                                <Link
                                    href={route('admin.brands.index')}
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
