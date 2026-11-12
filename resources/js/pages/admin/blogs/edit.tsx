import { Head, Link, useForm } from '@inertiajs/react';
import { RichTextEditor } from '@/components/admin/RichTextEditor';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { CharCount } from '@/components/ui/char-count';
import { ImageUpload } from '@/components/ui/image-upload';
import { RouteModal } from '@/components/ui/route-modal';
import { useAdminListUrl } from '@/hooks/use-admin-list-url';
import { maxLength, required, validate } from '@/lib/validate';
import type { Blog } from '@/types';

/** published_at_input is Bangladesh time, formatted for the date input. */
type Props = { blog: Blog & { published_at_input: string } };

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
            {children}
            {hint && !error && (
                <p className="mt-1 text-xs text-slate-400">{hint}</p>
            )}
            {error && <p className="mt-1 text-xs text-red-500">{error}</p>}
        </div>
    );
}

const inputClass = (err?: string) =>
    `h-9 w-full rounded-lg border px-3 text-sm focus:outline-none focus:ring-1 ${err ? 'border-red-400 focus:ring-red-400' : 'border-slate-200 focus:ring-[#c8102e]'}`;

export default function BlogEdit({ blog }: Props) {
    const listUrl = useAdminListUrl('admin.blogs.index');
    const { data, setData, post, processing, errors, setError, clearErrors } =
        useForm({
            _method: 'PUT',
            title: blog.title,
            slug: blog.slug,
            excerpt: blog.excerpt ?? '',
            content: blog.content,
            featured_image: blog.featured_image as File | string | null,
            author_name: blog.author_name ?? '',
            meta_title: blog.meta_title ?? '',
            meta_description: blog.meta_description ?? '',
            is_published: blog.is_published,
            published_at: blog.published_at_input,
        });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        clearErrors();
        const clientErrors = validate(data, {
            title: [required(), maxLength(150)],
            slug: [required(), maxLength(150)],
            excerpt: [maxLength(400)],
            content: [required()],
            meta_title: [maxLength(70)],
            meta_description: [maxLength(160)],
        });

        if (Object.keys(clientErrors).length > 0) {
            setError(clientErrors);

            return;
        }

        post(route('admin.blogs.update', blog.slug), { forceFormData: true });
    };

    return (
        <>
            <Head title="Edit Blog Post — Admin" />
            <RouteModal
                title="Edit Blog Post"
                returnTo={listUrl}
                className="sm:max-w-2xl"
            >
                <form onSubmit={handleSubmit}>
                    <Card className="shadow-sm">
                        <CardHeader className="border-b border-slate-100">
                            <CardTitle className="text-sm font-semibold text-slate-700">
                                Post Details
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 pt-3">
                            <div className="grid grid-cols-2 gap-4">
                                <Field label="Title *" error={errors.title}>
                                    <input
                                        maxLength={150}
                                        className={inputClass(errors.title)}
                                        value={data.title}
                                        onChange={(e) =>
                                            setData('title', e.target.value)
                                        }
                                    />
                                    <CharCount
                                        value={data.title}
                                        max={150}
                                        className="mt-1 block text-right"
                                    />
                                </Field>

                                <Field
                                    label="Slug *"
                                    hint="Used in the URL"
                                    error={errors.slug}
                                >
                                    <input
                                        maxLength={150}
                                        className={inputClass(errors.slug)}
                                        value={data.slug}
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
                                    maxLength={80}
                                    className={inputClass(errors.author_name)}
                                    value={data.author_name}
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
                                    maxLength={400}
                                    rows={2}
                                    className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-1 focus:ring-[#c8102e] focus:outline-none"
                                    value={data.excerpt}
                                    onChange={(e) =>
                                        setData('excerpt', e.target.value)
                                    }
                                />
                                <CharCount
                                    value={data.excerpt}
                                    max={400}
                                    className="mt-1 block text-right"
                                />
                            </Field>

                            <Field
                                label="Featured Image"
                                hint="Shown on the blog listing and the post header"
                            >
                                <ImageUpload
                                    value={data.featured_image}
                                    onChange={(file) =>
                                        setData('featured_image', file)
                                    }
                                    error={errors.featured_image}
                                    maxSizeMb={5}
                                    previewClassName="h-32 object-cover"
                                />
                            </Field>

                            <Field label="Content *" error={errors.content}>
                                <RichTextEditor
                                    value={data.content}
                                    maxLength={50000}
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
                                        maxLength={70}
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
                                    <CharCount
                                        value={data.meta_title}
                                        max={70}
                                        className="mt-1 block text-right"
                                    />
                                </Field>
                                <Field
                                    label="Published At (Bangladesh time)"
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
                                    {processing ? 'Saving…' : 'Save Changes'}
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
