import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { AnimatePresence, motion, useReducedMotion } from 'framer-motion';
import {
    ArrowRight,
    BadgeCheck,
    ChevronLeft,
    ChevronRight,
    Pause,
    Play,
    ShoppingBag,
    Sparkles,
    Star,
    Truck,
    Zap,
} from 'lucide-react';
import {
    Fragment,
    useEffect,
    useMemo,
    useRef,
    useState,
    type FormEvent,
} from 'react';
import {
    ProductCard,
    ProductCardSkeleton,
} from '@/components/shared/ProductCard';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { Input } from '@/components/ui/input';
import { resolveCategoryColor } from '@/lib/category-colors';
import { CategoryIcon } from '@/lib/category-icons';
import {
    MOTION,
    PRODUCT_GRID_CLASS,
    PRODUCT_RAIL_CLASS,
} from '@/lib/constants';
import { getInitials, isVideoUrl, cn } from '@/lib/utils';
import categoryRoutes from '@/routes/category';
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
    icon: string;
    productsCount: number;
    color: string;
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
    logo: string | null;
    accent: string;
};

type FlashSaleItem = {
    product: Product;
    sold_count: number;
    stock_limit: number | null;
};

const DEFAULT_HERO_AUTOPLAY_SECONDS = 6;

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
        <div
            className={cn(
                'flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between',
                className,
            )}
        >
            <div className="space-y-2">
                {eyebrow ? (
                    <p className="text-xs font-semibold tracking-[0.24em] text-[var(--color-text-muted)] uppercase">
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
                <Button
                    asChild
                    variant="ghost"
                    className="w-fit px-0 text-[var(--color-primary)] hover:bg-transparent"
                >
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
    autoplaySeconds: number;
};

function HeroSlider({
    slides,
    autoplaySeconds,
}: HeroSliderProps): React.JSX.Element {
    const prefersReducedMotion = useReducedMotion();
    const [activeIndex, setActiveIndex] = useState(0);
    const [isPaused, setIsPaused] = useState(false);

    useEffect(() => {
        if (prefersReducedMotion || slides.length <= 1 || isPaused) {
            return undefined;
        }

        const intervalId = window.setInterval(() => {
            setActiveIndex((current) => (current + 1) % slides.length);
        }, autoplaySeconds * 1000);

        return () => window.clearInterval(intervalId);
    }, [isPaused, prefersReducedMotion, slides.length, autoplaySeconds]);

    const goToSlide = (index: number): void => {
        setActiveIndex(index);
    };

    const goToPrevious = (): void => {
        setActiveIndex((current) =>
            current === 0 ? slides.length - 1 : current - 1,
        );
    };

    const goToNext = (): void => {
        setActiveIndex((current) => (current + 1) % slides.length);
    };

    const activeSlide = slides[activeIndex];

    return (
        <section
            className="group relative overflow-hidden rounded-[2rem] bg-[var(--color-secondary)] text-white shadow-[var(--shadow-modal)]"
            onMouseEnter={() => setIsPaused(true)}
            onMouseLeave={() => setIsPaused(false)}
            aria-label="Homepage hero banner"
        >
            <div className="relative min-h-[42vh] sm:min-h-[60vh] lg:min-h-[75vh]">
                <AnimatePresence mode="wait" initial={false}>
                    <motion.div
                        key={activeSlide.id}
                        className="absolute inset-0"
                        initial={{ opacity: 0, scale: 1.02 }}
                        animate={{ opacity: 1, scale: 1 }}
                        exit={{ opacity: 0, scale: 1.02 }}
                        transition={{
                            duration: MOTION.modal.duration,
                            ease: 'easeOut',
                        }}
                    >
                        {isVideoUrl(activeSlide.image) ? (
                            <video
                                src={activeSlide.image}
                                aria-hidden="true"
                                className="absolute inset-0 h-full w-full object-cover"
                                autoPlay
                                muted
                                loop
                                playsInline
                            />
                        ) : (
                            <img
                                src={activeSlide.image}
                                alt=""
                                aria-hidden="true"
                                className="absolute inset-0 h-full w-full object-cover"
                                loading="eager"
                                decoding="async"
                            />
                        )}
                        <div className="absolute inset-0 bg-gradient-to-r from-[var(--color-secondary)] via-[rgba(26,26,46,0.76)] to-[rgba(26,26,46,0.12)]" />
                        <div className="absolute inset-0 bg-gradient-to-t from-[rgba(26,26,46,0.7)] via-transparent to-transparent lg:bg-none" />
                        <div className="relative z-10 flex h-full flex-col justify-between px-4 py-6 sm:px-10 sm:py-10 lg:px-14 lg:py-14">
                            <motion.div
                                className="max-w-3xl space-y-3 sm:space-y-5"
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
                                    className="hidden sm:block"
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
                                    className="font-display text-2xl leading-[1.1] tracking-tight sm:text-4xl sm:leading-[1.05] lg:max-w-2xl lg:text-6xl"
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
                                    className="flex flex-row flex-wrap items-center gap-x-4 gap-y-2 sm:gap-x-5"
                                >
                                    <Button
                                        asChild
                                        className="h-8 shrink-0 gap-1.5 rounded-full bg-white px-4 text-xs font-semibold text-[var(--color-secondary)] shadow-md hover:bg-white/90 sm:h-10 sm:px-6 sm:text-sm"
                                    >
                                        <Link
                                            href={activeSlide.primaryCta.href}
                                        >
                                            {activeSlide.primaryCta.label}
                                            <ArrowRight className="size-3 sm:size-3.5" />
                                        </Link>
                                    </Button>
                                    <Link
                                        href={activeSlide.secondaryCta.href}
                                        className="flex shrink-0 items-center gap-1.5 text-xs font-medium whitespace-nowrap text-white/80 transition hover:text-white sm:text-sm"
                                    >
                                        {activeSlide.secondaryCta.label}
                                        <ArrowRight className="size-3 sm:size-3.5" />
                                    </Link>
                                </motion.div>
                            </motion.div>

                            {/* Stat cards — pinned to bottom above the two absolute pills */}
                            <motion.div
                                className="hidden grid-cols-3 gap-2 pb-16 sm:grid sm:gap-3 sm:pb-14 lg:pb-8"
                                initial={{ opacity: 0, y: 10 }}
                                animate={{ opacity: 1, y: 0 }}
                                transition={{
                                    duration: 0.35,
                                    delay: 0.35,
                                    ease: 'easeOut',
                                }}
                            >
                                {activeSlide.stats.map((stat) => (
                                    <div
                                        key={stat.label}
                                        className="rounded-xl border border-white/10 bg-white/10 px-2.5 py-2 backdrop-blur-md sm:rounded-2xl sm:px-4 sm:py-3"
                                    >
                                        <p className="text-[10px] tracking-[0.15em] text-white/60 uppercase sm:text-xs sm:tracking-[0.2em]">
                                            {stat.label}
                                        </p>
                                        <p className="mt-0.5 text-xs font-semibold text-white sm:mt-1 sm:text-sm">
                                            {stat.value}
                                        </p>
                                    </div>
                                ))}
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
                            className="absolute top-1/2 left-4 z-20 hidden -translate-y-1/2 rounded-full border border-white/20 bg-white/10 p-3 text-white opacity-0 backdrop-blur-md transition group-hover:opacity-100 hover:bg-white/20 lg:inline-flex"
                        >
                            <ChevronLeft className="size-5" />
                        </button>
                        <button
                            type="button"
                            onClick={goToNext}
                            aria-label="Next hero slide"
                            className="absolute top-1/2 right-4 z-20 hidden -translate-y-1/2 rounded-full border border-white/20 bg-white/10 p-3 text-white opacity-0 backdrop-blur-md transition group-hover:opacity-100 hover:bg-white/20 lg:inline-flex"
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
                    aria-label={
                        isPaused
                            ? 'Resume hero autoplay'
                            : 'Pause hero autoplay'
                    }
                    className="absolute right-4 bottom-4 z-20 hidden items-center gap-2 rounded-full border border-white/10 bg-white/10 px-3 py-2 text-xs text-white/90 backdrop-blur-md transition hover:bg-white/15 sm:right-6 sm:bottom-6 sm:inline-flex"
                >
                    {isPaused ? (
                        <Play className="size-4" />
                    ) : (
                        <Pause className="size-4" />
                    )}
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
                                    'h-2.5 rounded-full transition-all focus-visible:ring-2 focus-visible:ring-white/80 focus-visible:ring-offset-2 focus-visible:ring-offset-[var(--color-secondary)] focus-visible:outline-none',
                                    isActive
                                        ? 'w-10 bg-white'
                                        : 'w-2.5 bg-white/40 hover:bg-white/60',
                                )}
                            />
                        );
                    })}
                </div>

                <div className="text-xs text-white/70">
                    {String(activeIndex + 1).padStart(2, '0')} /{' '}
                    {String(slides.length).padStart(2, '0')}
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
        <Link
            href={categoryRoutes.show.url(category.slug)}
            className="group flex w-[86px] flex-col items-center gap-2.5 text-center sm:w-[104px]"
        >
            <span
                style={
                    {
                        '--category-color': category.color,
                    } as React.CSSProperties
                }
                className={cn(
                    'flex size-16 items-center justify-center rounded-full ring-1 transition-all duration-300 ease-out sm:size-20',
                    'bg-[color-mix(in_srgb,var(--category-color)_12%,white)]',
                    'text-[var(--category-color)]',
                    'ring-[color-mix(in_srgb,var(--category-color)_18%,white)]',
                    'group-hover:-translate-y-1 group-hover:shadow-[var(--category-color)]/20 group-hover:shadow-lg',
                    'group-hover:bg-[var(--category-color)] group-hover:text-white group-hover:ring-[var(--category-color)]',
                )}
            >
                <CategoryIcon
                    icon={category.icon}
                    className="size-6 transition-transform duration-300 ease-out group-hover:scale-110 sm:size-7"
                />
            </span>
            <span className="text-xs leading-tight font-semibold tracking-tight text-[var(--color-text-primary)] sm:text-sm">
                {category.name}
            </span>
            <span className="text-[11px] text-[var(--color-text-muted)]">
                {category.productsCount} products
            </span>
        </Link>
    );
}

type CategoryShowcaseProps = {
    categories: HomeCategoryCard[];
};

/**
 * Cards start stacked on top of each other at the section's center, then
 * spread out to their real flex-wrap positions once scrolled into view —
 * driven by Framer Motion's `layout` animation (FLIP), which measures the
 * before/after bounding box itself rather than hand-computed offsets that
 * would drift across responsive breakpoints.
 */
function CategoryShowcase({
    categories,
}: CategoryShowcaseProps): React.JSX.Element {
    const prefersReducedMotion = useReducedMotion();
    const [revealed, setRevealed] = useState(false);
    const showSpread = revealed || !!prefersReducedMotion;
    const centerIndex = (categories.length - 1) / 2;

    return (
        <motion.div
            layout
            onViewportEnter={() => setRevealed(true)}
            viewport={{ once: true, amount: 0.3 }}
            transition={{ layout: { duration: 0.5, ease: 'easeOut' } }}
            className={cn(
                'flex flex-wrap justify-center gap-x-5 gap-y-8 sm:gap-x-8',
                !showSpread && 'relative min-h-[158px] sm:min-h-[182px]',
            )}
        >
            {categories.map((category, index) => (
                <motion.div
                    key={category.slug}
                    layout
                    initial={false}
                    animate={{ opacity: showSpread ? 1 : 0 }}
                    className={cn(
                        !showSpread &&
                            'absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2',
                    )}
                    transition={{
                        layout: {
                            type: 'spring',
                            stiffness: 160,
                            damping: 20,
                            mass: 0.7,
                            delay: showSpread
                                ? Math.min(
                                      Math.abs(index - centerIndex) * 0.045,
                                      0.4,
                                  )
                                : 0,
                        },
                        opacity: { duration: 0.25 },
                    }}
                >
                    <CategoryCard category={category} />
                </motion.div>
            ))}
        </motion.div>
    );
}

type FlashSaleSliderProps = {
    items: FlashSaleItem[];
    saleProgressMap: Map<number, { label: string; value: number }>;
};

function FlashSaleSlider({
    items,
    saleProgressMap,
}: FlashSaleSliderProps): React.JSX.Element {
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
        const gap = parseFloat(getComputedStyle(el).columnGap) || 0;
        const step = card ? (card.offsetWidth + gap) * 2 : 480;
        el.scrollBy({
            left: dir === 'right' ? step : -step,
            behavior: 'smooth',
        });
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
                            className="w-[calc((100%-0.75rem)/2)] shrink-0 sm:w-[calc((100%-1.5rem)/3)] md:w-[calc((100%-2rem)/3)] lg:w-[calc((100%-3rem)/4)]"
                        >
                            <ProductCard
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
                    className="absolute top-1/2 left-1 z-10 flex size-8 -translate-y-1/2 items-center justify-center rounded-full border border-[#c8102e]/40 bg-[#c8102e]/90 text-white shadow-lg backdrop-blur-sm transition hover:bg-[#c8102e] sm:left-0 lg:-left-4 lg:size-10"
                >
                    <ChevronLeft className="size-4 lg:size-5" />
                </button>
            )}
            {canScrollRight && (
                <button
                    type="button"
                    onClick={() => scroll('right')}
                    aria-label="Scroll right"
                    className="absolute top-1/2 right-1 z-10 flex size-8 -translate-y-1/2 items-center justify-center rounded-full border border-[#c8102e]/40 bg-[#c8102e]/90 text-white shadow-lg backdrop-blur-sm transition hover:bg-[#c8102e] sm:right-0 lg:-right-4 lg:size-10"
                >
                    <ChevronRight className="size-4 lg:size-5" />
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
            <div className={cn(PRODUCT_GRID_CLASS, className)}>
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
        <div className={cn(PRODUCT_GRID_CLASS, className)}>
            {products.map((product, index) => {
                const saleProgress = saleProgressMap?.get(product.id);

                return (
                    <ProductCard
                        key={product.id}
                        product={product}
                        index={index}
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
            <div className={PRODUCT_RAIL_CLASS}>
                {Array.from({ length: 4 }, (_, index) => (
                    <div key={index} className="min-w-0">
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
            <div className={PRODUCT_RAIL_CLASS}>
                {products.map((product, index) => {
                    const saleProgress = saleProgressMap?.get(product.id);

                    return (
                        <div key={product.id} className="min-w-0">
                            <ProductCard
                                product={product}
                                index={index}
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

type BrandTileProps = {
    brand: BrandCard;
};

function BrandTile({ brand }: BrandTileProps): React.JSX.Element {
    const [hasLogoError, setHasLogoError] = useState(false);
    const showLogo = Boolean(brand.logo) && !hasLogoError;

    return (
        <Link
            href={`/brands?brand=${brand.slug}`}
            className="group flex min-w-[13rem] items-center gap-4 rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)] px-4 py-4 shadow-[var(--shadow-card)] transition hover:-translate-y-1 hover:shadow-[var(--shadow-card-hover)]"
        >
            <div
                className={cn(
                    'flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-2xl text-lg font-bold transition group-hover:scale-105',
                    brand.accent,
                )}
            >
                {showLogo ? (
                    <img
                        src={brand.logo ?? ''}
                        alt={`${brand.name} logo`}
                        loading="lazy"
                        decoding="async"
                        onError={() => setHasLogoError(true)}
                        className="h-full w-full object-contain p-1.5"
                    />
                ) : (
                    <span aria-hidden="true">{getInitials(brand.name)}</span>
                )}
            </div>
            <div className="min-w-0">
                <p className="truncate text-base font-semibold text-[var(--color-text-primary)]">
                    {brand.name}
                </p>
            </div>
        </Link>
    );
}

type FlashSaleCountdownProps = {
    endsAt: string;
};

function FlashSaleCountdown({
    endsAt,
}: FlashSaleCountdownProps): React.JSX.Element {
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
                    <span className="text-base text-white">
                        {String(segment.value).padStart(2, '0')}
                    </span>
                    <span className="text-xs tracking-[0.2em] text-white/70 uppercase">
                        {segment.label}
                    </span>
                </span>
            ))}
        </div>
    );
}

function NewsletterForm(): React.JSX.Element {
    const form = useForm<{ email: string }>({ email: '' });
    const [isSubscribed, setIsSubscribed] = useState(false);
    const [statusMessage, setStatusMessage] = useState('');
    const checkTimeout = useRef<ReturnType<typeof setTimeout> | null>(null);

    const checkStatus = (email: string): void => {
        if (checkTimeout.current) clearTimeout(checkTimeout.current);
        if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            setIsSubscribed(false);
            return;
        }
        checkTimeout.current = setTimeout(() => {
            fetch(`/newsletter/status?email=${encodeURIComponent(email)}`)
                .then((r) => r.json())
                .then((data: { is_subscribed: boolean }) =>
                    setIsSubscribed(data.is_subscribed),
                )
                .catch(() => {});
        }, 500);
    };

    const handleSubmit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        if (isSubscribed) {
            form.post('/newsletter/unsubscribe', {
                preserveScroll: true,
                onSuccess: () => {
                    setIsSubscribed(false);
                    setStatusMessage('You have been unsubscribed.');
                    form.reset('email');
                },
            });
        } else {
            form.post('/newsletter/subscribe', {
                preserveScroll: true,
                onSuccess: () => {
                    setIsSubscribed(true);
                    setStatusMessage('Thanks! You are now subscribed.');
                    form.reset('email');
                },
            });
        }
    };

    return (
        <Card className="overflow-hidden border-[var(--color-border)] bg-[var(--color-secondary)] text-white shadow-[var(--shadow-card-hover)]">
            <CardContent className="grid gap-8 px-6 py-6 lg:grid-cols-[1.1fr_0.9fr] lg:items-center lg:px-8 lg:py-8">
                <div className="space-y-4">
                    <div className="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/10 px-3 py-1 text-xs font-semibold tracking-[0.22em] text-white/75 uppercase">
                        <Sparkles className="size-3.5 text-[var(--color-accent)]" />
                        Newsletter
                    </div>
                    <h3 className="font-display text-3xl font-semibold tracking-tight sm:text-4xl">
                        Product drops, sale alerts, and delivery updates in one
                        place.
                    </h3>
                    <p className="max-w-2xl text-sm leading-7 text-white/75 sm:text-base">
                        Join the mailing list for curated launch alerts,
                        seasonal promotions, and practical shopping reminders.
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
                        onChange={(event) => {
                            form.setData('email', event.target.value);
                            checkStatus(event.target.value);
                        }}
                        error={form.errors.email}
                        containerClassName="space-y-2"
                        labelClassName="text-white"
                        className="border-white/10 bg-white text-[var(--color-text-primary)] placeholder:text-[var(--color-text-muted)]"
                    />

                    <Button
                        type="submit"
                        fullWidth
                        loading={form.processing}
                        loadingText={
                            isSubscribed ? 'Unsubscribing...' : 'Subscribing...'
                        }
                        className={
                            isSubscribed
                                ? 'border border-white/30 bg-white/20 text-white hover:bg-white/30'
                                : 'bg-white text-[var(--color-secondary)] hover:bg-white/90'
                        }
                    >
                        {isSubscribed ? 'Unsubscribe' : 'Subscribe'}
                    </Button>

                    <p
                        className={cn(
                            'min-h-5 text-sm text-white/75',
                            statusMessage ? 'text-emerald-300' : '',
                        )}
                        aria-live="polite"
                    >
                        {statusMessage ||
                            (isSubscribed
                                ? 'You are subscribed. Click above to unsubscribe.'
                                : 'Unsubscribe anytime. No spam, only useful updates.')}
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

    const categoryCards: HomeCategoryCard[] = (categories ?? []).map(
        (cat, index) => ({
            name: cat.name,
            slug: cat.slug,
            icon: cat.icon ?? 'shopping-bag',
            productsCount: cat.products_count ?? 0,
            color: resolveCategoryColor(cat.color, index),
        }),
    );

    const brandCards: BrandCard[] = (brands ?? []).map((brand, index) => ({
        id: brand.id,
        name: brand.name,
        slug: brand.slug,
        logo: brand.logo ?? null,
        accent: BRAND_ACCENTS[index % BRAND_ACCENTS.length],
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
                    value:
                        typeof item.stock_limit === 'number' &&
                        item.stock_limit > 0
                            ? Math.round(
                                  (item.sold_count / item.stock_limit) * 100,
                              )
                            : 0,
                },
            ]),
        );
    }, [activeFlashSale]);

    // Helper to get section data safely
    const getSection = (key: string) => homepage_settings?.[key];

    // Helper to render section heading
    const renderHeading = (
        key: string,
        defaultTitle: string,
        showAction = true,
    ) => {
        const s = getSection(key);
        if (!s) return null;
        return (
            <SectionHeading
                eyebrow={s.eyebrow ?? undefined}
                title={s.title || defaultTitle}
                description={s.description ?? undefined}
                actionLabel={
                    showAction ? (s.action_label ?? undefined) : undefined
                }
                actionHref={
                    showAction ? (s.action_href ?? undefined) : undefined
                }
            />
        );
    };

    // Each homepage section's position is driven by its admin-configured sort_order,
    // so the "Page Sections" drag order in /admin/homepage actually changes the page.
    const homepageSectionBlocks: { key: string; node: React.ReactNode }[] = [
        {
            key: 'hero_slider',
            node: getSection('hero_slider')?.is_visible &&
                hero_slides?.length > 0 && (
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <HeroSlider
                            slides={hero_slides.map((s) => ({
                                id: String(s.id),
                                eyebrow: s.eyebrow || '',
                                title: s.title,
                                description: s.description || '',
                                primaryCta: {
                                    label: s.primary_cta_label || 'Shop Now',
                                    href: s.primary_cta_href || '#',
                                },
                                secondaryCta: {
                                    label:
                                        s.secondary_cta_label || 'Learn More',
                                    href: s.secondary_cta_href || '#',
                                },
                                image: s.image,
                                stats: s.stats || [],
                            }))}
                            autoplaySeconds={
                                Number(
                                    getSection('hero_slider')?.metadata
                                        ?.autoplay_seconds,
                                ) || DEFAULT_HERO_AUTOPLAY_SECONDS
                            }
                        />
                    </div>
                ),
        },
        {
            key: 'flash_sale',
            node: getSection('flash_sale')?.is_visible &&
                flashSale &&
                flashSale.items.length > 0 && (
                    <section className="bg-[var(--color-secondary)] py-12 text-white lg:py-16">
                        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                            <div className="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                                <div className="space-y-2">
                                    <div className="flex items-center gap-2">
                                        <Badge className="w-fit bg-[var(--color-accent)] text-[var(--color-secondary)]">
                                            <Zap className="size-3.5 fill-current" />
                                            {getSection('flash_sale')
                                                ?.eyebrow || 'Flash sale'}
                                        </Badge>
                                    </div>
                                    <h2 className="text-2xl font-bold tracking-tight sm:text-3xl">
                                        {getSection('flash_sale')?.title ||
                                            flashSale.name}
                                    </h2>
                                    <FlashSaleCountdown
                                        endsAt={flashSale.ends_at}
                                    />
                                </div>
                                <div className="flex shrink-0 flex-wrap gap-2">
                                    <Button
                                        asChild
                                        size="sm"
                                        className="bg-white text-[var(--color-secondary)] hover:bg-white/90"
                                    >
                                        <Link
                                            href={
                                                getSection('flash_sale')
                                                    ?.action_href ||
                                                '/flash-sales'
                                            }
                                        >
                                            {getSection('flash_sale')
                                                ?.action_label || 'Shop all'}
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
                ),
        },
        {
            key: 'categories',
            node: getSection('categories')?.is_visible && (
                <section className="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
                    {renderHeading('categories', 'Shop by category', false)}
                    <CategoryShowcase categories={categoryCards} />
                </section>
            ),
        },
        {
            key: 'featured_products',
            node: getSection('featured_products')?.is_visible && (
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
            ),
        },
        {
            key: 'promotional_banners',
            node: getSection('promotional_banners')?.is_visible &&
                promotional_banners?.length > 0 && (
                    <section className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="grid gap-4 lg:grid-cols-2">
                            {promotional_banners.map((banner) => (
                                <Link
                                    key={banner.id}
                                    href={banner.href || '#'}
                                    className="group relative overflow-hidden rounded-[2rem] bg-[var(--color-secondary)] shadow-[var(--shadow-card)]"
                                >
                                    <img
                                        src={banner.image}
                                        alt={banner.title}
                                        loading="lazy"
                                        decoding="async"
                                        className="h-full min-h-[18rem] w-full object-cover transition duration-500 group-hover:scale-105"
                                    />
                                    <div
                                        className={cn(
                                            'absolute inset-0 bg-gradient-to-r',
                                            banner.accent ||
                                                'from-secondary/90 via-secondary/70 to-secondary/20',
                                        )}
                                    />
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
                ),
        },
        {
            key: 'new_arrivals',
            node: getSection('new_arrivals')?.is_visible && (
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
            ),
        },
        {
            key: 'brands',
            node: getSection('brands')?.is_visible && (
                <section className="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
                    {renderHeading('brands', 'Our brands')}
                    <div className="flex gap-4 overflow-x-auto pb-2">
                        {brandCards.map((brand) => (
                            <BrandTile key={brand.id} brand={brand} />
                        ))}
                    </div>
                </section>
            ),
        },
        {
            key: 'best_sellers',
            node: getSection('best_sellers')?.is_visible && (
                <section className="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
                    {renderHeading('best_sellers', 'Best selling items')}
                    <ProductRail
                        products={bestSellers}
                        emptyTitle="No best selling items yet"
                        emptyDescription="The best selling items rail will populate once product data is connected."
                        emptyHref="/products"
                        emptyActionLabel="Browse products"
                    />
                </section>
            ),
        },
    ];

    const orderedSectionBlocks = [...homepageSectionBlocks].sort(
        (a, b) =>
            (getSection(a.key)?.sort_order ?? 0) -
            (getSection(b.key)?.sort_order ?? 0),
    );

    return (
        <>
            <Head title="Premium BDT shopping" />

            <div className="space-y-16 py-8 lg:py-12">
                {orderedSectionBlocks.map((block) => (
                    <Fragment key={block.key}>{block.node}</Fragment>
                ))}

                <section className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <NewsletterForm />
                </section>

                <section className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <Card className="border-[var(--color-border)] bg-[var(--color-surface)] shadow-[var(--shadow-card)]">
                        <CardContent className="grid gap-4 px-6 py-6 sm:items-center sm:px-8 md:grid-cols-3">
                            <div className="flex items-center gap-3">
                                <div className="flex size-12 items-center justify-center rounded-2xl bg-[var(--color-primary-light)] text-[var(--color-primary)]">
                                    <BadgeCheck className="size-5" />
                                </div>
                                <div>
                                    <p className="text-sm font-semibold text-[var(--color-text-primary)]">
                                        Trusted commerce for{' '}
                                        {settings.site_name}
                                    </p>
                                    <p className="text-sm text-[var(--color-text-secondary)]">
                                        Designed for Bangladesh buyers who
                                        expect speed, clarity, and consistency.
                                    </p>
                                </div>
                            </div>

                            <div className="flex items-center gap-3 text-sm text-[var(--color-text-secondary)]">
                                <div className="flex size-10 items-center justify-center rounded-full bg-[var(--color-surface-alt)] text-[var(--color-primary)]">
                                    <Truck className="size-4" />
                                </div>
                                <div>
                                    <p className="font-medium text-[var(--color-text-primary)]">
                                        Delivery coverage
                                    </p>
                                    <p>
                                        Nationwide with supportive order
                                        tracking.
                                    </p>
                                </div>
                            </div>

                            <div className="flex items-center gap-3 text-sm text-[var(--color-text-secondary)]">
                                <div className="flex size-10 items-center justify-center rounded-full bg-[var(--color-accent-light)] text-[var(--color-accent)]">
                                    <Star className="size-4 fill-current" />
                                </div>
                                <div>
                                    <p className="font-medium text-[var(--color-text-primary)]">
                                        Confidence signals
                                    </p>
                                    <p>
                                        Ratings, flash-sale pricing, and
                                        wishlist persistence.
                                    </p>
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
