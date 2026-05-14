import { Head, Link, router, usePage } from '@inertiajs/react';
import { Edit, ExternalLink, Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';

type LandingPage = {
    id: number;
    title: string;
    slug: string;
    is_active: boolean;
    product: { id: number; name: string } | null;
    created_at: string;
};

type Props = { pages: LandingPage[] };

export default function LandingPagesIndex({ pages }: Props) {
    const { props } = usePage<any>();
    const flash = props.flash as { success?: string; error?: string } | undefined;

    const handleDelete = (page: LandingPage) => {
        if (!confirm(`Delete landing page "${page.title}"? This cannot be undone.`)) return;
        router.delete(route('admin.landing-pages.destroy', page.id));
    };

    return (
        <>
            <Head title="Landing Pages — Admin" />
            <div className="space-y-5">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-bold text-slate-900">Landing Pages</h1>
                        <p className="text-sm text-slate-500">{pages.length} total</p>
                    </div>
                    <Link href={route('admin.landing-pages.create')}>
                        <Button className="bg-[#c8102e] text-white hover:bg-[#a50d25]">
                            <Plus className="size-4" /> New Landing Page
                        </Button>
                    </Link>
                </div>

                {flash?.success && <div className="rounded-lg bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-700">{flash.success}</div>}
                {flash?.error && <div className="rounded-lg bg-red-50 px-4 py-2.5 text-sm font-medium text-red-700">{flash.error}</div>}

                <Card className="overflow-hidden p-0 shadow-sm">
                    {pages.length === 0 ? (
                        <div className="py-16 text-center text-sm text-slate-400">No landing pages yet. Create one to get started.</div>
                    ) : (
                        <div className="divide-y divide-slate-100">
                            {pages.map((page) => (
                                <div key={page.id} className="flex items-center gap-4 px-4 py-3">
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-center gap-2">
                                            <span className="text-sm font-semibold text-slate-800">{page.title}</span>
                                            <span className={`rounded-full px-2 py-0.5 text-[10px] font-semibold ${page.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'}`}>
                                                {page.is_active ? 'Active' : 'Inactive'}
                                            </span>
                                        </div>
                                        <div className="mt-0.5 flex items-center gap-2 text-xs text-slate-400">
                                            <span className="font-mono">/lp/{page.slug}</span>
                                            {page.product && <span>· {page.product.name}</span>}
                                        </div>
                                    </div>
                                    <div className="flex shrink-0 items-center gap-1">
                                        <a href={`/lp/${page.slug}`} target="_blank" rel="noreferrer" title="Preview" className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition-colors">
                                            <ExternalLink className="size-4" />
                                        </a>
                                        <Link href={route('admin.landing-pages.edit', page.id)}>
                                            <button type="button" className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition-colors">
                                                <Edit className="size-4" />
                                            </button>
                                        </Link>
                                        <button type="button" onClick={() => handleDelete(page)} className="rounded-lg p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-500 transition-colors">
                                            <Trash2 className="size-4" />
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </Card>
            </div>
        </>
    );
}
