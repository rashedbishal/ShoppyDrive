import { Head, Link, router } from '@inertiajs/react';
import {
    Eye,
    EyeOff,
    GripVertical,
    Images,
    LayoutTemplate,
    Megaphone,
    Pencil,
} from 'lucide-react';
import { useRef, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import type { HomepageSetting } from '@/types';

type Props = {
    sections: HomepageSetting[];
};

export default function HomepageIndex({ sections }: Props) {
    const [optimisticOrder, setOptimisticOrder] = useState<
        HomepageSetting[] | null
    >(null);
    const [draggingId, setDraggingId] = useState<number | null>(null);
    const dragIndexRef = useRef<number | null>(null);
    const orderedSections = optimisticOrder ?? sections;

    const handleDrop = (dropIndex: number) => {
        const dragIndex = dragIndexRef.current;
        dragIndexRef.current = null;
        setDraggingId(null);

        if (dragIndex === null || dragIndex === dropIndex) {
            return;
        }

        const reordered = [...orderedSections];
        const [moved] = reordered.splice(dragIndex, 1);
        reordered.splice(dropIndex, 0, moved);

        setOptimisticOrder(reordered);

        router.post(
            route('admin.homepage.reorder'),
            { ids: reordered.map((section) => section.id) },
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => setOptimisticOrder(null),
            },
        );
    };

    return (
        <>
            <Head title="Homepage Management — Admin" />
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900">
                            Homepage Management
                        </h1>
                        <p className="text-sm text-slate-500">
                            Control sections and content on your storefront home
                            page.
                        </p>
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <Card className="transition-shadow hover:shadow-md">
                        <CardHeader className="pb-3">
                            <CardTitle className="flex items-center gap-2 text-sm font-medium">
                                <Images className="size-4 text-blue-500" />
                                Hero Slides
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="mb-4 text-xs text-slate-500">
                                Manage the large sliding banners at the top of
                                your homepage.
                            </p>
                            <Button
                                asChild
                                variant="outline"
                                size="sm"
                                className="w-full"
                            >
                                <Link href={route('admin.hero-slides.index')}>
                                    Manage Slides
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>

                    <Card className="transition-shadow hover:shadow-md">
                        <CardHeader className="pb-3">
                            <CardTitle className="flex items-center gap-2 text-sm font-medium">
                                <Megaphone className="size-4 text-amber-500" />
                                Promotional Banners
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="mb-4 text-xs text-slate-500">
                                Control the wide visual banners between product
                                sections.
                            </p>
                            <Button
                                asChild
                                variant="outline"
                                size="sm"
                                className="w-full"
                            >
                                <Link
                                    href={route(
                                        'admin.promotional-banners.index',
                                    )}
                                >
                                    Manage Banners
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>

                    <Card className="transition-shadow hover:shadow-md">
                        <CardHeader className="pb-3">
                            <CardTitle className="flex items-center gap-2 text-sm font-medium">
                                <LayoutTemplate className="size-4 text-emerald-500" />
                                Active Flash Sale
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="mb-4 text-xs text-slate-500">
                                Set which flash sale is currently active on the
                                homepage.
                            </p>
                            <Button
                                asChild
                                variant="outline"
                                size="sm"
                                className="w-full"
                            >
                                <Link href={route('admin.flash-sales.index')}>
                                    Go to Flash Sales
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader className="border-b border-slate-100 pb-4">
                        <CardTitle className="text-base font-semibold">
                            Page Sections
                        </CardTitle>
                        <p className="mt-1 text-xs text-slate-500">
                            Drag a row by its handle to change the order
                            sections appear on the storefront homepage.
                        </p>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="divide-y divide-slate-100">
                            {orderedSections.map((section, index) => (
                                <div
                                    key={section.id}
                                    draggable
                                    onDragStart={() => {
                                        dragIndexRef.current = index;
                                        setDraggingId(section.id);
                                    }}
                                    onDragOver={(e) => e.preventDefault()}
                                    onDrop={() => handleDrop(index)}
                                    onDragEnd={() => {
                                        dragIndexRef.current = null;
                                        setDraggingId(null);
                                    }}
                                    className={cn(
                                        'flex items-center gap-4 px-6 py-4 transition-colors hover:bg-slate-50/50',
                                        draggingId === section.id &&
                                            'opacity-40',
                                    )}
                                >
                                    <div className="cursor-grab text-slate-300 hover:text-slate-400 active:cursor-grabbing">
                                        <GripVertical className="size-5" />
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-center gap-2">
                                            <h4 className="text-sm font-semibold text-slate-800">
                                                {section.section_name}
                                            </h4>
                                            {!section.is_visible && (
                                                <Badge
                                                    variant="secondary"
                                                    className="h-4 px-1.5 text-[10px] opacity-60"
                                                >
                                                    Hidden
                                                </Badge>
                                            )}
                                        </div>
                                        <p className="truncate text-xs text-slate-400">
                                            {section.title ||
                                                'Using default title'}{' '}
                                            {section.description
                                                ? `· ${section.description}`
                                                : ''}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        {section.is_visible ? (
                                            <Eye className="size-4 text-emerald-500" />
                                        ) : (
                                            <EyeOff className="size-4 text-slate-300" />
                                        )}
                                        <Button
                                            asChild
                                            variant="ghost"
                                            size="icon"
                                            className="size-8 text-slate-400 hover:text-slate-600"
                                        >
                                            <Link
                                                href={route(
                                                    'admin.homepage.edit',
                                                    section.id,
                                                )}
                                            >
                                                <Pencil className="size-4" />
                                            </Link>
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
