import { Head, Link, useForm, usePage } from '@inertiajs/react';
import {
    AnimatePresence,
    motion,
    useReducedMotion,
} from 'framer-motion';
import {
    ArrowRight,
    BadgeCheck,
    ChevronLeft,
    ChevronRight,
    Clock3,
    Flame,
    Heart,
    Pause,
    Play,
    ShoppingBag,
    Sparkles,
    Star,
    Truck,
    Zap,
} from 'lucide-react';
import { useEffect, useMemo, useState, type FormEvent } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { StarRating } from '@/components/ui/star-rating';
import categoryRoutes from '@/routes/category';
import productRoutes from '@/routes/product';
import { STORAGE_KEYS, MOTION } from '@/lib/constants';
import {
    formatCurrency,
    getDiscountPercent,
    getInitials,
    cn,
} from '@/lib/utils';
import { useLocalStorage } from '@/lib/hooks/useLocalStorage';
import { useCartStore } from '@/stores/cartStore';
import { useUiStore } from '@/stores/uiStore';
import { useWishlistStore } from '@/stores/wishlistStore';
import type {
    AppSettings,
    Brand,
    Category,
    Product,
    ProductImage,
    SharedPageProps,
} from '@/types';

type HomeCategoryCard = {
    name: string;
    slug: string;
    description: string;
    image: string;
    productsCount: number;
    accent: string;
};

type HeroStat = {
    label: string;
    value: string;
};

type HeroSlide = {
    id: string;
    eyebrow: string;
    title: string;
    description: string;
    primaryCta: {
        label: string;
        href: string;
    };
    secondaryCta: {
        label: string;
        href: string;
    };
    image: string;
    stats: HeroStat[];
};

type PromotionalBanner = {
    title: string;
    description: string;
    href: string;
    image: string;
    accent: string;
    badge: string;
};

type BrandCard = {
    id: number;
    name: string;
    slug: string;
    accent: string;
    description: string;
};

type ProductSeed = {
    id: number;
    name: string;
    slug: string;
    categorySlug: string;
    brand: string;
    price: number;
    comparePrice: number;
    stockQuantity: number;
    lowStockThreshold: number;
    weight: number;
    image: string;
    secondaryImage: string;
    description: string;
    tags: string[];
    rating: number;
    reviewsCount: number;
    isFeatured?: boolean;
    inFlashSale?: boolean;
    flashSalePrice?: number;
};

type FlashSaleItem = {
    product: Product;
    soldCount: number;
    stockLimit: number;
};

const DEMO_TIMESTAMP = '2026-04-29T12:00:00+06:00';
const HERO_AUTOPLAY_MS = 5000;
const FLASH_SALE_ENDS_AT = '2026-05-03T23:59:59+06:00';

const categoryCards: HomeCategoryCard[] = [
    {
        name: "Men's Clothing",
        slug: 'mens-clothing',
        description: 'T-shirts, shirts, jeans, hoodies and traditional wear.',
        image:
            'https://images.unsplash.com/photo-1489987707025-afc232f7ea0f?auto=format&fit=crop&w=1200&q=80',
        productsCount: 45,
        accent: 'from-sky-500/90 to-indigo-600/90',
    },
    {
        name: "Women's Clothing",
        slug: 'womens-clothing',
        description: 'Dresses, tops, sarees, kurtis and festive fashion.',
        image:
            'https://images.unsplash.com/photo-1483985988355-763728e1935b?auto=format&fit=crop&w=1200&q=80',
        productsCount: 52,
        accent: 'from-rose-500/90 to-amber-500/90',
    },
    {
        name: 'Electronics & Gadgets',
        slug: 'electronics',
        description: 'Earbuds, phone accessories, power banks and smart tech.',
        image:
            'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?auto=format&fit=crop&w=1200&q=80',
        productsCount: 38,
        accent: 'from-cyan-500/90 to-blue-600/90',
    },
    {
        name: 'Home & Living',
        slug: 'home-living',
        description: 'Kitchen appliances, decor, bedding and smart storage.',
        image:
            'https://images.unsplash.com/photo-1484101403633-562f891dc89a?auto=format&fit=crop&w=1200&q=80',
        productsCount: 34,
        accent: 'from-emerald-500/90 to-teal-600/90',
    },
    {
        name: 'Beauty & Health',
        slug: 'beauty-health',
        description: 'Skincare, makeup, grooming and self-care essentials.',
        image:
            'https://images.unsplash.com/photo-1596462502278-27bfdc403348?auto=format&fit=crop&w=1200&q=80',
        productsCount: 28,
        accent: 'from-fuchsia-500/90 to-pink-600/90',
    },
    {
        name: 'Sports & Outdoors',
        slug: 'sports-outdoors',
        description: 'Activewear, sports shoes, gym gear and outdoor accessories.',
        image:
            'https://images.unsplash.com/photo-1461896836934-ffe607ba8211?auto=format&fit=crop&w=1200&q=80',
        productsCount: 22,
        accent: 'from-violet-500/90 to-purple-600/90',
    },
];

const heroSlides: HeroSlide[] = [
    {
        id: 'fashion',
        eyebrow: 'ShoppyDrive — Bangladesh-ready shopping',
        title: 'Style for every occasion — from casual to festive',
        description:
            "Explore men's and women's fashion with premium fabrics, clear pricing, and fast nationwide delivery.",
        primaryCta: {
            label: "Shop Men's Clothing",
            href: '/category/mens-clothing',
        },
        secondaryCta: {
            label: "Women's Collection",
            href: '/category/womens-clothing',
        },
        image:
            'https://images.unsplash.com/photo-1483985988355-763728e1935b?auto=format&fit=crop&w=1800&q=80',
        stats: [
            { label: 'Style drops', value: 'Weekly' },
            { label: 'Returns', value: 'Easy exchange' },
            { label: 'Delivery', value: '24–48h' },
        ],
    },
    {
        id: 'tech',
        eyebrow: 'Gadgets & Electronics',
        title: 'Smart tech and audio gear at honest prices',
        description:
            'Premium earbuds, power banks, and accessories curated for buyers who want confidence before checkout.',
        primaryCta: {
            label: 'Shop Electronics',
            href: '/category/electronics',
        },
        secondaryCta: {
            label: 'View Flash Sale',
            href: '/flash-sales',
        },
        image:
            'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?auto=format&fit=crop&w=1800&q=80',
        stats: [
            { label: 'Payment', value: 'Cash on Delivery' },
            { label: 'Support', value: 'Bangla + English' },
            { label: 'Coverage', value: 'Nationwide' },
        ],
    },
    {
        id: 'home',
        eyebrow: 'Home & Living',
        title: 'Kitchen, decor, and daily essentials for every home',
        description:
            'Practical home goods chosen for utility, longevity, and a polished look that elevates any space.',
        primaryCta: {
            label: 'Shop Home & Living',
            href: '/category/home-living',
        },
        secondaryCta: {
            label: 'Best Sellers',
            href: '/products?sort=popular',
        },
        image:
            'https://images.unsplash.com/photo-1556911220-bff31c812dba?auto=format&fit=crop&w=1800&q=80',
        stats: [
            { label: 'Quality', value: 'Built to last' },
            { label: 'Categories', value: 'Kitchen + Decor' },
            { label: 'Shipping', value: 'Nationwide' },
        ],
    },
];

const promotionalBanners: PromotionalBanner[] = [
    {
        title: 'Refresh your daily essentials',
        description:
            'Smart restocks, curated home items, and low-friction buying for repeat shoppers.',
        href: '/products?sort=newest',
        image:
            'https://images.unsplash.com/photo-1520607162513-77705c0f0d4a?auto=format&fit=crop&w=1400&q=80',
        accent: 'from-secondary/90 via-secondary/70 to-secondary/20',
        badge: 'New arrivals',
    },
    {
        title: 'Upgrade your festival cart',
        description:
            'Premium gifts, standout fashion, and bundle-friendly picks for the season.',
        href: '/products?sort=popular',
        image:
            'https://images.unsplash.com/photo-1512436991641-6745cdb1723f?auto=format&fit=crop&w=1400&q=80',
        accent: 'from-primary/90 via-primary/70 to-primary/20',
        badge: 'Seasonal edit',
    },
];

const brandCards: BrandCard[] = [
    {
        id: 1,
        name: 'Samsung',
        slug: 'samsung',
        accent: 'bg-sky-50 text-sky-700',
        description: 'Flagship phones & displays',
    },
    {
        id: 2,
        name: 'Aarong',
        slug: 'aarong',
        accent: 'bg-amber-50 text-amber-700',
        description: 'Bangladesh-first fashion',
    },
    {
        id: 3,
        name: 'Anker',
        slug: 'anker',
        accent: 'bg-emerald-50 text-emerald-700',
        description: 'Power and audio accessories',
    },
    {
        id: 4,
        name: 'Walton',
        slug: 'walton',
        accent: 'bg-rose-50 text-rose-700',
        description: 'Home appliances & devices',
    },
    {
        id: 5,
        name: 'Nike',
        slug: 'nike',
        accent: 'bg-indigo-50 text-indigo-700',
        description: 'Performance sports & style',
    },
    {
        id: 6,
        name: 'CeraVe',
        slug: 'cerave',
        accent: 'bg-fuchsia-50 text-fuchsia-700',
        description: 'Skincare essentials',
    },
    {
        id: 7,
        name: 'Levi\'s',
        slug: 'levis',
        accent: 'bg-blue-50 text-blue-700',
        description: 'Iconic denim & casual wear',
    },
    {
        id: 8,
        name: 'Fossil',
        slug: 'fossil',
        accent: 'bg-orange-50 text-orange-700',
        description: 'Classic watches & accessories',
    },
];

const productSeeds: ProductSeed[] = [
    {
        id: 101,
        name: 'Samsung Galaxy S24 Ultra 5G',
        slug: 'samsung-galaxy-s24-ultra-5g',
        categorySlug: 'electronics',
        brand: 'Samsung',
        price: 134999,
        comparePrice: 149999,
        stockQuantity: 16,
        lowStockThreshold: 5,
        weight: 0.23,
        image:
            'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=1200&q=80',
        secondaryImage:
            'https://images.unsplash.com/photo-1510557880182-3d4d3cba35a5?auto=format&fit=crop&w=1200&q=80',
        description:
            'Flagship 200MP camera phone with S Pen, titanium frame and all-day battery.',
        tags: ['smartphone', 'flagship', 'android'],
        rating: 4.8,
        reviewsCount: 124,
        isFeatured: true,
        inFlashSale: true,
        flashSalePrice: 129999,
    },
    {
        id: 102,
        name: 'AirGo Pro ANC Wireless Earbuds',
        slug: 'airgo-pro-anc-wireless-earbuds',
        categorySlug: 'electronics',
        brand: 'Anker',
        price: 8990,
        comparePrice: 11990,
        stockQuantity: 42,
        lowStockThreshold: 8,
        weight: 0.08,
        image:
            'https://images.unsplash.com/photo-1590658268037-6bf12165a8df?auto=format&fit=crop&w=1200&q=80',
        secondaryImage:
            'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&w=1200&q=80',
        description:
            'Compact ANC earbuds with 30h battery, punchy bass and USB-C fast charging.',
        tags: ['audio', 'wireless', 'travel'],
        rating: 4.6,
        reviewsCount: 86,
        isFeatured: true,
    },
    {
        id: 103,
        name: 'Premium Linen Panjabi Cream',
        slug: 'premium-linen-panjabi-cream',
        categorySlug: 'mens-clothing',
        brand: 'Aarong',
        price: 3290,
        comparePrice: 3890,
        stockQuantity: 58,
        lowStockThreshold: 10,
        weight: 0.42,
        image:
            'https://images.unsplash.com/photo-1583391733956-6c78276477e2?auto=format&fit=crop&w=1200&q=80',
        secondaryImage:
            'https://images.unsplash.com/photo-1583846712148-b61ecca50c50?auto=format&fit=crop&w=1200&q=80',
        description:
            'Breathable linen panjabi with embroidered collar. Perfect for Eid and weddings.',
        tags: ['fashion', 'menswear', 'traditional'],
        rating: 4.7,
        reviewsCount: 73,
        isFeatured: true,
    },
    {
        id: 104,
        name: 'Heritage Banarasi Silk Saree',
        slug: 'heritage-banarasi-silk-saree',
        categorySlug: 'womens-clothing',
        brand: 'Aarong',
        price: 7990,
        comparePrice: 9990,
        stockQuantity: 24,
        lowStockThreshold: 5,
        weight: 0.58,
        image:
            'https://images.unsplash.com/photo-1614252369475-531eba1372f3?auto=format&fit=crop&w=1200&q=80',
        secondaryImage:
            'https://images.unsplash.com/photo-1611042553365-9b101441c135?auto=format&fit=crop&w=1200&q=80',
        description:
            'Exquisite Banarasi silk saree with zari work for ceremonies and gifting.',
        tags: ['fashion', 'women', 'festive'],
        rating: 4.9,
        reviewsCount: 41,
        isFeatured: true,
    },
    {
        id: 105,
        name: 'Compact Air Fryer 4L',
        slug: 'compact-air-fryer-4l',
        categorySlug: 'home-living',
        brand: 'Walton',
        price: 7990,
        comparePrice: 9990,
        stockQuantity: 19,
        lowStockThreshold: 4,
        weight: 3.2,
        image:
            'https://images.unsplash.com/photo-1556909172-5f7b3f2e6b1c?auto=format&fit=crop&w=1200&q=80',
        secondaryImage:
            'https://images.unsplash.com/photo-1644093549994-3dc2a7a44f5b?auto=format&fit=crop&w=1200&q=80',
        description:
            'Countertop air fryer with 7 presets, rapid air circulation and non-stick basket.',
        tags: ['home', 'kitchen', 'appliance'],
        rating: 4.5,
        reviewsCount: 57,
        isFeatured: true,
        inFlashSale: true,
        flashSalePrice: 7490,
    },
    {
        id: 106,
        name: 'Smart Rice Cooker 1.8L',
        slug: 'smart-rice-cooker-1-8l',
        categorySlug: 'home-living',
        brand: 'Walton',
        price: 5290,
        comparePrice: 6790,
        stockQuantity: 33,
        lowStockThreshold: 6,
        weight: 2.7,
        image:
            'https://images.unsplash.com/photo-1491203659242-5355db3a50e4?auto=format&fit=crop&w=1200&q=80',
        secondaryImage:
            'https://images.unsplash.com/photo-1556911220-bff31c812dba?auto=format&fit=crop&w=1200&q=80',
        description:
            'Multi-function rice cooker with warm-hold and steam basket for busy kitchens.',
        tags: ['home', 'kitchen', 'daily-use'],
        rating: 4.4,
        reviewsCount: 65,
        isFeatured: false,
    },
    {
        id: 107,
        name: 'CeraVe Hydrating Skincare Duo',
        slug: 'cerave-hydrating-skincare-duo',
        categorySlug: 'beauty-health',
        brand: 'CeraVe',
        price: 2890,
        comparePrice: 3590,
        stockQuantity: 70,
        lowStockThreshold: 6,
        weight: 0.31,
        image:
            'https://images.unsplash.com/photo-1571781926291-c477ebfd024b?auto=format&fit=crop&w=1200&q=80',
        secondaryImage:
            'https://images.unsplash.com/photo-1556228578-8c89e6adf883?auto=format&fit=crop&w=1200&q=80',
        description:
            'Gentle hydrating cleanser and moisturizer duo with ceramides and hyaluronic acid.',
        tags: ['beauty', 'skincare', 'self-care'],
        rating: 4.7,
        reviewsCount: 92,
        isFeatured: true,
        inFlashSale: true,
        flashSalePrice: 2490,
    },
    {
        id: 108,
        name: 'Urban Laptop Backpack 30L',
        slug: 'urban-laptop-backpack-30l',
        categorySlug: 'bags-luggage',
        brand: 'Nike',
        price: 4290,
        comparePrice: 5490,
        stockQuantity: 62,
        lowStockThreshold: 8,
        weight: 0.75,
        image:
            'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?auto=format&fit=crop&w=1200&q=80',
        secondaryImage:
            'https://images.unsplash.com/photo-1622560480654-d96214fdc887?auto=format&fit=crop&w=1200&q=80',
        description:
            'Durable 30L backpack with padded 15" laptop sleeve and organizer pockets.',
        tags: ['bags', 'travel', 'laptop'],
        rating: 4.5,
        reviewsCount: 38,
    },
];

function createCategoryEntity(seed: HomeCategoryCard): Category {
    return {
        id: seed.slug.length,
        name: seed.name,
        slug: seed.slug,
        image: seed.image,
        description: seed.description,
        meta_title: `${seed.name} | ShoppyDrive`,
        meta_description: seed.description,
        is_active: true,
        sort_order: 0,
        sub_categories: [],
        products_count: seed.productsCount,
        created_at: DEMO_TIMESTAMP,
        updated_at: DEMO_TIMESTAMP,
        deleted_at: null,
    };
}

function createBrandEntity(name: string, id: number): Brand {
    return {
        id,
        name,
        slug: name.toLowerCase().replace(/\s+/g, '-'),
        logo: null,
        description: `${name} storefront selection`,
        is_active: true,
        created_at: DEMO_TIMESTAMP,
        updated_at: DEMO_TIMESTAMP,
    };
}

function createProductEntity(seed: ProductSeed): Product {
    const catSlug = seed.categorySlug;
    const matchedCard = categoryCards.find((item) => item.slug === catSlug) ??
        categoryCards.find((item) => catSlug.startsWith(item.slug.split('-')[0])) ??
        categoryCards[0];
    const category = createCategoryEntity(matchedCard);
    const brand = createBrandEntity(seed.brand, seed.id + 1000);

    const images: ProductImage[] = [
        {
            id: seed.id * 10,
            product_id: seed.id,
            path: seed.image,
            alt: seed.name,
            sort_order: 1,
            is_primary: true,
            created_at: DEMO_TIMESTAMP,
            updated_at: DEMO_TIMESTAMP,
        },
        {
            id: seed.id * 10 + 1,
            product_id: seed.id,
            path: seed.secondaryImage,
            alt: `${seed.name} alternate view`,
            sort_order: 2,
            is_primary: false,
            created_at: DEMO_TIMESTAMP,
            updated_at: DEMO_TIMESTAMP,
        },
    ];

    return {
        id: seed.id,
        name: seed.name,
        slug: seed.slug,
        sku: `${seed.slug.slice(0, 3).toUpperCase()}-${seed.id}`,
        short_description: seed.description,
        description: seed.description,
        category,
        brand,
        price: seed.price,
        compare_price: seed.comparePrice,
        cost_price: null,
        formatted_price: formatCurrency(seed.price),
        formatted_compare_price: formatCurrency(seed.comparePrice),
        discount_percentage: getDiscountPercent(seed.price, seed.comparePrice),
        stock_quantity: seed.stockQuantity,
        low_stock_threshold: seed.lowStockThreshold,
        is_active: true,
        is_featured: seed.isFeatured ?? false,
        is_digital: false,
        weight: seed.weight,
        meta_title: `${seed.name} | ShoppyDrive`,
        meta_description: seed.description,
        meta_keywords: seed.tags.join(', '),
        published_at: DEMO_TIMESTAMP,
        primary_image_url: seed.image,
        images,
        variants: [],
        attributes: [],
        tags: seed.tags,
        average_rating: seed.rating,
        reviews_count: seed.reviewsCount,
        is_wishlisted: false,
        in_flash_sale: seed.inFlashSale ?? false,
        flash_sale_price: seed.flashSalePrice ?? null,
        created_at: DEMO_TIMESTAMP,
        updated_at: DEMO_TIMESTAMP,
        deleted_at: null,
    };
}

const catalogProducts: Product[] = productSeeds.map((seed) => createProductEntity(seed));

const flashSaleItems: FlashSaleItem[] = [
    {
        product: catalogProducts[0],
        soldCount: 86,
        stockLimit: 100,
    },
    {
        product: catalogProducts[4],
        soldCount: 54,
        stockLimit: 90,
    },
    {
        product: catalogProducts[6],
        soldCount: 72,
        stockLimit: 120,
    },
    {
        product: catalogProducts[1],
        soldCount: 31,
        stockLimit: 64,
    },
];

const flashSale = {
    name: 'Weekend flash sale',
    ends_at: FLASH_SALE_ENDS_AT,
    items: flashSaleItems,
};

const featuredProducts = catalogProducts.filter((product) => product.is_featured);
const newArrivals = [catalogProducts[5], catalogProducts[7], catalogProducts[2], catalogProducts[3]];
const bestSellers = [catalogProducts[0], catalogProducts[6], catalogProducts[4], catalogProducts[1]];

type SectionHeadingProps = {
    eyebrow?: string;
    title: string;
    description?: string;
    actionLabel?: string;
    actionHref?: string;
    className?: string;
};

function SectionHeading({
    eyebrow,
    title,
    description,
    actionLabel,
    actionHref,
    className,
}: SectionHeadingProps): React.JSX.Element {
    return (
        <div className={cn('flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between', className)}>
            <div className="space-y-2">
                {eyebrow ? (
                    <p className="text-xs font-semibold uppercase tracking-[0.24em] text-[var(--color-text-muted)]">
                        {eyebrow}
                    </p>
                ) : null}
                <h2 className="font-display text-3xl font-semibold tracking-tight text-[var(--color-text-primary)] sm:text-4xl">
                    {title}
                </h2>
                {description ? (
                    <p className="max-w-2xl text-sm leading-7 text-[var(--color-text-secondary)] sm:text-base">
                        {description}
                    </p>
                ) : null}
            </div>

            {actionLabel && actionHref ? (
                <Button asChild variant="ghost" className="w-fit px-0 text-[var(--color-primary)] hover:bg-transparent">
                    <Link href={actionHref}>
                        {actionLabel}
                        <ArrowRight className="size-4" />
                    </Link>
                </Button>
            ) : null}
        </div>
    );
}

type HeroSliderProps = {
    slides: HeroSlide[];
};

function HeroSlider({ slides }: HeroSliderProps): React.JSX.Element {
    const prefersReducedMotion = useReducedMotion();
    const [activeIndex, setActiveIndex] = useState(0);
    const [isPaused, setIsPaused] = useState(false);

    useEffect(() => {
        if (prefersReducedMotion || slides.length <= 1 || isPaused) {
            return undefined;
        }

        const intervalId = window.setInterval(() => {
            setActiveIndex((current) => (current + 1) % slides.length);
        }, HERO_AUTOPLAY_MS);

        return () => window.clearInterval(intervalId);
    }, [isPaused, prefersReducedMotion, slides.length]);

    const goToSlide = (index: number): void => {
        setActiveIndex(index);
    };

    const goToPrevious = (): void => {
        setActiveIndex((current) => (current === 0 ? slides.length - 1 : current - 1));
    };

    const goToNext = (): void => {
        setActiveIndex((current) => (current + 1) % slides.length);
    };

    const activeSlide = slides[activeIndex];

    return (
        <section
            className="group relative overflow-hidden rounded-[2rem] border border-[var(--color-border)] bg-[var(--color-secondary)] text-white shadow-[var(--shadow-modal)]"
            onMouseEnter={() => setIsPaused(true)}
            onMouseLeave={() => setIsPaused(false)}
            aria-label="Homepage hero banner"
        >
            <div className="relative min-h-[60vh] lg:min-h-[75vh]">
                <AnimatePresence mode="wait" initial={false}>
                    <motion.div
                        key={activeSlide.id}
                        className="absolute inset-0"
                        initial={{ opacity: 0, scale: 1.02 }}
                        animate={{ opacity: 1, scale: 1 }}
                        exit={{ opacity: 0, scale: 1.02 }}
                        transition={{ duration: MOTION.modal.duration, ease: 'easeOut' }}
                    >
                        <img
                            src={activeSlide.image}
                            alt=""
                            aria-hidden="true"
                            className="absolute inset-0 h-full w-full object-cover"
                            loading="eager"
                            decoding="async"
                        />
                        <div className="absolute inset-0 bg-gradient-to-r from-[var(--color-secondary)] via-[rgba(26,26,46,0.76)] to-[rgba(26,26,46,0.12)]" />
                        <div className="absolute inset-0 bg-gradient-to-t from-[rgba(26,26,46,0.7)] via-transparent to-transparent lg:bg-none" />
                        <div className="relative z-10 flex h-full flex-col justify-between px-6 py-8 sm:px-10 sm:py-10 lg:px-14 lg:py-14">
                            <motion.div
                                className="max-w-3xl space-y-5"
                                initial="hidden"
                                animate="visible"
                                variants={{
                                    hidden: { opacity: 0, y: 12 },
                                    visible: {
                                        opacity: 1,
                                        y: 0,
                                        transition: {
                                            staggerChildren: MOTION.stagger,
                                        },
                                    },
                                }}
                            >
                                <motion.div
                                    variants={{
                                        hidden: { opacity: 0, y: 12 },
                                        visible: { opacity: 1, y: 0 },
                                    }}
                                >
                                    <Badge
                                        variant="amber"
                                        className="border-0 bg-[rgba(245,166,35,0.16)] text-amber-100"
                                    >
                                        <Sparkles className="size-3.5" />
                                        {activeSlide.eyebrow}
                                    </Badge>
                                </motion.div>

                                <motion.h1
                                    variants={{
                                        hidden: { opacity: 0, y: 12 },
                                        visible: { opacity: 1, y: 0 },
                                    }}
                                    className="font-display text-4xl leading-[1.05] tracking-tight sm:text-5xl lg:max-w-2xl lg:text-6xl"
                                >
                                    {activeSlide.title}
                                </motion.h1>

                                <motion.p
                                    variants={{
                                        hidden: { opacity: 0, y: 12 },
                                        visible: { opacity: 1, y: 0 },
                                    }}
                                    className="max-w-2xl text-sm leading-7 text-white/80 sm:text-base lg:text-lg"
                                >
                                    {activeSlide.description}
                                </motion.p>

                                <motion.div
                                    variants={{
                                        hidden: { opacity: 0, y: 12 },
                                        visible: { opacity: 1, y: 0 },
                                    }}
                                    className="flex flex-col gap-3 sm:flex-row"
                                >
                                    <Button asChild size="lg" className="bg-white text-[var(--color-secondary)] hover:bg-white/90">
                                        <Link href={activeSlide.primaryCta.href}>
                                            {activeSlide.primaryCta.label}
                                            <ArrowRight className="size-4" />
                                        </Link>
                                    </Button>
                                    <Button
                                        asChild
                                        size="lg"
                                        variant="outline"
                                        className="border-white/20 bg-white/10 text-white hover:bg-white/20 hover:text-white"
                                    >
                                        <Link href={activeSlide.secondaryCta.href}>
                                            {activeSlide.secondaryCta.label}
                                        </Link>
                                    </Button>
                                </motion.div>

                                <motion.div
                                    variants={{
                                        hidden: { opacity: 0, y: 12 },
                                        visible: { opacity: 1, y: 0 },
                                    }}
                                    className="grid gap-3 sm:grid-cols-3"
                                >
                                    {activeSlide.stats.map((stat) => (
                                        <div
                                            key={stat.label}
                                            className="rounded-2xl border border-white/10 bg-white/10 px-4 py-3 backdrop-blur-md"
                                        >
                                            <p className="text-xs uppercase tracking-[0.2em] text-white/60">
                                                {stat.label}
                                            </p>
                                            <p className="mt-1 text-sm font-semibold text-white">
                                                {stat.value}
                                            </p>
                                        </div>
                                    ))}
                                </motion.div>
                            </motion.div>
                        </div>
                    </motion.div>
                </AnimatePresence>

                {slides.length > 1 ? (
                    <>
                        <button
                            type="button"
                            onClick={goToPrevious}
                            aria-label="Previous hero slide"
                            className="absolute left-4 top-1/2 z-20 hidden -translate-y-1/2 rounded-full border border-white/20 bg-white/10 p-3 text-white opacity-0 backdrop-blur-md transition hover:bg-white/20 group-hover:opacity-100 lg:inline-flex"
                        >
                            <ChevronLeft className="size-5" />
                        </button>
                        <button
                            type="button"
                            onClick={goToNext}
                            aria-label="Next hero slide"
                            className="absolute right-4 top-1/2 z-20 hidden -translate-y-1/2 rounded-full border border-white/20 bg-white/10 p-3 text-white opacity-0 backdrop-blur-md transition hover:bg-white/20 group-hover:opacity-100 lg:inline-flex"
                        >
                            <ChevronRight className="size-5" />
                        </button>
                    </>
                ) : null}

                <div className="absolute bottom-4 left-4 z-20 flex items-center gap-2 rounded-full border border-white/10 bg-white/10 px-3 py-2 text-xs text-white/90 backdrop-blur-md sm:bottom-6 sm:left-6">
                    <Truck className="size-4 text-[var(--color-accent)]" />
                    Nationwide delivery across Bangladesh
                </div>

                <button
                    type="button"
                    onClick={() => setIsPaused((current) => !current)}
                    aria-label={isPaused ? 'Resume hero autoplay' : 'Pause hero autoplay'}
                    className="absolute bottom-4 right-4 z-20 inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/10 px-3 py-2 text-xs text-white/90 backdrop-blur-md transition hover:bg-white/15 sm:bottom-6 sm:right-6"
                >
                    {isPaused ? <Play className="size-4" /> : <Pause className="size-4" />}
                    {isPaused ? 'Resume' : 'Pause'}
                </button>
            </div>

            <div className="flex flex-wrap items-center justify-between gap-3 border-t border-white/10 px-5 py-4 sm:px-6">
                <div className="flex items-center gap-2">
                    {slides.map((slide, index) => {
                        const isActive = index === activeIndex;

                        return (
                            <button
                                key={slide.id}
                                type="button"
                                onClick={() => goToSlide(index)}
                                aria-label={`Go to slide ${index + 1}`}
                                aria-pressed={isActive}
                                className={cn(
                                    'h-2.5 rounded-full transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/80 focus-visible:ring-offset-2 focus-visible:ring-offset-[var(--color-secondary)]',
                                    isActive ? 'w-10 bg-white' : 'w-2.5 bg-white/40 hover:bg-white/60',
                                )}
                            />
                        );
                    })}
                </div>

                <div className="text-xs text-white/70">
                    {String(activeIndex + 1).padStart(2, '0')} / {String(slides.length).padStart(2, '0')}
                </div>
            </div>
        </section>
    );
}

type CategoryCardProps = {
    category: HomeCategoryCard;
};

function CategoryCard({ category }: CategoryCardProps): React.JSX.Element {
    return (
        <Card className="group overflow-hidden border-[var(--color-border)] bg-[var(--color-surface)] p-0 shadow-[var(--shadow-card)] transition-transform duration-200 hover:-translate-y-1 hover:shadow-[var(--shadow-card-hover)]">
            <Link href={categoryRoutes.show.url(category.slug)} className="block h-full">
                <div className="relative aspect-square overflow-hidden">
                    <img
                        src={category.image}
                        alt={category.name}
                        loading="lazy"
                        decoding="async"
                        className="h-full w-full object-cover transition duration-500 group-hover:scale-105"
                    />
                    <div className={cn('absolute inset-0 bg-gradient-to-t from-[rgba(26,26,46,0.92)] via-[rgba(26,26,46,0.4)] to-transparent', category.accent)} />
                    <div className="absolute inset-0 bg-black/10 transition duration-300 group-hover:bg-black/20" />
                    <div className="absolute inset-x-0 bottom-0 p-5 text-white">
                        <div className="inline-flex items-center rounded-full border border-white/20 bg-white/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.22em] text-white/90">
                            {category.productsCount} products
                        </div>
                        <h3 className="mt-3 text-xl font-semibold tracking-tight">
                            {category.name}
                        </h3>
                        <p className="mt-1 text-sm leading-6 text-white/80">
                            {category.description}
                        </p>
                    </div>
                </div>
            </Link>
        </Card>
    );
}

type ProductCardProps = {
    product: Product;
    className?: string;
    saleProgress?: number;
    saleProgressLabel?: string;
};

function ProductCard({
    product,
    className,
    saleProgress,
    saleProgressLabel,
}: ProductCardProps): React.JSX.Element {
    const addItem = useCartStore((state) => state.addItem);
    const addToast = useUiStore((state) => state.addToast);
    const openCart = useUiStore((state) => state.openCart);
    const toggleWishlist = useWishlistStore((state) => state.toggleWishlist);
    const wishlisted = useWishlistStore((state) => state.isWishlisted(product.id));
    const [isHovered, setIsHovered] = useState(false);

    const imageSet = product.images.length > 0 ? product.images : [];
    const primaryImage = imageSet.find((image) => image.is_primary) ?? imageSet[0];
    const secondaryImage = imageSet.find((image) => !image.is_primary);
    const primaryUrl = primaryImage?.path ?? product.primary_image_url ?? '';
    const secondaryUrl = secondaryImage?.path ?? '';
    const discount = product.discount_percentage ?? getDiscountPercent(product.price, product.compare_price ?? 0);
    const currentPrice =
        product.in_flash_sale && typeof product.flash_sale_price === 'number'
            ? product.flash_sale_price
            : product.price;
    const isOutOfStock = product.stock_quantity <= 0;
    const isLowStock = !isOutOfStock && product.stock_quantity <= product.low_stock_threshold;

    const handleAddToCart = (): void => {
        if (isOutOfStock) {
            addToast({
                type: 'warning',
                title: 'Out of stock',
                message: `${product.name} is currently unavailable.`,
            });
            return;
        }

        addItem({ product, quantity: 1 });
        openCart();
        addToast({
            type: 'success',
            title: 'Added to cart',
            message: `${product.name} was added to your cart.`,
        });
    };

    const handleWishlist = (): void => {
        toggleWishlist(product.id);
        addToast({
            type: wishlisted ? 'info' : 'success',
            title: wishlisted ? 'Removed from wishlist' : 'Saved to wishlist',
            message: wishlisted
                ? `${product.name} removed from your wishlist.`
                : `${product.name} added to your wishlist.`,
        });
    };

    return (
        <motion.article
            className={cn(
                'group overflow-hidden rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)] shadow-[var(--shadow-card)] transition-transform duration-200 hover:shadow-[var(--shadow-card-hover)]',
                className,
            )}
            whileHover={{ y: -4, scale: 1.01 }}
            transition={{ duration: MOTION.cardHover.duration, ease: 'easeOut' }}
            onHoverStart={() => setIsHovered(true)}
            onHoverEnd={() => setIsHovered(false)}
        >
            <div className="relative">
                <Link href={productRoutes.show.url(product.slug)} className="block">
                    <div className="relative aspect-[4/3] overflow-hidden bg-[var(--color-surface-alt)]">
                        {primaryUrl ? (
                            <img
                                src={primaryUrl}
                                alt={product.name}
                                loading="lazy"
                                decoding="async"
                                className={cn(
                                    'h-full w-full object-cover transition duration-500',
                                    isHovered && secondaryUrl ? 'opacity-0 scale-105' : 'opacity-100',
                                )}
                            />
                        ) : (
                            <div className="flex h-full items-center justify-center bg-[var(--color-surface-alt)] text-[var(--color-text-muted)]">
                                <ShoppingBag className="size-12" />
                            </div>
                        )}

                        {secondaryUrl ? (
                            <img
                                src={secondaryUrl}
                                alt=""
                                aria-hidden="true"
                                loading="lazy"
                                decoding="async"
                                className={cn(
                                    'absolute inset-0 h-full w-full object-cover transition duration-500',
                                    isHovered ? 'opacity-100 scale-105' : 'opacity-0',
                                )}
                            />
                        ) : null}

                        <div className="absolute inset-0 bg-gradient-to-t from-black/10 via-transparent to-transparent" />

                        {product.in_flash_sale ? (
                            <Badge className="absolute left-4 top-4 bg-[var(--color-accent)] text-[var(--color-secondary)]">
                                FLASH {discount > 0 ? `${discount}%` : 'DEAL'}
                            </Badge>
                        ) : discount > 0 ? (
                            <Badge variant="amber" className="absolute left-4 top-4">
                                SALE {discount}%
                            </Badge>
                        ) : null}

                        <button
                            type="button"
                            onClick={(event) => {
                                event.preventDefault();
                                event.stopPropagation();
                                handleWishlist();
                            }}
                            aria-label={wishlisted ? `Remove ${product.name} from wishlist` : `Add ${product.name} to wishlist`}
                            className={cn(
                                'absolute right-4 top-4 inline-flex size-10 items-center justify-center rounded-full border border-[var(--color-border)] bg-white/95 shadow-sm transition hover:scale-105',
                                wishlisted ? 'text-[var(--color-primary)]' : 'text-[var(--color-text-secondary)]',
                            )}
                        >
                            <Heart className={cn('size-4', wishlisted ? 'fill-current' : '')} />
                        </button>

                        {isOutOfStock ? (
                            <div className="absolute inset-0 flex items-center justify-center bg-[rgba(26,26,46,0.6)] text-white">
                                <Badge variant="error" className="border-0 bg-[rgba(220,38,38,0.95)] text-white">
                                    Out of stock
                                </Badge>
                            </div>
                        ) : null}
                    </div>
                </Link>
            </div>

            <div className="space-y-4 p-4">
                <div className="space-y-1">
                    <p className="text-xs font-semibold uppercase tracking-[0.22em] text-[var(--color-text-muted)]">
                        {product.brand?.name ?? 'ShoppyDrive Select'}
                    </p>
                    <Link
                        href={productRoutes.show.url(product.slug)}
                        className="line-clamp-2 text-sm font-semibold leading-6 text-[var(--color-text-primary)] transition hover:text-[var(--color-primary)]"
                    >
                        {product.name}
                    </Link>
                </div>

                <div className="flex items-center gap-2">
                    <StarRating rating={product.average_rating ?? 0} size="sm" className="text-[var(--color-accent)]" />
                    <span className="text-xs text-[var(--color-text-secondary)]">
                        ({product.reviews_count ?? 0})
                    </span>
                </div>

                <div className="flex items-end gap-2">
                    <p className="text-lg font-bold tracking-tight text-[var(--color-primary)]">
                        {formatCurrency(currentPrice)}
                    </p>
                    {product.compare_price && product.compare_price > currentPrice ? (
                        <p className="pb-0.5 text-sm text-[var(--color-text-muted)] line-through">
                            {formatCurrency(product.compare_price)}
                        </p>
                    ) : null}
                </div>

                <div className="space-y-2">
                    <div className="flex items-center justify-between text-xs text-[var(--color-text-secondary)]">
                        <span>
                            {isOutOfStock
                                ? 'Out of stock'
                                : isLowStock
                                    ? `Only ${product.stock_quantity} left`
                                    : 'In stock'}
                        </span>
                        {saleProgressLabel ? <span>{saleProgressLabel}</span> : null}
                    </div>
                    {typeof saleProgress === 'number' ? (
                        <div
                            className="h-2 overflow-hidden rounded-full bg-[var(--color-surface-alt)]"
                            aria-label={`${saleProgressLabel ?? 'Sale progress'} ${saleProgress}%`}
                        >
                            <div
                                className="h-full rounded-full bg-[var(--color-accent)] transition-all"
                                style={{ width: `${Math.max(0, Math.min(100, saleProgress))}%` }}
                            />
                        </div>
                    ) : null}
                </div>

                <Button
                    type="button"
                    fullWidth
                    disabled={isOutOfStock}
                    onClick={handleAddToCart}
                    className="translate-y-0 transition-transform duration-200 group-hover:-translate-y-1"
                >
                    {isOutOfStock ? 'Out of Stock' : 'Add to Cart'}
                </Button>
            </div>
        </motion.article>
    );
}

function FlashSaleCard({
    product,
    saleProgress,
    saleProgressLabel,
}: {
    product: Product;
    saleProgress?: number;
    saleProgressLabel?: string;
}): React.JSX.Element {
    const addItem = useCartStore((state) => state.addItem);
    const addToast = useUiStore((state) => state.addToast);
    const openCart = useUiStore((state) => state.openCart);
    const toggleWishlist = useWishlistStore((state) => state.toggleWishlist);
    const wishlisted = useWishlistStore((state) => state.isWishlisted(product.id));
    const [isHovered, setIsHovered] = useState(false);

    const imageSet = product.images.length > 0 ? product.images : [];
    const primaryImage = imageSet.find((img) => img.is_primary) ?? imageSet[0];
    const secondaryImage = imageSet.find((img) => !img.is_primary);
    const primaryUrl = primaryImage?.path ?? product.primary_image_url ?? '';
    const secondaryUrl = secondaryImage?.path ?? '';
    const discount = product.discount_percentage ?? getDiscountPercent(product.price, product.compare_price ?? 0);
    const currentPrice =
        product.in_flash_sale && typeof product.flash_sale_price === 'number'
            ? product.flash_sale_price
            : product.price;
    const isOutOfStock = product.stock_quantity <= 0;
    const isLowStock = !isOutOfStock && product.stock_quantity <= product.low_stock_threshold;

    const handleAddToCart = (): void => {
        if (isOutOfStock) {
            addToast({ type: 'warning', title: 'Out of stock', message: `${product.name} is currently unavailable.` });
            return;
        }
        addItem({ product, quantity: 1 });
        openCart();
        addToast({ type: 'success', title: 'Added to cart', message: `${product.name} was added to your cart.` });
    };

    const handleWishlist = (): void => {
        toggleWishlist(product.id);
        addToast({
            type: wishlisted ? 'info' : 'success',
            title: wishlisted ? 'Removed from wishlist' : 'Saved to wishlist',
            message: wishlisted ? `${product.name} removed.` : `${product.name} saved to wishlist.`,
        });
    };

    return (
        <motion.article
            className="group flex h-full flex-col sm:flex-row overflow-hidden rounded-xl bg-white shadow-md transition-shadow hover:shadow-lg"
            whileHover={{ y: -2 }}
            transition={{ duration: 0.18, ease: 'easeOut' }}
            onHoverStart={() => setIsHovered(true)}
            onHoverEnd={() => setIsHovered(false)}
        >
            {/* Image */}
            <Link href={productRoutes.show.url(product.slug)} className="relative aspect-square sm:aspect-auto sm:w-36 shrink-0 overflow-hidden bg-gray-100">
                {primaryUrl ? (
                    <img
                        src={primaryUrl}
                        alt={product.name}
                        loading="lazy"
                        decoding="async"
                        className={cn(
                            'h-full w-full object-cover transition duration-500',
                            isHovered && secondaryUrl ? 'opacity-0 scale-105' : 'opacity-100',
                        )}
                    />
                ) : (
                    <div className="flex h-full items-center justify-center text-gray-300">
                        <ShoppingBag className="size-8" />
                    </div>
                )}
                {secondaryUrl && (
                    <img
                        src={secondaryUrl}
                        alt=""
                        aria-hidden="true"
                        loading="lazy"
                        decoding="async"
                        className={cn(
                            'absolute inset-0 h-full w-full object-cover transition duration-500',
                            isHovered ? 'opacity-100 scale-105' : 'opacity-0',
                        )}
                    />
                )}

                {/* Discount badge — inside image, bottom-left */}
                {discount > 0 && (
                    <span className="absolute bottom-2 left-2 rounded-md bg-[#c8102e] px-1.5 py-0.5 text-[11px] font-black leading-none text-white">
                        -{discount}%
                    </span>
                )}

                {isOutOfStock && (
                    <div className="absolute inset-0 flex items-center justify-center bg-black/40">
                        <span className="rounded bg-red-600 px-2 py-0.5 text-[10px] font-bold uppercase text-white">Sold Out</span>
                    </div>
                )}
            </Link>

            {/* Content */}
            <div className="flex min-w-0 flex-1 flex-col gap-1.5 p-3">

                {/* Brand + wishlist */}
                <div className="flex items-center justify-between">
                    <span className="truncate text-[10px] font-semibold uppercase tracking-widest text-slate-400">
                        {product.brand?.name ?? 'ShoppyDrive'}
                    </span>
                    <button
                        type="button"
                        onClick={(e) => { e.stopPropagation(); handleWishlist(); }}
                        aria-label={wishlisted ? 'Remove from wishlist' : 'Save to wishlist'}
                        className={cn('ml-1 shrink-0 transition', wishlisted ? 'text-[#c8102e]' : 'text-slate-300 hover:text-[#c8102e]')}
                    >
                        <Heart className={cn('size-3.5', wishlisted ? 'fill-current' : '')} />
                    </button>
                </div>

                {/* Name */}
                <Link
                    href={productRoutes.show.url(product.slug)}
                    className="line-clamp-2 text-[13px] font-semibold leading-snug text-slate-900 transition hover:text-[#c8102e]"
                >
                    {product.name}
                </Link>

                {/* Rating */}
                <div className="flex items-center gap-1">
                    <StarRating rating={product.average_rating ?? 0} size="sm" className="text-amber-400" />
                    <span className="text-[10px] text-slate-400">({product.reviews_count ?? 0})</span>
                </div>

                {/* Price */}
                <div className="flex flex-wrap items-baseline gap-x-1.5 gap-y-0.5">
                    <span className="text-[15px] font-black text-[#c8102e]">{formatCurrency(currentPrice)}</span>
                    {product.compare_price && product.compare_price > currentPrice && (
                        <span className="text-xs text-slate-400 line-through">{formatCurrency(product.compare_price)}</span>
                    )}
                </div>

                {/* Progress bar + labels */}
                {typeof saleProgress === 'number' && (
                    <div className="space-y-1">
                        <div className="h-1.5 w-full overflow-hidden rounded-full bg-gray-100">
                            <div
                                className="h-full rounded-full bg-gradient-to-r from-amber-400 to-orange-500"
                                style={{ width: `${Math.max(5, Math.min(100, saleProgress))}%` }}
                            />
                        </div>
                        <div className="flex items-center justify-between text-[10px]">
                            {isLowStock ? (
                                <span className="flex items-center gap-0.5 font-semibold text-orange-500">
                                    <Flame className="size-3 fill-orange-400" /> Only {product.stock_quantity} left
                                </span>
                            ) : (
                                <span className="text-slate-400">In stock</span>
                            )}
                            {saleProgressLabel && <span className="text-slate-400">{saleProgressLabel}</span>}
                        </div>
                    </div>
                )}

                {/* Button */}
                <button
                    type="button"
                    disabled={isOutOfStock}
                    onClick={handleAddToCart}
                    className={cn(
                        'mt-auto flex h-8 w-full items-center justify-center gap-1.5 rounded-lg text-xs font-semibold transition-all',
                        isOutOfStock
                            ? 'cursor-not-allowed bg-gray-100 text-gray-400'
                            : 'bg-[#c8102e] text-white hover:bg-[#a50d25] active:scale-[0.98]',
                    )}
                >
                    <ShoppingBag className="size-3.5" />
                    {isOutOfStock ? 'Sold Out' : 'Add to Cart'}
                </button>
            </div>
        </motion.article>
    );
}

type ProductGridProps = {
    products: Product[];
    loading?: boolean;
    emptyTitle: string;
    emptyDescription: string;
    emptyHref?: string;
    emptyActionLabel?: string;
    className?: string;
    saleProgressMap?: Map<number, { label: string; value: number }>;
};

function ProductGrid({
    products,
    loading = false,
    emptyTitle,
    emptyDescription,
    emptyHref,
    emptyActionLabel,
    className,
    saleProgressMap,
}: ProductGridProps): React.JSX.Element {
    if (loading) {
        return (
            <div className={cn('grid grid-cols-2 gap-3 md:gap-4 sm:grid-cols-3 xl:grid-cols-4', className)}>
                {Array.from({ length: 4 }, (_, index) => (
                    <ProductCardSkeleton key={index} />
                ))}
            </div>
        );
    }

    if (products.length === 0) {
        return (
            <EmptyState
                icon={ShoppingBag}
                title={emptyTitle}
                description={emptyDescription}
                action={
                    emptyHref && emptyActionLabel
                        ? {
                              label: emptyActionLabel,
                              href: emptyHref,
                          }
                        : undefined
                }
            />
        );
    }

    return (
        <div className={cn('grid grid-cols-2 gap-3 md:gap-4 sm:grid-cols-3 xl:grid-cols-4', className)}>
            {products.map((product) => {
                const saleProgress = saleProgressMap?.get(product.id);

                return (
                    <ProductCard
                        key={product.id}
                        product={product}
                        saleProgress={saleProgress?.value}
                        saleProgressLabel={saleProgress?.label}
                    />
                );
            })}
        </div>
    );
}

type ProductRailProps = {
    title?: string;
    products: Product[];
    loading?: boolean;
    emptyTitle: string;
    emptyDescription: string;
    emptyHref?: string;
    emptyActionLabel?: string;
    saleProgressMap?: Map<number, { label: string; value: number }>;
};

function ProductRail({
    title,
    products,
    loading = false,
    emptyTitle,
    emptyDescription,
    emptyHref,
    emptyActionLabel,
    saleProgressMap,
}: ProductRailProps): React.JSX.Element {
    if (loading) {
        return (
            <div className="grid grid-flow-col auto-cols-[minmax(11rem,1fr)] gap-3 md:gap-4 overflow-x-auto pb-2 lg:grid-flow-row lg:grid-cols-4 lg:overflow-visible">
                {Array.from({ length: 4 }, (_, index) => (
                    <div key={index} className="min-w-[11rem] lg:min-w-0">
                        <ProductCardSkeleton />
                    </div>
                ))}
            </div>
        );
    }

    if (products.length === 0) {
        return (
            <EmptyState
                icon={ShoppingBag}
                title={emptyTitle}
                description={emptyDescription}
                action={
                    emptyHref && emptyActionLabel
                        ? {
                              label: emptyActionLabel,
                              href: emptyHref,
                          }
                        : undefined
                }
            />
        );
    }

    return (
        <div className="space-y-4">
            {title ? (
                <h3 className="text-lg font-semibold text-[var(--color-text-primary)]">
                    {title}
                </h3>
            ) : null}
            <div className="grid grid-flow-col auto-cols-[minmax(11rem,1fr)] gap-3 md:gap-4 overflow-x-auto pb-2 lg:grid-flow-row lg:grid-cols-4 lg:overflow-visible">
                {products.map((product) => {
                    const saleProgress = saleProgressMap?.get(product.id);

                    return (
                        <div key={product.id} className="min-w-[11rem] lg:min-w-0">
                            <ProductCard
                                product={product}
                                saleProgress={saleProgress?.value}
                                saleProgressLabel={saleProgress?.label}
                            />
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

function ProductCardSkeleton(): React.JSX.Element {
    return (
        <Card className="overflow-hidden border-[var(--color-border)] bg-[var(--color-surface)] p-0 shadow-[var(--shadow-card)]">
            <div className="space-y-4 p-4">
                <Skeleton className="aspect-[4/3] w-full rounded-2xl" />
                <div className="space-y-2">
                    <Skeleton className="h-3 w-24" />
                    <Skeleton className="h-4 w-full" />
                    <Skeleton className="h-4 w-4/5" />
                </div>
                <div className="flex items-center gap-2">
                    <Skeleton className="h-4 w-20" />
                    <Skeleton className="h-3 w-12" />
                </div>
                <div className="flex items-end gap-2">
                    <Skeleton className="h-6 w-24" />
                    <Skeleton className="h-4 w-16" />
                </div>
                <Skeleton className="h-11 w-full rounded-xl" />
            </div>
        </Card>
    );
}

type BrandTileProps = {
    brand: BrandCard;
};

function BrandTile({ brand }: BrandTileProps): React.JSX.Element {
    return (
        <Link
            href={`/brands?brand=${brand.slug}`}
            className="group flex min-w-[13rem] items-center gap-4 rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)] px-4 py-4 shadow-[var(--shadow-card)] transition hover:-translate-y-1 hover:shadow-[var(--shadow-card-hover)]"
        >
            <div
                className={cn(
                    'flex size-14 items-center justify-center rounded-2xl text-lg font-bold transition group-hover:scale-105',
                    brand.accent,
                )}
                aria-hidden="true"
            >
                {getInitials(brand.name)}
            </div>
            <div className="min-w-0">
                <p className="truncate text-base font-semibold text-[var(--color-text-primary)]">
                    {brand.name}
                </p>
                <p className="truncate text-sm text-[var(--color-text-secondary)]">
                    {brand.description}
                </p>
            </div>
        </Link>
    );
}

type FlashSaleCountdownProps = {
    endsAt: string;
};

function FlashSaleCountdown({ endsAt }: FlashSaleCountdownProps): React.JSX.Element {
    const [now, setNow] = useState(() => new Date());

    useEffect(() => {
        const intervalId = window.setInterval(() => {
            setNow(new Date());
        }, 1000);

        return () => window.clearInterval(intervalId);
    }, []);

    const remaining = useMemo(() => {
        const end = new Date(endsAt).getTime();
        const current = now.getTime();
        const difference = Math.max(0, end - current);

        const totalSeconds = Math.floor(difference / 1000);
        const days = Math.floor(totalSeconds / 86_400);
        const hours = Math.floor((totalSeconds % 86_400) / 3_600);
        const minutes = Math.floor((totalSeconds % 3_600) / 60);
        const seconds = totalSeconds % 60;

        return {
            difference,
            days,
            hours,
            minutes,
            seconds,
        };
    }, [endsAt, now]);

    if (remaining.difference <= 0) {
        return (
            <span className="font-mono text-base font-semibold text-[var(--color-accent)]">
                Sale ended
            </span>
        );
    }

    const segments = [
        { label: 'days', value: remaining.days },
        { label: 'hours', value: remaining.hours },
        { label: 'minutes', value: remaining.minutes },
        { label: 'seconds', value: remaining.seconds },
    ];

    return (
        <div className="flex flex-wrap items-center gap-2 font-mono text-sm font-semibold text-[var(--color-accent)]">
            {segments.map((segment) => (
                <span
                    key={segment.label}
                    className="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-2"
                >
                    <span className="text-base text-white">{String(segment.value).padStart(2, '0')}</span>
                    <span className="text-xs uppercase tracking-[0.2em] text-white/70">
                        {segment.label}
                    </span>
                </span>
            ))}
        </div>
    );
}

function NewsletterForm(): React.JSX.Element {
    const form = useForm<{ email: string }>({
        email: '',
    });

    const handleSubmit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        form.post('/newsletter/subscribe', {
            preserveScroll: true,
            onSuccess: () => {
                form.reset('email');
            },
        });
    };

    return (
        <Card className="overflow-hidden border-[var(--color-border)] bg-[var(--color-secondary)] text-white shadow-[var(--shadow-card-hover)]">
            <CardContent className="grid gap-8 px-6 py-6 lg:grid-cols-[1.1fr_0.9fr] lg:items-center lg:px-8 lg:py-8">
                <div className="space-y-4">
                    <div className="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.22em] text-white/75">
                        <Sparkles className="size-3.5 text-[var(--color-accent)]" />
                        Newsletter
                    </div>
                    <h3 className="font-display text-3xl font-semibold tracking-tight sm:text-4xl">
                        Product drops, sale alerts, and delivery updates in one place.
                    </h3>
                    <p className="max-w-2xl text-sm leading-7 text-white/75 sm:text-base">
                        Join the mailing list for curated launch alerts, seasonal promotions,
                        and practical shopping reminders.
                    </p>
                </div>

                <form className="space-y-4" onSubmit={handleSubmit}>
                    <Input
                        id="newsletter-home-email"
                        type="email"
                        label="Email address"
                        autoComplete="email"
                        placeholder="you@example.com"
                        value={form.data.email}
                        onChange={(event) => form.setData('email', event.target.value)}
                        error={form.errors.email}
                        containerClassName="space-y-2"
                        labelClassName="text-white"
                        className="border-white/10 bg-white text-[var(--color-text-primary)] placeholder:text-[var(--color-text-muted)]"
                    />

                    <Button
                        type="submit"
                        fullWidth
                        loading={form.processing}
                        loadingText="Subscribing..."
                        className="bg-white text-[var(--color-secondary)] hover:bg-white/90"
                    >
                        Subscribe
                    </Button>

                    <p
                        className={cn(
                            'min-h-5 text-sm text-white/75',
                            form.recentlySuccessful ? 'text-emerald-300' : '',
                        )}
                        aria-live="polite"
                    >
                        {form.recentlySuccessful
                            ? 'Thanks. You are subscribed to updates.'
                            : 'Unsubscribe anytime. No spam, only useful updates.'}
                    </p>
                </form>
            </CardContent>
        </Card>
    );
}

function Home(): React.JSX.Element {
    const page = usePage<SharedPageProps>();
    const settings: AppSettings = page.props.settings;
    const [recentlyViewedSlugs] = useLocalStorage<string[]>(
        STORAGE_KEYS.recentlyViewed,
        [],
    );

    const recentlyViewedProducts = useMemo(() => {
        if (recentlyViewedSlugs.length === 0) {
            return [];
        }

        const lookup = new Map(catalogProducts.map((product) => [product.slug, product]));

        return recentlyViewedSlugs
            .map((slug) => lookup.get(slug))
            .filter((product): product is Product => product !== undefined);
    }, [recentlyViewedSlugs]);

    const flashSaleProgressMap = useMemo(() => {
        return new Map(
            flashSale.items.map((item) => [
                item.product.id,
                {
                    label: `${item.soldCount} sold`,
                    value: Math.round((item.soldCount / item.stockLimit) * 100),
                },
            ]),
        );
    }, []);

    return (
        <>
            <Head title="Premium BDT shopping" />

            <div className="space-y-16 py-8 lg:py-12">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <HeroSlider slides={heroSlides} />
                </div>

                {flashSale.items.length > 0 ? (
                    <section className="bg-[var(--color-secondary)] py-12 text-white lg:py-16">
                        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                            {/* Header row */}
                            <div className="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                                <div className="space-y-2">
                                    <div className="flex items-center gap-2">
                                        <Badge className="w-fit bg-[var(--color-accent)] text-[var(--color-secondary)]">
                                            <Zap className="size-3.5 fill-current" />
                                            Flash sale
                                        </Badge>
                                    </div>
                                    <h2 className="text-2xl font-bold tracking-tight sm:text-3xl">{flashSale.name}</h2>
                                    <FlashSaleCountdown endsAt={flashSale.ends_at} />
                                </div>
                                <div className="flex shrink-0 flex-wrap gap-2">
                                    <Button asChild size="sm" className="bg-white text-[var(--color-secondary)] hover:bg-white/90">
                                        <Link href="/flash-sales">
                                            Shop all
                                            <ArrowRight className="size-3.5" />
                                        </Link>
                                    </Button>
                                    <Button asChild size="sm" variant="outline" className="border-white/20 bg-white/10 text-white hover:bg-white/20 hover:text-white">
                                        <Link href="/products?sort=popular">Best sellers</Link>
                                    </Button>
                                </div>
                            </div>

                            {/* Cards grid */}
                            <div className="grid grid-cols-2 gap-3 md:gap-4 sm:grid-cols-3 xl:grid-cols-4">
                                {flashSale.items.map((item) => {
                                    const progress = flashSaleProgressMap.get(item.product.id);
                                    return (
                                        <FlashSaleCard
                                            key={item.product.id}
                                            product={item.product}
                                            saleProgress={progress?.value}
                                            saleProgressLabel={progress?.label}
                                        />
                                    );
                                })}
                            </div>
                        </div>
                    </section>
                ) : null}

                <section className="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
                    <SectionHeading
                        eyebrow="Categories"
                        title="Shop by category"
                        description="Curated entry points to the Bangladesh shopping experience, organized around the categories shoppers reach for most."
                        actionLabel="View all categories"
                        actionHref="/products"
                    />

                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        {categoryCards.map((category) => (
                            <CategoryCard key={category.slug} category={category} />
                        ))}
                    </div>
                </section>

                <section className="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
                    <SectionHeading
                        eyebrow="Featured"
                        title="Featured products"
                        description="Balanced merchandising that keeps the page premium, readable, and focused on conversion."
                        actionLabel="View all products"
                        actionHref="/products"
                    />

                    <ProductGrid
                        products={featuredProducts}
                        emptyTitle="No featured products"
                        emptyDescription="Feature products in the admin panel to surface them here."
                        emptyHref="/products"
                        emptyActionLabel="Browse products"
                    />
                </section>

                <section className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="grid gap-4 lg:grid-cols-2">
                        {promotionalBanners.map((banner) => (
                            <Link
                                key={banner.title}
                                href={banner.href}
                                className="group relative overflow-hidden rounded-[2rem] border border-[var(--color-border)] bg-[var(--color-secondary)] shadow-[var(--shadow-card)]"
                            >
                                <img
                                    src={banner.image}
                                    alt={banner.title}
                                    loading="lazy"
                                    decoding="async"
                                    className="h-full min-h-[18rem] w-full object-cover transition duration-500 group-hover:scale-105"
                                />
                                <div className={cn('absolute inset-0 bg-gradient-to-r', banner.accent)} />
                                <div className="absolute inset-0 bg-gradient-to-t from-black/40 via-black/10 to-transparent" />
                                <div className="absolute inset-0 flex flex-col justify-end p-6 text-white sm:p-8">
                                    <Badge className="mb-4 w-fit bg-white/10 text-white backdrop-blur-md">
                                        {banner.badge}
                                    </Badge>
                                    <h3 className="font-display text-3xl font-semibold tracking-tight sm:text-4xl">
                                        {banner.title}
                                    </h3>
                                    <p className="mt-2 max-w-lg text-sm leading-7 text-white/80 sm:text-base">
                                        {banner.description}
                                    </p>
                                    <div className="mt-5 inline-flex items-center gap-2 text-sm font-semibold text-white">
                                        Explore now
                                        <ArrowRight className="size-4 transition group-hover:translate-x-1" />
                                    </div>
                                </div>
                            </Link>
                        ))}
                    </div>
                </section>

                <section className="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
                    <SectionHeading
                        eyebrow="Arrivals"
                        title="New arrivals"
                        description="Fresh inventory, updated merchandising, and products that feel new the moment they land."
                        actionLabel="See newest"
                        actionHref="/products?sort=newest"
                    />

                    <ProductRail
                        title="Recently added"
                        products={newArrivals}
                        emptyTitle="No new arrivals yet"
                        emptyDescription="Use the admin inventory flow to publish the latest assortment."
                        emptyHref="/products"
                        emptyActionLabel="Browse products"
                    />
                </section>

                <section className="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
                    <SectionHeading
                        eyebrow="Brands"
                        title="Our brands"
                        description="A quieter section with grayscale brand treatment, letting shoppers scan premium labels without visual noise."
                        actionLabel="Explore brands"
                        actionHref="/brands"
                    />

                    <div className="flex gap-4 overflow-x-auto pb-2">
                        {brandCards.map((brand) => (
                            <BrandTile key={brand.id} brand={brand} />
                        ))}
                    </div>
                </section>

                <section className="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
                    <SectionHeading
                        eyebrow="Best sellers"
                        title="Best sellers"
                        description="Products with proven demand, strong ratings, and enough detail to justify the purchase quickly."
                        actionLabel="Shop best sellers"
                        actionHref="/products?sort=popular"
                    />

                    <ProductRail
                        products={bestSellers}
                        emptyTitle="No best sellers yet"
                        emptyDescription="The best sellers rail will populate once product data is connected."
                        emptyHref="/products"
                        emptyActionLabel="Browse products"
                    />
                </section>

                {recentlyViewedProducts.length > 0 ? (
                    <section className="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
                        <SectionHeading
                            eyebrow="History"
                            title="Recently viewed"
                            description="Saved locally on the shopper's device so they can return to products they checked earlier."
                            actionLabel="Clear local history"
                            actionHref="/products"
                        />

                        <ProductRail
                            products={recentlyViewedProducts}
                            emptyTitle="No recently viewed products"
                            emptyDescription="Viewed products will appear here after the shopper visits product detail pages."
                            emptyHref="/products"
                            emptyActionLabel="Browse products"
                        />
                    </section>
                ) : null}

                <section className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <NewsletterForm />
                </section>

                <section className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <Card className="border-[var(--color-border)] bg-[var(--color-surface)] shadow-[var(--shadow-card)]">
                        <CardContent className="grid gap-4 px-6 py-6 sm:grid-cols-3 sm:items-center sm:px-8">
                            <div className="flex items-center gap-3">
                                <div className="flex size-12 items-center justify-center rounded-2xl bg-[var(--color-primary-light)] text-[var(--color-primary)]">
                                    <BadgeCheck className="size-5" />
                                </div>
                                <div>
                                    <p className="text-sm font-semibold text-[var(--color-text-primary)]">
                                        Trusted commerce for {settings.site_name}
                                    </p>
                                    <p className="text-sm text-[var(--color-text-secondary)]">
                                        Designed for Bangladesh buyers who expect speed, clarity, and consistency.
                                    </p>
                                </div>
                            </div>

                            <div className="flex items-center gap-3 text-sm text-[var(--color-text-secondary)]">
                                <div className="flex size-10 items-center justify-center rounded-full bg-[var(--color-surface-alt)] text-[var(--color-primary)]">
                                    <Truck className="size-4" />
                                </div>
                                <div>
                                    <p className="font-medium text-[var(--color-text-primary)]">Delivery coverage</p>
                                    <p>Nationwide with supportive order tracking.</p>
                                </div>
                            </div>

                            <div className="flex items-center gap-3 text-sm text-[var(--color-text-secondary)]">
                                <div className="flex size-10 items-center justify-center rounded-full bg-[var(--color-accent-light)] text-[var(--color-accent)]">
                                    <Star className="size-4 fill-current" />
                                </div>
                                <div>
                                    <p className="font-medium text-[var(--color-text-primary)]">Confidence signals</p>
                                    <p>Ratings, flash-sale pricing, and wishlist persistence.</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </section>
            </div>
        </>
    );
}

export default Home;
