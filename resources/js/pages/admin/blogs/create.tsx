import { Head, Link, useForm } from '@inertiajs/react';
import { RichTextEditor } from '@/components/admin/RichTextEditor';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ImageUpload } from '@/components/ui/image-upload';
import { RouteModal } from '@/components/ui/route-modal';
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
            {error && <p className="mt-1 text-xs text-red-500">{error}</p>}
        </div>
    );
}

const inputClass = (err?: string) =>
    `h-9 w-full rounded-lg border px-3 text-sm focus:outline-none focus:ring-1 ${err ? 'border-red-400 focus:ring-red-400' : 'border-slate-200 focus:ring-[#c8102e]'}`;

export default function BlogCreate() {
    const { data, setData, post, processing, errors, setError, clearErrors } =
        useForm({
            title: '',
            slug: '',
            excerpt: '',
            content: '',
            featured_image: null as File | string | null,
            author_name: '',
            meta_title: '',
            meta_description: '',
            is_published: false,
            published_at: '',
        });

    const slugify = (title: string) =>
        title
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/(^-|-$)/g, '');

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        clearErrors();
        const clientErrors = validate(data, {
            title: [required(), maxLength(255)],
            slug: [required(), maxLength(255)],
            excerpt: [maxLength(500)],
            content: [required()],
            meta_title: [maxLength(255)],
            meta_description: [maxLength(500)],
        });

        if (Object.keys(clientErrors).length > 0) {
            setError(clientErrors);

            return;
        }

        post(route('admin.blogs.store'), { forceFormData: true });
    };

    return (
        <>
            <Head title="New Blog Post — Admin" />
            <RouteModal
                title="New Blog Post"
                description="Create a new blog post"
                returnTo={route('admin.blogs.index')}
                className="sm:max-w-2xl"
            >
                <form onSubmit={handleSubmit}>
                    <Card className="shadow-sm">
                        <CardHeader className="border-b border-slate-100 pb-4">
                            <CardTitle className="text-sm font-semibold text-slate-700">
                                Post Details
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 pt-4">
                            <div className="grid grid-cols-2 gap-4">
                                <Field label="Title *" error={errors.title}>
                                    <input
                                        className={inputClass(errors.title)}
                                        value={data.title}
                                        placeholder="e.g. 5 Tips for Smarter Online Shopping"
                                        onChange={(e) => {
                                            setData((prev) => ({
                                                ...prev,
                                                title: e.target.value,
                                                slug: slugify(e.target.value),
                                            }));
                                        }}
                                    />
                                </Field>

                                <Field
                                    label="Slug *"
                                    hint="Used in the URL — auto-generated from title"
                                    error={errors.slug}
                                >
                                    <input
                                        className={inputClass(errors.slug)}
                                        value={data.slug}
                                        placeholder="e.g. 5-tips-for-smarter-online-shopping"
                                        onChange={(e) =>
                                            setData('slug', e.target.value)
                                        }
                                    />
                                </Field>
                            </div>

                            <Field
                                label="Author Name"
                                error={errors.author_name}
                            >
                                <input
                                    className={inputClass(errors.author_name)}
                                    value={data.author_name}
                                    placeholder="e.g. ShoppyDrive Team"
                                    onChange={(e) =>
                                        setData('author_name', e.target.value)
                                    }
                                />
                            </Field>

                            <Field
                                label="Excerpt"
                                hint="Short summary shown on the blog listing"
                                error={errors.excerpt}
                            >
                                <textarea
                                    rows={2}
                                    className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-1 focus:ring-[#c8102e] focus:outline-none"
                                    value={data.excerpt}
                                    onChange={(e) =>
                                        setData('excerpt', e.target.value)
                                    }
                                />
                            </Field>

                            <Field
                                label="Featured Image"
                                hint="Shown on the blog listing and the post header"
                                error={errors.featured_image}
                            >
                                <ImageUpload
                                    value={data.featured_image}
                                    onChange={(file) =>
                                        setData('featured_image', file)
                                    }
                                    error={errors.featured_image}
                                    previewClassName="h-32 object-cover"
                                />
                            </Field>

                            <Field label="Content *" error={errors.content}>
                                <RichTextEditor
                                    value={data.content}
                                    onChange={(html) =>
                                        setData('content', html)
                                    }
                                />
                            </Field>

                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <Field
                                    label="Meta Title"
                                    hint="SEO title (optional)"
                                    error={errors.meta_title}
                                >
                                    <input
                                        className={inputClass(
                                            errors.meta_title,
                                        )}
                                        value={data.meta_title}
                                        onChange={(e) =>
                                            setData(
                                                'meta_title',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                <Field
                                    label="Published At"
                                    hint="Leave blank to publish immediately"
                                    error={errors.published_at}
                                >
                                    <input
                                        type="datetime-local"
                                        className={inputClass(
                                            errors.published_at,
                                        )}
                                        value={data.published_at}
                                        onChange={(e) =>
                                            setData(
                                                'published_at',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </Field>
                            </div>

                            <Field
                                label="Meta Description"
                                hint="SEO description (optional)"
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

                            <label className="flex cursor-pointer items-center gap-2.5 text-sm text-slate-700">
                                <input
                                    type="checkbox"
                                    checked={data.is_published}
                                    onChange={(e) =>
                                        setData(
                                            'is_published',
                                            e.target.checked,
                                        )
                                    }
                                    className="size-4 rounded border-slate-300 accent-[#c8102e]"
                                />
                                Published (visible on the storefront)
                            </label>

                            <div className="flex gap-2 pt-2">
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="bg-[#c8102e] text-white hover:bg-[#a50d25]"
                                >
                                    {processing
                                        ? 'Saving…'
                                        : 'Create Blog Post'}
                                </Button>
                                <Link
                                    href={route('admin.blogs.index')}
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
