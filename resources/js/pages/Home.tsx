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
import { useEffect, useMemo, useRef, useState, type FormEvent } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { StarRating } from '@/components/ui/star-rating';
import categoryRoutes from '@/routes/category';
import productRoutes from '@/routes/product';
import { MOTION } from '@/lib/constants';
import {
    formatCurrency,
    getDiscountPercent,
    getInitials,
    cn,
} from '@/lib/utils';
import { useCartStore } from '@/stores/cartStore';
import { useUiStore } from '@/stores/uiStore';
import { useWishlistStore } from '@/stores/wishlistStore';
import type {
    Brand,
    Category,
    FlashSale as FlashSaleData,
    Product,
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

type BrandCard = {
    id: number;
    name: string;
    slug: string;
    accent: string;
    description: string;
};

type FlashSaleItem = {
    product: Product;
    sold_count: number;
    stock_limit: number | null;
};

const HERO_AUTOPLAY_MS = 5000;

const CATEGORY_ACCENTS = [
    'from-sky-500/90 to-indigo-600/90',
    'from-rose-500/90 to-amber-500/90',
    'from-cyan-500/90 to-blue-600/90',
    'from-emerald-500/90 to-teal-600/90',
    'from-fuchsia-500/90 to-pink-600/90',
    'from-violet-500/90 to-purple-600/90',
];

const BRAND_ACCENTS = [
    'bg-sky-50 text-sky-700',
    'bg-amber-50 text-amber-700',
    'bg-emerald-50 text-emerald-700',
    'bg-rose-50 text-rose-700',
    'bg-indigo-50 text-indigo-700',
    'bg-fuchsia-50 text-fuchsia-700',
    'bg-blue-50 text-blue-700',
    'bg-orange-50 text-orange-700',
];

type HomePageProps = SharedPageProps & {
    activeFlashSale: FlashSaleData | null;
    featuredProducts: Product[];
    newArrivals: Product[];
    bestSellers: Product[];
    brands: Brand[];
    categories: Category[];
};

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

    const imageSet = product.images ?? [];
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

    const imageSet = product.images ?? [];
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

type FlashSaleSliderProps = {
    items: FlashSaleItem[];
    saleProgressMap: Map<number, { label: string; value: number }>;
};

function FlashSaleSlider({ items, saleProgressMap }: FlashSaleSliderProps): React.JSX.Element {
    const scrollRef = useRef<HTMLDivElement>(null);
    const [canScrollLeft, setCanScrollLeft] = useState(false);
    const [canScrollRight, setCanScrollRight] = useState(false);

    const syncButtons = () => {
        const el = scrollRef.current;
        if (!el) return;
        setCanScrollLeft(el.scrollLeft > 4);
        setCanScrollRight(el.scrollLeft < el.scrollWidth - el.clientWidth - 4);
    };

    useEffect(() => {
        syncButtons();
        const el = scrollRef.current;
        if (!el) return;
        const ro = new ResizeObserver(syncButtons);
        ro.observe(el);
        return () => ro.disconnect();
    }, [items]);

    const scroll = (dir: 'left' | 'right') => {
        const el = scrollRef.current;
        if (!el) return;
        const card = el.querySelector<HTMLElement>('[data-flash-card]');
        const step = card ? (card.offsetWidth + 12) * 2 : 480;
        el.scrollBy({ left: dir === 'right' ? step : -step, behavior: 'smooth' });
    };

    return (
        <div className="relative">
            <div
                ref={scrollRef}
                onScroll={syncButtons}
                className="flex gap-3 overflow-x-auto scroll-smooth md:gap-4"
                style={{ scrollbarWidth: 'none' }}
            >
                {items.map((item) => {
                    const progress = saleProgressMap.get(item.product.id);
                    return (
                        <div
                            key={item.product.id}
                            data-flash-card
                            className="shrink-0 w-[calc(50%-6px)] sm:w-[calc(33.333%-8px)] lg:w-[calc(25%-9px)] xl:w-[calc(20%-10px)]"
                        >
                            <FlashSaleCard
                                product={item.product}
                                saleProgress={progress?.value}
                                saleProgressLabel={progress?.label}
                            />
                        </div>
                    );
                })}
            </div>

            {canScrollLeft && (
                <button
                    type="button"
                    onClick={() => scroll('left')}
                    aria-label="Scroll left"
                    className="absolute -left-4 top-1/2 -translate-y-1/2 z-10 hidden lg:flex size-10 items-center justify-center rounded-full border border-white/20 bg-white/10 text-white backdrop-blur-sm transition hover:bg-white/25 shadow-lg"
                >
                    <ChevronLeft className="size-5" />
                </button>
            )}
            {canScrollRight && (
                <button
                    type="button"
                    onClick={() => scroll('right')}
                    aria-label="Scroll right"
                    className="absolute -right-4 top-1/2 -translate-y-1/2 z-10 hidden lg:flex size-10 items-center justify-center rounded-full border border-white/20 bg-white/10 text-white backdrop-blur-sm transition hover:bg-white/25 shadow-lg"
                >
                    <ChevronRight className="size-5" />
                </button>
            )}
        </div>
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
    const {
        settings,
        homepage_settings,
        hero_slides,
        promotional_banners,
        activeFlashSale,
        featuredProducts,
        newArrivals,
        bestSellers,
        brands,
        categories,
    } = usePage<HomePageProps>().props;

    const categoryCards: HomeCategoryCard[] = (categories ?? []).map((cat, index) => ({
        name: cat.name,
        slug: cat.slug,
        description: cat.description ?? '',
        image: cat.image ?? '',
        productsCount: cat.products_count ?? 0,
        accent: CATEGORY_ACCENTS[index % CATEGORY_ACCENTS.length],
    }));

    const brandCards: BrandCard[] = (brands ?? []).map((brand, index) => ({
        id: brand.id,
        name: brand.name,
        slug: brand.slug,
        accent: BRAND_ACCENTS[index % BRAND_ACCENTS.length],
        description: brand.description ?? '',
    }));

    const flashSale = activeFlashSale
        ? {
              name: activeFlashSale.name,
              ends_at: activeFlashSale.ends_at,
              items: (activeFlashSale.items ?? []) as FlashSaleItem[],
          }
        : null;

    const flashSaleProgressMap = useMemo(() => {
        if (!flashSale) {
            return new Map<number, { label: string; value: number }>();
        }

        return new Map(
            flashSale.items.map((item) => [
                item.product.id,
                {
                    label: `${item.sold_count} sold`,
                    value: typeof item.stock_limit === 'number' && item.stock_limit > 0
                        ? Math.round((item.sold_count / item.stock_limit) * 100)
                        : 0,
                },
            ]),
        );
    }, [activeFlashSale]);

    // Helper to get section data safely
    const getSection = (key: string) => homepage_settings?.[key];

    // Helper to render section heading
    const renderHeading = (key: string, defaultTitle: string) => {
        const s = getSection(key);
        if (!s) return null;
        return (
            <SectionHeading
                eyebrow={s.eyebrow ?? undefined}
                title={s.title || defaultTitle}
                description={s.description ?? undefined}
                actionLabel={s.action_label ?? undefined}
                actionHref={s.action_href ?? undefined}
            />
        );
    };

    return (
        <>
            <Head title="Premium BDT shopping" />

            <div className="space-y-16 py-8 lg:py-12">
                {/* Hero Section */}
                {getSection('hero_slider')?.is_visible && hero_slides?.length > 0 && (
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <HeroSlider slides={hero_slides.map(s => ({
                            id: String(s.id),
                            eyebrow: s.eyebrow || '',
                            title: s.title,
                            description: s.description || '',
                            primaryCta: { label: s.primary_cta_label || 'Shop Now', href: s.primary_cta_href || '#' },
                            secondaryCta: { label: s.secondary_cta_label || 'Learn More', href: s.secondary_cta_href || '#' },
                            image: s.image,
                            stats: s.stats || []
                        }))} />
                    </div>
                )}

                {/* Flash Sale Section */}
                {getSection('flash_sale')?.is_visible && flashSale && flashSale.items.length > 0 && (
                    <section className="bg-[var(--color-secondary)] py-12 text-white lg:py-16">
                        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                            <div className="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                                <div className="space-y-2">
                                    <div className="flex items-center gap-2">
                                        <Badge className="w-fit bg-[var(--color-accent)] text-[var(--color-secondary)]">
                                            <Zap className="size-3.5 fill-current" />
                                            {getSection('flash_sale')?.eyebrow || 'Flash sale'}
                                        </Badge>
                                    </div>
                                    <h2 className="text-2xl font-bold tracking-tight sm:text-3xl">
                                        {getSection('flash_sale')?.title || flashSale.name}
                                    </h2>
                                    <FlashSaleCountdown endsAt={flashSale.ends_at} />
                                </div>
                                <div className="flex shrink-0 flex-wrap gap-2">
                                    <Button asChild size="sm" className="bg-white text-[var(--color-secondary)] hover:bg-white/90">
                                        <Link href={getSection('flash_sale')?.action_href || '/flash-sales'}>
                                            {getSection('flash_sale')?.action_label || 'Shop all'}
                                            <ArrowRight className="size-3.5" />
                                        </Link>
                                    </Button>
                                </div>
                            </div>

                            <FlashSaleSlider
                                items={flashSale.items}
                                saleProgressMap={flashSaleProgressMap}
                            />
                        </div>
                    </section>
                )}

                {/* Categories Section */}
                {getSection('categories')?.is_visible && (
                    <section className="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
                        {renderHeading('categories', 'Shop by category')}
                        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            {categoryCards.map((category) => (
                                <CategoryCard key={category.slug} category={category} />
                            ))}
                        </div>
                    </section>
                )}

                {/* Featured Products Section */}
                {getSection('featured_products')?.is_visible && (
                    <section className="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
                        {renderHeading('featured_products', 'Featured products')}
                        <ProductGrid
                            products={featuredProducts}
                            emptyTitle="No featured products"
                            emptyDescription="Feature products in the admin panel to surface them here."
                            emptyHref="/products"
                            emptyActionLabel="Browse products"
                        />
                    </section>
                )}

                {/* Promotional Banners */}
                {getSection('promotional_banners')?.is_visible && promotional_banners?.length > 0 && (
                    <section className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="grid gap-4 lg:grid-cols-2">
                            {promotional_banners.map((banner) => (
                                <Link
                                    key={banner.id}
                                    href={banner.href || '#'}
                                    className="group relative overflow-hidden rounded-[2rem] border border-[var(--color-border)] bg-[var(--color-secondary)] shadow-[var(--shadow-card)]"
                                >
                                    <img
                                        src={banner.image}
                                        alt={banner.title}
                                        loading="lazy"
                                        decoding="async"
                                        className="h-full min-h-[18rem] w-full object-cover transition duration-500 group-hover:scale-105"
                                    />
                                    <div className={cn('absolute inset-0 bg-gradient-to-r', banner.accent || 'from-secondary/90 via-secondary/70 to-secondary/20')} />
                                    <div className="absolute inset-0 bg-gradient-to-t from-black/40 via-black/10 to-transparent" />
                                    <div className="absolute inset-0 flex flex-col justify-end p-6 text-white sm:p-8">
                                        {banner.badge && (
                                            <Badge className="mb-4 w-fit bg-white/10 text-white backdrop-blur-md">
                                                {banner.badge}
                                            </Badge>
                                        )}
                                        <h3 className="font-display text-3xl font-semibold tracking-tight sm:text-4xl">
                                            {banner.title}
                                        </h3>
                                        {banner.description && (
                                            <p className="mt-2 max-w-lg text-sm leading-7 text-white/80 sm:text-base">
                                                {banner.description}
                                            </p>
                                        )}
                                        <div className="mt-5 inline-flex items-center gap-2 text-sm font-semibold text-white">
                                            Explore now
                                            <ArrowRight className="size-4 transition group-hover:translate-x-1" />
                                        </div>
                                    </div>
                                </Link>
                            ))}
                        </div>
                    </section>
                )}

                {/* New Arrivals Section */}
                {getSection('new_arrivals')?.is_visible && (
                    <section className="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
                        {renderHeading('new_arrivals', 'New arrivals')}
                        <ProductRail
                            title="Recently added"
                            products={newArrivals}
                            emptyTitle="No new arrivals yet"
                            emptyDescription="Use the admin inventory flow to publish the latest assortment."
                            emptyHref="/products"
                            emptyActionLabel="Browse products"
                        />
                    </section>
                )}

                {/* Brands Section */}
                {getSection('brands')?.is_visible && (
                    <section className="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
                        {renderHeading('brands', 'Our brands')}
                        <div className="flex gap-4 overflow-x-auto pb-2">
                            {brandCards.map((brand) => (
                                <BrandTile key={brand.id} brand={brand} />
                            ))}
                        </div>
                    </section>
                )}

                {/* Best Sellers Section */}
                {getSection('best_sellers')?.is_visible && (
                    <section className="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
                        {renderHeading('best_sellers', 'Best sellers')}
                        <ProductRail
                            products={bestSellers}
                            emptyTitle="No best sellers yet"
                            emptyDescription="The best sellers rail will populate once product data is connected."
                            emptyHref="/products"
                            emptyActionLabel="Browse products"
                        />
                    </section>
                )}

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
