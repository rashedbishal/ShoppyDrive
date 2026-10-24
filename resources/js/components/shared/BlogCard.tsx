import { Link } from '@inertiajs/react';
import { ArrowRight, Newspaper } from 'lucide-react';
import type { Blog } from '@/types';

export function formatBlogDate(value: string | null): string {
    if (!value) {
        return '';
    }

    return new Date(value).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

export function BlogCard({ blog }: { blog: Blog }): React.JSX.Element {
    return (
        <Link
            href={`/blog/${blog.slug}`}
            className="group flex flex-col overflow-hidden rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)] shadow-[var(--shadow-card)] transition duration-300 hover:-translate-y-1 hover:border-[var(--color-primary)] hover:shadow-[var(--shadow-card-hover)]"
        >
            <div className="aspect-[16/9] w-full overflow-hidden bg-[var(--color-surface-alt)]">
                {blog.featured_image ? (
                    <img
                        src={blog.featured_image}
                        alt={blog.title}
                        loading="lazy"
                        decoding="async"
                        className="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                    />
                ) : (
                    <div className="flex h-full w-full items-center justify-center">
                        <Newspaper className="size-10 text-[var(--color-text-muted)]" />
                    </div>
                )}
            </div>

            <div className="flex flex-1 flex-col gap-3 p-5">
                <p className="text-xs font-medium text-[var(--color-text-muted)]">
                    {[blog.author_name, formatBlogDate(blog.published_at)]
                        .filter(Boolean)
                        .join(' · ')}
                </p>
                <h3 className="text-lg font-bold text-[var(--color-text-primary)] transition group-hover:text-[var(--color-primary)]">
                    {blog.title}
                </h3>
                {blog.excerpt ? (
                    <p className="line-clamp-3 text-sm leading-6 text-[var(--color-text-secondary)]">
                        {blog.excerpt}
                    </p>
                ) : null}
                <span className="mt-auto inline-flex items-center gap-1.5 text-sm font-semibold text-[var(--color-primary)]">
                    Read more
                    <ArrowRight className="size-3.5 transition group-hover:translate-x-1" />
                </span>
            </div>
        </Link>
    );
}
