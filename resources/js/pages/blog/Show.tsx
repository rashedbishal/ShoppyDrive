import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Calendar, Newspaper, User } from 'lucide-react';
import { PageMeta } from '@/components/seo/PageMeta';
import { BlogCard } from '@/components/shared/BlogCard';
import type { Blog } from '@/types';

type BlogShowProps = {
    blog: Blog;
    relatedBlogs: Blog[];
};

function formatDate(value: string | null): string {
    if (!value) {
        return '';
    }

    return new Date(value).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
}

export default function BlogShow({
    blog,
    relatedBlogs,
}: BlogShowProps): React.JSX.Element {
    return (
        <>
            <PageMeta
                title={blog.meta_title ?? blog.title}
                description={blog.meta_description ?? blog.excerpt ?? undefined}
                image={blog.featured_image ?? undefined}
                type="article"
            />
            <Head title={blog.title} />

            <article className="py-6 lg:py-10">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <Link
                        href="/blog"
                        className="inline-flex items-center gap-1.5 text-sm font-medium text-[var(--color-text-muted)] transition hover:text-[var(--color-primary)]"
                    >
                        <ArrowLeft className="size-4" />
                        Back to Blog
                    </Link>

                    <header className="mt-6 space-y-4">
                        <h1 className="text-3xl font-bold tracking-tight text-[var(--color-text-primary)] sm:text-4xl">
                            {blog.title}
                        </h1>
                        <div className="flex flex-wrap items-center gap-4 text-sm text-[var(--color-text-muted)]">
                            {blog.author_name ? (
                                <span className="inline-flex items-center gap-1.5">
                                    <User className="size-4" />
                                    {blog.author_name}
                                </span>
                            ) : null}
                            {blog.published_at ? (
                                <span className="inline-flex items-center gap-1.5">
                                    <Calendar className="size-4" />
                                    {formatDate(blog.published_at)}
                                </span>
                            ) : null}
                        </div>
                    </header>

                    {blog.featured_image ? (
                        <div className="mt-8 aspect-[16/9] w-full overflow-hidden rounded-2xl bg-[var(--color-surface-alt)] shadow-[var(--shadow-card)]">
                            <img
                                src={blog.featured_image}
                                alt={blog.title}
                                className="h-full w-full object-cover"
                            />
                        </div>
                    ) : null}

                    <div
                        className="prose prose-base mt-10 max-w-none prose-headings:font-semibold prose-headings:tracking-tight prose-headings:text-[var(--color-text-primary)] prose-h2:mt-8 prose-h2:mb-3 prose-h2:text-xl prose-p:mt-0 prose-p:mb-5 prose-p:leading-8 prose-p:text-[var(--color-text-secondary)] prose-a:font-semibold prose-a:text-[var(--color-primary)] prose-a:no-underline hover:prose-a:underline prose-strong:font-semibold prose-strong:text-[var(--color-text-primary)] prose-ul:mt-4 prose-ul:mb-5 prose-ul:space-y-2 prose-li:leading-7 prose-li:text-[var(--color-text-secondary)] prose-img:rounded-xl [&_li::marker]:text-[var(--color-primary)]"
                        dangerouslySetInnerHTML={{ __html: blog.content }}
                    />
                </div>

                {relatedBlogs.length > 0 ? (
                    <div className="mx-auto mt-16 max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
                        <h2 className="flex items-center gap-2 text-xl font-bold text-[var(--color-text-primary)]">
                            <Newspaper className="size-5 text-[var(--color-primary)]" />
                            More from the Blog
                        </h2>
                        <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            {relatedBlogs.map((related) => (
                                <BlogCard key={related.id} blog={related} />
                            ))}
                        </div>
                    </div>
                ) : null}
            </article>
        </>
    );
}
