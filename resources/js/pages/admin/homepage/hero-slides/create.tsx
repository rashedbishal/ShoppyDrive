import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { ImageUpload } from '@/components/ui/image-upload';

export default function HeroSlideCreate() {
    const { data, setData, post, processing, errors } = useForm({
        title: '',
        eyebrow: '',
        description: '',
        image: null as File | null,
        primary_cta_label: '',
        primary_cta_href: '',
        secondary_cta_label: '',
        secondary_cta_href: '',
        is_active: true,
        sort_order: '0',
        stats: [] as { label: string, value: string }[],
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('admin.hero-slides.store'));
    };

    const addStat = () => {
        setData('stats', [...data.stats, { label: '', value: '' }]);
    };

    const removeStat = (index: number) => {
        setData('stats', data.stats.filter((_, i) => i !== index));
    };

    const updateStat = (index: number, key: 'label' | 'value', value: string) => {
        const newStats = [...data.stats];
        newStats[index][key] = value;
        setData('stats', newStats);
    };

    return (
        <>
            <Head title="Create Hero Slide — Admin" />
            <div className="space-y-6">
                <div className="flex items-center gap-4">
                    <Button asChild variant="ghost" size="icon" className="rounded-full">
                        <Link href={route('admin.hero-slides.index')}>
                            <ArrowLeft className="size-5" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900">Add Hero Slide</h1>
                        <p className="text-sm text-slate-500">Create a new banner for the homepage slider.</p>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="max-w-4xl space-y-6">
                    <div className="grid gap-6 md:grid-cols-2">
                        <Card className="md:col-span-2">
                            <CardHeader className="border-b border-slate-100 pb-4">
                                <CardTitle className="text-base font-semibold">Content</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4 pt-6">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Input
                                        label="Eyebrow"
                                        value={data.eyebrow}
                                        onChange={e => setData('eyebrow', e.target.value)}
                                        placeholder="e.g. New Arrivals"
                                        error={errors.eyebrow}
                                    />
                                    <Input
                                        label="Title *"
                                        value={data.title}
                                        onChange={e => setData('title', e.target.value)}
                                        placeholder="Main headline"
                                        error={errors.title}
                                        required
                                    />
                                </div>
                                <Textarea
                                    label="Description"
                                    value={data.description}
                                    onChange={e => setData('description', e.target.value)}
                                    placeholder="Supporting text"
                                    error={errors.description}
                                    rows={4}
                                />
                                <div className="space-y-2">
                                    <p className="text-sm font-medium text-text-primary">Slide Image *</p>
                                    <ImageUpload
                                        value={data.image}
                                        onChange={file => setData('image', file)}
                                        error={errors.image}
                                        previewClassName="h-56"
                                    />
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="border-b border-slate-100 pb-4">
                                <CardTitle className="text-base font-semibold">Call to Actions</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4 pt-6">
                                <div className="space-y-4">
                                    <h5 className="text-xs font-bold text-slate-400 uppercase">Primary CTA</h5>
                                    <div className="grid gap-3">
                                        <Input
                                            label="Label"
                                            value={data.primary_cta_label}
                                            onChange={e => setData('primary_cta_label', e.target.value)}
                                            placeholder="e.g. Shop Now"
                                        />
                                        <Input
                                            label="Link"
                                            value={data.primary_cta_href}
                                            onChange={e => setData('primary_cta_href', e.target.value)}
                                            placeholder="/category/..."
                                        />
                                    </div>
                                </div>
                                <div className="space-y-4 pt-4 border-t border-slate-50">
                                    <h5 className="text-xs font-bold text-slate-400 uppercase">Secondary CTA</h5>
                                    <div className="grid gap-3">
                                        <Input
                                            label="Label"
                                            value={data.secondary_cta_label}
                                            onChange={e => setData('secondary_cta_label', e.target.value)}
                                            placeholder="e.g. Learn More"
                                        />
                                        <Input
                                            label="Link"
                                            value={data.secondary_cta_href}
                                            onChange={e => setData('secondary_cta_href', e.target.value)}
                                            placeholder="/..."
                                        />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="border-b border-slate-100 pb-4 flex flex-row items-center justify-between">
                                <CardTitle className="text-base font-semibold">Stats / Features</CardTitle>
                                <Button type="button" variant="outline" size="sm" onClick={addStat}>
                                    <Plus className="size-3.5 mr-1" /> Add Stat
                                </Button>
                            </CardHeader>
                            <CardContent className="space-y-4 pt-6">
                                {data.stats.map((stat, index) => (
                                    <div key={index} className="flex gap-2 items-end">
                                        <div className="flex-1">
                                            <Input value={stat.label} onChange={e => updateStat(index, 'label', e.target.value)} placeholder="Label (e.g. Delivery)" />
                                        </div>
                                        <div className="flex-1">
                                            <Input value={stat.value} onChange={e => updateStat(index, 'value', e.target.value)} placeholder="Value (e.g. 24h)" />
                                        </div>
                                        <Button type="button" variant="ghost" size="icon" className="text-red-400 shrink-0" onClick={() => removeStat(index)}>
                                            <Trash2 className="size-4" />
                                        </Button>
                                    </div>
                                ))}
                                {data.stats.length === 0 && (
                                    <p className="text-center py-4 text-xs text-slate-400 italic">No stats added. Max 3 recommended.</p>
                                )}
                            </CardContent>
                        </Card>

                        <Card className="md:col-span-2">
                            <CardHeader className="border-b border-slate-100 pb-4">
                                <CardTitle className="text-base font-semibold">Settings</CardTitle>
                            </CardHeader>
                            <CardContent className="flex items-center gap-8 pt-6">
                                <div className="flex items-center gap-2">
                                    <label className="text-sm font-medium text-slate-700">Active</label>
                                    <Switch checked={data.is_active} onCheckedChange={checked => setData('is_active', checked)} />
                                </div>
                                <div className="flex items-center gap-3">
                                    <label className="text-sm font-medium text-slate-700 whitespace-nowrap">Sort Order</label>
                                    <Input type="number" className="w-20" value={data.sort_order} onChange={e => setData('sort_order', e.target.value)} error={errors.sort_order} />
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <div className="flex items-center gap-3">
                        <Button type="submit" disabled={processing} className="px-8">
                            <Plus className="size-4 mr-2" />
                            {processing ? 'Creating...' : 'Create Hero Slide'}
                        </Button>
                        <Button asChild variant="ghost" className="text-slate-500">
                            <Link href={route('admin.hero-slides.index')}>Cancel</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}
