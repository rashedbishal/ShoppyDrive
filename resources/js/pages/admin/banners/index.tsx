import { Head, Link, router, usePage } from '@inertiajs/react';
import { Edit, Plus, Trash2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';

type Banner = {
    id: number;
    title: string;
    subtitle: string | null;
    image: string;
    link: string | null;
    sort_order: number;
    is_active: boolean;
    starts_at: string | null;
    expires_at: string | null;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};

type Props = {
    banners: Paginated<Banner>;
    filters: { search?: string };
};

export default function BannersIndex({ banners, filters }: Props) {
    const { props } = usePage<any>();
    const flash = props.flash as { success?: string } | undefined;

    const handleDelete = (id: number, title: string) => {
        if (!confirm(`Delete banner "${title}"?`)) return;
        router.delete(route('admin.banners.destroy', id));
    };

    return (
        <>
            <Head title="Banners — Admin" />
            <div className="space-y-5">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-bold text-slate-900">Banners</h1>
                        <p className="text-sm text-slate-500">{banners.total} total</p>
                    </div>
                    <Button asChild size="sm" className="bg-[#c8102e] text-white hover:bg-[#a50d25]">
                        <Link href={route('admin.banners.create')}><Plus className="mr-1.5 size-4" /> Add Banner</Link>
                    </Button>
                </div>

                {flash?.success && (
                    <div className="rounded-lg bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-700">{flash.success}</div>
                )}

                <Card className="overflow-hidden p-0 shadow-sm">
                    <div className="divide-y divide-slate-50">
                        {banners.data.length === 0 ? (
                            <p className="py-12 text-center text-sm text-slate-400">No banners found.</p>
                        ) : banners.data.map((banner) => (
                            <div key={banner.id} className="flex items-center gap-4 px-4 py-3 hover:bg-slate-50/60">
                                <img src={banner.image} alt={banner.title} className="h-12 w-20 rounded-lg object-cover border border-slate-100" onError={(e) => { (e.target as HTMLImageElement).style.display = 'none'; }} />
                                <div className="min-w-0 flex-1">
                                    <p className="text-sm font-semibold text-slate-800">{banner.title}</p>
                                    {banner.subtitle && <p className="truncate text-xs text-slate-400">{banner.subtitle}</p>}
                                    {banner.link && <p className="truncate text-xs text-blue-500">{banner.link}</p>}
                                </div>
                                <div className="hidden text-right text-xs text-slate-400 sm:block">
                                    <p>Order: {banner.sort_order}</p>
                                    {banner.expires_at && <p>Exp: {new Date(banner.expires_at).toLocaleDateString()}</p>}
                                </div>
                                <Badge variant={banner.is_active ? 'success' : 'error'} size="sm">
                                    {banner.is_active ? 'Active' : 'Inactive'}
                                </Badge>
                                <div className="flex items-center gap-1.5">
                                    <Link href={route('admin.banners.edit', banner.id)} className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                                        <Edit className="size-4" />
                                    </Link>
                                    <button type="button" onClick={() => handleDelete(banner.id, banner.title)} className="rounded-lg p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600">
                                        <Trash2 className="size-4" />
                                    </button>
                                </div>
                            </div>
                        ))}
                    </div>

                    {banners.last_page > 1 && (
                        <div className="flex items-center justify-between border-t border-slate-100 px-4 py-3">
                            <p className="text-xs text-slate-500">Page {banners.current_page} of {banners.last_page}</p>
                            <div className="flex gap-1">
                                {banners.links[0]?.url && <Link href={banners.links[0].url} className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs hover:bg-slate-50">Prev</Link>}
                                {banners.links[banners.links.length - 1]?.url && <Link href={banners.links[banners.links.length - 1].url!} className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs hover:bg-slate-50">Next</Link>}
                            </div>
                        </div>
                    )}
                </Card>
            </div>
        </>
    );
}
