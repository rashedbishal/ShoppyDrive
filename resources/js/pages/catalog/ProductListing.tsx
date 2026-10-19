import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    Banknote,
    Check,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    Filter,
    Heart,
    LayoutGrid,
    List,
    Package,
    Search,
    ShieldCheck,
    SlidersHorizontal,
    Truck,
    X,
} from 'lucide-react';
import {
    useEffect,
    useMemo,
    useState,
    type FormEvent,
    type ReactNode,
} from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
    SubCategory,
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

type QueryValue =
    | string
    | number
    | boolean
    | Array<string | number>
    | Record<string, number[]>;

type DraftFilters = ProductListingFilters;

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
    lo,
    hi,
    cap,
    onApply,
}: {
    lo: number;
    hi: number;
    cap: number;
    onApply: (lo: number, hi: number) => void;
}): React.JSX.Element {
    const [inputLo, setInputLo] = useState(String(lo));
    const [inputHi, setInputHi] = useState(String(hi));

    useEffect(() => setInputLo(String(lo)), [lo]);
    useEffect(() => setInputHi(String(hi)), [hi]);

    const apply = (): void => {
        const parsedLo = Math.max(0, Math.min(Number(inputLo) || 0, cap));
        const parsedHi = Math.max(
            parsedLo,
            Math.min(Number(inputHi) || cap, cap),
        );
        onApply(parsedLo, parsedHi);
    };

    return (
        <div className="flex items-center gap-2">
            <div className="relative flex-1">
                <span className="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-xs font-medium text-[var(--color-text-muted)]">
                    ৳
                </span>
                <input
                    type="number"
                    min={0}
                    inputMode="numeric"
                    value={inputLo}
                    placeholder="Min"
                    onChange={(e) => setInputLo(e.target.value)}
                    onKeyDown={(e) => e.key === 'Enter' && apply()}
                    className="h-10 w-full rounded-lg border border-[var(--color-border)] bg-white pr-2 pl-7 text-sm transition focus:border-[var(--color-primary)] focus:ring-1 focus:ring-[var(--color-primary)] focus:outline-none"
                />
            </div>
            <span className="text-sm text-[var(--color-text-muted)]">—</span>
            <div className="relative flex-1">
                <span className="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-xs font-medium text-[var(--color-text-muted)]">
                    ৳
                </span>
                <input
                    type="number"
                    min={0}
                    inputMode="numeric"
                    value={inputHi}
                    placeholder="Max"
                    onChange={(e) => setInputHi(e.target.value)}
                    onKeyDown={(e) => e.key === 'Enter' && apply()}
                    className="h-10 w-full rounded-lg border border-[var(--color-border)] bg-white pr-2 pl-7 text-sm transition focus:border-[var(--color-primary)] focus:ring-1 focus:ring-[var(--color-primary)] focus:outline-none"
                />
            </div>
            <button
                type="button"
                onClick={apply}
                aria-label="Apply price range"
                className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-[var(--color-surface-alt)] text-[var(--color-text-secondary)] transition hover:bg-[var(--color-primary)] hover:text-white"
            >
                <ChevronRight className="size-4" />
            </button>
        </div>
    );
}

// ── Flat collapsible section (Envato-inspired: no card chrome, just a divider) ─
function FilterSection({
    title,
    defaultOpen = true,
    children,
}: {
    title: string;
    defaultOpen?: boolean;
    children: ReactNode;
}): React.JSX.Element {
    const [open, setOpen] = useState(defaultOpen);

    return (
        <div className="border-b border-[var(--color-border)] py-5 first:pt-0 last:border-b-0 last:pb-0">
            <button
                type="button"
                onClick={() => setOpen((current) => !current)}
                className="flex w-full items-center justify-between text-sm font-semibold text-[var(--color-text-primary)]"
                aria-expanded={open}
            >
                {title}
                <ChevronDown
                    className={cn(
                        'size-4 text-[var(--color-text-muted)] transition-transform duration-200',
                        open ? '' : '-rotate-90',
                    )}
                />
            </button>
            {open ? <div className="mt-4">{children}</div> : null}
        </div>
    );
}

// ── Filter row primitives (flat rows, right-aligned counts — no card borders) ──
function FilterCheckboxRow({
    label,
    count,
    checked,
    onChange,
}: {
    label: string;
    count?: number;
    checked: boolean;
    onChange: () => void;
}): React.JSX.Element {
    return (
        <label className="flex cursor-pointer items-center justify-between gap-3 rounded-lg px-1.5 py-1.5 text-sm transition hover:bg-[var(--color-surface-alt)]">
            <span className="flex items-center gap-2.5 text-[var(--color-text-secondary)]">
                <input
                    type="checkbox"
                    checked={checked}
                    onChange={onChange}
                    className="size-4 rounded border-[var(--color-border)] text-[var(--color-primary)] focus-visible:ring-2 focus-visible:ring-[var(--color-primary)]"
                />
                {label}
            </span>
            {typeof count === 'number' ? (
                <span className="text-xs text-[var(--color-text-muted)]">
                    {count}
                </span>
            ) : null}
        </label>
    );
}

function CategoryRow({
    label,
    count,
    active = false,
    indent = false,
    onClick,
}: {
    label: string;
    count?: number;
    active?: boolean;
    indent?: boolean;
    onClick: () => void;
}): React.JSX.Element {
    return (
        <button
            type="button"
            onClick={onClick}
            className={cn(
                'flex w-full items-center justify-between gap-3 rounded-lg py-1.5 text-left text-sm transition hover:bg-[var(--color-surface-alt)]',
                indent ? 'pl-5' : '',
                active
                    ? 'font-semibold text-[var(--color-primary)]'
                    : 'text-[var(--color-text-secondary)]',
            )}
        >
            <span className="px-1.5">{label}</span>
            {typeof count === 'number' ? (
                <span className="px-1.5 text-xs text-[var(--color-text-muted)]">
                    {count}
                </span>
            ) : null}
        </button>
    );
}

function ListingProductSkeleton({
    view,
}: {
    view: 'grid' | 'list';
}): React.JSX.Element {
    if (view === 'list') {
        return (
            <Card className="overflow-hidden border-[var(--color-border)] bg-[var(--color-surface)] p-0 shadow-[var(--shadow-card)]">
                <div className="grid grid-cols-1 gap-4 p-4 sm:grid-cols-[220px_minmax(0,1fr)]">
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
    const isWishlisted = useWishlistStore((state) =>
        state.isWishlisted(product.id),
    );
    const [isHovered, setIsHovered] = useState(false);

    const imageSet = product.images ?? [];
    const primaryImage =
        imageSet.find((image) => image.is_primary) ?? imageSet[0];
    const secondaryImage = imageSet.find((image) => !image.is_primary);
    const currentPrice =
        product.in_flash_sale && typeof product.flash_sale_price === 'number'
            ? product.flash_sale_price
            : product.price;
    const discountPercent = product.compare_price
        ? getDiscountPercent(currentPrice, product.compare_price)
        : 0;
    const isOutOfStock = product.stock_quantity <= 0;
    const stockLabel = isOutOfStock
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
                <div className="grid grid-cols-1 gap-4 p-4 sm:grid-cols-[220px_minmax(0,1fr)]">
                    <Link
                        href={route('product.show', product.slug)}
                        className="block"
                    >
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
                                            isHovered && secondaryImage?.path
                                                ? 'scale-105 opacity-0'
                                                : 'opacity-100',
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
                                                isHovered
                                                    ? 'scale-105 opacity-100'
                                                    : 'opacity-0',
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
                                <Badge className="absolute top-3 left-3 bg-[var(--color-accent)] text-[var(--color-secondary)]">
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
                                aria-label={
                                    isWishlisted
                                        ? `Remove ${product.name} from wishlist`
                                        : `Add ${product.name} to wishlist`
                                }
                                className="absolute top-3 right-3 inline-flex size-10 items-center justify-center rounded-full border border-[var(--color-border)] bg-white/95 text-[var(--color-text-secondary)] shadow-sm transition hover:scale-105"
                            >
                                <Heart
                                    className={cn(
                                        'size-4',
                                        isWishlisted
                                            ? 'fill-current text-[var(--color-primary)]'
                                            : '',
                                    )}
                                />
                            </button>
                        </div>
                    </Link>

                    <div className="flex min-w-0 flex-col gap-4">
                        <div className="space-y-2">
                            <p className="text-xs font-semibold tracking-[0.22em] text-[var(--color-text-muted)] uppercase">
                                {product.brand?.name ?? 'ShoppyDrive Select'}
                            </p>
                            <Link
                                href={route('product.show', product.slug)}
                                className="line-clamp-2 text-lg font-semibold text-[var(--color-text-primary)] transition hover:text-[var(--color-primary)]"
                            >
                                {product.name}
                            </Link>
                            <p className="text-sm leading-6 text-[var(--color-text-secondary)]">
                                {truncate(
                                    product.short_description ??
                                        product.description ??
                                        '',
                                    150,
                                )}
                            </p>
                        </div>

                        <div className="flex flex-wrap items-center gap-2">
                            <StarRating
                                rating={product.average_rating ?? 0}
                                size="sm"
                                className="text-[var(--color-accent)]"
                            />
                            <span className="text-xs text-[var(--color-text-secondary)]">
                                ({product.reviews_count ?? 0})
                            </span>
                            <Badge
                                variant={isOutOfStock ? 'error' : 'success'}
                                size="sm"
                            >
                                {stockLabel}
                            </Badge>
                        </div>

                        <div className="flex flex-wrap items-end gap-2">
                            <p className="text-2xl font-bold tracking-tight text-[var(--color-primary)]">
                                {formatCurrency(currentPrice)}
                            </p>
                            {product.compare_price &&
                            product.compare_price > currentPrice ? (
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
                            <Button
                                asChild
                                variant="outline"
                                className="flex-1 sm:flex-none"
                            >
                                <Link
                                    href={route('product.show', product.slug)}
                                >
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
                <Link
                    href={route('product.show', product.slug)}
                    className="block"
                >
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
                                        isHovered && secondaryImage?.path
                                            ? 'scale-105 opacity-0'
                                            : 'opacity-100',
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
                                            isHovered
                                                ? 'scale-105 opacity-100'
                                                : 'opacity-0',
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
                            <Badge className="absolute top-4 left-4 bg-[var(--color-accent)] text-[var(--color-secondary)]">
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
                            aria-label={
                                isWishlisted
                                    ? `Remove ${product.name} from wishlist`
                                    : `Add ${product.name} to wishlist`
                            }
                            className="absolute top-4 right-4 inline-flex size-10 items-center justify-center rounded-full border border-[var(--color-border)] bg-white/95 text-[var(--color-text-secondary)] shadow-sm transition hover:scale-105"
                        >
                            <Heart
                                className={cn(
                                    'size-4',
                                    isWishlisted
                                        ? 'fill-current text-[var(--color-primary)]'
                                        : '',
                                )}
                            />
                        </button>

                        {isOutOfStock ? (
                            <div className="absolute inset-0 flex items-center justify-center bg-[rgba(26,26,46,0.62)]">
                                <Badge
                                    variant="error"
                                    className="bg-[rgba(220,38,38,0.96)] text-white"
                                >
                                    Out of stock
                                </Badge>
                            </div>
                        ) : null}
                    </div>
                </Link>
            </div>

            <div className="space-y-4 p-4">
                <div className="space-y-1">
                    <p className="text-xs font-semibold tracking-[0.22em] text-[var(--color-text-muted)] uppercase">
                        {product.brand?.name ?? 'ShoppyDrive Select'}
                    </p>
                    <Link
                        href={route('product.show', product.slug)}
                        className="line-clamp-2 text-sm leading-6 font-semibold text-[var(--color-text-primary)] transition hover:text-[var(--color-primary)]"
                    >
                        {product.name}
                    </Link>
                </div>

                <div className="flex items-center gap-2">
                    <StarRating
                        rating={product.average_rating ?? 0}
                        size="sm"
                        className="text-[var(--color-accent)]"
                    />
                    <span className="text-xs text-[var(--color-text-secondary)]">
                        ({product.reviews_count ?? 0})
                    </span>
                </div>

                <div className="flex items-end gap-2">
                    <p className="text-lg font-bold tracking-tight text-[var(--color-primary)]">
                        {formatCurrency(currentPrice)}
                    </p>
                    {product.compare_price &&
                    product.compare_price > currentPrice ? (
                        <p className="pb-0.5 text-sm text-[var(--color-text-muted)] line-through">
                            {formatCurrency(product.compare_price)}
                        </p>
                    ) : null}
                </div>

                <div className="space-y-2">
                    <div className="flex items-center justify-between text-xs text-[var(--color-text-secondary)]">
                        <span>{stockLabel}</span>
                        {product.stock_quantity <=
                            product.low_stock_threshold && !isOutOfStock ? (
                            <span className="text-[var(--color-accent)]">
                                Selling fast
                            </span>
                        ) : null}
                    </div>
                    {product.in_flash_sale &&
                    typeof product.flash_sale_price === 'number' ? (
                        <div className="h-2 overflow-hidden rounded-full bg-[var(--color-surface-alt)]">
                            <div
                                className="h-full rounded-full bg-[var(--color-accent)]"
                                style={{
                                    width: `${Math.min(100, Math.max(15, discountPercent * 2))}%`,
                                }}
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
    const [draftFilters, setDraftFilters] =
        useState<ProductListingFilters>(filters);
    const [searchInput, setSearchInput] = useState(filters.q);
    const [showAllBrands, setShowAllBrands] = useState(false);

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

    const activeFilterCount = useMemo(() => {
        return (
            filters.brand.length +
            (filters.min_price !== null || filters.max_price !== null ? 1 : 0) +
            (filters.rating !== null ? 1 : 0) +
            (filters.in_stock ? 1 : 0) +
            Object.values(filters.attributes).reduce(
                (sum, values) => sum + values.length,
                0,
            )
        );
    }, [filters]);

    const submitFilters = (
        nextFilters: ProductListingFilters,
        nextUrl: string = listingUrl,
    ): void => {
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
            brand: draftFilters.brand.filter(
                (current) => current !== brandSlug,
            ),
            page: 1,
        };

        setDraftFilters(next);
        submitFilters(next);
    };

    const removeAttribute = (attributeId: string, valueId: number): void => {
        const currentValues = draftFilters.attributes[attributeId] ?? [];
        const nextAttributes = {
            ...draftFilters.attributes,
            [attributeId]: currentValues.filter(
                (current) => current !== valueId,
            ),
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

    // SubCategory rows carry `category_id`; plain Category rows never do — use
    // that to disambiguate, since Category and SubCategory ids are independent
    // auto-increment sequences and can collide.
    const isActiveCategorySub = category ? 'category_id' in category : false;

    const activeTopCategory = category
        ? (categories.find((top) =>
              isActiveCategorySub
                  ? top.sub_categories?.some((sub) => sub.id === category.id)
                  : top.id === category.id,
          ) ?? null)
        : null;

    const visibleBrands = showAllBrands ? brands : brands.slice(0, 6);

    const sidebarControls = (
        <div>
            <FilterSection title="Category">
                <div className="space-y-0.5">
                    {category ? (
                        <>
                            <button
                                type="button"
                                onClick={clearCategory}
                                className="mb-2 inline-flex items-center gap-1 px-1.5 text-xs font-medium text-[var(--color-text-muted)] transition hover:text-[var(--color-primary)]"
                            >
                                <ChevronLeft className="size-3.5" />
                                All categories
                            </button>
                            {activeTopCategory ? (
                                <>
                                    <CategoryRow
                                        label={activeTopCategory.name}
                                        count={activeTopCategory.products_count}
                                        active={
                                            !isActiveCategorySub &&
                                            activeCategory?.id ===
                                                activeTopCategory.id
                                        }
                                        onClick={() =>
                                            handleCategoryNavigate(
                                                activeTopCategory.slug,
                                            )
                                        }
                                    />
                                    {activeTopCategory.sub_categories?.map(
                                        (child) => (
                                            <CategoryRow
                                                key={child.id}
                                                label={child.name}
                                                count={child.products_count}
                                                indent
                                                active={
                                                    isActiveCategorySub &&
                                                    activeCategory?.id ===
                                                        child.id
                                                }
                                                onClick={() =>
                                                    handleCategoryNavigate(
                                                        child.slug,
                                                    )
                                                }
                                            />
                                        ),
                                    )}
                                </>
                            ) : (
                                <CategoryRow
                                    label={category.name}
                                    active
                                    onClick={() => {}}
                                />
                            )}
                        </>
                    ) : (
                        categories.map((top) => (
                            <CategoryRow
                                key={top.id}
                                label={top.name}
                                count={top.products_count}
                                onClick={() => handleCategoryNavigate(top.slug)}
                            />
                        ))
                    )}
                </div>
            </FilterSection>

            <FilterSection title="Price Range">
                <PriceRangeFilter
                    lo={draftFilters.min_price ?? 0}
                    hi={draftFilters.max_price ?? Math.max(1000, maxPrice)}
                    cap={Math.max(1000, maxPrice)}
                    onApply={(lo, hi) =>
                        applyImmediateChange((c) => ({
                            ...c,
                            min_price: lo,
                            max_price: hi,
                            page: 1,
                        }))
                    }
                />
            </FilterSection>

            <FilterSection title="Availability">
                <FilterCheckboxRow
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
            </FilterSection>

            {brands.length > 0 ? (
                <FilterSection title={`Brand (${brands.length})`}>
                    <div className="space-y-0.5">
                        {visibleBrands.map((brand) => {
                            const checked = draftFilters.brand.includes(
                                brand.slug,
                            );

                            return (
                                <FilterCheckboxRow
                                    key={brand.id}
                                    label={brand.name}
                                    count={brand.products_count}
                                    checked={checked}
                                    onChange={() => {
                                        applyImmediateChange((current) => ({
                                            ...current,
                                            brand: checked
                                                ? current.brand.filter(
                                                      (item) =>
                                                          item !== brand.slug,
                                                  )
                                                : [
                                                      ...current.brand,
                                                      brand.slug,
                                                  ],
                                            page: 1,
                                        }));
                                    }}
                                />
                            );
                        })}
                        {brands.length > 6 ? (
                            <button
                                type="button"
                                onClick={() =>
                                    setShowAllBrands((current) => !current)
                                }
                                className="mt-1 inline-flex items-center gap-1 px-1.5 text-sm font-semibold text-[var(--color-primary)] hover:underline"
                            >
                                {showAllBrands
                                    ? 'Show less'
                                    : `Show ${brands.length - 6} more`}
                                <ChevronDown
                                    className={cn(
                                        'size-3.5 transition-transform',
                                        showAllBrands ? 'rotate-180' : '',
                                    )}
                                />
                            </button>
                        ) : null}
                    </div>
                </FilterSection>
            ) : null}

            {attributes.map((attribute) => (
                <FilterSection key={attribute.id} title={attribute.name}>
                    {attribute.type === 'color' ? (
                        <div className="flex flex-wrap gap-2.5">
                            {attribute.values.map((value) => {
                                const activeValues =
                                    draftFilters.attributes[
                                        String(attribute.id)
                                    ] ?? [];
                                const checked = activeValues.includes(value.id);

                                return (
                                    <button
                                        key={value.id}
                                        type="button"
                                        title={value.value}
                                        aria-pressed={checked}
                                        aria-label={value.value}
                                        onClick={() => {
                                            applyImmediateChange((current) =>
                                                toggleAttributeSelection(
                                                    current,
                                                    attribute.id,
                                                    value.id,
                                                ),
                                            );
                                        }}
                                        className={cn(
                                            'relative flex size-10 items-center justify-center rounded-full border-2 transition',
                                            checked
                                                ? 'border-[var(--color-primary)]'
                                                : 'border-transparent hover:border-[var(--color-border)]',
                                        )}
                                    >
                                        <span
                                            className="size-8 rounded-full border border-black/10 shadow-sm"
                                            style={{
                                                backgroundColor:
                                                    value.color_hex ??
                                                    '#e5e7eb',
                                            }}
                                        />
                                        {checked ? (
                                            <span className="absolute inset-0 flex items-center justify-center">
                                                <Check className="size-4 text-white mix-blend-difference" />
                                            </span>
                                        ) : null}
                                    </button>
                                );
                            })}
                        </div>
                    ) : (
                        <div className="space-y-0.5">
                            {attribute.values.map((value) => {
                                const activeValues =
                                    draftFilters.attributes[
                                        String(attribute.id)
                                    ] ?? [];
                                const checked = activeValues.includes(value.id);

                                return (
                                    <FilterCheckboxRow
                                        key={value.id}
                                        label={value.value}
                                        checked={checked}
                                        onChange={() => {
                                            applyImmediateChange((current) =>
                                                toggleAttributeSelection(
                                                    current,
                                                    attribute.id,
                                                    value.id,
                                                ),
                                            );
                                        }}
                                    />
                                );
                            })}
                        </div>
                    )}
                </FilterSection>
            ))}
        </div>
    );

    const activeAttributeChips = Object.entries(
        draftFilters.attributes,
    ).flatMap(([attributeId, valueIds]) => {
        const attribute = attributes.find(
            (item) => item.id === Number(attributeId),
        );

        if (!attribute) {
            return [];
        }

        return valueIds.flatMap((valueId) => {
            const value = attribute.values.find((item) => item.id === valueId);

            if (!value) {
                return [];
            }

            return [
                {
                    key: `attribute-${attributeId}-${valueId}`,
                    label: `${attribute.name}: ${value.value}`,
                    onRemove: () => removeAttribute(attributeId, valueId),
                },
            ];
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
        ...(draftFilters.q
            ? [
                  {
                      key: 'search',
                      label: `Search: ${draftFilters.q}`,
                      onRemove: () => {
                          const next = { ...draftFilters, q: '', page: 1 };
                          setDraftFilters(next);
                          setSearchInput('');
                          submitFilters(next);
                      },
                  },
              ]
            : []),
        ...activeBrandNames.map((brand) => ({
            key: `brand-${brand.slug}`,
            label: `Brand: ${brand.label}`,
            onRemove: () => removeBrand(brand.slug),
        })),
        ...(draftFilters.min_price !== null || draftFilters.max_price !== null
            ? [
                  {
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
                  },
              ]
            : []),
        ...(draftFilters.rating !== null
            ? [
                  {
                      key: 'rating',
                      label: `${draftFilters.rating}★ & up`,
                      onRemove: () => {
                          const next = {
                              ...draftFilters,
                              rating: null,
                              page: 1,
                          };
                          setDraftFilters(next);
                          submitFilters(next);
                      },
                  },
              ]
            : []),
        ...(draftFilters.in_stock
            ? [
                  {
                      key: 'stock',
                      label: 'In stock only',
                      onRemove: () => {
                          const next = {
                              ...draftFilters,
                              in_stock: false,
                              page: 1,
                          };
                          setDraftFilters(next);
                          submitFilters(next);
                      },
                  },
              ]
            : []),
        ...activeAttributeChips,
    ];

    const drawerFooter = (
        <div className="flex items-center gap-3">
            <Button variant="outline" fullWidth onClick={handleClearAll}>
                Clear All
            </Button>
            <Button fullWidth onClick={() => submitFilters(draftFilters)}>
                Apply Filters
            </Button>
        </div>
    );

    return (
        <>
            <Head title={title} />

            <div className="space-y-8 py-8 lg:py-12">
                <section className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="grid grid-cols-1 gap-6 rounded-[2rem] border border-[var(--color-border)] bg-[var(--color-surface)] p-6 shadow-[var(--shadow-card)] lg:grid-cols-[1.1fr_0.9fr] lg:p-8">
                        <div className="space-y-4">
                            <Badge variant="amber" className="w-fit">
                                <SlidersHorizontal className="size-3.5" />
                                Catalog filters
                            </Badge>
                            <div className="space-y-2">
                                <p className="text-xs font-semibold tracking-[0.22em] text-[var(--color-text-muted)] uppercase">
                                    {filters.banner_id
                                        ? 'Collection'
                                        : category
                                          ? 'Category browse'
                                          : 'All products'}
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

                        <div className="grid grid-cols-3 gap-2.5 self-center sm:gap-3">
                            {[
                                {
                                    label: 'Result count',
                                    value: products.total.toLocaleString(),
                                    detail:
                                        products.total === 1 ? 'item' : 'items',
                                    icon: Package,
                                    color: 'var(--color-primary)',
                                },
                                {
                                    label: 'Current view',
                                    value:
                                        draftFilters.view === 'grid'
                                            ? 'Grid'
                                            : 'List',
                                    detail: 'layout',
                                    icon:
                                        draftFilters.view === 'grid'
                                            ? LayoutGrid
                                            : List,
                                    color: '#2563eb',
                                },
                                {
                                    label: 'Currency',
                                    value: '৳ BDT',
                                    detail: 'Bangladeshi taka',
                                    icon: Banknote,
                                    color: 'var(--color-success)',
                                },
                            ].map(
                                ({
                                    label,
                                    value,
                                    detail,
                                    icon: Icon,
                                    color,
                                }) => (
                                    <div
                                        key={label}
                                        style={
                                            {
                                                '--stat-color': color,
                                            } as React.CSSProperties
                                        }
                                        className="group relative isolate flex min-w-0 flex-col overflow-hidden rounded-2xl border border-[var(--color-border)] bg-gradient-to-br from-[var(--color-surface)] to-[color-mix(in_srgb,var(--stat-color)_6%,var(--color-surface))] p-3 shadow-sm transition duration-300 hover:-translate-y-0.5 hover:border-[color-mix(in_srgb,var(--stat-color)_35%,transparent)] hover:shadow-[var(--shadow-card-hover)] sm:p-4"
                                    >
                                        {/* Soft corner glow in the stat's accent colour */}
                                        <span className="pointer-events-none absolute -top-8 -right-8 -z-10 size-24 rounded-full bg-[var(--stat-color)] opacity-[0.08] blur-2xl transition-opacity duration-300 group-hover:opacity-[0.16]" />
                                        <span className="flex size-8 items-center justify-center rounded-xl bg-[color-mix(in_srgb,var(--stat-color)_12%,transparent)] text-[var(--stat-color)] ring-1 ring-[color-mix(in_srgb,var(--stat-color)_18%,transparent)] sm:size-10">
                                            <Icon className="size-4 sm:size-5" />
                                        </span>
                                        <p className="mt-3 truncate font-display text-lg leading-none font-bold tracking-tight text-[var(--color-text-primary)] sm:mt-4 sm:text-2xl">
                                            {value}
                                        </p>
                                        <p className="mt-1.5 truncate text-[11px] text-[var(--color-text-secondary)] sm:text-xs">
                                            {detail}
                                        </p>
                                        <p className="mt-3 truncate border-t border-dashed border-[var(--color-border)] pt-2 text-[10px] font-semibold tracking-[0.16em] text-[var(--color-text-muted)] uppercase">
                                            {label}
                                        </p>
                                    </div>
                                ),
                            )}
                        </div>
                    </div>
                </section>

                <section className="mx-auto grid max-w-7xl grid-cols-1 gap-8 px-4 sm:px-6 lg:grid-cols-[280px_minmax(0,1fr)] lg:px-8">
                    <aside className="hidden lg:block">
                        <div className="sticky top-28 rounded-[2rem] border border-[var(--color-border)] bg-[var(--color-surface)] p-5 shadow-[var(--shadow-card)]">
                            <div className="mb-4 flex items-center justify-between gap-3 border-b border-[var(--color-border)] pb-4">
                                <h2 className="text-base font-bold text-[var(--color-text-primary)]">
                                    Filters
                                    {activeFilterCount > 0 ? (
                                        <span className="ml-1.5 text-xs font-normal text-[var(--color-text-muted)]">
                                            ({activeFilterCount} active)
                                        </span>
                                    ) : null}
                                </h2>
                                {hasActiveFilters ? (
                                    <button
                                        type="button"
                                        onClick={handleClearAll}
                                        className="text-xs font-semibold text-[var(--color-primary)] hover:underline"
                                    >
                                        Clear all
                                    </button>
                                ) : null}
                            </div>
                            {sidebarControls}
                        </div>
                    </aside>

                    <div className="min-w-0 space-y-6">
                        <div className="rounded-[2rem] border border-[var(--color-border)] bg-[var(--color-surface)] p-3 shadow-[var(--shadow-card)] sm:p-4">
                            <div className="grid grid-cols-1 gap-3 sm:gap-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
                                <form
                                    onSubmit={handleSearchSubmit}
                                    className="flex min-w-0 gap-3"
                                >
                                    <div className="relative min-w-0 flex-1">
                                        <Search className="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-[var(--color-text-muted)]" />
                                        <Input
                                            value={searchInput}
                                            onChange={(event) =>
                                                setSearchInput(
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="Search products, brands, or SKUs"
                                            className="h-12 rounded-full border-[var(--color-border)] pl-11"
                                        />
                                    </div>
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        className="hidden sm:inline-flex"
                                    >
                                        Search
                                    </Button>
                                </form>

                                <div className="flex min-w-0 items-center gap-2 sm:gap-3">
                                    <Button
                                        variant="outline"
                                        className="shrink-0 lg:hidden"
                                        onClick={() => setIsFiltersOpen(true)}
                                    >
                                        <Filter className="size-4" />
                                        Filters
                                    </Button>

                                    <Select
                                        value={draftFilters.sort}
                                        onChange={(event) => {
                                            const next = {
                                                ...draftFilters,
                                                sort: event.target.value,
                                                page: 1,
                                            };
                                            setDraftFilters(next);
                                            submitFilters(next);
                                        }}
                                        options={SORT_OPTIONS.map((option) => ({
                                            label: option.label,
                                            value: option.value,
                                        }))}
                                        aria-label="Sort products"
                                        containerClassName="min-w-0 flex-1 lg:flex-none"
                                        selectClassName="truncate sm:min-w-52"
                                    />

                                    <div className="inline-flex shrink-0 overflow-hidden rounded-xl border border-[var(--color-border)]">
                                        <button
                                            type="button"
                                            onClick={() => {
                                                const next: ProductListingFilters =
                                                    {
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
                                                const next: ProductListingFilters =
                                                    {
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
                                Showing {products.from ?? 0}-{products.to ?? 0}{' '}
                                of {products.total} products
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
                                {Array.from(
                                    { length: DEFAULT_GRID_COUNT },
                                    (_, index) => (
                                        <ListingProductSkeleton
                                            key={index}
                                            view={draftFilters.view}
                                        />
                                    ),
                                )}
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
                                    <ProductCard
                                        key={product.id}
                                        product={product}
                                        view={draftFilters.view}
                                    />
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

                        <Pagination
                            data={products}
                            onPageChange={handlePageChange}
                            className="pt-2"
                        />
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

function buildQuery(
    filters: ProductListingFilters,
): Record<string, QueryValue> {
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

export default function ProductListing(
    props: ProductListingPageProps,
): React.JSX.Element {
    return <ProductListingPage {...props} />;
}
