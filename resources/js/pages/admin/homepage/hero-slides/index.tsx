import { Head, Link, router } from '@inertiajs/react';
import { Plus, Pencil, Trash2, GripVertical, Eye, EyeOff, ArrowLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
                    <Button asChild variant="ghost" size="icon" className="rounded-full">
                        <Link href={route('admin.homepage.index')}>
                            <ArrowLeft className="size-5" />
                        </Link>
                    </Button>
                    <div className="flex-1">
                        <h1 className="text-2xl font-bold text-slate-900">Hero Slides</h1>
                        <p className="text-sm text-slate-500">Manage the large banners at the top of the homepage.</p>
                    </div>
                    <Button asChild>
                        <Link href={route('admin.hero-slides.create')}>
                            <Plus className="size-4 mr-2" />
                            Add New Slide
                        </Link>
                    </Button>
                </div>

                <div className="grid gap-6">
                    {slides.length === 0 ? (
                        <Card className="py-12 flex flex-col items-center justify-center text-slate-400">
                            <p>No hero slides created yet.</p>
                        </Card>
                    ) : (
                        slides.map((slide) => (
                            <Card key={slide.id} className="overflow-hidden">
                                <div className="flex flex-col md:flex-row">
                                    <div className="w-full md:w-64 h-40 md:h-auto bg-slate-100 relative shrink-0">
                                        <img 
                                            src={slide.image} 
                                            alt={slide.title} 
                                            className="w-full h-full object-cover"
                                        />
                                        {!slide.is_active && (
                                            <div className="absolute inset-0 bg-black/40 flex items-center justify-center">
                                                <span className="bg-white/90 text-[10px] font-bold px-2 py-1 rounded">INACTIVE</span>
                                            </div>
                                        )}
                                    </div>
                                    <CardContent className="flex-1 p-6 flex flex-col justify-between">
                                        <div className="space-y-2">
                                            <div className="flex items-center gap-2">
                                                <p className="text-[10px] font-bold uppercase tracking-widest text-primary">{slide.eyebrow}</p>
                                                <span className="text-slate-300">·</span>
                                                <p className="text-xs text-slate-500">Order: {slide.sort_order}</p>
                                            </div>
                                            <h3 className="text-lg font-bold text-slate-800">{slide.title}</h3>
                                            <p className="text-sm text-slate-500 line-clamp-2">{slide.description}</p>
                                        </div>
                                        <div className="flex items-center justify-end gap-2 mt-4">
                                            <Button asChild variant="outline" size="sm">
                                                <Link href={route('admin.hero-slides.edit', slide.id)}>
                                                    <Pencil className="size-3.5 mr-2" />
                                                    Edit
                                                </Link>
                                            </Button>
                                            <Button 
                                                variant="outline" 
                                                size="sm" 
                                                className="text-red-500 hover:text-red-600 hover:bg-red-50 border-red-100"
                                                onClick={() => handleDelete(slide.id)}
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
