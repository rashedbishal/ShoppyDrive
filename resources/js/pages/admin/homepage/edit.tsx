import { Head, Link, useForm } from '@inertiajs/react';
import { AnimatePresence } from 'framer-motion';
import {
    ArrowLeft,
    Award,
    Check,
    Package,
    Plus,
    Save,
    Search,
    ShoppingBag,
    Star,
    Trash2,
} from 'lucide-react';
import { useState, useEffect, useRef } from 'react';
import { AdminTableRow } from '@/components/admin/admin-motion';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import type { HomepageSetting, Product } from '@/types';

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
    products?: Product[];
    featuredProducts?: SimpleProduct[];
    sectionProducts?: SimpleProduct[];
    sectionBrands?: SimpleBrand[];
};

export default function HomepageEdit({
    section,
    featuredProducts,
    sectionProducts,
    sectionBrands,
}: Props) {
    const isAnnouncementBar = section.section_key === 'announcement_bar';
    const isHeroSlider = section.section_key === 'hero_slider';
    const isPromotionalBanners = section.section_key === 'promotional_banners';
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
        metadata: section.metadata ?? {},
        featured_products: (featuredProducts ?? []).map((p) => ({
            id: p.id,
            name: p.name,
            price: p.price,
        })),
        section_products: (sectionProducts ?? []).map((p) => ({
            id: p.id,
            name: p.name,
            price: p.price,
        })),
        section_brands: (sectionBrands ?? []).map((b) => ({
            id: b.id,
            name: b.name,
            logo: b.logo,
        })),
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
            if (
                searchContainerRef.current &&
                !searchContainerRef.current.contains(e.target as Node)
            ) {
                setSearchResults([]);
            }
        };
        document.addEventListener('mousedown', handleClickOutside);

        return () =>
            document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    useEffect(() => {
        const handleClickOutside = (e: MouseEvent) => {
            if (
                brandSearchRef.current &&
                !brandSearchRef.current.contains(e.target as Node)
            ) {
                setBrandResults([]);
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
                    `/admin/homepage/products/search?q=${encodeURIComponent(searchQuery)}`,
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
                // aborted or network error — leave results as-is
            } finally {
                setSearching(false);
            }
        }, 300);

        return () => {
            clearTimeout(timeoutId);
            controller.abort();
        };
    }, [searchQuery]);

    useEffect(() => {
        if (!brandQuery.trim()) {
            // eslint-disable-next-line react-hooks/set-state-in-effect -- clears results at once when the query is emptied; fetched results are set asynchronously
            setBrandResults([]);

            return;
        }

        const controller = new AbortController();
        const timeoutId = setTimeout(async () => {
            setSearchingBrands(true);

            try {
                const res = await fetch(
                    `/admin/homepage/brands/search?q=${encodeURIComponent(brandQuery)}`,
                    {
                        signal: controller.signal,
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    },
                );

                if (res.ok) {
                    setBrandResults(await res.json());
                }
            } catch {
                // aborted
            } finally {
                setSearchingBrands(false);
            }
        }, 300);

        return () => {
            clearTimeout(timeoutId);
            controller.abort();
        };
    }, [brandQuery]);

    const addFeaturedProduct = (product: Product) => {
        if (data.featured_products.some((p) => p.id === product.id)) {
            return;
        }

        setData('featured_products', [
            ...data.featured_products,
            { id: product.id, name: product.name, price: product.price },
        ]);
        setSearchQuery('');
        setSearchResults([]);
    };

    const removeFeaturedProduct = (productId: number) => {
        setData(
            'featured_products',
            data.featured_products.filter((p) => p.id !== productId),
        );
    };

    const addSectionProduct = (product: Product) => {
        if (data.section_products.some((p) => p.id === product.id)) {
            return;
        }

        setData('section_products', [
            ...data.section_products,
            { id: product.id, name: product.name, price: product.price },
        ]);
        setSearchQuery('');
        setSearchResults([]);
    };

    const removeSectionProduct = (productId: number) => {
        setData(
            'section_products',
            data.section_products.filter((p) => p.id !== productId),
        );
    };

    const addSectionBrand = (brand: SimpleBrand) => {
        if (data.section_brands.some((b) => b.id === brand.id)) {
            return;
        }

        setData('section_brands', [
            ...data.section_brands,
            { id: brand.id, name: brand.name, logo: brand.logo },
        ]);
        setBrandQuery('');
        setBrandResults([]);
    };

    const removeSectionBrand = (brandId: number) => {
        setData(
            'section_brands',
            data.section_brands.filter((b) => b.id !== brandId),
        );
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route('admin.homepage.update', section.id));
    };

    return (
        <>
            <Head title={`Edit ${section.section_name} — Admin`} />
            <div className="space-y-5">
                <div className="flex items-center gap-4">
                    <Button
                        asChild
                        variant="ghost"
                        size="icon"
                        className="rounded-full"
                    >
                        <Link href={route('admin.homepage.index')}>
                            <ArrowLeft className="size-5" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900">
                            Edit Section: {section.section_name}
                        </h1>
                        <p className="text-sm text-slate-500">
                            Customize the appearance and visibility of this
                            homepage section.
                        </p>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="space-y-5">
                    <div className="grid grid-cols-1 gap-5 lg:grid-cols-[minmax(0,1fr)_320px]">
                        <div className="space-y-5">
                            {isAnnouncementBar ? (
                                <Card>
                                    <CardHeader className="border-b border-slate-100">
                                        <CardTitle className="text-base font-semibold">
                                            Announcement Bar Content
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-4 pt-3">
                                        <Input
                                            maxLength={120}
                                            showCount
                                            label="Announcement Message"
                                            value={data.title}
                                            onChange={(e) =>
                                                setData('title', e.target.value)
                                            }
                                            placeholder="e.g. Free delivery on orders over ৳999"
                                            error={errors.title}
                                        />
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <Input
                                                maxLength={30}
                                                showCount
                                                label="Phone Number"
                                                value={
                                                    data.metadata.phone ?? ''
                                                }
                                                onChange={(e) =>
                                                    setData('metadata', {
                                                        ...data.metadata,
                                                        phone: e.target.value,
                                                    })
                                                }
                                                placeholder="e.g. +880 1234-567890"
                                                error={
                                                    (
                                                        errors as Record<
                                                            string,
                                                            string
                                                        >
                                                    )['metadata.phone']
                                                }
                                            />
                                            <Input
                                                maxLength={150}
                                                showCount
                                                label="Email Address"
                                                type="email"
                                                value={
                                                    data.metadata.email ?? ''
                                                }
                                                onChange={(e) =>
                                                    setData('metadata', {
                                                        ...data.metadata,
                                                        email: e.target.value,
                                                    })
                                                }
                                                placeholder="e.g. support@shoppy.com"
                                                error={
                                                    (
                                                        errors as Record<
                                                            string,
                                                            string
                                                        >
                                                    )['metadata.email']
                                                }
                                            />
                                        </div>
                                        <Input
                                            label="Free Delivery Minimum Amount (৳)"
                                            type="number"
                                            min={0}
                                            max={99999999.99}
                                            value={String(
                                                data.metadata
                                                    .free_delivery_amount ?? '',
                                            )}
                                            onChange={(e) =>
                                                setData('metadata', {
                                                    ...data.metadata,
                                                    free_delivery_amount: e
                                                        .target.value
                                                        ? Number(e.target.value)
                                                        : '',
                                                })
                                            }
                                            placeholder="e.g. 999"
                                            error={
                                                (
                                                    errors as Record<
                                                        string,
                                                        string
                                                    >
                                                )[
                                                    'metadata.free_delivery_amount'
                                                ]
                                            }
                                        />
                                    </CardContent>
                                </Card>
                            ) : isHeroSlider ? (
                                <Card>
                                    <CardHeader className="border-b border-slate-100">
                                        <CardTitle className="text-base font-semibold">
                                            Hero Slider Settings
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-4 pt-3">
                                        <p className="text-sm text-slate-500">
                                            Slide content (images, titles,
                                            descriptions, CTAs) is managed under{' '}
                                            <Link
                                                href={route(
                                                    'admin.hero-slides.index',
                                                )}
                                                className="font-medium text-slate-700 underline underline-offset-2"
                                            >
                                                Hero Slides
                                            </Link>
                                            . This page only controls whether
                                            the slider is shown and how fast it
                                            rotates.
                                        </p>
                                        <Input
                                            label="Autoplay Interval (seconds)"
                                            type="number"
                                            min={2}
                                            max={30}
                                            value={String(
                                                data.metadata
                                                    .autoplay_seconds ?? 6,
                                            )}
                                            onChange={(e) =>
                                                setData('metadata', {
                                                    ...data.metadata,
                                                    autoplay_seconds: e.target
                                                        .value
                                                        ? Number(e.target.value)
                                                        : '',
                                                })
                                            }
                                            hint="How long each slide stays on screen before advancing to the next one."
                                            error={
                                                (
                                                    errors as Record<
                                                        string,
                                                        string
                                                    >
                                                )['metadata.autoplay_seconds']
                                            }
                                        />
                                    </CardContent>
                                </Card>
                            ) : isPromotionalBanners ? (
                                <Card>
                                    <CardHeader className="border-b border-slate-100">
                                        <CardTitle className="text-base font-semibold">
                                            Promotional Banners
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="pt-3">
                                        <p className="text-sm text-slate-500">
                                            Banner content (images, titles,
                                            links) is managed under{' '}
                                            <Link
                                                href={route(
                                                    'admin.promotional-banners.index',
                                                )}
                                                className="font-medium text-slate-700 underline underline-offset-2"
                                            >
                                                Promotional Banners
                                            </Link>
                                            . This page only controls whether
                                            the banners are shown on the
                                            homepage.
                                        </p>
                                    </CardContent>
                                </Card>
                            ) : (
                                <Card>
                                    <CardHeader className="border-b border-slate-100">
                                        <CardTitle className="text-base font-semibold">
                                            Section Content
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-4 pt-3">
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <Input
                                                maxLength={60}
                                                showCount
                                                label="Eyebrow (optional)"
                                                value={data.eyebrow}
                                                onChange={(e) =>
                                                    setData(
                                                        'eyebrow',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="e.g. Featured"
                                                error={errors.eyebrow}
                                            />
                                            <Input
                                                maxLength={120}
                                                showCount
                                                label="Title"
                                                value={data.title}
                                                onChange={(e) =>
                                                    setData(
                                                        'title',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="e.g. Featured Products"
                                                error={errors.title}
                                            />
                                        </div>
                                        {!isFlashSale && (
                                            <Textarea
                                                maxLength={300}
                                                showCount
                                                label="Description (optional)"
                                                value={data.description}
                                                onChange={(e) =>
                                                    setData(
                                                        'description',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="Brief text shown below the title"
                                                error={errors.description}
                                                rows={4}
                                            />
                                        )}
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <Input
                                                maxLength={40}
                                                showCount
                                                label="Action Button Label"
                                                value={data.action_label}
                                                onChange={(e) =>
                                                    setData(
                                                        'action_label',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="e.g. View All"
                                                error={errors.action_label}
                                            />
                                            <Input
                                                maxLength={255}
                                                showCount
                                                label="Action Button Link"
                                                value={data.action_href}
                                                onChange={(e) =>
                                                    setData(
                                                        'action_href',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="e.g. /products"
                                                error={errors.action_href}
                                            />
                                        </div>
                                    </CardContent>
                                </Card>
                            )}

                            {isFeaturedProducts && (
                                <Card>
                                    <CardHeader className="flex flex-col gap-3 border-b border-slate-100 sm:flex-row sm:items-center sm:justify-between">
                                        <CardTitle className="flex items-center gap-2 text-base font-semibold">
                                            <Star className="size-4 fill-amber-500 text-amber-500" />
                                            Featured Products
                                        </CardTitle>
                                        <div
                                            ref={searchContainerRef}
                                            className="relative w-full sm:w-72"
                                        >
                                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                                            <Input
                                                maxLength={100}
                                                placeholder="Search products..."
                                                className="h-9 pl-9"
                                                value={searchQuery}
                                                onChange={(e) =>
                                                    setSearchQuery(
                                                        e.target.value,
                                                    )
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
                                                                    data.featured_products.some(
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
                                                                            addFeaturedProduct(
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
                                        {data.featured_products.length === 0 ? (
                                            <div className="rounded-xl border-2 border-dashed border-slate-200 bg-slate-50 py-10 text-center">
                                                <Star className="mx-auto mb-3 size-10 text-slate-300" />
                                                <p className="text-slate-500">
                                                    No featured products yet.
                                                </p>
                                                <p className="text-xs text-slate-400">
                                                    Search and add products to
                                                    feature them on the
                                                    homepage.
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
                                                            {data.featured_products.map(
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
                                                                                    removeFeaturedProduct(
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
                            )}

                            {isProductSection && (
                                <Card>
                                    <CardHeader className="flex flex-col gap-3 border-b border-slate-100 sm:flex-row sm:items-center sm:justify-between">
                                        <CardTitle className="flex items-center gap-2 text-base font-semibold">
                                            {isNewArrivals ? (
                                                <Package className="size-4 text-emerald-500" />
                                            ) : (
                                                <Award className="size-4 text-amber-500" />
                                            )}
                                            {isNewArrivals
                                                ? 'New Arrival Products'
                                                : 'Best Selling Products'}
                                        </CardTitle>
                                        <div
                                            ref={searchContainerRef}
                                            className="relative w-full sm:w-72"
                                        >
                                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                                            <Input
                                                maxLength={100}
                                                placeholder="Search products..."
                                                className="h-9 pl-9"
                                                value={searchQuery}
                                                onChange={(e) =>
                                                    setSearchQuery(
                                                        e.target.value,
                                                    )
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
                                                                    data.section_products.some(
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
                                                                            addSectionProduct(
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
                                        {data.section_products.length === 0 ? (
                                            <div className="rounded-xl border-2 border-dashed border-slate-200 bg-slate-50 py-10 text-center">
                                                <ShoppingBag className="mx-auto mb-3 size-10 text-slate-300" />
                                                <p className="text-slate-500">
                                                    No products added yet.
                                                </p>
                                                <p className="text-xs text-slate-400">
                                                    Search and add products to
                                                    this section.
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
                                                            {data.section_products.map(
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
                                                                                    removeSectionProduct(
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
                            )}

                            {isBrands && (
                                <Card>
                                    <CardHeader className="flex flex-col gap-3 border-b border-slate-100 sm:flex-row sm:items-center sm:justify-between">
                                        <CardTitle className="flex items-center gap-2 text-base font-semibold">
                                            <ShoppingBag className="size-4 text-violet-500" />
                                            Featured Brands
                                        </CardTitle>
                                        <div
                                            ref={brandSearchRef}
                                            className="relative w-full sm:w-72"
                                        >
                                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                                            <Input
                                                maxLength={100}
                                                placeholder="Search brands..."
                                                className="h-9 pl-9"
                                                value={brandQuery}
                                                onChange={(e) =>
                                                    setBrandQuery(
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                            {(searchingBrands ||
                                                brandResults.length > 0) && (
                                                <div className="absolute top-full right-0 left-0 z-50 mt-1 max-h-72 overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg">
                                                    {searchingBrands && (
                                                        <p className="px-4 py-3 text-sm text-slate-400">
                                                            Searching...
                                                        </p>
                                                    )}
                                                    {!searchingBrands &&
                                                        brandResults.map(
                                                            (brand) => {
                                                                const alreadyAdded =
                                                                    data.section_brands.some(
                                                                        (b) =>
                                                                            b.id ===
                                                                            brand.id,
                                                                    );

                                                                return (
                                                                    <button
                                                                        key={
                                                                            brand.id
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
                                                                            addSectionBrand(
                                                                                brand,
                                                                            )
                                                                        }
                                                                    >
                                                                        <p className="flex-1 truncate text-sm font-medium text-slate-900">
                                                                            {
                                                                                brand.name
                                                                            }
                                                                        </p>
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
                                        {data.section_brands.length === 0 ? (
                                            <div className="rounded-xl border-2 border-dashed border-slate-200 bg-slate-50 py-10 text-center">
                                                <ShoppingBag className="mx-auto mb-3 size-10 text-slate-300" />
                                                <p className="text-slate-500">
                                                    No brands added yet.
                                                </p>
                                                <p className="text-xs text-slate-400">
                                                    Search and add brands to
                                                    show in this section.
                                                </p>
                                            </div>
                                        ) : (
                                            <div className="overflow-hidden rounded-lg border border-slate-200">
                                                <table className="w-full text-sm">
                                                    <thead className="border-b border-slate-200 bg-slate-50">
                                                        <tr>
                                                            <th className="px-3 py-3 text-left font-semibold text-slate-700 sm:px-4">
                                                                Brand
                                                            </th>
                                                            <th className="w-12" />
                                                        </tr>
                                                    </thead>
                                                    <tbody className="divide-y divide-slate-100">
                                                        <AnimatePresence
                                                            initial={false}
                                                        >
                                                            {data.section_brands.map(
                                                                (brand) => (
                                                                    <AdminTableRow
                                                                        key={
                                                                            brand.id
                                                                        }
                                                                        className="transition hover:bg-slate-50/50"
                                                                    >
                                                                        <td className="px-3 py-3 font-medium text-slate-900 sm:px-4">
                                                                            {
                                                                                brand.name
                                                                            }
                                                                        </td>
                                                                        <td className="px-3 py-3 text-right sm:px-4">
                                                                            <button
                                                                                type="button"
                                                                                onClick={() =>
                                                                                    removeSectionBrand(
                                                                                        brand.id,
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
                            )}

                            {isFlashSale && (
                                <Card>
                                    <CardHeader className="border-b border-slate-100">
                                        <CardTitle className="text-base font-semibold">
                                            Flash Sale
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="pt-3">
                                        <p className="text-sm text-slate-500">
                                            The sale's schedule, products, and
                                            pricing are managed under{' '}
                                            <Link
                                                href={route(
                                                    'admin.flash-sales.index',
                                                )}
                                                className="font-medium text-slate-700 underline underline-offset-2"
                                            >
                                                Flash Sales
                                            </Link>
                                            . This page only controls the
                                            section heading shown above the sale
                                            and whether it's visible on the
                                            homepage. Note: the homepage always
                                            shows whichever flash sale is
                                            currently active and within its date
                                            range — not necessarily the last one
                                            you edited here.
                                        </p>
                                    </CardContent>
                                </Card>
                            )}
                        </div>

                        <div className="space-y-5">
                            <Card>
                                <CardHeader className="border-b border-slate-100">
                                    <CardTitle className="text-base font-semibold">
                                        Settings
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4 pt-3">
                                    <div className="flex items-center justify-between">
                                        <div className="space-y-0.5">
                                            <p className="text-sm font-medium text-slate-700">
                                                Section Visibility
                                            </p>
                                            <p className="text-xs text-slate-500">
                                                Toggle whether this section is
                                                shown on the storefront.
                                            </p>
                                        </div>
                                        <Switch
                                            checked={data.is_visible}
                                            onCheckedChange={(checked) =>
                                                setData('is_visible', checked)
                                            }
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
                            <Link href={route('admin.homepage.index')}>
                                Cancel
                            </Link>
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}
