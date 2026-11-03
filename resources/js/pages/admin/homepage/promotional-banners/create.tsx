import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { GradientPicker } from '@/components/ui/gradient-picker';
import { ImageUpload } from '@/components/ui/image-upload';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { DEFAULT_BANNER_GRADIENT } from '@/lib/gradient';
import { maxLength, required, validate } from '@/lib/validate';

export default function PromotionalBannerCreate() {
    const { data, setData, post, processing, errors, setError, clearErrors } =
        useForm({
            title: '',
            description: '',
            image: null as File | null,
            href: '',
            badge: '',
            accent: DEFAULT_BANNER_GRADIENT,
            is_active: true,
            sort_order: '0',
        });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        clearErrors();

        const clientErrors = validate(data, {
            title: [required(), maxLength(80)],
            description: [maxLength(150)],
            href: [maxLength(255)],
            badge: [maxLength(40)],
        });

        if (Object.keys(clientErrors).length > 0) {
            setError(clientErrors);

            return;
        }

        post(route('admin.promotional-banners.store'));
    };

    return (
        <>
            <Head title="Create Promotional Banner — Admin" />
            <div className="space-y-6">
                <div className="flex items-center gap-4">
                    <Button
                        asChild
                        variant="ghost"
                        size="icon"
                        className="rounded-full"
                    >
                        <Link href={route('admin.promotional-banners.index')}>
                            <ArrowLeft className="size-5" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900">
                            Add Promotional Banner
                        </h1>
                        <p className="text-sm text-slate-500">
                            Create a new wide banner for the homepage.
                        </p>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="space-y-5">
                    <div className="grid gap-5 lg:grid-cols-[1fr_320px]">
                        <div className="space-y-5">
                            <Card>
                                <CardHeader className="border-b border-slate-100 pb-4">
                                    <CardTitle className="text-base font-semibold">
                                        Banner Content
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4 pt-4">
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <Input
                                            label="Badge"
                                            value={data.badge}
                                            onChange={(e) => {
                                                setData(
                                                    'badge',
                                                    e.target.value,
                                                );
                                                clearErrors('badge');
                                            }}
                                            placeholder="e.g. New arrivals"
                                            error={errors.badge}
                                            maxLength={40}
                                            hint={`${data.badge.length}/40`}
                                        />
                                        <Input
                                            label="Title"
                                            value={data.title}
                                            onChange={(e) => {
                                                setData(
                                                    'title',
                                                    e.target.value,
                                                );
                                                clearErrors('title');
                                            }}
                                            placeholder="Banner headline"
                                            error={errors.title}
                                            required
                                            maxLength={80}
                                            hint={`${data.title.length}/80`}
                                        />
                                    </div>
                                    <Textarea
                                        label="Description"
                                        value={data.description}
                                        onChange={(e) => {
                                            setData(
                                                'description',
                                                e.target.value,
                                            );
                                            clearErrors('description');
                                        }}
                                        placeholder="Brief supporting text"
                                        error={errors.description}
                                        rows={4}
                                        maxLength={150}
                                        hint={`${data.description.length}/150`}
                                    />
                                    <div className="space-y-2">
                                        <p className="text-sm font-medium text-text-primary">
                                            Banner Image
                                        </p>
                                        <p className="text-xs text-slate-400">
                                            Leave empty to use a placeholder.
                                            Upload your own image to replace it.
                                        </p>
                                        <ImageUpload
                                            value={data.image}
                                            onChange={(file) => {
                                                setData('image', file);
                                                clearErrors('image');
                                            }}
                                            error={errors.image}
                                            hint="Best resolution: 1200×800px (3:2) landscape, minimum 800×600px. Banners display two per row and crop to fill, so keep key content centered."
                                            previewClassName="h-48"
                                        />
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        <div className="space-y-5">
                            <Card>
                                <CardHeader className="border-b border-slate-100 pb-4">
                                    <CardTitle className="text-base font-semibold">
                                        Call to Action
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4 pt-4">
                                    <Input
                                        label="Link URL"
                                        value={data.href}
                                        onChange={(e) => {
                                            setData('href', e.target.value);
                                            clearErrors('href');
                                        }}
                                        placeholder="/products?sort=..."
                                        error={errors.href}
                                    />
                                    <GradientPicker
                                        label="Overlay Gradient"
                                        value={data.accent}
                                        onChange={(value) => {
                                            setData('accent', value);
                                            clearErrors('accent');
                                        }}
                                        hint="Darkens the image left-to-right so the text stays readable."
                                        error={errors.accent}
                                    />
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader className="border-b border-slate-100 pb-4">
                                    <CardTitle className="text-base font-semibold">
                                        Settings
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4 pt-4">
                                    <div className="flex items-center justify-between">
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
                                    <div className="space-y-1.5">
                                        <label className="text-sm font-medium text-slate-700">
                                            Sort Order
                                        </label>
                                        <Input
                                            type="number"
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
                    </div>

                    <div className="flex items-center gap-3">
                        <Button
                            type="submit"
                            disabled={processing}
                            className="px-8"
                        >
                            <Plus className="mr-2 size-4" />
                            {processing ? 'Creating...' : 'Create Banner'}
                        </Button>
                        <Button
                            asChild
                            variant="ghost"
                            className="text-slate-500"
                        >
                            <Link
                                href={route('admin.promotional-banners.index')}
                            >
                                Cancel
                            </Link>
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}
