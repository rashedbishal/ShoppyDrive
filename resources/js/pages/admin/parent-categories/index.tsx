import { Head, Link, router, usePage } from '@inertiajs/react';
import { Edit, FolderOpen, Plus, Search, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';

type ParentCategory = {
    id: number;
    name: string;
    slug: string;
    sort_order: number;
    is_active: boolean;
    children_count: number;
    direct_products_count: number;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};

type Props = {
    categories: Paginated<ParentCategory>;
    filters: { search?: string };
};

export default function ParentCategoriesIndex({ categories, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const { props } = usePage<any>();
    const flash = props.flash as { success?: string } | undefined;

    const applySearch = () => {
        router.get(route('admin.parent-categories.index'), { search }, { preserveState: true, replace: true });
    };

    const handleDelete = (id: number, name: string, childrenCount: number) => {
        const warning = childrenCount > 0
            ? `"${name}" has ${childrenCount} sub-categor${childrenCount === 1 ? 'y' : 'ies'}. Deleting it will also remove them. Continue?`
            : `Delete parent category "${name}"?`;
        if (!confirm(warning)) return;
        router.delete(route('admin.parent-categories.destroy', id));
    };

    return (
        <>
            <Head title="Parent Categories — Admin" />
            <div className="space-y-5">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-bold text-slate-900">Parent Categories</h1>
                        <p className="text-sm text-slate-500">{categories.total} top-level categories</p>
                    </div>
                    <Button asChild size="sm" className="bg-[#c8102e] text-white hover:bg-[#a50d25]">
                        <Link href={route('admin.parent-categories.create')}>
                            <Plus className="mr-1.5 size-4" /> Add Parent Category
                        </Link>
                    </Button>
                </div>

                {flash?.success && (
                    <div className="rounded-lg bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-700">{flash.success}</div>
                )}

                {/* Info banner */}
                <div className="rounded-xl border border-blue-100 bg-blue-50/60 px-4 py-3 text-xs text-blue-700">
                    Parent categories are top-level groupings (e.g. "Electronics"). Sub-categories are managed under <Link href={route('admin.categories.index')} className="font-semibold underline underline-offset-2">All Categories</Link>.
                </div>

                <Card className="overflow-hidden p-0 shadow-sm">
                    <div className="flex gap-2 border-b border-slate-100 p-4">
                        <div className="relative flex-1">
                            <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                            <input
                                type="text"
                                placeholder="Search parent categories…"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                onKeyDown={(e) => e.key === 'Enter' && applySearch()}
                                className="h-9 w-full rounded-lg border border-slate-200 pl-9 pr-3 text-sm focus:border-[#c8102e] focus:outline-none"
                            />
                        </div>
                    </div>

                    <div className="divide-y divide-slate-50">
                        {categories.data.length === 0 ? (
                            <div className="flex flex-col items-center gap-2 py-14">
                                <FolderOpen className="size-10 text-slate-200" />
                                <p className="text-sm text-slate-400">No parent categories found.</p>
                            </div>
                        ) : categories.data.map((cat) => (
                            <div key={cat.id} className="flex items-center gap-4 px-4 py-3 hover:bg-slate-50/60">
                                <div className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-slate-100">
                                    <FolderOpen className="size-4 text-slate-500" />
                                </div>
                                <div className="min-w-0 flex-1">
                                    <p className="text-sm font-semibold text-slate-800">{cat.name}</p>
                                    <p className="text-xs text-slate-400">{cat.slug}</p>
                                </div>
                                <div className="hidden flex-col items-end gap-0.5 sm:flex">
                                    <p className="text-xs font-medium text-slate-600">
                                        {cat.children_count} sub-{cat.children_count === 1 ? 'category' : 'categories'}
                                    </p>
                                    <p className="text-xs text-slate-400">{cat.direct_products_count} direct products</p>
                                </div>
                                <span className="hidden text-xs text-slate-400 sm:block">Order: {cat.sort_order}</span>
                                <Badge variant={cat.is_active ? 'success' : 'error'} size="sm">
                                    {cat.is_active ? 'Active' : 'Inactive'}
                                </Badge>
                                <div className="flex items-center gap-1.5">
                                    <Link
                                        href={route('admin.parent-categories.edit', cat.id)}
                                        className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                                    >
                                        <Edit className="size-4" />
                                    </Link>
                                    <button
                                        type="button"
                                        onClick={() => handleDelete(cat.id, cat.name, cat.children_count)}
                                        className="rounded-lg p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600"
                                    >
                                        <Trash2 className="size-4" />
                                    </button>
                                </div>
                            </div>
                        ))}
                    </div>

                    {categories.last_page > 1 && (
                        <div className="flex items-center justify-between border-t border-slate-100 px-4 py-3">
                            <p className="text-xs text-slate-500">Page {categories.current_page} of {categories.last_page}</p>
                            <div className="flex gap-1">
                                {categories.links[0]?.url && (
                                    <Link href={categories.links[0].url} className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs hover:bg-slate-50">Prev</Link>
                                )}
                                {categories.links[categories.links.length - 1]?.url && (
                                    <Link href={categories.links[categories.links.length - 1].url!} className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs hover:bg-slate-50">Next</Link>
                                )}
                            </div>
                        </div>
                    )}
                </Card>
            </div>
        </>
    );
}
