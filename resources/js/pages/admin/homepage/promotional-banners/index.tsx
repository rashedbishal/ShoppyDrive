import { Head, Link, router } from '@inertiajs/react';
import { Plus, Pencil, Trash2, ArrowLeft, Megaphone } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import type { PromotionalBanner } from '@/types';
import { cn } from '@/lib/utils';

type Props = {
    banners: PromotionalBanner[];
};

export default function PromotionalBannersIndex({ banners }: Props) {
    const handleDelete = (id: number) => {
        if (confirm('Are you sure you want to delete this banner?')) {
            router.delete(route('admin.promotional-banners.destroy', id));
        }
    };

    return (
        <>
            <Head title="Promotional Banners — Admin" />
            <div className="space-y-6">
                <div className="flex items-center gap-4">
                    <Button asChild variant="ghost" size="icon" className="rounded-full">
                        <Link href={route('admin.homepage.index')}>
                            <ArrowLeft className="size-5" />
                        </Link>
                    </Button>
                    <div className="flex-1">
                        <h1 className="text-2xl font-bold text-slate-900">Promotional Banners</h1>
                        <p className="text-sm text-slate-500">Wide visual banners shown between product sections.</p>
                    </div>
                    <Button asChild>
                        <Link href={route('admin.promotional-banners.create')}>
                            <Plus className="size-4 mr-2" />
                            Add New Banner
                        </Link>
                    </Button>
                </div>

                <div className="grid gap-6">
                    {banners.length === 0 ? (
                        <Card className="py-12 flex flex-col items-center justify-center text-slate-400">
                            <p>No promotional banners created yet.</p>
                        </Card>
                    ) : (
                        banners.map((banner) => (
                            <Card key={banner.id} className="overflow-hidden">
                                <div className="flex flex-col md:flex-row">
                                    <div className="w-full md:w-80 h-40 md:h-auto relative shrink-0">
                                        <img 
                                            src={banner.image} 
                                            alt={banner.title} 
                                            className="w-full h-full object-cover"
                                        />
                                        <div className={cn('absolute inset-0 bg-gradient-to-r opacity-60', banner.accent)} />
                                        {!banner.is_active && (
                                            <div className="absolute inset-0 bg-black/40 flex items-center justify-center">
                                                <span className="bg-white/90 text-[10px] font-bold px-2 py-1 rounded">INACTIVE</span>
                                            </div>
                                        )}
                                    </div>
                                    <CardContent className="flex-1 p-6 flex flex-col justify-between">
                                        <div className="space-y-2">
                                            <div className="flex items-center justify-between">
                                                <div className="flex items-center gap-2">
                                                    <Badge className="bg-primary/10 text-primary border-none hover:bg-primary/20 text-[10px] uppercase font-bold">
                                                        {banner.badge}
                                                    </Badge>
                                                    <span className="text-xs text-slate-400">Order: {banner.sort_order}</span>
                                                </div>
                                            </div>
                                            <h3 className="text-lg font-bold text-slate-800">{banner.title}</h3>
                                            <p className="text-sm text-slate-500 line-clamp-2">{banner.description}</p>
                                            {banner.href && (
                                                <p className="text-[10px] font-mono text-slate-400 truncate">Link: {banner.href}</p>
                                            )}
                                        </div>
                                        <div className="flex items-center justify-end gap-2 mt-4">
                                            <Button asChild variant="outline" size="sm">
                                                <Link href={route('admin.promotional-banners.edit', banner.id)}>
                                                    <Pencil className="size-3.5 mr-2" />
                                                    Edit
                                                </Link>
                                            </Button>
                                            <Button 
                                                variant="outline" 
                                                size="sm" 
                                                className="text-red-500 hover:text-red-600 hover:bg-red-50 border-red-100"
                                                onClick={() => handleDelete(banner.id)}
                                            >
                                                <Trash2 className="size-3.5 mr-2" />
                                                Delete
                                            </Button>
                                        </div>
                                    </CardContent>
                                </div>
                            </Card>
                        ))
                    )}
                </div>
            </div>
        </>
    );
}
