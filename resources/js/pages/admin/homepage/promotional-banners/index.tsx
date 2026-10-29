import { Head, Link, router } from '@inertiajs/react';
import { Plus, Pencil, Trash2, ArrowLeft } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import type { PromotionalBanner } from '@/types';

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
                    <Button
                        asChild
                        variant="ghost"
                        size="icon"
                        className="rounded-full"
                    >
                        <Link href={route('admin.homepage.index')}>
                            <ArrowLeft className="size-5" />
                        </Link>
                    </Button>
                    <div className="flex-1">
                        <h1 className="text-2xl font-bold text-slate-900">
                            Promotional Banners
                        </h1>
                        <p className="text-sm text-slate-500">
                            Wide visual banners shown between product sections.
                        </p>
                    </div>
                    <Button asChild>
                        <Link href={route('admin.promotional-banners.create')}>
                            <Plus className="mr-2 size-4" />
                            Add New Banner
                        </Link>
                    </Button>
                </div>

                <div className="grid gap-6">
                    {banners.length === 0 ? (
                        <Card className="flex flex-col items-center justify-center py-12 text-slate-400">
                            <p>No promotional banners created yet.</p>
                        </Card>
                    ) : (
                        banners.map((banner) => (
                            <Card key={banner.id} className="overflow-hidden">
                                <div className="flex flex-col md:flex-row">
                                    <div className="relative h-40 w-full shrink-0 md:h-auto md:w-80">
                                        <img
                                            src={banner.image}
                                            alt={banner.title}
                                            className="h-full w-full object-cover"
                                        />
                                        <div
                                            className={cn(
                                                'absolute inset-0 bg-gradient-to-r opacity-60',
                                                banner.accent,
                                            )}
                                        />
                                        {!banner.is_active && (
                                            <div className="absolute inset-0 flex items-center justify-center bg-black/40">
                                                <span className="rounded bg-white/90 px-2 py-1 text-[10px] font-bold">
                                                    INACTIVE
                                                </span>
                                            </div>
                                        )}
                                    </div>
                                    <CardContent className="flex flex-1 flex-col justify-between p-6">
                                        <div className="space-y-2">
                                            <div className="flex items-center justify-between">
                                                <div className="flex items-center gap-2">
                                                    <Badge className="border-none bg-primary/10 text-[10px] font-bold text-primary uppercase hover:bg-primary/20">
                                                        {banner.badge}
                                                    </Badge>
                                                    <span className="text-xs text-slate-400">
                                                        Order:{' '}
                                                        {banner.sort_order}
                                                    </span>
                                                </div>
                                            </div>
                                            <h3 className="text-lg font-bold text-slate-800">
                                                {banner.title}
                                            </h3>
                                            <p className="line-clamp-2 text-sm text-slate-500">
                                                {banner.description}
                                            </p>
                                            {banner.href && (
                                                <p className="truncate font-mono text-[10px] text-slate-400">
                                                    Link: {banner.href}
                                                </p>
                                            )}
                                        </div>
                                        <div className="mt-4 flex items-center justify-end gap-2">
                                            <Button
                                                asChild
                                                variant="outline"
                                                size="sm"
                                            >
                                                <Link
                                                    href={route(
                                                        'admin.promotional-banners.edit',
                                                        banner.id,
                                                    )}
                                                >
                                                    <Pencil className="mr-2 size-3.5" />
                                                    Edit
                                                </Link>
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                className="border-red-100 text-red-500 hover:bg-red-50 hover:text-red-600"
                                                onClick={() =>
                                                    handleDelete(banner.id)
                                                }
                                            >
                                                <Trash2 className="mr-2 size-3.5" />
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
