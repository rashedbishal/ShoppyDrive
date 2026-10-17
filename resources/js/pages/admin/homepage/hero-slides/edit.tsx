import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Save, Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { ImageUpload } from '@/components/ui/image-upload';
import type { HeroSlide } from '@/types';

type Props = {
    slide: HeroSlide;
};

export default function HeroSlideEdit({ slide }: Props) {
    const { data, setData, post, processing, errors, clearErrors } = useForm({
        _method: 'PUT',
        title: slide.title,
        eyebrow: slide.eyebrow ?? '',
        description: slide.description ?? '',
        image: slide.image as File | string | null,
        primary_cta_label: slide.primary_cta_label ?? '',
        primary_cta_href: slide.primary_cta_href ?? '',
        secondary_cta_label: slide.secondary_cta_label ?? '',
        secondary_cta_href: slide.secondary_cta_href ?? '',
        is_active: slide.is_active,
        sort_order: String(slide.sort_order),
        stats: slide.stats ?? ([] as { label: string; value: string }[]),
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        clearErrors();
        // PHP never populates $_POST/$_FILES for a literal PUT + multipart/form-data
        // request, so this must go as a real POST with _method spoofing (forceFormData
        // keeps the encoding consistent even when no new image is selected).
        post(route('admin.hero-slides.update', slide.id), {
            forceFormData: true,
            // Keeps "stats" array items correctly indexed (stats[0][label], stats[1][label], ...).
            queryStringArrayFormat: 'indices',
        });
    };

    const addStat = () => {
        setData('stats', [...data.stats, { label: '', value: '' }]);
    };

    const removeStat = (index: number) => {
        setData(
            'stats',
            data.stats.filter((_, i) => i !== index),
        );
    };

    const updateStat = (
        index: number,
        key: 'label' | 'value',
        value: string,
    ) => {
        const newStats = [...data.stats];
        newStats[index][key] = value;
        setData('stats', newStats);
    };

    return (
        <>
            <Head title="Edit Hero Slide — Admin" />
            <div className="space-y-6">
                <div className="flex items-center gap-4">
                    <Button
                        asChild
                        variant="ghost"
                        size="icon"
                        className="rounded-full"
                    >
                        <Link href={route('admin.hero-slides.index')}>
                            <ArrowLeft className="size-5" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900">
                            Edit Hero Slide
                        </h1>
                        <p className="text-sm text-slate-500">
                            Update homepage banner slide information.
                        </p>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="max-w-4xl space-y-6">
                    <div className="grid gap-6 md:grid-cols-2">
                        <Card className="md:col-span-2">
                            <CardHeader className="border-b border-slate-100 pb-4">
                                <CardTitle className="text-base font-semibold">
                                    Content
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4 pt-6">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Input
                                        label="Eyebrow"
                                        value={data.eyebrow}
                                        onChange={(e) => {
                                            setData('eyebrow', e.target.value);
                                            clearErrors('eyebrow');
                                        }}
                                        placeholder="e.g. New Arrivals"
                                        error={errors.eyebrow}
                                    />
                                    <Input
                                        label="Title"
                                        value={data.title}
                                        onChange={(e) => {
                                            setData('title', e.target.value);
                                            clearErrors('title');
                                        }}
                                        placeholder="Main headline"
                                        error={errors.title}
                                        required
                                    />
                                </div>
                                <Textarea
                                    label="Description"
                                    value={data.description}
                                    onChange={(e) => {
                                        setData('description', e.target.value);
                                        clearErrors('description');
                                    }}
                                    placeholder="Supporting text"
                                    error={errors.description}
                                    rows={4}
                                />
                                <div className="space-y-2">
                                    <p className="text-sm font-medium text-text-primary">
                                        Slide Image
                                    </p>
                                    <p className="text-xs text-slate-400">
                                        Leave as-is to keep the current image.
                                        Upload a new one to replace it.
                                    </p>
                                    <ImageUpload
                                        value={data.image}
                                        onChange={(file) => {
                                            setData(
                                                'image',
                                                file ?? slide.image,
                                            );
                                            clearErrors('image');
                                        }}
                                        error={errors.image}
                                        hint="Best resolution: 1920×1080px (16:9) landscape, minimum 1600×800px. The image fills the full-width hero and crops to this ratio on smaller screens."
                                        previewClassName="h-56"
                                    />
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="border-b border-slate-100 pb-4">
                                <CardTitle className="text-base font-semibold">
                                    Call to Actions
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4 pt-6">
                                <div className="space-y-4">
                                    <h5 className="text-xs font-bold text-slate-400 uppercase">
                                        Primary CTA
                                    </h5>
                                    <div className="grid gap-3">
                                        <Input
                                            label="Label"
                                            value={data.primary_cta_label}
                                            onChange={(e) =>
                                                setData(
                                                    'primary_cta_label',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="e.g. Shop Now"
                                        />
                                        <Input
                                            label="Link"
                                            value={data.primary_cta_href}
                                            onChange={(e) =>
                                                setData(
                                                    'primary_cta_href',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="/category/..."
                                        />
                                    </div>
                                </div>
                                <div className="space-y-4 border-t border-slate-50 pt-4">
                                    <h5 className="text-xs font-bold text-slate-400 uppercase">
                                        Secondary CTA
                                    </h5>
                                    <div className="grid gap-3">
                                        <Input
                                            label="Label"
                                            value={data.secondary_cta_label}
                                            onChange={(e) =>
                                                setData(
                                                    'secondary_cta_label',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="e.g. Learn More"
                                        />
                                        <Input
                                            label="Link"
                                            value={data.secondary_cta_href}
                                            onChange={(e) =>
                                                setData(
                                                    'secondary_cta_href',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="/..."
                                        />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between border-b border-slate-100 pb-4">
                                <CardTitle className="text-base font-semibold">
                                    Stats / Features
                                </CardTitle>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={addStat}
                                >
                                    <Plus className="mr-1 size-3.5" /> Add Stat
                                </Button>
                            </CardHeader>
                            <CardContent className="space-y-4 pt-6">
                                {data.stats.map((stat, index) => (
                                    <div
                                        key={index}
                                        className="flex items-end gap-2"
                                    >
                                        <div className="flex-1">
                                            <Input
                                                value={stat.label}
                                                onChange={(e) =>
                                                    updateStat(
                                                        index,
                                                        'label',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="Label (e.g. Delivery)"
                                            />
                                        </div>
                                        <div className="flex-1">
                                            <Input
                                                value={stat.value}
                                                onChange={(e) =>
                                                    updateStat(
                                                        index,
                                                        'value',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="Value (e.g. 24h)"
                                            />
                                        </div>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="shrink-0 text-red-400"
                                            onClick={() => removeStat(index)}
                                        >
                                            <Trash2 className="size-4" />
                                        </Button>
                                    </div>
                                ))}
                                {data.stats.length === 0 && (
                                    <p className="py-4 text-center text-xs text-slate-400 italic">
                                        No stats added. Max 3 recommended.
                                    </p>
                                )}
                            </CardContent>
                        </Card>

                        <Card className="md:col-span-2">
                            <CardHeader className="border-b border-slate-100 pb-4">
                                <CardTitle className="text-base font-semibold">
                                    Settings
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="flex items-center gap-8 pt-6">
                                <div className="flex items-center gap-2">
                                    <label className="text-sm font-medium text-slate-700">
                                        Active
                                    </label>
                                    <Switch
                                        checked={data.is_active}
                                        onCheckedChange={(checked) =>
                                            setData('is_active', checked)
                                        }
                                    />
                                </div>
                                <div className="flex items-center gap-3">
                                    <label className="text-sm font-medium whitespace-nowrap text-slate-700">
                                        Sort Order
                                    </label>
                                    <Input
                                        type="number"
                                        className="w-20"
                                        value={data.sort_order}
                                        onChange={(e) => {
                                            setData(
                                                'sort_order',
                                                e.target.value,
                                            );
                                            clearErrors('sort_order');
                                        }}
                                        error={errors.sort_order}
                                    />
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <div className="flex items-center gap-3">
                        <Button
                            type="submit"
                            disabled={processing}
                            className="px-8"
                        >
                            <Save className="mr-2 size-4" />
                            {processing ? 'Saving...' : 'Save Changes'}
                        </Button>
                        <Button
                            asChild
                            variant="ghost"
                            className="text-slate-500"
                        >
                            <Link href={route('admin.hero-slides.index')}>
                                Cancel
                            </Link>
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}
