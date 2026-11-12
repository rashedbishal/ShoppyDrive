import { Head, router } from '@inertiajs/react';
import { Newspaper, Search, X } from 'lucide-react';
import { useState } from 'react';
import { BlogCard } from '@/components/shared/BlogCard';
import { PageHero } from '@/components/shared/PageHero';
import { EmptyState } from '@/components/ui/empty-state';
import { Input } from '@/components/ui/input';
import { Pagination } from '@/components/ui/pagination';
import { useLiveSearch } from '@/hooks/use-live-search';
import type { Blog, PaginatedData } from '@/types';

type BlogIndexProps = {
    blogs: PaginatedData<Blog>;
    filters: { search?: string };
};

export default function BlogIndex({
    blogs,
    filters,
}: BlogIndexProps): React.JSX.Element {
    const [search, setSearch] = useState(filters.search ?? '');

    const runSearch = (): void => {
        router.get(
            '/blog',
            { search: search.trim() || undefined },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const submitSearch = (event: React.FormEvent): void => {
        event.preventDefault();
        runSearch();
    };

    // Posts follow the search box as you type.
    useLiveSearch(search, filters.search ?? '', runSearch);

    const clearSearch = (): void => {
        setSearch('');
        router.get('/blog', {}, { preserveState: true, replace: true });
    };

    return (
        <>
            <Head title="Blog" />

            <div className="space-y-6 py-6 lg:py-8">
                <PageHero
                    badge={{ icon: Newspaper, label: 'ShoppyDrive Blog' }}
                    eyebrow="Stories & guides"
                    title="From the Blog"
                    description="Shopping guides, product tips and the latest news from the ShoppyDrive team."
                    aside={
                        <form onSubmit={submitSearch} className="space-y-2">
                            <div className="relative">
                                <Search className="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-[var(--color-text-muted)]" />
                                <Input
                                    maxLength={64}
                                    type="search"
                                    placeholder="Search posts..."
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    aria-label="Search blog posts"
                                    className="h-11 rounded-full border-[var(--color-border)] bg-[var(--color-surface)] pr-11 pl-11 shadow-sm"
                                />
                                {search ? (
                                    <button
                                        type="button"
                                        onClick={clearSearch}
                                        aria-label="Clear search"
                                        className="absolute top-1/2 right-3 flex size-7 -translate-y-1/2 items-center justify-center rounded-full text-[var(--color-text-muted)] transition hover:bg-[var(--color-surface-alt)] hover:text-[var(--color-text-primary)]"
                                    >
                                        <X className="size-4" />
                                    </button>
                                ) : null}
                            </div>
                        </form>
                    }
                />

                <section className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    {blogs.data.length === 0 ? (
                        <EmptyState
                            icon={Newspaper}
                            title="No posts found"
                            description={
                                filters.search
                                    ? `No posts match "${filters.search}".`
                                    : 'Check back soon for new posts.'
                            }
                            action={
                                filters.search
                                    ? { label: 'Clear search', href: '/blog' }
                                    : undefined
                            }
                        />
                    ) : (
                        <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            {blogs.data.map((blog) => (
                                <BlogCard key={blog.id} blog={blog} />
                            ))}
                        </div>
                    )}

                    <Pagination
                        data={blogs}
                        onPageChange={(page) =>
                            router.get(
                                '/blog',
                                { search: filters.search, page },
                                { preserveState: true },
                            )
                        }
                    />
                </section>
            </div>
        </>
    );
}
