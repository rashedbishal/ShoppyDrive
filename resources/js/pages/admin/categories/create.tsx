import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { CharCount } from '@/components/ui/char-count';
import { ColorPicker } from '@/components/ui/color-picker';
import { IconPicker } from '@/components/ui/icon-picker';
import { ImageUpload } from '@/components/ui/image-upload';
import { RouteModal } from '@/components/ui/route-modal';
import { scrollToFirstFormError } from '@/lib/form-error-scroll';
import { maxLength, required, validate } from '@/lib/validate';

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
            {hint && <p className="mb-1.5 text-xs text-slate-400">{hint}</p>}
            {children}
            {error && (
                <p data-form-error className="mt-1 text-xs text-red-500">
                    {error}
                </p>
            )}
        </div>
    );
}

export default function CategoryCreate() {
    const { data, setData, post, processing, errors, setError, clearErrors } =
        useForm({
            name: '',
            slug: '',
            description: '',
            meta_title: '',
            meta_description: '',
            image: null as File | string | null,
            icon: 'shopping-bag',
            color: '',
            is_active: true,
        });

    const slugify = (name: string) =>
        name
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/(^-|-$)/g, '');

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        clearErrors();
        const clientErrors = validate(data, {
            name: [required(), maxLength(80)],
            slug: [required(), maxLength(100)],
        });

        if (Object.keys(clientErrors).length > 0) {
            setError(clientErrors);
            scrollToFirstFormError();

            return;
        }

        post(route('admin.categories.store'));
    };

    const inputClass = (err?: string) =>
        `h-9 w-full rounded-lg border px-3 text-sm focus:outline-none focus:ring-1 ${err ? 'border-red-400 focus:ring-red-400' : 'border-slate-200 focus:ring-[#c8102e]'}`;

    return (
        <>
            <Head title="Add Category — Admin" />
            <RouteModal
                title="Add Category"
                description="Top-level category"
                returnTo={route('admin.categories.index')}
                className="sm:max-w-xl"
            >
                <form onSubmit={handleSubmit}>
                    <Card className="shadow-sm">
                        <CardHeader className="border-b border-slate-100">
                            <CardTitle className="text-sm font-semibold text-slate-700">
                                Category Details
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 pt-3">
                            <Field label="Name *" error={errors.name}>
                                <input
                                    maxLength={80}
                                    className={inputClass(errors.name)}
                                    value={data.name}
                                    placeholder="e.g. Electronics & Gadgets"
                                    onChange={(e) => {
                                        setData((prev) => ({
                                            ...prev,
                                            name: e.target.value,
                                            slug: slugify(e.target.value),
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
                                    placeholder="e.g. electronics-gadgets"
                                    onChange={(e) =>
                                        setData('slug', e.target.value)
                                    }
                                />
                                <CharCount
                                    value={data.slug}
                                    max={100}
                                    className="mt-1 block text-right"
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
                                    placeholder="Short description shown in the storefront"
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

                            <Field
                                label="Meta Title"
                                hint="Shown in Google. Leave blank to use the name."
                                error={errors.meta_title}
                            >
                                <input
                                    maxLength={70}
                                    className={inputClass(errors.meta_title)}
                                    value={data.meta_title}
                                    onChange={(e) =>
                                        setData('meta_title', e.target.value)
                                    }
                                />
                                <CharCount
                                    value={data.meta_title}
                                    max={70}
                                    className="mt-1 block text-right"
                                />
                            </Field>

                            <Field
                                label="Meta Description"
                                hint="Shown in Google. Leave blank to use the description."
                                error={errors.meta_description}
                            >
                                <textarea
                                    maxLength={160}
                                    rows={2}
                                    className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-1 focus:ring-[#c8102e] focus:outline-none"
                                    value={data.meta_description}
                                    onChange={(e) =>
                                        setData(
                                            'meta_description',
                                            e.target.value,
                                        )
                                    }
                                />
                                <CharCount
                                    value={data.meta_description}
                                    max={160}
                                    className="mt-1 block text-right"
                                />
                            </Field>

                            <Field
                                label="Image"
                                hint="Used on the category page banner"
                            >
                                <ImageUpload
                                    value={data.image}
                                    onChange={(file) => setData('image', file)}
                                    error={errors.image}
                                    maxSizeMb={5}
                                    previewClassName="h-32 object-contain"
                                />
                            </Field>

                            <Field
                                label="Icon"
                                hint="Shown in the homepage 'Shop by category' section"
                                error={errors.icon}
                            >
                                <IconPicker
                                    value={data.icon}
                                    onChange={(icon) => setData('icon', icon)}
                                    error={errors.icon}
                                />
                            </Field>

                            <Field
                                label="Color"
                                hint="Badge color for the homepage 'Shop by category' section"
                                error={errors.color}
                            >
                                <ColorPicker
                                    value={data.color || null}
                                    onChange={(color) =>
                                        setData('color', color ?? '')
                                    }
                                    error={errors.color}
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
                                Active (visible in storefront)
                            </label>

                            <div className="flex gap-2 pt-2">
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="bg-[#c8102e] text-white hover:bg-[#a50d25]"
                                >
                                    {processing ? 'Saving…' : 'Create Category'}
                                </Button>
                                <Link
                                    href={route('admin.categories.index')}
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
