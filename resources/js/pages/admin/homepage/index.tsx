import { Head, Link, useForm } from '@inertiajs/react';
import { 
    LayoutDashboard, 
    Eye, 
    EyeOff, 
    GripVertical, 
    Pencil, 
    Plus,
    LayoutTemplate,
    Images,
    Megaphone
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import type { HomepageSetting } from '@/types';
type Props = {
    sections: HomepageSetting[];
};

export default function HomepageIndex({ sections }: Props) {
    return (
        <>
            <Head title="Homepage Management — Admin" />
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900">Homepage Management</h1>
                        <p className="text-sm text-slate-500">Control sections and content on your storefront home page.</p>
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <Card className="hover:shadow-md transition-shadow">
                        <CardHeader className="pb-3">
                            <CardTitle className="text-sm font-medium flex items-center gap-2">
                                <Images className="size-4 text-blue-500" />
                                Hero Slides
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-xs text-slate-500 mb-4">Manage the large sliding banners at the top of your homepage.</p>
                            <Button asChild variant="outline" size="sm" className="w-full">
                                <Link href={route('admin.hero-slides.index')}>Manage Slides</Link>
                            </Button>
                        </CardContent>
                    </Card>

                    <Card className="hover:shadow-md transition-shadow">
                        <CardHeader className="pb-3">
                            <CardTitle className="text-sm font-medium flex items-center gap-2">
                                <Megaphone className="size-4 text-amber-500" />
                                Promotional Banners
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-xs text-slate-500 mb-4">Control the wide visual banners between product sections.</p>
                            <Button asChild variant="outline" size="sm" className="w-full">
                                <Link href={route('admin.promotional-banners.index')}>Manage Banners</Link>
                            </Button>
                        </CardContent>
                    </Card>

                    <Card className="hover:shadow-md transition-shadow">
                        <CardHeader className="pb-3">
                            <CardTitle className="text-sm font-medium flex items-center gap-2">
                                <LayoutTemplate className="size-4 text-emerald-500" />
                                Active Flash Sale
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-xs text-slate-500 mb-4">Set which flash sale is currently active on the homepage.</p>
                            <Button asChild variant="outline" size="sm" className="w-full">
                                <Link href={route('admin.flash-sales.index')}>Go to Flash Sales</Link>
                            </Button>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader className="border-b border-slate-100 pb-4">
                        <CardTitle className="text-base font-semibold">Page Sections</CardTitle>
                        <p className="text-xs text-slate-500 mt-1">Order and visibility of standard product rails and category grids.</p>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="divide-y divide-slate-100">
                            {sections.map((section) => (
                                <div key={section.id} className="flex items-center gap-4 px-6 py-4 hover:bg-slate-50/50 transition-colors">
                                    <div className="cursor-grab text-slate-300 hover:text-slate-400">
                                        <GripVertical className="size-5" />
                                    </div>
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-center gap-2">
                                            <h4 className="text-sm font-semibold text-slate-800">{section.section_name}</h4>
                                            {!section.is_visible && (
                                                <Badge variant="secondary" className="text-[10px] h-4 px-1.5 opacity-60">
                                                    Hidden
                                                </Badge>
                                            )}
                                        </div>
                                        <p className="text-xs text-slate-400 truncate">
                                            {section.title || 'Using default title'} {section.description ? `· ${section.description}` : ''}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        {section.is_visible ? (
                                            <Eye className="size-4 text-emerald-500" />
                                        ) : (
                                            <EyeOff className="size-4 text-slate-300" />
                                        )}
                                        <Button asChild variant="ghost" size="icon" className="size-8 text-slate-400 hover:text-slate-600">
                                            <Link href={route('admin.homepage.edit', section.id)}>
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
