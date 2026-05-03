import { Head, Link, router, usePage } from '@inertiajs/react';
import { Edit, Plus, Search, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';

type Category = {
    id: number;
    name: string;
    slug: string;
    sort_order: number;
    is_active: boolean;
    parent: { id: number; name: string } | null;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};

type Props = {
    categories: Paginated<Category>;
    filters: { search?: string };
};

export default function CategoriesIndex({ categories, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const { props } = usePage<any>();
    const flash = props.flash as { success?: string } | undefined;

    const applySearch = () => {
        router.get(route('admin.categories.index'), { search }, { preserveState: true, replace: true });
    };

    const handleDelete = (id: number, name: string) => {
        if (!confirm(`Delete category "${name}"?`)) return;
        router.delete(route('admin.categories.destroy', id));
    };

    return (
        <>
            <Head title="Categories — Admin" />
            <div className="space-y-5">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-bold text-slate-900">Categories</h1>
                        <p className="text-sm text-slate-500">{categories.total} total</p>
                    </div>
                    <Button asChild size="sm" className="bg-[#c8102e] text-white hover:bg-[#a50d25]">
                        <Link href={route('admin.categories.create')}><Plus className="mr-1.5 size-4" /> Add Category</Link>
                    </Button>
                </div>

                {flash?.success && (
                    <div className="rounded-lg bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-700">{flash.success}</div>
                )}

                <Card className="overflow-hidden p-0 shadow-sm">
                    <div className="flex gap-2 border-b border-slate-100 p-4">
                        <div className="relative flex-1">
                            <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                            <input
                                type="text"
                                placeholder="Search categories…"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                onKeyDown={(e) => e.key === 'Enter' && applySearch()}
                                className="h-9 w-full rounded-lg border border-slate-200 pl-9 pr-3 text-sm focus:border-[#c8102e] focus:outline-none"
                            />
                        </div>
                    </div>

                    <div className="divide-y divide-slate-50">
                        {categories.data.length === 0 ? (
                            <p className="py-12 text-center text-sm text-slate-400">No categories found.</p>
                        ) : categories.data.map((cat) => (
                            <div key={cat.id} className="flex items-center gap-4 px-4 py-3 hover:bg-slate-50/60">
                                <div className="min-w-0 flex-1">
                                    <p className="text-sm font-semibold text-slate-800">{cat.name}</p>
                                    <p className="text-xs text-slate-400">{cat.slug}{cat.parent ? ` · parent: ${cat.parent.name}` : ''}</p>
                                </div>
                                <Badge variant={cat.is_active ? 'success' : 'error'} size="sm">
                                    {cat.is_active ? 'Active' : 'Inactive'}
                                </Badge>
                                <div className="flex items-center gap-1.5">
                                    <Link href={route('admin.categories.edit', cat.id)} className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                                        <Edit className="size-4" />
                                    </Link>
                                    <button type="button" onClick={() => handleDelete(cat.id, cat.name)} className="rounded-lg p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600">
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
                                {categories.links[0]?.url && <Link href={categories.links[0].url} className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs hover:bg-slate-50">Prev</Link>}
                                {categories.links[categories.links.length - 1]?.url && <Link href={categories.links[categories.links.length - 1].url!} className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs hover:bg-slate-50">Next</Link>}
                            </div>
                        </div>
                    )}
                </Card>
            </div>
        </>
    );
}
