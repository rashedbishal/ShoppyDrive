import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    Filter,
    Heart,
    LayoutGrid,
    List,
    Search,
    ShieldCheck,
    SlidersHorizontal,
    Truck,
    X,
} from 'lucide-react';
import { useEffect, useMemo, useState, type FormEvent, type ReactNode } from 'react';
import { Accordion } from '@/components/ui/accordion';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Drawer } from '@/components/ui/drawer';
import { EmptyState } from '@/components/ui/empty-state';
import { Input } from '@/components/ui/input';
import { Pagination } from '@/components/ui/pagination';
import { Select } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { StarRating } from '@/components/ui/star-rating';
import { cn, formatCurrency, getDiscountPercent, truncate } from '@/lib/utils';
import { useMediaQuery } from '@/lib/hooks/useMediaQuery';
import { useCartStore } from '@/stores/cartStore';
import { useUiStore } from '@/stores/uiStore';
import { useWishlistStore } from '@/stores/wishlistStore';
import type {
    Attribute,
    AttributeValue,
    Brand,
    Category,
    PaginatedData,
    Product,
    SharedPageProps,
} from '@/types';
import { route } from 'ziggy-js';

type ListingAttributeValue = AttributeValue;

type ListingAttribute = Attribute & {
    values: ListingAttributeValue[];
};

type ListingCategory = Category & {
    sub_categories?: SubCategory[];
    products_count?: number;
};

type ListingBrand = Brand & {
    products_count?: number;
};

type ProductListingFilters = {
    q: string;
    sort: string;
    view: 'grid' | 'list';
    brand: string[];
    attributes: Record<string, number[]>;
    min_price: number | null;
    max_price: number | null;
    rating: number | null;
    in_stock: boolean;
    page: number;
    banner_id: number | null;
};

type ProductListingPageProps = {
    title: string;
    description: string;
    category: Category | null;
    categories: ListingCategory[];
    brands: ListingBrand[];
    attributes: ListingAttribute[];
    products: PaginatedData<Product>;
    listingUrl: string;
    maxPrice: number;
    filters: ProductListingFilters;
};

type QueryValue = string | number | boolean | Array<string | number> | Record<string, number[]>;

type DraftFilters = ProductListingFilters;

type FilterGroupProps = {
    title: string;
    children: ReactNode;
    action?: ReactNode;
};

const SORT_OPTIONS = [
    { label: 'Relevance', value: 'relevance' },
    { label: 'Price: Low to High', value: 'price_asc' },
    { label: 'Price: High to Low', value: 'price_desc' },
    { label: 'Newest', value: 'newest' },
    { label: 'Top Rated', value: 'top_rated' },
    { label: 'Most Reviewed', value: 'most_reviewed' },
] as const;

const DEFAULT_GRID_COUNT = 12;

// ── Price Range Filter ────────────────────────────────────────────────────────
function PriceRangeFilter({
    lo, hi, cap,
    onChange,
}: {
    lo: number;
    hi: number;
    cap: number;
    onChange: (lo: number, hi: number) => void;
}): React.JSX.Element {
    const [inputLo, setInputLo] = useState(String(lo));
    const [inputHi, setInputHi] = useState(String(hi));

    useEffect(() => setInputLo(String(lo)), [lo]);
    useEffect(() => setInputHi(String(hi)), [hi]);

    const loPercent = cap > 0 ? (lo / cap) * 100 : 0;
    const hiPercent = cap > 0 ? (hi / cap) * 100 : 100;
    // When min handle is pushed far right, give it priority so it stays grabbable
    const minOnTop = loPercent > 95;

    const commitLo = (raw: string) => {
        const v = Math.max(0, Math.min(Math.round(Number(raw) / 10) * 10, hi - 10));
        onChange(isNaN(v) ? 0 : v, hi);
    };
    const commitHi = (raw: string) => {
        const v = Math.max(lo + 10, Math.min(Math.round(Number(raw) / 10) * 10, cap));
        onChange(lo, isNaN(v) ? cap : v);
    };

    return (
        <div className="space-y-5">
            {/* Value display */}
            <div className="flex items-stretch gap-2">
                <div className="flex flex-1 flex-col items-center justify-center rounded-xl border border-[var(--color-primary)]/20 bg-[var(--color-primary)]/5 py-2">
                    <span className="text-[10px] font-bold uppercase tracking-widest text-[var(--color-text-muted)]">Min</span>
                    <span className="text-sm font-bold text-[var(--color-primary)]">৳{lo.toLocaleString('en-BD')}</span>
                </div>
                <div className="flex items-center text-sm text-[var(--color-text-muted)]">—</div>
                <div className="flex flex-1 flex-col items-center justify-center rounded-xl border border-[var(--color-primary)]/20 bg-[var(--color-primary)]/5 py-2">
                    <span className="text-[10px] font-bold uppercase tracking-widest text-[var(--color-text-muted)]">Max</span>
                    <span className="text-sm font-bold text-[var(--color-primary)]">৳{hi.toLocaleString('en-BD')}</span>
                </div>
            </div>

            {/* Dual-handle track */}
            <div className="relative mx-1.5 py-3">
                {/* Background track */}
                <div className="absolute top-1/2 left-0 right-0 h-2 -translate-y-1/2 rounded-full bg-[var(--color-surface-alt)] shadow-inner" />
                {/* Active fill */}
                <div
                    className="absolute top-1/2 h-2 -translate-y-1/2 rounded-full bg-[var(--color-primary)] transition-[left,width] duration-75"
                    style={{ left: `${loPercent}%`, width: `${Math.max(0, hiPercent - loPercent)}%` }}
                />
                {/* Visual thumbs (pointer-events-none — interaction via hidden inputs) */}
                <div
                    className="pointer-events-none absolute top-1/2 h-5 w-5 -translate-x-1/2 -translate-y-1/2 rounded-full border-[3px] border-[var(--color-primary)] bg-white shadow-[0_2px_6px_rgba(0,0,0,0.18)] transition-[left] duration-75"
                    style={{ left: `${loPercent}%` }}
                />
                <div
                    className="pointer-events-none absolute top-1/2 h-5 w-5 -translate-x-1/2 -translate-y-1/2 rounded-full border-[3px] border-[var(--color-primary)] bg-white shadow-[0_2px_6px_rgba(0,0,0,0.18)] transition-[left] duration-75"
                    style={{ left: `${hiPercent}%` }}
                />
                {/* Hidden interactive inputs */}
                <input
                    type="range" min={0} max={cap} step={10} value={lo}
                    onChange={(e) => onChange(Math.min(Number(e.target.value), hi - 10), hi)}
                    className={cn(
                        'absolute inset-0 h-full w-full cursor-pointer appearance-none bg-transparent opacity-0',
                        minOnTop ? 'z-30' : 'z-20',
                    )}
                />
                <input
                    type="range" min={10} max={cap} step={10} value={hi}
                    onChange={(e) => onChange(lo, Math.max(Number(e.target.value), lo + 10))}
                    className={cn(
                        'absolute inset-0 h-full w-full cursor-pointer appearance-none bg-transparent opacity-0',
                        minOnTop ? 'z-20' : 'z-30',
                    )}
                />
            </div>

            {/* Editable inputs — apply on blur or Enter */}
            <div className="grid grid-cols-2 gap-2.5">
                <div className="space-y-1.5">
                    <label className="text-[10px] font-bold uppercase tracking-widest text-[var(--color-text-muted)]">
                        Min price
                    </label>
                    <div className="relative">
                        <span className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs font-bold text-[var(--color-text-muted)]">৳</span>
                        <input
                            type="number" min={0} step={10}
                            value={inputLo}
                            onChange={(e) => setInputLo(e.target.value)}
                            onBlur={() => commitLo(inputLo)}
                            onKeyDown={(e) => e.key === 'Enter' && commitLo(inputLo)}
                            className="h-10 w-full rounded-xl border border-[var(--color-border)] bg-white pl-7 pr-2 text-sm font-medium transition focus:border-[var(--color-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]/20"
                        />
                    </div>
                </div>
                <div className="space-y-1.5">
                    <label className="text-[10px] font-bold uppercase tracking-widest text-[var(--color-text-muted)]">
                        Max price
                    </label>
                    <div className="relative">
                        <span className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs font-bold text-[var(--color-text-muted)]">৳</span>
                        <input
                            type="number" min={10} step={10}
                            value={inputHi}
                            onChange={(e) => setInputHi(e.target.value)}
                            onBlur={() => commitHi(inputHi)}
                            onKeyDown={(e) => e.key === 'Enter' && commitHi(inputHi)}
                            className="h-10 w-full rounded-xl border border-[var(--color-border)] bg-white pl-7 pr-2 text-sm font-medium transition focus:border-[var(--color-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]/20"
                        />
                    </div>
                </div>
            </div>
        </div>
    );
}

function FilterGroup({ title, children, action }: FilterGroupProps): React.JSX.Element {
    return (
        <div className="space-y-4 border-b border-[var(--color-border)] pb-6 last:border-b-0 last:pb-0">
            <div className="flex items-center justify-between gap-3">
                <h3 className="text-sm font-semibold uppercase tracking-[0.2em] text-[var(--color-text-primary)]">
                    {title}
                </h3>
                {action}
            </div>
            {children}
        </div>
    );
}

function ListingProductSkeleton({ view }: { view: 'grid' | 'list' }): React.JSX.Element {
    if (view === 'list') {
        return (
            <Card className="overflow-hidden border-[var(--color-border)] bg-[var(--color-surface)] p-0 shadow-[var(--shadow-card)]">
                <div className="grid gap-4 p-4 sm:grid-cols-[220px_minmax(0,1fr)]">
                    <Skeleton className="aspect-[4/3] w-full rounded-2xl" />
                    <div className="space-y-4">
                        <div className="space-y-2">
                            <Skeleton className="h-3 w-24" />
                            <Skeleton className="h-5 w-4/5" />
                            <Skeleton className="h-4 w-full" />
                            <Skeleton className="h-4 w-5/6" />
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <Skeleton className="h-4 w-20" />
                            <Skeleton className="h-4 w-12" />
                        </div>
                        <div className="flex items-end gap-2">
                            <Skeleton className="h-7 w-28" />
                            <Skeleton className="h-4 w-16" />
                        </div>
                        <Skeleton className="h-11 w-40 rounded-xl" />
                    </div>
                </div>
            </Card>
        );
    }

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

function ProductCard({
    product,
    view,
}: {
    product: Product;
    view: 'grid' | 'list';
}): React.JSX.Element {
    const addItem = useCartStore((state) => state.addItem);
    const openCart = useUiStore((state) => state.openCart);
    const addToast = useUiStore((state) => state.addToast);
    const toggleWishlist = useWishlistStore((state) => state.toggleWishlist);
    const isWishlisted = useWishlistStore((state) => state.isWishlisted(product.id));
    const [isHovered, setIsHovered] = useState(false);

    const imageSet = product.images ?? [];
    const primaryImage = imageSet.find((image) => image.is_primary) ?? imageSet[0];
    const secondaryImage = imageSet.find((image) => !image.is_primary);
    const currentPrice =
        product.in_flash_sale && typeof product.flash_sale_price === 'number'
            ? product.flash_sale_price
            : product.price;
    const discountPercent = product.compare_price
        ? getDiscountPercent(currentPrice, product.compare_price)
        : 0;
    const isOutOfStock = product.stock_quantity <= 0;
    const stockLabel =
        isOutOfStock
            ? 'Out of stock'
            : product.stock_quantity <= product.low_stock_threshold
                ? `Only ${product.stock_quantity} left`
                : 'In stock';

    const handleAddToCart = (): void => {
        if (isOutOfStock) {
            addToast({
                type: 'warning',
                title: 'Out of stock',
                message: `${product.name} is not currently available.`,
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
            type: isWishlisted ? 'info' : 'success',
            title: isWishlisted ? 'Removed from wishlist' : 'Saved to wishlist',
            message: isWishlisted
                ? `${product.name} was removed from your wishlist.`
                : `${product.name} was added to your wishlist.`,
        });
    };

    if (view === 'list') {
        return (
            <article
                onMouseEnter={() => setIsHovered(true)}
                onMouseLeave={() => setIsHovered(false)}
                className="overflow-hidden border-[var(--color-border)] bg-[var(--color-surface)] shadow-[var(--shadow-card)]"
            >
                <div className="grid gap-4 p-4 sm:grid-cols-[220px_minmax(0,1fr)]">
                    <Link href={route('product.show', product.slug)} className="block">
                        <div className="relative aspect-[4/3] overflow-hidden rounded-2xl bg-[var(--color-surface-alt)]">
                            {primaryImage?.path ? (
                                <>
                                    <img
                                        src={primaryImage.path}
                                        alt={primaryImage.alt ?? product.name}
                                        loading="lazy"
                                        decoding="async"
                                        className={cn(
                                            'h-full w-full object-cover transition duration-500',
                                            isHovered && secondaryImage?.path ? 'opacity-0 scale-105' : 'opacity-100',
                                        )}
                                    />
                                    {secondaryImage?.path ? (
                                        <img
                                            src={secondaryImage.path}
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
                                </>
                            ) : (
                                <div className="flex h-full items-center justify-center text-[var(--color-text-muted)]">
                                    <Search className="size-10" />
                                </div>
                            )}
                            {discountPercent > 0 ? (
                                <Badge className="absolute left-3 top-3 bg-[var(--color-accent)] text-[var(--color-secondary)]">
                                    SALE {discountPercent}%
                                </Badge>
                            ) : null}
                            <button
                                type="button"
                                onClick={(event) => {
                                    event.preventDefault();
                                    event.stopPropagation();
                                    handleWishlist();
                                }}
                                aria-label={isWishlisted ? `Remove ${product.name} from wishlist` : `Add ${product.name} to wishlist`}
                                className="absolute right-3 top-3 inline-flex size-10 items-center justify-center rounded-full border border-[var(--color-border)] bg-white/95 text-[var(--color-text-secondary)] shadow-sm transition hover:scale-105"
                            >
                                <Heart className={cn('size-4', isWishlisted ? 'fill-current text-[var(--color-primary)]' : '')} />
                            </button>
                        </div>
                    </Link>

                    <div className="flex min-w-0 flex-col gap-4">
                        <div className="space-y-2">
                            <p className="text-xs font-semibold uppercase tracking-[0.22em] text-[var(--color-text-muted)]">
                                {product.brand?.name ?? 'ShoppyDrive Select'}
                            </p>
                            <Link
                                href={route('product.show', product.slug)}
                                className="line-clamp-2 text-lg font-semibold text-[var(--color-text-primary)] transition hover:text-[var(--color-primary)]"
                            >
                                {product.name}
                            </Link>
                            <p className="text-sm leading-6 text-[var(--color-text-secondary)]">
                                {truncate(product.short_description ?? product.description ?? '', 150)}
                            </p>
                        </div>

                        <div className="flex flex-wrap items-center gap-2">
                            <StarRating rating={product.average_rating ?? 0} size="sm" className="text-[var(--color-accent)]" />
                            <span className="text-xs text-[var(--color-text-secondary)]">
                                ({product.reviews_count ?? 0})
                            </span>
                            <Badge variant={isOutOfStock ? 'error' : 'success'} size="sm">
                                {stockLabel}
                            </Badge>
                        </div>

                        <div className="flex flex-wrap items-end gap-2">
                            <p className="text-2xl font-bold tracking-tight text-[var(--color-primary)]">
                                {formatCurrency(currentPrice)}
                            </p>
                            {product.compare_price && product.compare_price > currentPrice ? (
                                <p className="pb-0.5 text-sm text-[var(--color-text-muted)] line-through">
                                    {formatCurrency(product.compare_price)}
                                </p>
                            ) : null}
                        </div>

                        <div className="flex flex-wrap gap-3">
                            <Button
                                type="button"
                                onClick={handleAddToCart}
                                disabled={isOutOfStock}
                                className="flex-1 sm:flex-none"
                            >
                                {isOutOfStock ? 'Out of Stock' : 'Add to Cart'}
                            </Button>
                            <Button asChild variant="outline" className="flex-1 sm:flex-none">
                                <Link href={route('product.show', product.slug)}>
                                    View Details
                                    <ArrowRight className="size-4" />
                                </Link>
                            </Button>
                        </div>
                    </div>
                </div>
            </article>
        );
    }

    return (
        <article
            onMouseEnter={() => setIsHovered(true)}
            onMouseLeave={() => setIsHovered(false)}
            className="group overflow-hidden border-[var(--color-border)] bg-[var(--color-surface)] p-0 shadow-[var(--shadow-card)] transition hover:shadow-[var(--shadow-card-hover)]"
        >
            <div className="relative">
                <Link href={route('product.show', product.slug)} className="block">
                    <div className="relative aspect-[4/3] overflow-hidden bg-[var(--color-surface-alt)]">
                        {primaryImage?.path ? (
                            <>
                                <img
                                    src={primaryImage.path}
                                    alt={primaryImage.alt ?? product.name}
                                    loading="lazy"
                                    decoding="async"
                                    className={cn(
                                        'h-full w-full object-cover transition duration-500',
                                        isHovered && secondaryImage?.path ? 'opacity-0 scale-105' : 'opacity-100',
                                    )}
                                />
                                {secondaryImage?.path ? (
                                    <img
                                        src={secondaryImage.path}
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
                            </>
                        ) : (
                            <div className="flex h-full items-center justify-center text-[var(--color-text-muted)]">
                                <Search className="size-10" />
                            </div>
                        )}

                        {discountPercent > 0 ? (
                            <Badge className="absolute left-4 top-4 bg-[var(--color-accent)] text-[var(--color-secondary)]">
                                SALE {discountPercent}%
                            </Badge>
                        ) : null}

                        <button
                            type="button"
                            onClick={(event) => {
                                event.preventDefault();
                                event.stopPropagation();
                                handleWishlist();
                            }}
                            aria-label={isWishlisted ? `Remove ${product.name} from wishlist` : `Add ${product.name} to wishlist`}
                            className="absolute right-4 top-4 inline-flex size-10 items-center justify-center rounded-full border border-[var(--color-border)] bg-white/95 text-[var(--color-text-secondary)] shadow-sm transition hover:scale-105"
                        >
                            <Heart className={cn('size-4', isWishlisted ? 'fill-current text-[var(--color-primary)]' : '')} />
                        </button>

                        {isOutOfStock ? (
                            <div className="absolute inset-0 flex items-center justify-center bg-[rgba(26,26,46,0.62)]">
                                <Badge variant="error" className="bg-[rgba(220,38,38,0.96)] text-white">
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
                        href={route('product.show', product.slug)}
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
                        <span>{stockLabel}</span>
                        {product.stock_quantity <= product.low_stock_threshold && !isOutOfStock ? (
                            <span className="text-[var(--color-accent)]">Selling fast</span>
                        ) : null}
                    </div>
                    {product.in_flash_sale && typeof product.flash_sale_price === 'number' ? (
                        <div className="h-2 overflow-hidden rounded-full bg-[var(--color-surface-alt)]">
                            <div
                                className="h-full rounded-full bg-[var(--color-accent)]"
                                style={{ width: `${Math.min(100, Math.max(15, discountPercent * 2))}%` }}
                            />
                        </div>
                    ) : null}
                </div>

                <Button
                    type="button"
                    fullWidth
                    onClick={handleAddToCart}
                    disabled={isOutOfStock}
                    className="translate-y-0 transition-transform duration-200 group-hover:-translate-y-1"
                >
                    {isOutOfStock ? 'Out of Stock' : 'Add to Cart'}
                </Button>
            </div>
        </article>
    );
}

function ProductListingPage({
    title,
    description,
    category,
    categories,
    brands,
    attributes,
    products,
    listingUrl,
    maxPrice,
    filters,
}: ProductListingPageProps): React.JSX.Element {
    const page = usePage<SharedPageProps>();
    const isDesktop = useMediaQuery('(min-width: 768px)');
    const [isFiltersOpen, setIsFiltersOpen] = useState(false);
    const [isLoading, setIsLoading] = useState(false);
    const [draftFilters, setDraftFilters] = useState<ProductListingFilters>(filters);
    const [searchInput, setSearchInput] = useState(filters.q);

    useEffect(() => {
        setDraftFilters(filters);
        setSearchInput(filters.q);
        setIsLoading(false);
    }, [filters]);

    const activeCategory = useMemo(() => category, [category]);

    const hasActiveFilters = useMemo(() => {
        return Boolean(
            filters.q ||
                filters.brand.length > 0 ||
                filters.min_price !== null ||
                filters.max_price !== null ||
                filters.rating !== null ||
                filters.in_stock ||
                Object.values(filters.attributes).some((value) => value.length > 0),
        );
    }, [filters]);

    const submitFilters = (nextFilters: ProductListingFilters, nextUrl: string = listingUrl): void => {
        setIsLoading(true);

        const query = buildQuery(nextFilters);

        router.get(nextUrl, query as never, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
            onFinish: () => {
                setIsLoading(false);
                setIsFiltersOpen(false);
            },
        });
    };

    const applyImmediateChange = (
        updater: (current: ProductListingFilters) => ProductListingFilters,
        nextUrl?: string,
    ): void => {
        setDraftFilters((current) => {
            const next = updater(current);

            if (isDesktop) {
                submitFilters(next, nextUrl);
            }

            return next;
        });
    };

    const handleSearchSubmit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        const next = {
            ...draftFilters,
            q: searchInput.trim(),
            page: 1,
        };
        setDraftFilters(next);
        submitFilters(next);
    };

    const handleClearAll = (): void => {
        const next: ProductListingFilters = {
            ...draftFilters,
            q: '',
            brand: [],
            min_price: null,
            max_price: null,
            rating: null,
            in_stock: false,
            attributes: {},
            sort: 'relevance',
            view: draftFilters.view,
            page: 1,
        };

        setDraftFilters(next);
        setSearchInput('');
        submitFilters(next, category === null ? '/products' : listingUrl);
    };

    const handlePageChange = (pageNumber: number): void => {
        const next = {
            ...draftFilters,
            page: pageNumber,
        };

        setDraftFilters(next);
        submitFilters(next);
    };

    const handleCategoryNavigate = (categorySlug: string): void => {
        const nextUrl = route('category.show', { slug: categorySlug });
        const next = {
            ...draftFilters,
            page: 1,
        };

        setDraftFilters(next);
        submitFilters(next, nextUrl);
    };

    const clearCategory = (): void => {
        const next = {
            ...draftFilters,
            page: 1,
        };

        setDraftFilters(next);
        submitFilters(next, '/products');
    };

    const removeBrand = (brandSlug: string): void => {
        const next = {
            ...draftFilters,
            brand: draftFilters.brand.filter((current) => current !== brandSlug),
            page: 1,
        };

        setDraftFilters(next);
        submitFilters(next);
    };

    const removeAttribute = (attributeId: string, valueId: number): void => {
        const currentValues = draftFilters.attributes[attributeId] ?? [];
        const nextAttributes = {
            ...draftFilters.attributes,
            [attributeId]: currentValues.filter((current) => current !== valueId),
        };

        if (nextAttributes[attributeId].length === 0) {
            delete nextAttributes[attributeId];
        }

        const next = {
            ...draftFilters,
            attributes: nextAttributes,
            page: 1,
        };

        setDraftFilters(next);
        submitFilters(next);
    };

    const activeBrandNames = brands
        .filter((brand) => draftFilters.brand.includes(brand.slug))
        .map((brand) => ({ slug: brand.slug, label: brand.name }));

    const categoryItems = categories.map((node) => ({
        id: `category-${node.id}`,
        title: `${node.name}${typeof node.products_count === 'number' ? ` (${node.products_count})` : ''}`,
        content: (
            <div className="space-y-3">
                <button
                    type="button"
                    onClick={() => handleCategoryNavigate(node.slug)}
                    className={cn(
                        'block w-full rounded-xl px-3 py-2 text-left text-sm font-medium transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primary)]',
                        activeCategory?.id === node.id
                            ? 'bg-[var(--color-primary-light)] text-[var(--color-primary)]'
                            : 'text-[var(--color-text-secondary)] hover:bg-[var(--color-surface-alt)] hover:text-[var(--color-text-primary)]',
                    )}
                >
                    Shop {node.name}
                </button>
                {node.sub_categories && node.sub_categories.length > 0 ? (
                    <div className="space-y-2 pl-3">
                        {node.sub_categories.map((child) => (
                            <button
                                key={child.id}
                                type="button"
                                onClick={() => handleCategoryNavigate(child.slug)}
                                className={cn(
                                    'block w-full rounded-xl px-3 py-2 text-left text-sm transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primary)]',
                                    activeCategory?.id === child.id
                                        ? 'bg-[var(--color-primary-light)] text-[var(--color-primary)]'
                                        : 'text-[var(--color-text-secondary)] hover:bg-[var(--color-surface-alt)] hover:text-[var(--color-text-primary)]',
                                )}
                            >
                                {child.name}
                                {typeof child.products_count === 'number' ? ` (${child.products_count})` : ''}
                            </button>
                        ))}
                    </div>
                ) : null}
            </div>
        ),
    }));

    const sidebarControls = (
        <div className="space-y-6">
            <FilterGroup title="Categories">
                <Accordion items={categoryItems} defaultOpenIds={categoryItems.length > 0 ? [categoryItems[0].id] : []} />
            </FilterGroup>

            <FilterGroup title="Price Range">
                <PriceRangeFilter
                    lo={draftFilters.min_price ?? 0}
                    hi={draftFilters.max_price ?? Math.max(1000, maxPrice)}
                    cap={Math.max(1000, maxPrice)}
                    onChange={(lo, hi) =>
                        applyImmediateChange((c) => ({ ...c, min_price: lo, max_price: hi, page: 1 }))
                    }
                />
            </FilterGroup>

            <FilterGroup title="Brands">
                <div className="space-y-3">
                    {brands.map((brand) => {
                        const checked = draftFilters.brand.includes(brand.slug);

                        return (
                            <Checkbox
                                key={brand.id}
                                label={`${brand.name}${typeof brand.products_count === 'number' ? ` (${brand.products_count})` : ''}`}
                                checked={checked}
                                onChange={() => {
                                    applyImmediateChange((current) => ({
                                        ...current,
                                        brand: checked
                                            ? current.brand.filter((item) => item !== brand.slug)
                                            : [...current.brand, brand.slug],
                                        page: 1,
                                    }));
                                }}
                            />
                        );
                    })}
                </div>
            </FilterGroup>


            <FilterGroup title="Availability">
                <Checkbox
                    label="In stock only"
                    checked={draftFilters.in_stock}
                    onChange={() => {
                        applyImmediateChange((current) => ({
                            ...current,
                            in_stock: !current.in_stock,
                            page: 1,
                        }));
                    }}
                />
            </FilterGroup>

            {attributes.length > 0 ? (
                <FilterGroup title="Attributes">
                    <div className="space-y-5">
                        {attributes.map((attribute) => (
                            <div key={attribute.id} className="space-y-3">
                                <p className="text-sm font-semibold text-[var(--color-text-primary)]">
                                    {attribute.name}
                                </p>
                                <div className="space-y-2">
                                    {attribute.values.map((value) => {
                                        const activeValues = draftFilters.attributes[String(attribute.id)] ?? [];
                                        const checked = activeValues.includes(value.id);

                                        return attribute.type === 'color' && value.color_hex ? (
                                            <button
                                                key={value.id}
                                                type="button"
                                                onClick={() => {
                                                    applyImmediateChange((current) => toggleAttributeSelection(current, attribute.id, value.id));
                                                }}
                                                className={cn(
                                                    'flex w-full items-center justify-between rounded-xl border px-3 py-2 text-sm transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primary)]',
                                                    checked
                                                        ? 'border-[var(--color-primary)] bg-[var(--color-primary-light)] text-[var(--color-primary)]'
                                                        : 'border-[var(--color-border)] bg-white text-[var(--color-text-secondary)] hover:bg-[var(--color-surface-alt)]',
                                                )}
                                            >
                                                <span className="inline-flex items-center gap-3">
                                                    <span
                                                        className="size-4 rounded-full border border-white shadow-sm"
                                                        style={{ backgroundColor: value.color_hex }}
                                                        aria-hidden="true"
                                                    />
                                                    {value.value}
                                                </span>
                                                {checked ? <X className="size-3.5" /> : null}
                                            </button>
                                        ) : (
                                            <Checkbox
                                                key={value.id}
                                                label={value.value}
                                                checked={checked}
                                                onChange={() => {
                                                    applyImmediateChange((current) => toggleAttributeSelection(current, attribute.id, value.id));
                                                }}
                                            />
                                        );
                                    })}
                                </div>
                            </div>
                        ))}
                    </div>
                </FilterGroup>
            ) : null}
        </div>
    );

    const activeAttributeChips = Object.entries(draftFilters.attributes).flatMap(([attributeId, valueIds]) => {
        const attribute = attributes.find((item) => item.id === Number(attributeId));

        if (!attribute) {
            return [];
        }

        return valueIds.flatMap((valueId) => {
            const value = attribute.values.find((item) => item.id === valueId);

            if (!value) {
                return [];
            }

            return [{
                key: `attribute-${attributeId}-${valueId}`,
                label: `${attribute.name}: ${value.value}`,
                onRemove: () => removeAttribute(attributeId, valueId),
            }];
        });
    });

    const activeFilterChips = [
        ...(category
            ? [
                  {
                      key: 'category',
                      label: `Category: ${category.name}`,
                      onRemove: clearCategory,
                  },
              ]
            : []),
        ...(draftFilters.q ? [{
            key: 'search',
            label: `Search: ${draftFilters.q}`,
            onRemove: () => {
                const next = { ...draftFilters, q: '', page: 1 };
                setDraftFilters(next);
                setSearchInput('');
                submitFilters(next);
            },
        }] : []),
        ...activeBrandNames.map((brand) => ({
            key: `brand-${brand.slug}`,
            label: `Brand: ${brand.label}`,
            onRemove: () => removeBrand(brand.slug),
        })),
        ...(draftFilters.min_price !== null || draftFilters.max_price !== null ? [{
            key: 'price',
            label: `Price: ৳${(draftFilters.min_price ?? 0).toLocaleString('en-BD')} - ৳${(draftFilters.max_price ?? maxPrice).toLocaleString('en-BD')}`,
            onRemove: () => {
                const next = {
                    ...draftFilters,
                    min_price: null,
                    max_price: null,
                    page: 1,
                };
                setDraftFilters(next);
                submitFilters(next);
            },
        }] : []),
        ...(draftFilters.rating !== null ? [{
            key: 'rating',
            label: `${draftFilters.rating}★ & up`,
            onRemove: () => {
                const next = { ...draftFilters, rating: null, page: 1 };
                setDraftFilters(next);
                submitFilters(next);
            },
        }] : []),
        ...(draftFilters.in_stock ? [{
            key: 'stock',
            label: 'In stock only',
            onRemove: () => {
                const next = { ...draftFilters, in_stock: false, page: 1 };
                setDraftFilters(next);
                submitFilters(next);
            },
        }] : []),
        ...activeAttributeChips,
    ];

    const drawerFooter = (
        <div className="flex items-center gap-3">
            <Button variant="outline" fullWidth onClick={handleClearAll}>
                Clear All
            </Button>
            <Button
                fullWidth
                onClick={() => submitFilters(draftFilters)}
            >
                Apply Filters
            </Button>
        </div>
    );

    return (
        <>
            <Head title={title} />

            <div className="space-y-8 py-8 lg:py-12">
                <section className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="grid gap-6 rounded-[2rem] border border-[var(--color-border)] bg-[var(--color-surface)] p-6 shadow-[var(--shadow-card)] lg:grid-cols-[1.1fr_0.9fr] lg:p-8">
                        <div className="space-y-4">
                            <Badge variant="amber" className="w-fit">
                                <SlidersHorizontal className="size-3.5" />
                                Catalog filters
                            </Badge>
                            <div className="space-y-2">
                                <p className="text-xs font-semibold uppercase tracking-[0.22em] text-[var(--color-text-muted)]">
                                    {filters.banner_id ? 'Collection' : category ? 'Category browse' : 'All products'}
                                </p>
                                <h1 className="font-display text-3xl font-semibold tracking-tight text-[var(--color-text-primary)] sm:text-4xl">
                                    {title}
                                </h1>
                                <p className="max-w-2xl text-sm leading-7 text-[var(--color-text-secondary)] sm:text-base">
                                    {description}
                                </p>
                            </div>
                            <div className="flex flex-wrap gap-3">
                                <div className="inline-flex items-center gap-2 rounded-full border border-[var(--color-border)] bg-[var(--color-surface-alt)] px-4 py-2 text-sm text-[var(--color-text-secondary)]">
                                    <ShieldCheck className="size-4 text-[var(--color-success)]" />
                                    Secure checkout ready
                                </div>
                                <div className="inline-flex items-center gap-2 rounded-full border border-[var(--color-border)] bg-[var(--color-surface-alt)] px-4 py-2 text-sm text-[var(--color-text-secondary)]">
                                    <Truck className="size-4 text-[var(--color-primary)]" />
                                    Nationwide delivery
                                </div>
                            </div>
                        </div>

                        <div className="grid gap-3 sm:grid-cols-3">
                            {[
                                ['Result count', `${products.total} items`],
                                ['Current view', draftFilters.view === 'grid' ? 'Grid' : 'List'],
                                ['Currency', 'Bangladeshi taka'],
                            ].map(([label, value]) => (
                                <div
                                    key={label}
                                    className="rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface-alt)] p-4"
                                >
                                    <p className="text-xs uppercase tracking-[0.2em] text-[var(--color-text-muted)]">
                                        {label}
                                    </p>
                                    <p className="mt-2 text-sm font-semibold text-[var(--color-text-primary)]">
                                        {value}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                <section className="mx-auto grid max-w-7xl gap-8 px-4 lg:grid-cols-[280px_minmax(0,1fr)] lg:px-8">
                    <aside className="hidden lg:block">
                        <div className="sticky top-28 space-y-6 rounded-[2rem] border border-[var(--color-border)] bg-[var(--color-surface)] p-5 shadow-[var(--shadow-card)]">
                            <div className="flex items-center justify-between">
                                <h2 className="text-lg font-semibold text-[var(--color-text-primary)]">
                                    Filters
                                </h2>
                                {hasActiveFilters ? (
                                    <Button variant="ghost" className="px-0 text-[var(--color-primary)]" onClick={handleClearAll}>
                                        Clear All
                                    </Button>
                                ) : null}
                            </div>
                            {sidebarControls}
                        </div>
                    </aside>

                    <div className="space-y-6">
                        <div className="rounded-[2rem] border border-[var(--color-border)] bg-[var(--color-surface)] p-4 shadow-[var(--shadow-card)]">
                            <div className="grid gap-4 lg:grid-cols-[1fr_auto_auto] lg:items-center">
                                <form onSubmit={handleSearchSubmit} className="flex gap-3">
                                    <div className="relative flex-1">
                                        <Search className="pointer-events-none absolute left-4 top-1/2 size-4 -translate-y-1/2 text-[var(--color-text-muted)]" />
                                        <Input
                                            value={searchInput}
                                            onChange={(event) => setSearchInput(event.target.value)}
                                            placeholder="Search products, brands, or SKUs"
                                            className="h-12 rounded-full border-[var(--color-border)] pl-11"
                                        />
                                    </div>
                                    <Button type="submit" variant="outline" className="hidden sm:inline-flex">
                                        Search
                                    </Button>
                                </form>

                                <div className="flex items-center gap-3">
                                    <Button
                                        variant="outline"
                                        className="lg:hidden"
                                        onClick={() => setIsFiltersOpen(true)}
                                    >
                                        <Filter className="size-4" />
                                        Filters
                                    </Button>

                                    <Select
                                        value={draftFilters.sort}
                                        onChange={(event) => {
                                            const next = { ...draftFilters, sort: event.target.value, page: 1 };
                                            setDraftFilters(next);
                                            submitFilters(next);
                                        }}
                                        options={SORT_OPTIONS.map((option) => ({
                                            label: option.label,
                                            value: option.value,
                                        }))}
                                        selectClassName="min-w-52"
                                    />

                                    <div className="inline-flex overflow-hidden rounded-xl border border-[var(--color-border)]">
                                        <button
                                            type="button"
                                            onClick={() => {
                                                const next: ProductListingFilters = {
                                                    ...draftFilters,
                                                    view: 'grid',
                                                    page: 1,
                                                };
                                                setDraftFilters(next);
                                                submitFilters(next);
                                            }}
                                            aria-label="Grid view"
                                            className={cn(
                                                'inline-flex size-11 items-center justify-center transition',
                                                draftFilters.view === 'grid'
                                                    ? 'bg-[var(--color-primary-light)] text-[var(--color-primary)]'
                                                    : 'bg-white text-[var(--color-text-secondary)] hover:bg-[var(--color-surface-alt)]',
                                            )}
                                        >
                                            <LayoutGrid className="size-4" />
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => {
                                                const next: ProductListingFilters = {
                                                    ...draftFilters,
                                                    view: 'list',
                                                    page: 1,
                                                };
                                                setDraftFilters(next);
                                                submitFilters(next);
                                            }}
                                            aria-label="List view"
                                            className={cn(
                                                'inline-flex size-11 items-center justify-center transition',
                                                draftFilters.view === 'list'
                                                    ? 'bg-[var(--color-primary-light)] text-[var(--color-primary)]'
                                                    : 'bg-white text-[var(--color-text-secondary)] hover:bg-[var(--color-surface-alt)]',
                                            )}
                                        >
                                            <List className="size-4" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {activeFilterChips.length > 0 ? (
                            <div className="flex flex-wrap gap-2">
                                {activeFilterChips.map((chip) => (
                                    <button
                                        key={chip.key}
                                        type="button"
                                        onClick={chip.onRemove}
                                        className="inline-flex items-center gap-2 rounded-full border border-[var(--color-border)] bg-[var(--color-surface)] px-3 py-2 text-sm text-[var(--color-text-secondary)] transition hover:border-[var(--color-primary)] hover:text-[var(--color-primary)]"
                                    >
                                        {chip.label}
                                        <X className="size-3.5" />
                                    </button>
                                ))}
                            </div>
                        ) : null}

                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <p className="text-sm text-[var(--color-text-secondary)]">
                                Showing {products.from ?? 0}-{products.to ?? 0} of {products.total} products
                            </p>
                            <p className="text-sm text-[var(--color-text-muted)]">
                                {page.props.settings.site_name} marketplace
                            </p>
                        </div>

                        {isLoading ? (
                            <div
                                className={cn(
                                    'grid gap-3 md:gap-4',
                                    draftFilters.view === 'list'
                                        ? 'grid-cols-1'
                                        : 'grid-cols-2 sm:grid-cols-3 xl:grid-cols-4',
                                )}
                            >
                                {Array.from({ length: DEFAULT_GRID_COUNT }, (_, index) => (
                                    <ListingProductSkeleton key={index} view={draftFilters.view} />
                                ))}
                            </div>
                        ) : products.data.length > 0 ? (
                            <div
                                className={cn(
                                    'grid gap-3 md:gap-4',
                                    draftFilters.view === 'list'
                                        ? 'grid-cols-1'
                                        : 'grid-cols-2 sm:grid-cols-3 xl:grid-cols-4',
                                )}
                            >
                                {products.data.map((product) => (
                                    <ProductCard key={product.id} product={product} view={draftFilters.view} />
                                ))}
                            </div>
                        ) : (
                            <EmptyState
                                icon={Search}
                                title="No products found"
                                description="Try adjusting your filters, clearing the search term, or broadening the price range."
                                action={{
                                    label: 'Clear filters',
                                    onClick: handleClearAll,
                                }}
                            />
                        )}

                        <Pagination data={products} onPageChange={handlePageChange} className="pt-2" />
                    </div>
                </section>
            </div>

            <Drawer
                isOpen={isFiltersOpen}
                onClose={() => setIsFiltersOpen(false)}
                title="Filters"
                description="Refine the product list on mobile."
                side="left"
                footer={drawerFooter}
            >
                <div className="space-y-6">{sidebarControls}</div>
            </Drawer>
        </>
    );
}

function buildQuery(filters: ProductListingFilters): Record<string, QueryValue> {
    const query: Record<string, QueryValue> = {
        q: filters.q,
        sort: filters.sort,
        view: filters.view,
        page: filters.page,
    };

    if (filters.brand.length > 0) {
        query.brand = filters.brand;
    }

    if (filters.min_price !== null) {
        query.min_price = filters.min_price;
    }

    if (filters.max_price !== null) {
        query.max_price = filters.max_price;
    }

    if (filters.rating !== null) {
        query.rating = filters.rating;
    }

    if (filters.in_stock) {
        query.in_stock = true;
    }

    if (Object.keys(filters.attributes).length > 0) {
        query.attributes = filters.attributes;
    }

    if (filters.banner_id !== null) {
        query.banner_id = filters.banner_id;
    }

    return query;
}

function toggleAttributeSelection(
    filters: ProductListingFilters,
    attributeId: number,
    valueId: number,
): ProductListingFilters {
    const key = String(attributeId);
    const currentValues = filters.attributes[key] ?? [];
    const exists = currentValues.includes(valueId);
    const nextValues = exists
        ? currentValues.filter((current) => current !== valueId)
        : [...currentValues, valueId];
    const nextAttributes = { ...filters.attributes };

    if (nextValues.length === 0) {
        delete nextAttributes[key];
    } else {
        nextAttributes[key] = nextValues;
    }

    return {
        ...filters,
        attributes: nextAttributes,
        page: 1,
    };
}

export default function ProductListing(props: ProductListingPageProps): React.JSX.Element {
    return <ProductListingPage {...props} />;
}
