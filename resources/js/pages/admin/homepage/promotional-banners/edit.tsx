import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { ImageUpload } from '@/components/ui/image-upload';
import type { PromotionalBanner } from '@/types';

type Props = {
    banner: PromotionalBanner;
};

export default function PromotionalBannerEdit({ banner }: Props) {
    const { data, setData, put, processing, errors } = useForm({
        title: banner.title,
        description: banner.description ?? '',
        image: banner.image as File | string | null,
        href: banner.href ?? '',
        badge: banner.badge ?? '',
        accent: banner.accent ?? 'from-secondary/90 via-secondary/70 to-secondary/20',
        is_active: banner.is_active,
        sort_order: String(banner.sort_order),
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route('admin.promotional-banners.update', banner.id));
    };

    return (
        <>
            <Head title="Edit Promotional Banner — Admin" />
            <div className="space-y-6">
                <div className="flex items-center gap-4">
                    <Button asChild variant="ghost" size="icon" className="rounded-full">
                        <Link href={route('admin.promotional-banners.index')}>
                            <ArrowLeft className="size-5" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900">Edit Promotional Banner</h1>
                        <p className="text-sm text-slate-500">Update homepage banner information.</p>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="max-w-2xl space-y-6">
                    <Card>
                        <CardHeader className="border-b border-slate-100 pb-4">
                            <CardTitle className="text-base font-semibold">Banner Content</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 pt-6">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Input
                                    label="Badge"
                                    value={data.badge}
                                    onChange={e => setData('badge', e.target.value)}
                                    placeholder="e.g. New arrivals"
                                    error={errors.badge}
                                />
                                <Input
                                    label="Title *"
                                    value={data.title}
                                    onChange={e => setData('title', e.target.value)}
                                    placeholder="Banner headline"
                                    error={errors.title}
                                    required
                                />
                            </div>
                            <Textarea
                                label="Description"
                                value={data.description}
                                onChange={e => setData('description', e.target.value)}
                                placeholder="Brief supporting text"
                                error={errors.description}
                                rows={4}
                            />
                            <div className="space-y-2">
                                <p className="text-sm font-medium text-text-primary">Banner Image</p>
                                <p className="text-xs text-slate-400">Upload a new image to replace the current one. Leave as-is to keep the existing image.</p>
                                <ImageUpload
                                    value={data.image}
                                    onChange={file => setData('image', file ?? banner.image)}
                                    error={errors.image}
                                    previewClassName="h-48"
                                />
                            </div>
                            <Input
                                label="Link URL"
                                value={data.href}
                                onChange={e => setData('href', e.target.value)}
                                placeholder="/products?sort=..."
                                error={errors.href}
                            />
                            <Input
                                label="Tailwind Accent Gradient"
                                value={data.accent}
                                onChange={e => setData('accent', e.target.value)}
                                placeholder="from-secondary/90 via-secondary/70 to-secondary/20"
                                error={errors.accent}
                            />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="border-b border-slate-100 pb-4">
                            <CardTitle className="text-base font-semibold">Settings</CardTitle>
                        </CardHeader>
                        <CardContent className="flex items-center gap-8 pt-6">
                            <div className="flex items-center gap-2">
                                <label className="text-sm font-medium text-slate-700">Active</label>
                                <Switch checked={data.is_active} onCheckedChange={checked => setData('is_active', checked)} />
                            </div>
                            <div className="flex items-center gap-3">
                                <label className="text-sm font-medium text-slate-700">Sort Order</label>
                                <Input type="number" className="w-20" value={data.sort_order} onChange={e => setData('sort_order', e.target.value)} error={errors.sort_order} />
                            </div>
                        </CardContent>
                    </Card>

                    <div className="flex items-center gap-3">
                        <Button type="submit" disabled={processing} className="px-8">
                            <Save className="size-4 mr-2" />
                            {processing ? 'Saving...' : 'Save Changes'}
                        </Button>
                        <Button asChild variant="ghost" className="text-slate-500">
                            <Link href={route('admin.promotional-banners.index')}>Cancel</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}
