import { Head, Link, router, usePage } from '@inertiajs/react';
import { Edit, Plus, Search, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import type { Blog, PaginatedData } from '@/types';

type Props = {
    blogs: PaginatedData<Blog>;
    filters: { search?: string };
};

export default function BlogsIndex({ blogs, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [deleteTarget, setDeleteTarget] = useState<Blog | null>(null);
    const [deleting, setDeleting] = useState(false);
    const { props } = usePage<any>();
    const flash = props.flash as { success?: string } | undefined;

    const applySearch = () => {
        router.get(
            route('admin.blogs.index'),
            { search },
            { preserveState: true, replace: true },
        );
    };

    const confirmDelete = () => {
        if (!deleteTarget) {
            return;
        }

        setDeleting(true);
        router.delete(route('admin.blogs.destroy', deleteTarget.id), {
            onFinish: () => {
                setDeleting(false);
                setDeleteTarget(null);
            },
        });
    };

    return (
        <>
            <Head title="Blog — Admin" />
            <div className="space-y-5">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-bold text-slate-900">
                            Blog
                        </h1>
                        <p className="text-sm text-slate-500">
                            {blogs.total} total
                        </p>
                    </div>
                    <Button
                        asChild
                        size="sm"
                        className="bg-[#c8102e] text-white hover:bg-[#a50d25]"
                    >
                        <Link href={route('admin.blogs.create')}>
                            <Plus className="mr-1.5 size-4" /> Add Post
                        </Link>
                    </Button>
                </div>

                {flash?.success && (
                    <div className="rounded-lg bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-700">
                        {flash.success}
                    </div>
                )}

                <Card className="overflow-hidden p-0 shadow-sm">
                    <div className="flex gap-2 border-b border-slate-100 p-4">
                        <div className="relative flex-1">
                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                            <input
                                type="text"
                                placeholder="Search by title…"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                onKeyDown={(e) =>
                                    e.key === 'Enter' && applySearch()
                                }
                                className="h-9 w-full rounded-lg border border-slate-200 pr-3 pl-9 text-sm focus:border-[#c8102e] focus:outline-none"
                            />
                        </div>
                    </div>

                    <div className="divide-y divide-slate-50">
                        {blogs.data.length === 0 ? (
                            <p className="py-12 text-center text-sm text-slate-400">
                                No blog posts found.
                            </p>
                        ) : (
                            blogs.data.map((blog) => (
                                <div
                                    key={blog.id}
                                    className="flex items-center gap-4 px-4 py-3 hover:bg-slate-50/60"
                                >
                                    {blog.featured_image ? (
                                        <img
                                            src={blog.featured_image}
                                            alt={blog.title}
                                            className="size-12 shrink-0 rounded-lg object-cover"
                                        />
                                    ) : (
                                        <div className="size-12 shrink-0 rounded-lg bg-slate-100" />
                                    )}
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-bold text-slate-800">
                                            {blog.title}
                                        </p>
                                        <p className="truncate text-xs text-slate-400">
                                            {blog.author_name ??
                                                'Unknown author'}
                                            {blog.published_at
                                                ? ` · ${new Date(blog.published_at).toLocaleDateString()}`
                                                : ''}
                                        </p>
                                    </div>
                                    <Badge
                                        variant={
                                            blog.is_published
                                                ? 'success'
                                                : 'error'
                                        }
                                        size="sm"
                                    >
                                        {blog.is_published
                                            ? 'Published'
                                            : 'Draft'}
                                    </Badge>
                                    <div className="flex items-center gap-1.5">
                                        <Link
                                            href={route(
                                                'admin.blogs.edit',
                                                blog.id,
                                            )}
                                            className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                                        >
                                            <Edit className="size-4" />
                                        </Link>
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setDeleteTarget(blog)
                                            }
                                            className="rounded-lg p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600"
                                        >
                                            <Trash2 className="size-4" />
                                        </button>
                                    </div>
                                </div>
                            ))
                        )}
                    </div>

                    {blogs.last_page > 1 && (
                        <div className="flex items-center justify-between border-t border-slate-100 px-4 py-3">
                            <p className="text-xs text-slate-500">
                                Page {blogs.current_page} of {blogs.last_page}
                            </p>
                            <div className="flex gap-1">
                                {blogs.links[0]?.url && (
                                    <Link
                                        href={blogs.links[0].url}
                                        className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs hover:bg-slate-50"
                                    >
                                        Prev
                                    </Link>
                                )}
                                {blogs.links[blogs.links.length - 1]?.url && (
                                    <Link
                                        href={
                                            blogs.links[blogs.links.length - 1]
                                                .url!
                                        }
                                        className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs hover:bg-slate-50"
                                    >
                                        Next
                                    </Link>
                                )}
                            </div>
                        </div>
                    )}
                </Card>
            </div>

            <ConfirmDialog
                open={deleteTarget !== null}
                onOpenChange={(open) => !open && setDeleteTarget(null)}
                title="Delete blog post?"
                description={
                    deleteTarget
                        ? `This will permanently delete "${deleteTarget.title}". This cannot be undone.`
                        : undefined
                }
                onConfirm={confirmDelete}
                processing={deleting}
            />
        </>
    );
}
