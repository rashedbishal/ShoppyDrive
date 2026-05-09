import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Check, Package, Plus, Save, Search, Trash2 } from 'lucide-react';
import { useState, useEffect, useRef } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { ImageUpload } from '@/components/ui/image-upload';
import { cn } from '@/lib/utils';
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

export default function PromotionalBannerEdit({ banner, bannerProducts }: Props) {
    const { data, setData, put, processing, errors } = useForm({
        title: banner.title,
        description: banner.description ?? '',
        image: banner.image as File | string | null,
        href: banner.href ?? '',
        badge: banner.badge ?? '',
        accent: banner.accent ?? 'from-secondary/90 via-secondary/70 to-secondary/20',
        is_active: banner.is_active,
        sort_order: String(banner.sort_order),
        products: bannerProducts.map(p => p.id),
    });

    const [selectedProducts, setSelectedProducts] = useState<SimpleProduct[]>(bannerProducts);
    const [searchResults, setSearchResults] = useState<SimpleProduct[]>([]);
    const [searchQuery, setSearchQuery] = useState('');
    const [searching, setSearching] = useState(false);
    const searchRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const handleClickOutside = (e: MouseEvent) => {
            if (searchRef.current && !searchRef.current.contains(e.target as Node)) {
                setSearchResults([]);
            }
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    useEffect(() => {
        if (!searchQuery.trim()) {
            setSearchResults([]);
            return;
        }

        const controller = new AbortController();
        const timeoutId = setTimeout(async () => {
            setSearching(true);
            try {
                const res = await fetch(
                    `/admin/promotional-banners/products/search?q=${encodeURIComponent(searchQuery)}`,
                    { signal: controller.signal, headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } },
                );
                if (res.ok) setSearchResults(await res.json());
            } catch {
                // aborted or network error
            } finally {
                setSearching(false);
            }
        }, 300);

        return () => { clearTimeout(timeoutId); controller.abort(); };
    }, [searchQuery]);

    const addProduct = (product: SimpleProduct) => {
        if (selectedProducts.some(p => p.id === product.id)) return;
        const next = [...selectedProducts, product];
        setSelectedProducts(next);
        setData('products', next.map(p => p.id));
        setSearchQuery('');
        setSearchResults([]);
    };

    const removeProduct = (productId: number) => {
        const next = selectedProducts.filter(p => p.id !== productId);
        setSelectedProducts(next);
        setData('products', next.map(p => p.id));
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route('admin.promotional-banners.update', banner.id));
    };

    const hasProducts = selectedProducts.length > 0;

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
                            <div className="space-y-1">
                                <Input
                                    label="Link URL"
                                    value={hasProducts ? `/products?banner_id=${banner.id}` : data.href}
                                    onChange={e => setData('href', e.target.value)}
                                    placeholder="/products?sort=..."
                                    error={errors.href}
                                    disabled={hasProducts}
                                />
                                {hasProducts && (
                                    <p className="text-xs text-slate-400">
                                        Auto-set to show this banner's products. Remove all products to enter a custom link.
                                    </p>
                                )}
                            </div>
                            <Input
                                label="Tailwind Accent Gradient"
                                value={data.accent}
                                onChange={e => setData('accent', e.target.value)}
                                placeholder="from-secondary/90 via-secondary/70 to-secondary/20"
                                error={errors.accent}
                            />
                        </CardContent>
                    </Card>

                    {/* Product Picker */}
                    <Card>
                        <CardHeader className="border-b border-slate-100 pb-4 flex flex-row items-center justify-between">
                            <CardTitle className="text-base font-semibold flex items-center gap-2">
                                <Package className="size-4 text-emerald-500" />
                                Banner Products
                            </CardTitle>
                            <div ref={searchRef} className="relative w-72">
                                <Search className="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-slate-400" />
                                <Input
                                    placeholder="Search products..."
                                    className="pl-9 h-9"
                                    value={searchQuery}
                                    onChange={e => setSearchQuery(e.target.value)}
                                />
                                {(searching || searchResults.length > 0) && (
                                    <div className="absolute top-full left-0 right-0 mt-1 bg-white border border-slate-200 rounded-lg shadow-lg z-50 max-h-72 overflow-y-auto">
                                        {searching && (
                                            <p className="px-4 py-3 text-sm text-slate-400">Searching...</p>
                                        )}
                                        {!searching && searchResults.map(product => {
                                            const alreadyAdded = selectedProducts.some(p => p.id === product.id);
                                            return (
                                                <button
                                                    key={product.id}
                                                    type="button"
                                                    disabled={alreadyAdded}
                                                    className={cn(
                                                        'w-full text-left px-4 py-2.5 border-b border-slate-100 last:border-0 flex items-center justify-between gap-3',
                                                        alreadyAdded
                                                            ? 'opacity-50 cursor-not-allowed bg-slate-50'
                                                            : 'hover:bg-slate-50 group cursor-pointer',
                                                    )}
                                                    onClick={() => !alreadyAdded && addProduct(product)}
                                                >
                                                    <div className="min-w-0 flex-1">
                                                        <p className="text-sm font-medium text-slate-900 truncate">{product.name}</p>
                                                        <p className="text-xs text-slate-500">৳{product.price}</p>
                                                    </div>
                                                    {alreadyAdded
                                                        ? <Check className="size-4 shrink-0 text-emerald-500" />
                                                        : <Plus className="size-4 shrink-0 text-slate-400 group-hover:text-slate-700" />
                                                    }
                                                </button>
                                            );
                                        })}
                                    </div>
                                )}
                            </div>
                        </CardHeader>
                        <CardContent className="pt-6">
                            {selectedProducts.length === 0 ? (
                                <div className="text-center py-10 bg-slate-50 rounded-xl border-2 border-dashed border-slate-200">
                                    <Package className="size-10 text-slate-300 mx-auto mb-3" />
                                    <p className="text-slate-500">No products added yet.</p>
                                    <p className="text-xs text-slate-400">Search and add products — "Explore now" will link to them.</p>
                                </div>
                            ) : (
                                <div className="border border-slate-200 rounded-lg overflow-hidden">
                                    <table className="w-full text-sm">
                                        <thead className="bg-slate-50 border-b border-slate-200">
                                            <tr>
                                                <th className="text-left px-4 py-3 font-semibold text-slate-700">Product</th>
                                                <th className="text-right px-4 py-3 font-semibold text-slate-700 w-28">Price</th>
                                                <th className="w-12" />
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-100">
                                            {selectedProducts.map(product => (
                                                <tr key={product.id} className="hover:bg-slate-50/50 transition">
                                                    <td className="px-4 py-3 font-medium text-slate-900">{product.name}</td>
                                                    <td className="px-4 py-3 text-right text-slate-600">৳{Number(product.price).toFixed(0)}</td>
                                                    <td className="px-4 py-3 text-right">
                                                        <button
                                                            type="button"
                                                            onClick={() => removeProduct(product.id)}
                                                            className="p-1.5 text-slate-400 hover:text-red-500 rounded-lg hover:bg-red-50 transition"
                                                        >
                                                            <Trash2 className="size-4" />
                                                        </button>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
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
