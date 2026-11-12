import { Head, Link, useForm } from '@inertiajs/react';
import { RichTextEditor } from '@/components/admin/RichTextEditor';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { CharCount } from '@/components/ui/char-count';
import { maxLength, required, validate } from '@/lib/validate';

type EditablePage = {
    id: number;
    title: string;
    slug: string;
    content: string;
    meta_title: string | null;
    meta_description: string | null;
    is_published: boolean;
};

type Props = { page: EditablePage; publicUrl: string };

const inputClass = (err?: string) =>
    `h-9 w-full rounded-lg border px-3 text-sm focus:outline-none focus:ring-1 ${err ? 'border-red-400 focus:ring-red-400' : 'border-slate-200 focus:ring-[#c8102e]'}`;

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

/** DISC-05: edit one of the six legal/info pages. The URL (slug) is fixed. */
export default function PageEdit({ page, publicUrl }: Props) {
    const { data, setData, put, processing, errors, setError, clearErrors } =
        useForm({
            title: page.title,
            content: page.content,
            meta_title: page.meta_title ?? '',
            meta_description: page.meta_description ?? '',
            is_published: page.is_published,
        });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        clearErrors();
        const clientErrors = validate(data, {
            title: [required(), maxLength(150)],
            content: [required()],
            meta_title: [maxLength(70)],
            meta_description: [maxLength(160)],
        });

        if (Object.keys(clientErrors).length > 0) {
            setError(clientErrors);

            return;
        }

        put(route('admin.pages.update', page.id));
    };

    return (
        <>
            <Head title={`Edit ${page.title} — Admin`} />
            <form onSubmit={handleSubmit} className="mx-auto max-w-3xl">
                <Card className="shadow-sm">
                    <CardHeader className="border-b border-slate-100">
                        <CardTitle className="text-sm font-semibold text-slate-700">
                            Edit page ·{' '}
                            <a
                                href={publicUrl}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="font-normal text-[#c8102e] hover:underline"
                            >
                                /{page.slug}
                            </a>
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4 pt-3">
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
                            label="Content *"
                            hint={
                                page.slug === 'faq'
                                    ? 'FAQ layout: use Heading 2 for each group and Heading 3 for each question; the answer is the text below it.'
                                    : undefined
                            }
                            error={errors.content}
                        >
                            <RichTextEditor
                                value={data.content}
                                maxLength={100000}
                                onChange={(html) => setData('content', html)}
                            />
                        </Field>

                        <Field
                            label="Meta Title"
                            hint="SEO title (optional)"
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
                            hint="SEO description (optional)"
                            error={errors.meta_description}
                        >
                            <textarea
                                maxLength={160}
                                rows={2}
                                className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:ring-1 focus:ring-[#c8102e] focus:outline-none"
                                value={data.meta_description}
                                onChange={(e) =>
                                    setData('meta_description', e.target.value)
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
                                    setData('is_published', e.target.checked)
                                }
                                className="size-4 rounded border-slate-300 accent-[#c8102e]"
                            />
                            Published (visible on the storefront; hidden pages
                            show "not found")
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
                                href={route('admin.pages.index')}
                                className="inline-flex items-center rounded-lg border border-slate-200 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50"
                            >
                                Cancel
                            </Link>
                        </div>
                    </CardContent>
                </Card>
            </form>
        </>
    );
}
