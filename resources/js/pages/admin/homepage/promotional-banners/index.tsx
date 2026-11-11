import { Head, Link, router } from '@inertiajs/react';
import { Plus, Pencil, Trash2, ArrowLeft } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { clickableRow } from '@/lib/clickable-row';
import { DEFAULT_BANNER_GRADIENT, isCssGradient } from '@/lib/gradient';
import type { PromotionalBanner } from '@/types';

type Props = {
    banners: PromotionalBanner[];
};

export default function PromotionalBannersIndex({ banners }: Props) {
    const [deleteTarget, setDeleteTarget] = useState<number | null>(null);
    const [deleting, setDeleting] = useState(false);

    const confirmDelete = () => {
        if (deleteTarget === null) {
            return;
        }

        setDeleting(true);
        router.delete(
            route('admin.promotional-banners.destroy', deleteTarget),
            {
                onFinish: () => {
                    setDeleting(false);
                    setDeleteTarget(null);
                },
            },
        );
    };

    return (
        <>
            <Head title="Promotional Banners — Admin" />
            <div className="space-y-5">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
                    <div className="flex items-center gap-4">
                        <Button
                            asChild
                            variant="ghost"
                            size="icon"
                            className="shrink-0 rounded-full"
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
                                Wide visual banners shown between product
                                sections.
                            </p>
                        </div>
                    </div>
                    <Button asChild className="w-full sm:ml-auto sm:w-auto">
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
                            <Card
                                key={banner.id}
                                {...clickableRow(
                                    route(
                                        'admin.promotional-banners.edit',
                                        banner.id,
                                    ),
                                )}
                                className="overflow-hidden"
                            >
                                <div className="flex flex-col md:flex-row">
                                    <div className="relative h-40 w-full shrink-0 md:h-auto md:w-80">
                                        <img
                                            src={banner.image}
                                            alt={banner.title}
                                            className="h-full w-full object-cover"
                                        />
                                        <div
                                            className="absolute inset-0"
                                            style={{
                                                backgroundImage: isCssGradient(
                                                    banner.accent,
                                                )
                                                    ? banner.accent
                                                    : DEFAULT_BANNER_GRADIENT,
                                            }}
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
                                                    setDeleteTarget(banner.id)
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
            <ConfirmDialog
                open={deleteTarget !== null}
                onOpenChange={(open) => !open && setDeleteTarget(null)}
                title="Delete promotional banner?"
                description="This will permanently remove the banner from your homepage."
                onConfirm={confirmDelete}
                processing={deleting}
            />
        </>
    );
}
