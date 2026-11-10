import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ImageUpload } from '@/components/ui/image-upload';
import { RouteModal } from '@/components/ui/route-modal';
import { Select } from '@/components/ui/select';
import {
    maxLength,
    minValue,
    numeric,
    required,
    validate,
} from '@/lib/validate';

type Props = {
    categories: { id: number; name: string }[];
};

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
            {error && <p className="mt-1 text-xs text-red-500">{error}</p>}
        </div>
    );
}

export default function SubCategoryCreate({ categories }: Props) {
    const { data, setData, post, processing, errors, setError, clearErrors } =
        useForm({
            name: '',
            slug: '',
            category_id: '',
            description: '',
            meta_title: '',
            meta_description: '',
            image: null as File | string | null,
            is_active: true,
            sort_order: '0',
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
            name: [required(), maxLength(255)],
            slug: [required(), maxLength(255)],
            category_id: [required()],
            sort_order: [numeric(), minValue(0)],
        });

        if (Object.keys(clientErrors).length > 0) {
            setError(clientErrors);

            return;
        }

        post(route('admin.sub-categories.store'));
    };

    const inputClass = (err?: string) =>
        `h-9 w-full rounded-lg border px-3 text-sm focus:outline-none focus:ring-1 ${err ? 'border-red-400 focus:ring-red-400' : 'border-slate-200 focus:ring-[#c8102e]'}`;

    return (
        <>
            <Head title="Add Sub-category — Admin" />
            <RouteModal
                title="Add Sub-category"
                returnTo={route('admin.sub-categories.index')}
                className="sm:max-w-xl"
            >
                <form onSubmit={handleSubmit}>
                    <Card className="shadow-sm">
                        <CardHeader className="border-b border-slate-100">
                            <CardTitle className="text-sm font-semibold text-slate-700">
                                Sub-category Details
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 pt-3">
                            <Field label="Name *" error={errors.name}>
                                <input
                                    className={inputClass(errors.name)}
                                    value={data.name}
                                    onChange={(e) => {
                                        setData((prev) => ({
                                            ...prev,
                                            name: e.target.value,
                                            slug: slugify(e.target.value),
                                        }));
                                    }}
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
                            <Field
                                label="Category *"
                                error={errors.category_id}
                            >
                                <Select
                                    selectClassName="h-9 w-full rounded-lg border border-slate-200 px-3 text-sm focus:ring-1 focus:ring-[#c8102e] focus:outline-none"
                                    value={data.category_id}
                                    onChange={(e) =>
                                        setData('category_id', e.target.value)
                                    }
                                >
                                    <option value="">
                                        — Select Category —
                                    </option>
                                    {categories.map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {p.name}
                                        </option>
                                    ))}
                                </Select>
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

                            <Field
                                label="Meta Title"
                                hint="Shown in Google. Leave blank to use the name."
                                error={errors.meta_title}
                            >
                                <input
                                    className={inputClass(errors.meta_title)}
                                    value={data.meta_title}
                                    onChange={(e) =>
                                        setData('meta_title', e.target.value)
                                    }
                                />
                            </Field>

                            <Field
                                label="Meta Description"
                                hint="Shown in Google. Leave blank to use the description."
                                error={errors.meta_description}
                            >
                                <textarea
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
                            </Field>
                            <Field label="Image">
                                <ImageUpload
                                    value={data.image}
                                    onChange={(file) => setData('image', file)}
                                    error={errors.image}
                                    maxSizeMb={5}
                                    previewClassName="h-32 object-contain"
                                />
                            </Field>
                            <Field label="Sort Order" error={errors.sort_order}>
                                <input
                                    type="number"
                                    min="0"
                                    className={inputClass(errors.sort_order)}
                                    value={data.sort_order}
                                    onChange={(e) =>
                                        setData('sort_order', e.target.value)
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
                                    {processing
                                        ? 'Saving…'
                                        : 'Create Sub-category'}
                                </Button>
                                <Link
                                    href={route('admin.sub-categories.index')}
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
