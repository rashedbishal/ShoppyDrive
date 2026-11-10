import { Head, Link, useForm } from '@inertiajs/react';
import { AnimatePresence } from 'framer-motion';
import {
    ArrowLeft,
    Check,
    Package,
    Plus,
    Save,
    Search,
    Trash2,
} from 'lucide-react';
import { useState, useEffect, useRef } from 'react';
import { AdminReveal, AdminTableRow } from '@/components/admin/admin-motion';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { GradientPicker } from '@/components/ui/gradient-picker';
import { ImageUpload } from '@/components/ui/image-upload';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { DEFAULT_BANNER_GRADIENT, isCssGradient } from '@/lib/gradient';
import { cn } from '@/lib/utils';
import { maxLength, required, validate } from '@/lib/validate';
import type { PromotionalBanner } from '@/types';

type SimpleProduct = {
    id: number;
    name: string;
    price: number;
};

type Props = {
    banner: PromotionalBanner;
    bannerProducts: SimpleProduct[];
};

export default function PromotionalBannerEdit({
    banner,
    bannerProducts,
}: Props) {
    const { data, setData, post, processing, errors, setError, clearErrors } =
        useForm({
            _method: 'PUT',
            title: banner.title,
            description: banner.description ?? '',
            image: banner.image as File | string | null,
            href: banner.href ?? '',
            badge: banner.badge ?? '',
            accent: isCssGradient(banner.accent)
                ? banner.accent
                : DEFAULT_BANNER_GRADIENT,
            is_active: banner.is_active,
            sort_order: String(banner.sort_order),
            products: bannerProducts.map((p) => p.id),
        });

    const [selectedProducts, setSelectedProducts] =
        useState<SimpleProduct[]>(bannerProducts);
    const [searchResults, setSearchResults] = useState<SimpleProduct[]>([]);
    const [searchQuery, setSearchQuery] = useState('');
    const [searching, setSearching] = useState(false);
    const searchRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const handleClickOutside = (e: MouseEvent) => {
            if (
                searchRef.current &&
                !searchRef.current.contains(e.target as Node)
            ) {
                setSearchResults([]);
            }
        };
        document.addEventListener('mousedown', handleClickOutside);

        return () =>
            document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    useEffect(() => {
        if (!searchQuery.trim()) {
            // eslint-disable-next-line react-hooks/set-state-in-effect -- clears results at once when the query is emptied; fetched results are set asynchronously
            setSearchResults([]);

            return;
        }

        const controller = new AbortController();
        const timeoutId = setTimeout(async () => {
            setSearching(true);

            try {
                const res = await fetch(
                    `/admin/promotional-banners/products/search?q=${encodeURIComponent(searchQuery)}`,
                    {
                        signal: controller.signal,
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    },
                );

                if (res.ok) {
                    setSearchResults(await res.json());
                }
            } catch {
                // aborted or network error
            } finally {
                setSearching(false);
            }
        }, 300);

        return () => {
            clearTimeout(timeoutId);
            controller.abort();
        };
    }, [searchQuery]);

    const addProduct = (product: SimpleProduct) => {
        if (selectedProducts.some((p) => p.id === product.id)) {
            return;
        }

        const next = [...selectedProducts, product];
        setSelectedProducts(next);
        setData(
            'products',
            next.map((p) => p.id),
        );
        setSearchQuery('');
        setSearchResults([]);
    };

    const removeProduct = (productId: number) => {
        const next = selectedProducts.filter((p) => p.id !== productId);
        setSelectedProducts(next);
        setData(
            'products',
            next.map((p) => p.id),
        );
    };

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

        // PHP never populates $_POST/$_FILES for a literal PUT + multipart/form-data
        // request, so this must go as a real POST with _method spoofing (forceFormData
        // keeps the encoding consistent even when no new image is selected).
        post(route('admin.promotional-banners.update', banner.id), {
            forceFormData: true,
        });
    };

    const hasProducts = selectedProducts.length > 0;

    return (
        <>
            <Head title="Edit Promotional Banner — Admin" />
            <div className="space-y-5">
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
                            Edit Promotional Banner
                        </h1>
                        <p className="text-sm text-slate-500">
                            Update homepage banner information.
                        </p>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="space-y-5">
                    <div className="grid grid-cols-1 gap-5 lg:grid-cols-[minmax(0,1fr)_320px]">
                        <div className="space-y-5">
                            <Card>
                                <CardHeader className="border-b border-slate-100">
                                    <CardTitle className="text-base font-semibold">
                                        Banner Content
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4 pt-3">
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
                                            Upload a new image to replace the
                                            current one. Leave as-is to keep the
                                            existing image.
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

                            {/* Product Picker */}
                            <Card>
                                <CardHeader className="flex flex-col gap-3 border-b border-slate-100 sm:flex-row sm:items-center sm:justify-between">
                                    <CardTitle className="flex items-center gap-2 text-base font-semibold">
                                        <Package className="size-4 text-emerald-500" />
                                        Banner Products
                                    </CardTitle>
                                    <div
                                        ref={searchRef}
                                        className="relative w-full sm:w-72"
                                    >
                                        <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                                        <Input
                                            placeholder="Search products..."
                                            className="h-9 pl-9"
                                            value={searchQuery}
                                            onChange={(e) =>
                                                setSearchQuery(e.target.value)
                                            }
                                        />
                                        {(searching ||
                                            searchResults.length > 0) && (
                                            <div className="absolute top-full right-0 left-0 z-50 mt-1 max-h-72 overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg">
                                                {searching && (
                                                    <p className="px-4 py-3 text-sm text-slate-400">
                                                        Searching...
                                                    </p>
                                                )}
                                                {!searching &&
                                                    searchResults.map(
                                                        (product) => {
                                                            const alreadyAdded =
                                                                selectedProducts.some(
                                                                    (p) =>
                                                                        p.id ===
                                                                        product.id,
                                                                );

                                                            return (
                                                                <button
                                                                    key={
                                                                        product.id
                                                                    }
                                                                    type="button"
                                                                    disabled={
                                                                        alreadyAdded
                                                                    }
                                                                    className={cn(
                                                                        'flex w-full items-center justify-between gap-3 border-b border-slate-100 px-4 py-2.5 text-left last:border-0',
                                                                        alreadyAdded
                                                                            ? 'cursor-not-allowed bg-slate-50 opacity-50'
                                                                            : 'group cursor-pointer hover:bg-slate-50',
                                                                    )}
                                                                    onClick={() =>
                                                                        !alreadyAdded &&
                                                                        addProduct(
                                                                            product,
                                                                        )
                                                                    }
                                                                >
                                                                    <div className="min-w-0 flex-1">
                                                                        <p className="truncate text-sm font-medium text-slate-900">
                                                                            {
                                                                                product.name
                                                                            }
                                                                        </p>
                                                                        <p className="text-xs text-slate-500">
                                                                            ৳
                                                                            {
                                                                                product.price
                                                                            }
                                                                        </p>
                                                                    </div>
                                                                    {alreadyAdded ? (
                                                                        <Check className="size-4 shrink-0 text-emerald-500" />
                                                                    ) : (
                                                                        <Plus className="size-4 shrink-0 text-slate-400 group-hover:text-slate-700" />
                                                                    )}
                                                                </button>
                                                            );
                                                        },
                                                    )}
                                            </div>
                                        )}
                                    </div>
                                </CardHeader>
                                <CardContent className="pt-3">
                                    {selectedProducts.length === 0 ? (
                                        <div className="rounded-xl border-2 border-dashed border-slate-200 bg-slate-50 py-10 text-center">
                                            <Package className="mx-auto mb-3 size-10 text-slate-300" />
                                            <p className="text-slate-500">
                                                No products added yet.
                                            </p>
                                            <p className="text-xs text-slate-400">
                                                Search and add products —
                                                "Explore now" will link to them.
                                            </p>
                                        </div>
                                    ) : (
                                        <div className="overflow-hidden rounded-lg border border-slate-200">
                                            <table className="w-full text-sm">
                                                <thead className="border-b border-slate-200 bg-slate-50">
                                                    <tr>
                                                        <th className="px-3 py-3 text-left font-semibold text-slate-700 sm:px-4">
                                                            Product
                                                        </th>
                                                        <th className="w-20 px-2 py-3 text-right font-semibold text-slate-700 sm:w-28 sm:px-4">
                                                            Price
                                                        </th>
                                                        <th className="w-12" />
                                                    </tr>
                                                </thead>
                                                <tbody className="divide-y divide-slate-100">
                                                    <AnimatePresence
                                                        initial={false}
                                                    >
                                                        {selectedProducts.map(
                                                            (product) => (
                                                                <AdminTableRow
                                                                    key={
                                                                        product.id
                                                                    }
                                                                    className="transition hover:bg-slate-50/50"
                                                                >
                                                                    <td className="px-3 py-3 font-medium text-slate-900 sm:px-4">
                                                                        {
                                                                            product.name
                                                                        }
                                                                    </td>
                                                                    <td className="px-3 py-3 text-right text-slate-600 sm:px-4">
                                                                        ৳
                                                                        {Number(
                                                                            product.price,
                                                                        ).toFixed(
                                                                            0,
                                                                        )}
                                                                    </td>
                                                                    <td className="px-3 py-3 text-right sm:px-4">
                                                                        <button
                                                                            type="button"
                                                                            onClick={() =>
                                                                                removeProduct(
                                                                                    product.id,
                                                                                )
                                                                            }
                                                                            className="rounded-lg p-1.5 text-slate-400 transition hover:bg-red-50 hover:text-red-500"
                                                                        >
                                                                            <Trash2 className="size-4" />
                                                                        </button>
                                                                    </td>
                                                                </AdminTableRow>
                                                            ),
                                                        )}
                                                    </AnimatePresence>
                                                </tbody>
                                            </table>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        </div>

                        <div className="space-y-5">
                            <Card>
                                <CardHeader className="border-b border-slate-100">
                                    <CardTitle className="text-base font-semibold">
                                        Call to Action
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4 pt-3">
                                    <div className="space-y-1">
                                        <Input
                                            label="Link URL"
                                            value={
                                                hasProducts
                                                    ? `/products?banner_id=${banner.id}`
                                                    : data.href
                                            }
                                            onChange={(e) => {
                                                setData('href', e.target.value);
                                                clearErrors('href');
                                            }}
                                            placeholder="/products?sort=..."
                                            error={errors.href}
                                            disabled={hasProducts}
                                        />
                                        <AnimatePresence initial={false}>
                                            {hasProducts && (
                                                <AdminReveal key="has-products">
                                                    <p className="text-xs text-slate-400">
                                                        Auto-set to show this
                                                        banner's products.
                                                        Remove all products to
                                                        enter a custom link.
                                                    </p>
                                                </AdminReveal>
                                            )}
                                        </AnimatePresence>
                                    </div>
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
                                <CardHeader className="border-b border-slate-100">
                                    <CardTitle className="text-base font-semibold">
                                        Settings
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4 pt-3">
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
                            <Save className="mr-2 size-4" />
                            {processing ? 'Saving...' : 'Save Changes'}
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
