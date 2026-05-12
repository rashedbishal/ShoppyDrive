import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Award, Calendar, Check, Package, Plus, Save, Search, ShoppingBag, Star, Trash2, Zap } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import type { Brand, FlashSale, HomepageSetting, Product } from '@/types';
import { useState, useEffect, useRef } from 'react';
import { cn } from '@/lib/utils';

type SimpleProduct = {
    id: number;
    name: string;
    price: number;
};

type SimpleBrand = {
    id: number;
    name: string;
    logo?: string | null;
};

type Props = {
    section: HomepageSetting;
    flashSale?: FlashSale;
    products?: Product[];
    featuredProducts?: SimpleProduct[];
    sectionProducts?: SimpleProduct[];
    sectionBrands?: SimpleBrand[];
};

export default function HomepageEdit({ section, flashSale, products: initialProducts, featuredProducts, sectionProducts, sectionBrands }: Props) {
    const isAnnouncementBar = section.section_key === 'announcement_bar';
    const isFlashSale = section.section_key === 'flash_sale';
    const isFeaturedProducts = section.section_key === 'featured_products';
    const isNewArrivals = section.section_key === 'new_arrivals';
    const isBestSellers = section.section_key === 'best_sellers';
    const isBrands = section.section_key === 'brands';
    const isProductSection = isNewArrivals || isBestSellers;

    const { data, setData, put, processing, errors } = useForm({
        title: section.title ?? '',
        description: section.description ?? '',
        eyebrow: section.eyebrow ?? '',
        action_label: section.action_label ?? '',
        action_href: section.action_href ?? '',
        is_visible: section.is_visible,
        sort_order: String(section.sort_order),
        metadata: section.metadata ?? {},
        flash_sale: flashSale ? {
            starts_at: flashSale.starts_at ? new Date(flashSale.starts_at).toISOString().slice(0, 16) : '',
            ends_at: flashSale.ends_at ? new Date(flashSale.ends_at).toISOString().slice(0, 16) : '',
            is_active: flashSale.is_active,
            items: flashSale.items?.map(item => ({
                product_id: item.product_id,
                sale_price: String(item.sale_price),
                stock_limit: String(item.stock_limit ?? 0),
                product: item.product,
            })) ?? [],
        } : null,
        featured_products: (featuredProducts ?? []).map(p => ({ id: p.id, name: p.name, price: p.price })),
        section_products: (sectionProducts ?? []).map(p => ({ id: p.id, name: p.name, price: p.price })),
        section_brands: (sectionBrands ?? []).map(b => ({ id: b.id, name: b.name, logo: b.logo })),
    });

    const [searchResults, setSearchResults] = useState<Product[]>([]);
    const [searchQuery, setSearchQuery] = useState('');
    const [searching, setSearching] = useState(false);
    const searchContainerRef = useRef<HTMLDivElement>(null);

    const [brandResults, setBrandResults] = useState<SimpleBrand[]>([]);
    const [brandQuery, setBrandQuery] = useState('');
    const [searchingBrands, setSearchingBrands] = useState(false);
    const brandSearchRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const handleClickOutside = (e: MouseEvent) => {
            if (searchContainerRef.current && !searchContainerRef.current.contains(e.target as Node)) {
                setSearchResults([]);
            }
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    useEffect(() => {
        const handleClickOutside = (e: MouseEvent) => {
            if (brandSearchRef.current && !brandSearchRef.current.contains(e.target as Node)) {
                setBrandResults([]);
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
                    `/admin/homepage/products/search?q=${encodeURIComponent(searchQuery)}`,
                    { signal: controller.signal, headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } },
                );
                if (res.ok) setSearchResults(await res.json());
            } catch {
                // aborted or network error — leave results as-is
            } finally {
                setSearching(false);
            }
        }, 300);

        return () => { clearTimeout(timeoutId); controller.abort(); };
    }, [searchQuery]);

    useEffect(() => {
        if (!brandQuery.trim()) {
            setBrandResults([]);
            return;
        }
        const controller = new AbortController();
        const timeoutId = setTimeout(async () => {
            setSearchingBrands(true);
            try {
                const res = await fetch(
                    `/admin/homepage/brands/search?q=${encodeURIComponent(brandQuery)}`,
                    { signal: controller.signal, headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } },
                );
                if (res.ok) setBrandResults(await res.json());
            } catch {
                // aborted
            } finally {
                setSearchingBrands(false);
            }
        }, 300);
        return () => { clearTimeout(timeoutId); controller.abort(); };
    }, [brandQuery]);

    const addProduct = (product: Product) => {
        if (data.flash_sale?.items.some(item => item.product_id === product.id)) return;

        setData('flash_sale', {
            ...data.flash_sale!,
            items: [
                ...data.flash_sale!.items,
                {
                    product_id: product.id,
                    sale_price: String(product.price),
                    stock_limit: '0',
                    product,
                },
            ],
        });
        setSearchQuery('');
        setSearchResults([]);
    };

    const removeProduct = (productId: number) => {
        setData('flash_sale', {
            ...data.flash_sale!,
            items: data.flash_sale!.items.filter(item => item.product_id !== productId),
        });
    };

    const addFeaturedProduct = (product: Product) => {
        if (data.featured_products.some(p => p.id === product.id)) return;
        setData('featured_products', [
            ...data.featured_products,
            { id: product.id, name: product.name, price: product.price },
        ]);
        setSearchQuery('');
        setSearchResults([]);
    };

    const removeFeaturedProduct = (productId: number) => {
        setData('featured_products', data.featured_products.filter(p => p.id !== productId));
    };

    const addSectionProduct = (product: Product) => {
        if (data.section_products.some(p => p.id === product.id)) return;
        setData('section_products', [
            ...data.section_products,
            { id: product.id, name: product.name, price: product.price },
        ]);
        setSearchQuery('');
        setSearchResults([]);
    };

    const removeSectionProduct = (productId: number) => {
        setData('section_products', data.section_products.filter(p => p.id !== productId));
    };

    const addSectionBrand = (brand: SimpleBrand) => {
        if (data.section_brands.some(b => b.id === brand.id)) return;
        setData('section_brands', [
            ...data.section_brands,
            { id: brand.id, name: brand.name, logo: brand.logo },
        ]);
        setBrandQuery('');
        setBrandResults([]);
    };

    const removeSectionBrand = (brandId: number) => {
        setData('section_brands', data.section_brands.filter(b => b.id !== brandId));
    };

    const updateItem = (productId: number, field: string, value: string) => {
        setData('flash_sale', {
            ...data.flash_sale!,
            items: data.flash_sale!.items.map(item => 
                item.product_id === productId ? { ...item, [field]: value } : item
            ),
        });
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route('admin.homepage.update', section.id));
    };

    return (
        <>
            <Head title={`Edit ${section.section_name} — Admin`} />
            <div className="space-y-6">
                <div className="flex items-center gap-4">
                    <Button asChild variant="ghost" size="icon" className="rounded-full">
                        <Link href={route('admin.homepage.index')}>
                            <ArrowLeft className="size-5" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900">Edit Section: {section.section_name}</h1>
                        <p className="text-sm text-slate-500">Customize the appearance and visibility of this homepage section.</p>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="max-w-4xl space-y-6 pb-20">
                    <div className="grid gap-6 lg:grid-cols-3">
                        <div className="lg:col-span-2 space-y-6">
                            {isAnnouncementBar ? (
                                <Card>
                                    <CardHeader className="border-b border-slate-100 pb-4">
                                        <CardTitle className="text-base font-semibold">Announcement Bar Content</CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-4 pt-6">
                                        <Input
                                            label="Announcement Message"
                                            value={data.title}
                                            onChange={e => setData('title', e.target.value)}
                                            placeholder="e.g. Free delivery on orders over ৳999"
                                            error={errors.title}
                                        />
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <Input
                                                label="Phone Number"
                                                value={data.metadata.phone ?? ''}
                                                onChange={e => setData('metadata', { ...data.metadata, phone: e.target.value })}
                                                placeholder="e.g. +880 1234-567890"
                                                error={(errors as Record<string, string>)['metadata.phone']}
                                            />
                                            <Input
                                                label="Email Address"
                                                type="email"
                                                value={data.metadata.email ?? ''}
                                                onChange={e => setData('metadata', { ...data.metadata, email: e.target.value })}
                                                placeholder="e.g. support@shoppy.com"
                                                error={(errors as Record<string, string>)['metadata.email']}
                                            />
                                        </div>
                                        <Input
                                            label="Free Delivery Minimum Amount (৳)"
                                            type="number"
                                            value={String(data.metadata.free_delivery_amount ?? '')}
                                            onChange={e => setData('metadata', { ...data.metadata, free_delivery_amount: e.target.value ? Number(e.target.value) : '' })}
                                            placeholder="e.g. 999"
                                            error={(errors as Record<string, string>)['metadata.free_delivery_amount']}
                                        />
                                    </CardContent>
                                </Card>
                            ) : (
                                <Card>
                                    <CardHeader className="border-b border-slate-100 pb-4">
                                        <CardTitle className="text-base font-semibold">Section Content</CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-4 pt-6">
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <Input
                                                label="Eyebrow (optional)"
                                                value={data.eyebrow}
                                                onChange={e => setData('eyebrow', e.target.value)}
                                                placeholder="e.g. Featured"
                                                error={errors.eyebrow}
                                            />
                                            <Input
                                                label="Title"
                                                value={data.title}
                                                onChange={e => setData('title', e.target.value)}
                                                placeholder="e.g. Featured Products"
                                                error={errors.title}
                                            />
                                        </div>
                                        <Textarea
                                            label="Description (optional)"
                                            value={data.description}
                                            onChange={e => setData('description', e.target.value)}
                                            placeholder="Brief text shown below the title"
                                            error={errors.description}
                                            rows={4}
                                        />
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <Input
                                                label="Action Button Label"
                                                value={data.action_label}
                                                onChange={e => setData('action_label', e.target.value)}
                                                placeholder="e.g. View All"
                                                error={errors.action_label}
                                            />
                                            <Input
                                                label="Action Button Link"
                                                value={data.action_href}
                                                onChange={e => setData('action_href', e.target.value)}
                                                placeholder="e.g. /products"
                                                error={errors.action_href}
                                            />
                                        </div>
                                    </CardContent>
                                </Card>
                            )}

                            {isFeaturedProducts && (
                                <Card>
                                    <CardHeader className="border-b border-slate-100 pb-4 flex flex-row items-center justify-between">
                                        <CardTitle className="text-base font-semibold flex items-center gap-2">
                                            <Star className="size-4 text-amber-500 fill-amber-500" />
                                            Featured Products
                                        </CardTitle>
                                        <div ref={searchContainerRef} className="relative w-72">
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
                                                        const alreadyAdded = data.featured_products.some(p => p.id === product.id);
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
                                                                onClick={() => !alreadyAdded && addFeaturedProduct(product)}
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
                                        {data.featured_products.length === 0 ? (
                                            <div className="text-center py-10 bg-slate-50 rounded-xl border-2 border-dashed border-slate-200">
                                                <Star className="size-10 text-slate-300 mx-auto mb-3" />
                                                <p className="text-slate-500">No featured products yet.</p>
                                                <p className="text-xs text-slate-400">Search and add products to feature them on the homepage.</p>
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
                                                        {data.featured_products.map(product => (
                                                            <tr key={product.id} className="hover:bg-slate-50/50 transition">
                                                                <td className="px-4 py-3 font-medium text-slate-900">{product.name}</td>
                                                                <td className="px-4 py-3 text-right text-slate-600">৳{Number(product.price).toFixed(0)}</td>
                                                                <td className="px-4 py-3 text-right">
                                                                    <button
                                                                        type="button"
                                                                        onClick={() => removeFeaturedProduct(product.id)}
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
                            )}

                            {isProductSection && (
                                <Card>
                                    <CardHeader className="border-b border-slate-100 pb-4 flex flex-row items-center justify-between">
                                        <CardTitle className="text-base font-semibold flex items-center gap-2">
                                            {isNewArrivals
                                                ? <Package className="size-4 text-emerald-500" />
                                                : <Award className="size-4 text-amber-500" />}
                                            {isNewArrivals ? 'New Arrival Products' : 'Best Seller Products'}
                                        </CardTitle>
                                        <div ref={searchContainerRef} className="relative w-72">
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
                                                        const alreadyAdded = data.section_products.some(p => p.id === product.id);
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
                                                                onClick={() => !alreadyAdded && addSectionProduct(product)}
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
                                        {data.section_products.length === 0 ? (
                                            <div className="text-center py-10 bg-slate-50 rounded-xl border-2 border-dashed border-slate-200">
                                                <ShoppingBag className="size-10 text-slate-300 mx-auto mb-3" />
                                                <p className="text-slate-500">No products added yet.</p>
                                                <p className="text-xs text-slate-400">Search and add products to this section.</p>
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
                                                        {data.section_products.map(product => (
                                                            <tr key={product.id} className="hover:bg-slate-50/50 transition">
                                                                <td className="px-4 py-3 font-medium text-slate-900">{product.name}</td>
                                                                <td className="px-4 py-3 text-right text-slate-600">৳{Number(product.price).toFixed(0)}</td>
                                                                <td className="px-4 py-3 text-right">
                                                                    <button
                                                                        type="button"
                                                                        onClick={() => removeSectionProduct(product.id)}
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
                            )}

                            {isBrands && (
                                <Card>
                                    <CardHeader className="border-b border-slate-100 pb-4 flex flex-row items-center justify-between">
                                        <CardTitle className="text-base font-semibold flex items-center gap-2">
                                            <ShoppingBag className="size-4 text-violet-500" />
                                            Featured Brands
                                        </CardTitle>
                                        <div ref={brandSearchRef} className="relative w-72">
                                            <Search className="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-slate-400" />
                                            <Input
                                                placeholder="Search brands..."
                                                className="pl-9 h-9"
                                                value={brandQuery}
                                                onChange={e => setBrandQuery(e.target.value)}
                                            />
                                            {(searchingBrands || brandResults.length > 0) && (
                                                <div className="absolute top-full left-0 right-0 mt-1 bg-white border border-slate-200 rounded-lg shadow-lg z-50 max-h-72 overflow-y-auto">
                                                    {searchingBrands && (
                                                        <p className="px-4 py-3 text-sm text-slate-400">Searching...</p>
                                                    )}
                                                    {!searchingBrands && brandResults.map(brand => {
                                                        const alreadyAdded = data.section_brands.some(b => b.id === brand.id);
                                                        return (
                                                            <button
                                                                key={brand.id}
                                                                type="button"
                                                                disabled={alreadyAdded}
                                                                className={cn(
                                                                    'w-full text-left px-4 py-2.5 border-b border-slate-100 last:border-0 flex items-center justify-between gap-3',
                                                                    alreadyAdded
                                                                        ? 'opacity-50 cursor-not-allowed bg-slate-50'
                                                                        : 'hover:bg-slate-50 group cursor-pointer',
                                                                )}
                                                                onClick={() => !alreadyAdded && addSectionBrand(brand)}
                                                            >
                                                                <p className="text-sm font-medium text-slate-900 truncate flex-1">{brand.name}</p>
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
                                        {data.section_brands.length === 0 ? (
                                            <div className="text-center py-10 bg-slate-50 rounded-xl border-2 border-dashed border-slate-200">
                                                <ShoppingBag className="size-10 text-slate-300 mx-auto mb-3" />
                                                <p className="text-slate-500">No brands added yet.</p>
                                                <p className="text-xs text-slate-400">Search and add brands to show in this section.</p>
                                            </div>
                                        ) : (
                                            <div className="border border-slate-200 rounded-lg overflow-hidden">
                                                <table className="w-full text-sm">
                                                    <thead className="bg-slate-50 border-b border-slate-200">
                                                        <tr>
                                                            <th className="text-left px-4 py-3 font-semibold text-slate-700">Brand</th>
                                                            <th className="w-12" />
                                                        </tr>
                                                    </thead>
                                                    <tbody className="divide-y divide-slate-100">
                                                        {data.section_brands.map(brand => (
                                                            <tr key={brand.id} className="hover:bg-slate-50/50 transition">
                                                                <td className="px-4 py-3 font-medium text-slate-900">{brand.name}</td>
                                                                <td className="px-4 py-3 text-right">
                                                                    <button
                                                                        type="button"
                                                                        onClick={() => removeSectionBrand(brand.id)}
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
                            )}

                            {isFlashSale && data.flash_sale && (
                                <Card>
                                    <CardHeader className="border-b border-slate-100 pb-4 flex flex-row items-center justify-between">
                                        <CardTitle className="text-base font-semibold flex items-center gap-2">
                                            <Zap className="size-4 text-amber-500 fill-amber-500" />
                                            Flash Sale Products
                                        </CardTitle>
                                        <div ref={searchContainerRef} className="relative w-72">
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
                                                        const alreadyAdded = data.flash_sale?.items.some(i => i.product_id === product.id) ?? false;
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
                                        <div className="space-y-4">
                                            {data.flash_sale.items.length === 0 ? (
                                                <div className="text-center py-10 bg-slate-50 rounded-xl border-2 border-dashed border-slate-200">
                                                    <Zap className="size-10 text-slate-300 mx-auto mb-3" />
                                                    <p className="text-slate-500">No products added to this flash sale.</p>
                                                    <p className="text-xs text-slate-400">Search and add products from the search bar above.</p>
                                                </div>
                                            ) : (
                                                <div className="border border-slate-200 rounded-lg overflow-hidden">
                                                    <table className="w-full text-sm">
                                                        <thead className="bg-slate-50 border-b border-slate-200">
                                                            <tr>
                                                                <th className="text-left px-4 py-3 font-semibold text-slate-700">Product</th>
                                                                <th className="text-left px-4 py-3 font-semibold text-slate-700 w-32">Sale Price</th>
                                                                <th className="text-left px-4 py-3 font-semibold text-slate-700 w-32">Stock Limit</th>
                                                                <th className="text-right px-4 py-3 font-semibold text-slate-700 w-16"></th>
                                                            </tr>
                                                        </thead>
                                                        <tbody className="divide-y divide-slate-100">
                                                            {data.flash_sale.items.map((item, index) => (
                                                                <tr key={item.product_id} className="hover:bg-slate-50/50 transition">
                                                                    <td className="px-4 py-3">
                                                                        <p className="font-medium text-slate-900">{item.product?.name}</p>
                                                                        <p className="text-xs text-slate-500">Original: ৳{item.product?.price}</p>
                                                                    </td>
                                                                    <td className="px-4 py-3">
                                                                        <div className="relative">
                                                                            <span className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">৳</span>
                                                                            <Input
                                                                                type="number"
                                                                                className="pl-7 h-8 text-sm"
                                                                                value={item.sale_price}
                                                                                onChange={e => updateItem(item.product_id, 'sale_price', e.target.value)}
                                                                            />
                                                                        </div>
                                                                    </td>
                                                                    <td className="px-4 py-3">
                                                                        <Input
                                                                            type="number"
                                                                            className="h-8 text-sm"
                                                                            value={item.stock_limit}
                                                                            onChange={e => updateItem(item.product_id, 'stock_limit', e.target.value)}
                                                                        />
                                                                    </td>
                                                                    <td className="px-4 py-3 text-right">
                                                                        <button
                                                                            type="button"
                                                                            onClick={() => removeProduct(item.product_id)}
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
                                        </div>
                                    </CardContent>
                                </Card>
                            )}
                        </div>

                        <div className="space-y-6">
                            <Card>
                                <CardHeader className="border-b border-slate-100 pb-4">
                                    <CardTitle className="text-base font-semibold">Settings</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-6 pt-6">
                                    <div className="flex items-center justify-between">
                                        <div className="space-y-0.5">
                                            <p className="text-sm font-medium text-slate-700">Section Visibility</p>
                                            <p className="text-xs text-slate-500">Toggle whether this section is shown on the storefront.</p>
                                        </div>
                                        <Switch
                                            checked={data.is_visible}
                                            onCheckedChange={checked => setData('is_visible', checked)}
                                        />
                                    </div>
                                    <div>
                                        <Input
                                            label="Sort Order"
                                            type="number"
                                            value={data.sort_order}
                                            onChange={e => setData('sort_order', e.target.value)}
                                            error={errors.sort_order}
                                        />
                                    </div>
                                </CardContent>
                            </Card>

                            {isFlashSale && data.flash_sale && (
                                <Card>
                                    <CardHeader className="border-b border-slate-100 pb-4">
                                        <CardTitle className="text-base font-semibold flex items-center gap-2">
                                            <Calendar className="size-4" />
                                            Sale Schedule
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-4 pt-6">
                                        <div className="flex items-center justify-between">
                                            <div className="space-y-0.5">
                                                <p className="text-sm font-medium text-slate-700">Sale Active</p>
                                                <p className="text-xs text-slate-500">Enable/disable the entire flash sale.</p>
                                            </div>
                                            <Switch
                                                checked={data.flash_sale.is_active}
                                                onCheckedChange={checked => setData('flash_sale', { ...data.flash_sale!, is_active: checked })}
                                            />
                                        </div>
                                        <Input
                                            label="Starts At"
                                            type="datetime-local"
                                            value={data.flash_sale.starts_at}
                                            onChange={e => setData('flash_sale', { ...data.flash_sale!, starts_at: e.target.value })}
                                            error={(errors as any)['flash_sale.starts_at']}
                                        />
                                        <Input
                                            label="Ends At"
                                            type="datetime-local"
                                            value={data.flash_sale.ends_at}
                                            onChange={e => setData('flash_sale', { ...data.flash_sale!, ends_at: e.target.value })}
                                            error={(errors as any)['flash_sale.ends_at']}
                                        />
                                    </CardContent>
                                </Card>
                            )}

                            <div className="flex flex-col gap-3">
                                <Button type="submit" disabled={processing} className="w-full">
                                    <Save className="size-4 mr-2" />
                                    {processing ? 'Saving...' : 'Save Changes'}
                                </Button>
                                <Button asChild variant="ghost" className="w-full text-slate-500">
                                    <Link href={route('admin.homepage.index')}>Cancel</Link>
                                </Button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </>
    );
}
