import { Head, Link, router } from '@inertiajs/react';
import {
    Plus,
    Pencil,
    Trash2,
    GripVertical,
    Eye,
    EyeOff,
    ArrowLeft,
    Film,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { isVideoUrl } from '@/lib/utils';
import type { HeroSlide } from '@/types';
type Props = {
    slides: HeroSlide[];
};

export default function HeroSlidesIndex({ slides }: Props) {
    const handleDelete = (id: number) => {
        if (confirm('Are you sure you want to delete this slide?')) {
            router.delete(route('admin.hero-slides.destroy', id));
        }
    };

    return (
        <>
            <Head title="Hero Slides — Admin" />
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
                            Hero Slides
                        </h1>
                        <p className="text-sm text-slate-500">
                            Manage the large banners at the top of the homepage.
                        </p>
                    </div>
                    <Button asChild>
                        <Link href={route('admin.hero-slides.create')}>
                            <Plus className="mr-2 size-4" />
                            Add New Slide
                        </Link>
                    </Button>
                </div>

                <div className="grid gap-6">
                    {slides.length === 0 ? (
                        <Card className="flex flex-col items-center justify-center py-12 text-slate-400">
                            <p>No hero slides created yet.</p>
                        </Card>
                    ) : (
                        slides.map((slide) => (
                            <Card key={slide.id} className="overflow-hidden">
                                <div className="flex flex-col md:flex-row">
                                    <div className="relative h-40 w-full shrink-0 bg-slate-100 md:h-auto md:w-64">
                                        {isVideoUrl(slide.image) ? (
                                            <video
                                                src={`${slide.image}#t=0.1`}
                                                muted
                                                playsInline
                                                preload="metadata"
                                                className="h-full w-full object-cover"
                                            />
                                        ) : (
                                            <img
                                                src={slide.image}
                                                alt={slide.title}
                                                className="h-full w-full object-cover"
                                            />
                                        )}
                                        {isVideoUrl(slide.image) && (
                                            <div className="absolute top-2 left-2 rounded-md bg-black/60 p-1 text-white backdrop-blur-sm">
                                                <Film className="size-3.5" />
                                            </div>
                                        )}
                                        {!slide.is_active && (
                                            <div className="absolute inset-0 flex items-center justify-center bg-black/40">
                                                <span className="rounded bg-white/90 px-2 py-1 text-[10px] font-bold">
                                                    INACTIVE
                                                </span>
                                            </div>
                                        )}
                                    </div>
                                    <CardContent className="flex flex-1 flex-col justify-between p-6">
                                        <div className="space-y-2">
                                            <div className="flex items-center gap-2">
                                                <p className="text-[10px] font-bold tracking-widest text-primary uppercase">
                                                    {slide.eyebrow}
                                                </p>
                                                <span className="text-slate-300">
                                                    ·
                                                </span>
                                                <p className="text-xs text-slate-500">
                                                    Order: {slide.sort_order}
                                                </p>
                                            </div>
                                            <h3 className="text-lg font-bold text-slate-800">
                                                {slide.title}
                                            </h3>
                                            <p className="line-clamp-2 text-sm text-slate-500">
                                                {slide.description}
                                            </p>
                                        </div>
                                        <div className="mt-4 flex items-center justify-end gap-2">
                                            <Button
                                                asChild
                                                variant="outline"
                                                size="sm"
                                            >
                                                <Link
                                                    href={route(
                                                        'admin.hero-slides.edit',
                                                        slide.id,
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
                                                    handleDelete(slide.id)
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
