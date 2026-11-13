import { Head, Link, usePage } from '@inertiajs/react';
import { AnimatePresence, motion, useReducedMotion } from 'framer-motion';
import type { PanInfo } from 'framer-motion';
import {
    ArrowRight,
    BadgeCheck,
    ChevronLeft,
    ChevronRight,
    PackagePlus,
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
    useSyncExternalStore,
} from 'react';
import { AutoSlider } from '@/components/shared/AutoSlider';
import { BrandCard } from '@/components/shared/BrandCard';
import type { BrandCardData } from '@/components/shared/BrandCard';
import { BundleCard } from '@/components/shared/BundleCard';
import type { ProductBundle } from '@/components/shared/BundleCard';
import { LazyCategoryIcon } from '@/components/shared/LazyCategoryIcon';
import NewsletterThankYouModal from '@/components/shared/NewsletterThankYouModal';
import {
    ProductCard,
    ProductCardSkeleton,
} from '@/components/shared/ProductCard';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { EmptyState } from '@/components/ui/empty-state';
import { Input } from '@/components/ui/input';
import { useNewsletterSubscribe } from '@/hooks/use-newsletter-subscribe';
import { resolveCategoryColor } from '@/lib/category-colors';
import {
    MOTION,
    PRODUCT_GRID_CLASS,
    PRODUCT_RAIL_CLASS,
} from '@/lib/constants';
import { DEFAULT_BANNER_GRADIENT, isCssGradient } from '@/lib/gradient';
import { isVideoUrl, cn, getSoldPercent } from '@/lib/utils';
import categoryRoutes from '@/routes/category';
import type {
    Brand,
    Category,
    FlashSale as FlashSaleData,
    HeroSlide as RawHeroSlide,
    Product,
    PromotionalBanner,
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
    image: string;
    stats: HeroStat[];
};

type FlashSaleItem = {
    product: Product;
    sold_count: number;
};

const DEFAULT_HERO_AUTOPLAY_SECONDS = 6;

type HomePageProps = SharedPageProps & {
    // HomeController always includes these (see app/Http/Controllers/HomeController.php) —
    // optional on SharedPageProps because every other page no longer
    // receives them, but guaranteed present here. RawHeroSlide (the actual
    // prop shape from the server) is distinct from this file's own local
    // `HeroSlide` type below, which is what hero_slides gets mapped into
    // for the HeroSlider component.
    hero_slides: RawHeroSlide[];
    promotional_banners: PromotionalBanner[];
    activeFlashSale: FlashSaleData | null;
    featuredProducts: Product[];
    newArrivals: Product[];
    bestSellers: Product[];
    bundleDeals: ProductBundle[];
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
                    <p className="text-xs font-semibold tracking-[0.24em] text-[var(--color-text-secondary)] uppercase">
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
    // `direction` drives which side the slide enters/exits from (1 = forward,
    // -1 = backward), kept alongside the index so a swipe continues sliding
    // the way it was dragged instead of snapping back to center before the
    // next slide crossfades in.
    const [[activeIndex, direction], setSlide] = useState<[number, number]>([
        0, 1,
    ]);
    const [isPaused, setIsPaused] = useState(false);

    const goToIndex = (index: number, dir: number): void => {
        setSlide([index, dir]);
    };

    const goToPrevious = (): void => {
        setSlide(([current]) => [
            current === 0 ? slides.length - 1 : current - 1,
            -1,
        ]);
    };

    const goToNext = (): void => {
        setSlide(([current]) => [(current + 1) % slides.length, 1]);
    };

    useEffect(() => {
        if (prefersReducedMotion || slides.length <= 1 || isPaused) {
            return undefined;
        }

        const intervalId = window.setInterval(() => {
            setSlide(([current]) => [(current + 1) % slides.length, 1]);
        }, autoplaySeconds * 1000);

        return () => window.clearInterval(intervalId);
    }, [isPaused, prefersReducedMotion, slides.length, autoplaySeconds]);

    const SWIPE_DISTANCE_THRESHOLD = 60;
    const SWIPE_VELOCITY_THRESHOLD = 400;

    const handleDragStart = (): void => {
        setIsPaused(true);
    };

    const handleDragEnd = (
        _event: MouseEvent | TouchEvent | PointerEvent,
        info: PanInfo,
    ): void => {
        setIsPaused(false);

        if (
            info.offset.x < -SWIPE_DISTANCE_THRESHOLD ||
            info.velocity.x < -SWIPE_VELOCITY_THRESHOLD
        ) {
            goToNext();
        } else if (
            info.offset.x > SWIPE_DISTANCE_THRESHOLD ||
            info.velocity.x > SWIPE_VELOCITY_THRESHOLD
        ) {
            goToPrevious();
        }
    };

    const slideVariants = {
        enter: (dir: number) => ({
            x: dir > 0 ? '100%' : '-100%',
            opacity: 0,
        }),
        center: { x: 0, opacity: 1 },
        exit: (dir: number) => ({
            x: dir > 0 ? '-100%' : '100%',
            opacity: 0,
        }),
    };

    const activeSlide = slides[activeIndex];

    return (
        <section
            className="group selection-light relative rounded-[1.25rem] bg-[var(--color-secondary)] text-white shadow-[var(--shadow-modal)] [clip-path:inset(0_round_1.25rem)]"
            onMouseEnter={() => setIsPaused(true)}
            onMouseLeave={() => setIsPaused(false)}
            aria-label="Homepage hero banner"
        >
            {/* The viewport-height sizes keep the hero filling the screen, but the rem
                floors are what the slide's content needs at each breakpoint: on a short
                viewport (a scaled-up display, a landscape phone) vh alone gave a box
                smaller than its content and everything overlapped. */}
            <div className="relative min-h-[max(42vh,18rem)] sm:min-h-[max(60vh,24rem)] lg:min-h-[max(75vh,30rem)]">
                <AnimatePresence initial={false} custom={direction}>
                    <motion.div
                        key={activeSlide.id}
                        custom={direction}
                        className={cn(
                            'absolute inset-0',
                            slides.length > 1 &&
                                !prefersReducedMotion &&
                                'cursor-grab active:cursor-grabbing',
                        )}
                        variants={slideVariants}
                        initial="enter"
                        animate="center"
                        exit="exit"
                        transition={{
                            x: { type: 'spring', stiffness: 300, damping: 32 },
                            opacity: { duration: 0.2 },
                        }}
                        drag={
                            slides.length > 1 && !prefersReducedMotion
                                ? 'x'
                                : false
                        }
                        dragConstraints={{ left: 0, right: 0 }}
                        dragElastic={0.65}
                        dragMomentum={false}
                        onDragStart={handleDragStart}
                        onDragEnd={handleDragEnd}
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
                        <div className="relative z-10 flex h-full min-w-0 flex-col justify-between overflow-hidden px-4 py-5 sm:px-10 sm:py-8 lg:px-14 lg:py-10">
                            <motion.div
                                className="max-w-full space-y-2 sm:max-w-xl sm:space-y-3 lg:max-w-2xl"
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
                                    className="hidden max-w-full sm:block"
                                    variants={{
                                        hidden: { opacity: 0, y: 12 },
                                        visible: { opacity: 1, y: 0 },
                                    }}
                                >
                                    <Badge
                                        variant="amber"
                                        className="max-w-full border-0 bg-[rgba(245,166,35,0.16)] text-amber-100"
                                    >
                                        <Sparkles className="size-3.5 shrink-0" />
                                        <span className="truncate">
                                            {activeSlide.eyebrow}
                                        </span>
                                    </Badge>
                                </motion.div>

                                <motion.h1
                                    variants={{
                                        hidden: { opacity: 0, y: 12 },
                                        visible: { opacity: 1, y: 0 },
                                    }}
                                    className="line-clamp-2 font-display text-xl leading-[1.15] tracking-tight break-words sm:text-3xl sm:leading-[1.1] lg:text-5xl"
                                >
                                    {activeSlide.title}
                                </motion.h1>

                                <motion.div
                                    className="hidden sm:block"
                                    variants={{
                                        hidden: { opacity: 0, y: 12 },
                                        visible: { opacity: 1, y: 0 },
                                    }}
                                >
                                    <p className="line-clamp-1 max-w-xl text-sm leading-6 break-words text-white/80 sm:text-base lg:line-clamp-2 lg:text-lg lg:leading-7">
                                        {activeSlide.description}
                                    </p>
                                </motion.div>

                                <motion.div
                                    variants={{
                                        hidden: { opacity: 0, y: 12 },
                                        visible: { opacity: 1, y: 0 },
                                    }}
                                    className="flex flex-row flex-wrap items-center gap-x-4 gap-y-2 sm:gap-x-5"
                                >
                                    <Button
                                        asChild
                                        className="h-8 max-w-full shrink-0 gap-1.5 rounded-full bg-white px-4 text-xs font-semibold text-[var(--color-secondary)] shadow-md hover:bg-white/90 sm:h-10 sm:px-6 sm:text-sm"
                                    >
                                        <Link
                                            href={activeSlide.primaryCta.href}
                                        >
                                            <span className="truncate">
                                                {activeSlide.primaryCta.label}
                                            </span>
                                            <ArrowRight className="size-3 shrink-0 sm:size-3.5" />
                                        </Link>
                                    </Button>
                                </motion.div>
                            </motion.div>

                            {/* Stat cards — desktop only (avoids squeezing/overlapping the bottom pills on shorter tablet/mobile viewports); capped at 3 to match the fixed 3-column strip */}
                            <motion.div
                                className="hidden grid-cols-3 gap-3 pb-8 lg:grid"
                                initial={{ opacity: 0, y: 10 }}
                                animate={{ opacity: 1, y: 0 }}
                                transition={{
                                    duration: 0.35,
                                    delay: 0.35,
                                    ease: 'easeOut',
                                }}
                            >
                                {activeSlide.stats
                                    .slice(0, 3)
                                    .map((stat, index) => (
                                        <div
                                            key={`${stat.label}-${index}`}
                                            className="min-w-0 rounded-xl border border-white/10 bg-white/10 px-2.5 py-2 backdrop-blur-md sm:rounded-2xl sm:px-4 sm:py-3"
                                        >
                                            <p className="truncate text-[10px] tracking-[0.15em] text-white/60 uppercase sm:text-xs sm:tracking-[0.2em]">
                                                {stat.label}
                                            </p>
                                            <p className="mt-0.5 truncate text-xs font-semibold text-white sm:mt-1 sm:text-sm">
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
                            className="absolute top-1/2 left-2 z-20 hidden -translate-y-1/2 rounded-full border border-white/20 bg-white/10 p-2 text-white backdrop-blur-md transition hover:bg-white/20 sm:left-4 sm:inline-flex sm:p-2.5 lg:p-3 lg:opacity-0 lg:group-hover:opacity-100"
                        >
                            <ChevronLeft className="size-4 sm:size-5" />
                        </button>
                        <button
                            type="button"
                            onClick={goToNext}
                            aria-label="Next hero slide"
                            className="absolute top-1/2 right-2 z-20 hidden -translate-y-1/2 rounded-full border border-white/20 bg-white/10 p-2 text-white backdrop-blur-md transition hover:bg-white/20 sm:right-4 sm:inline-flex sm:p-2.5 lg:p-3 lg:opacity-0 lg:group-hover:opacity-100"
                        >
                            <ChevronRight className="size-4 sm:size-5" />
                        </button>

                        <div className="absolute right-4 bottom-4 z-20 flex items-center gap-1.5 sm:right-10 sm:bottom-6 lg:right-14">
                            {slides.map((slide, index) => (
                                <button
                                    key={slide.id}
                                    type="button"
                                    onClick={() =>
                                        goToIndex(
                                            index,
                                            index > activeIndex ? 1 : -1,
                                        )
                                    }
                                    aria-label={`Go to slide ${index + 1}`}
                                    aria-current={index === activeIndex}
                                    className={cn(
                                        'h-1.5 rounded-full transition-all',
                                        index === activeIndex
                                            ? 'w-5 bg-white'
                                            : 'w-1.5 bg-white/40 hover:bg-white/60',
                                    )}
                                />
                            ))}
                        </div>
                    </>
                ) : null}

                <div className="absolute bottom-4 left-4 z-20 flex max-w-[calc(100%-5rem)] items-center gap-2 truncate rounded-full border border-white/10 bg-white/10 px-3 py-2 text-xs text-white/90 backdrop-blur-md sm:bottom-6 sm:left-10 sm:max-w-[calc(100%-8rem)] lg:left-14 lg:max-w-[calc(100%-10rem)]">
                    <Truck className="size-4 shrink-0 text-[var(--color-accent)]" />
                    <span className="truncate">
                        Nationwide delivery across Bangladesh
                    </span>
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
                <LazyCategoryIcon
                    icon={category.icon}
                    className="size-6 transition-transform duration-300 ease-out group-hover:scale-110 sm:size-7"
                />
            </span>
            <span className="text-xs leading-tight font-semibold tracking-tight text-[var(--color-text-primary)] sm:text-sm">
                {category.name}
            </span>
            <span className="text-[11px] text-[var(--color-text-secondary)]">
                {category.productsCount} products
            </span>
        </Link>
    );
}

type CategoryShowcaseProps = {
    categories: HomeCategoryCard[];
};

/** Categories as an auto-advancing, swipeable row instead of a big grid. */
function CategoryShowcase({
    categories,
}: CategoryShowcaseProps): React.JSX.Element {
    return (
        <AutoSlider label="Shop by category" className="gap-5 py-2 sm:gap-8">
            {categories.map((category) => (
                <div key={category.slug} className="shrink-0 snap-start">
                    <CategoryCard category={category} />
                </div>
            ))}
        </AutoSlider>
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

        if (!el) {
            return;
        }

        setCanScrollLeft(el.scrollLeft > 4);
        setCanScrollRight(el.scrollLeft < el.scrollWidth - el.clientWidth - 4);
    };

    useEffect(() => {
        syncButtons();
        const el = scrollRef.current;

        if (!el) {
            return;
        }

        const ro = new ResizeObserver(syncButtons);
        ro.observe(el);

        return () => ro.disconnect();
    }, [items]);

    const scroll = (dir: 'left' | 'right') => {
        const el = scrollRef.current;

        if (!el) {
            return;
        }

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

type FlashSaleCountdownProps = {
    endsAt: string;
};

function subscribeToClockTick(callback: () => void): () => void {
    const intervalId = window.setInterval(callback, 1000);

    return () => window.clearInterval(intervalId);
}

function getClockSnapshot(): number | null {
    return Date.now();
}

// `null` until mounted so the server render and the client's first hydration
// pass produce identical output — reading `Date.now()` for both would
// capture two different timestamps (one at SSR time, one when the client
// hydrates a moment later), which can disagree on the displayed seconds and
// trigger a hydration mismatch. `useSyncExternalStore` then swaps in the
// real, ticking clock right after mount without an extra manual effect.
function getServerClockSnapshot(): number | null {
    return null;
}

function FlashSaleCountdown({
    endsAt,
}: FlashSaleCountdownProps): React.JSX.Element {
    const nowMs = useSyncExternalStore(
        subscribeToClockTick,
        getClockSnapshot,
        getServerClockSnapshot,
    );

    const remaining = useMemo(() => {
        if (nowMs === null) {
            return { difference: 1, days: 0, hours: 0, minutes: 0, seconds: 0 };
        }

        const end = new Date(endsAt).getTime();
        const difference = Math.max(0, end - nowMs);

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
    }, [endsAt, nowMs]);

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
    const {
        form,
        submit: handleSubmit,
        thankYou,
        closeThankYou,
    } = useNewsletterSubscribe();

    return (
        <Card className="selection-light overflow-hidden border-[var(--color-border)] bg-[var(--color-secondary)] text-white shadow-[var(--shadow-card-hover)]">
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
                        maxLength={150}
                        id="newsletter-home-email"
                        type="email"
                        label="Email address"
                        autoComplete="email"
                        placeholder="you@example.com"
                        value={form.data.email}
                        onChange={(event) =>
                            form.setData('email', event.target.value)
                        }
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

                    <p className="min-h-5 text-sm text-white/75">
                        Unsubscribe anytime via the link in our emails. No spam,
                        only useful updates.
                    </p>
                </form>
            </CardContent>
            <NewsletterThankYouModal
                status={thankYou?.status ?? null}
                email={thankYou?.email ?? ''}
                onClose={closeThankYou}
            />
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
        bundleDeals,
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

    const brandCards: BrandCardData[] = (brands ?? []).map((brand) => ({
        id: brand.id,
        name: brand.name,
        slug: brand.slug,
        logo: brand.logo ?? null,
        productsCount: brand.products_count ?? 0,
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
                    value: getSoldPercent(
                        item.sold_count,
                        item.product.available_stock ??
                            item.product.stock_quantity,
                    ),
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

        if (!s) {
            return null;
        }

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
                    <section className="selection-light bg-[var(--color-secondary)] py-12 text-white lg:py-16">
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
                                    className="group selection-light relative min-w-0 overflow-hidden rounded-[2rem] bg-[var(--color-secondary)] shadow-[var(--shadow-card)]"
                                >
                                    <img
                                        src={banner.image}
                                        alt={banner.title}
                                        loading="lazy"
                                        decoding="async"
                                        className="h-full min-h-[18rem] w-full object-cover transition duration-500 group-hover:scale-105"
                                    />
                                    <div
                                        className="absolute inset-0"
                                        style={{
                                            backgroundImage: isCssGradient(
                                                banner.accent,
                                            )
                                                ? banner.accent
                                                : DEFAULT_BANNER_GRADIENT,
                                        }}
                                    />
                                    <div className="absolute inset-0 bg-gradient-to-t from-black/40 via-black/10 to-transparent" />
                                    <div className="absolute inset-0 flex min-w-0 flex-col justify-end overflow-hidden p-6 text-white sm:p-8">
                                        {banner.badge && (
                                            <Badge className="mb-4 w-fit max-w-full bg-white/10 text-white backdrop-blur-md">
                                                <span className="truncate">
                                                    {banner.badge}
                                                </span>
                                            </Badge>
                                        )}
                                        <h3 className="line-clamp-2 font-display text-2xl leading-tight font-semibold tracking-tight break-words sm:text-3xl lg:text-4xl">
                                            {banner.title}
                                        </h3>
                                        {banner.description && (
                                            <p className="mt-2 line-clamp-2 max-w-lg text-sm leading-6 break-words text-white/80 sm:text-base sm:leading-7">
                                                {banner.description}
                                            </p>
                                        )}
                                        <div className="mt-5 inline-flex max-w-full shrink-0 items-center gap-2 text-sm font-semibold text-white">
                                            <span className="truncate">
                                                Explore now
                                            </span>
                                            <ArrowRight className="size-4 shrink-0 transition group-hover:translate-x-1" />
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
                    <AutoSlider label="Our brands" className="gap-4 pb-2">
                        {brandCards.map((brand) => (
                            <div key={brand.id} className="shrink-0 snap-start">
                                <BrandCard
                                    brand={brand}
                                    className="min-w-[8rem]"
                                />
                            </div>
                        ))}
                    </AutoSlider>
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
        {
            key: 'bundle_deals',
            node: getSection('bundle_deals')?.is_visible &&
                bundleDeals?.length > 0 && (
                    <section className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="relative overflow-hidden rounded-[2.5rem] border border-[var(--color-border)] bg-gradient-to-br from-[var(--color-primary-light)] via-[var(--color-surface)] to-[var(--color-accent-light)] p-6 sm:p-10">
                            <div
                                aria-hidden="true"
                                className="pointer-events-none absolute -top-16 -right-16 size-64 rounded-full bg-[var(--color-accent)]/15 blur-3xl"
                            />
                            <div
                                aria-hidden="true"
                                className="pointer-events-none absolute -bottom-20 -left-16 size-64 rounded-full bg-[var(--color-primary)]/10 blur-3xl"
                            />

                            <div className="relative space-y-8">
                                <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                                    <div className="space-y-2">
                                        <div className="flex items-center gap-2">
                                            <span className="flex size-8 items-center justify-center rounded-xl bg-[var(--color-primary)] text-white shadow-[var(--shadow-card)]">
                                                <PackagePlus className="size-4" />
                                            </span>
                                            <span className="text-xs font-bold tracking-[0.24em] text-[var(--color-primary)] uppercase">
                                                {getSection('bundle_deals')
                                                    ?.eyebrow || 'Save more'}
                                            </span>
                                        </div>
                                        <h2 className="font-display text-2xl font-semibold tracking-tight text-[var(--color-text-primary)] sm:text-3xl">
                                            {getSection('bundle_deals')
                                                ?.title || 'Bundle deals'}
                                        </h2>
                                        {getSection('bundle_deals')
                                            ?.description && (
                                            <p className="max-w-2xl text-sm text-[var(--color-text-secondary)] sm:text-base">
                                                {
                                                    getSection('bundle_deals')
                                                        ?.description
                                                }
                                            </p>
                                        )}
                                    </div>
                                    <Button
                                        asChild
                                        variant="secondary"
                                        className="w-fit shrink-0"
                                    >
                                        <Link
                                            href={
                                                getSection('bundle_deals')
                                                    ?.action_href ||
                                                '/bundle-deals'
                                            }
                                        >
                                            {getSection('bundle_deals')
                                                ?.action_label ||
                                                'View all bundles'}
                                            <ArrowRight className="size-3.5" />
                                        </Link>
                                    </Button>
                                </div>

                                <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                                    {bundleDeals.map((bundle) => (
                                        <BundleCard
                                            key={bundle.id}
                                            bundle={bundle}
                                        />
                                    ))}
                                </div>
                            </div>
                        </div>
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
