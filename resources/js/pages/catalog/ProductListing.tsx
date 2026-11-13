import { Head, router, usePage } from '@inertiajs/react';
import {
    Check,
    ChevronDown,
    ChevronLeft,
    Filter,
    LayoutGrid,
    List,
    Search,
    SlidersHorizontal,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { route } from 'ziggy-js';
import {
    ProductCard,
    ProductCardSkeleton,
} from '@/components/shared/ProductCard';
import { SubcategoryShowcase } from '@/components/shared/SubcategoryShowcase';
import { Button } from '@/components/ui/button';
import { Drawer } from '@/components/ui/drawer';
import { EmptyState } from '@/components/ui/empty-state';
import { Input } from '@/components/ui/input';
import { Pagination } from '@/components/ui/pagination';
import { Select } from '@/components/ui/select';
import { useLiveSearch } from '@/hooks/use-live-search';
import {
    PRODUCT_GRID_BESIDE_SIDEBAR_CLASS,
    SIDEBAR_LAYOUT_CLASS,
} from '@/lib/constants';
import { useMediaQuery } from '@/lib/hooks/useMediaQuery';
import { usePriceRangeCommit } from '@/lib/hooks/usePriceRangeCommit';
import { cn } from '@/lib/utils';
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
    /** The category's chosen colour (a sub-category uses its parent's); null = theme colour. */
    bannerColor: string | null;
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

const SORT_OPTIONS = [
    { label: 'Relevance', value: 'relevance' },
    { label: 'Price: Low to High', value: 'price_asc' },
    { label: 'Price: High to Low', value: 'price_desc' },
    { label: 'Newest', value: 'newest' },
    { label: 'Top Rated', value: 'top_rated' },
    { label: 'Most Reviewed', value: 'most_reviewed' },
] as const;

const DEFAULT_GRID_COUNT = 12;

const PRICE_SLIDER_STEP = 10;
/**
 * Invisible native range input stretched over the custom track. Only its thumb
 * (sized to the visual handle) accepts pointer events, so overlapping inputs
 * never steal each other's drags.
 */
const PRICE_SLIDER_INPUT_CLASS = cn(
    // Overhang by half a thumb (12px) so the native thumb centre spans exactly 0–100% of the track.
    'pointer-events-none absolute inset-x-[-12px] top-0 h-full w-[calc(100%+24px)] cursor-pointer appearance-none bg-transparent opacity-0',
    '[&::-webkit-slider-thumb]:pointer-events-auto [&::-webkit-slider-thumb]:size-6 [&::-webkit-slider-thumb]:cursor-grab [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:active:cursor-grabbing',
    '[&::-moz-range-thumb]:pointer-events-auto [&::-moz-range-thumb]:size-6 [&::-moz-range-thumb]:cursor-grab [&::-moz-range-thumb]:border-0 [&::-moz-range-thumb]:active:cursor-grabbing',
);

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

    // eslint-disable-next-line react-hooks/set-state-in-effect -- resets local draft/UI state when the server props or URL change
    useEffect(() => setInputLo(String(lo)), [lo]);
    // eslint-disable-next-line react-hooks/set-state-in-effect -- resets local draft/UI state when the server props or URL change
    useEffect(() => setInputHi(String(hi)), [hi]);

    const priceCommit = usePriceRangeCommit(onApply);

    const parseLo = (raw: string): number =>
        Math.max(0, Math.min(Number(raw) || 0, cap));
    const parseHi = (raw: string, loValue: number): number =>
        Math.max(loValue, Math.min(raw === '' ? cap : Number(raw) || cap, cap));

    // Slider mirrors the (possibly half-typed) inputs, clamped to a valid range.
    const sliderLo = Math.max(0, Math.min(Number(inputLo) || 0, cap));
    const sliderHi = Math.max(
        sliderLo,
        Math.min(inputHi === '' ? cap : Number(inputHi) || cap, cap),
    );
    const loPercent = cap > 0 ? (sliderLo / cap) * 100 : 0;
    const hiPercent = cap > 0 ? (sliderHi / cap) * 100 : 100;
    // When both handles meet, keep the one that can still move on top.
    const isLoOnTop = loPercent > 50;

    const commitSlider = (): void => priceCommit.flush();
    const isCommitKey = (key: string): boolean =>
        [
            'ArrowLeft',
            'ArrowRight',
            'ArrowUp',
            'ArrowDown',
            'Home',
            'End',
            'PageUp',
            'PageDown',
        ].includes(key);

    const noSpinnerClass =
        '[appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none';

    return (
        <div className="space-y-5 rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface-alt)]/60 p-4">
            <div className="flex items-end gap-2.5">
                <div className="flex-1 space-y-1.5">
                    <label className="block text-[10px] font-bold tracking-[0.12em] text-[var(--color-text-secondary)] uppercase">
                        Min
                    </label>
                    <div className="relative">
                        <span className="pointer-events-none absolute top-1/2 left-2.5 -translate-y-1/2 text-xs font-semibold text-[var(--color-text-secondary)]">
                            ৳
                        </span>
                        <input
                            type="number"
                            min={0}
                            inputMode="numeric"
                            value={inputLo}
                            onChange={(e) => {
                                const { value } = e.target;
                                setInputLo(value);
                                const parsedLo = parseLo(value);
                                priceCommit.schedule(
                                    parsedLo,
                                    parseHi(inputHi, parsedLo),
                                );
                            }}
                            className={cn(
                                'h-9 w-full rounded-xl border border-[var(--color-border)] bg-white py-0 pr-2 pl-5 text-sm font-medium text-[var(--color-text-primary)] shadow-sm transition focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[var(--color-primary)]/15 focus:outline-none',
                                noSpinnerClass,
                            )}
                        />
                    </div>
                </div>
                <div className="mb-[18px] h-px w-3 shrink-0 bg-[var(--color-border-strong)]" />
                <div className="flex-1 space-y-1.5">
                    <label className="block text-[10px] font-bold tracking-[0.12em] text-[var(--color-text-secondary)] uppercase">
                        Max
                    </label>
                    <div className="relative">
                        <span className="pointer-events-none absolute top-1/2 left-2.5 -translate-y-1/2 text-xs font-semibold text-[var(--color-text-secondary)]">
                            ৳
                        </span>
                        <input
                            type="number"
                            min={0}
                            inputMode="numeric"
                            value={inputHi}
                            onChange={(e) => {
                                const { value } = e.target;
                                setInputHi(value);
                                const parsedLo = parseLo(inputLo);
                                priceCommit.schedule(
                                    parsedLo,
                                    parseHi(value, parsedLo),
                                );
                            }}
                            className={cn(
                                'h-9 w-full rounded-xl border border-[var(--color-border)] bg-white py-0 pr-2 pl-5 text-sm font-medium text-[var(--color-text-primary)] shadow-sm transition focus:border-[var(--color-primary)] focus:ring-2 focus:ring-[var(--color-primary)]/15 focus:outline-none',
                                noSpinnerClass,
                            )}
                        />
                    </div>
                </div>
            </div>

            {/* Dual-handle slider. The native range inputs are invisible and ignore
                pointer events except on their own thumb, so each handle is only
                draggable where it is. While a handle is held only the numbers
                move; the filter applies once, on release (usePriceRangeCommit),
                so a slow drag can't flood the server with requests. */}
            <div className="px-1 pt-1">
                <div className="relative mx-2.5 h-6">
                    <div className="absolute inset-x-0 top-1/2 h-2 -translate-y-1/2 rounded-full bg-[var(--color-border)]" />
                    <div
                        className="absolute top-1/2 h-2 -translate-y-1/2 rounded-full bg-gradient-to-r from-[var(--color-primary)] to-[var(--color-accent)]"
                        style={{
                            left: `${loPercent}%`,
                            width: `${Math.max(0, hiPercent - loPercent)}%`,
                        }}
                    />
                    <input
                        type="range"
                        min={0}
                        max={cap}
                        step={PRICE_SLIDER_STEP}
                        value={sliderLo}
                        aria-label="Minimum price"
                        onChange={(e) => {
                            const nextLo = Math.min(
                                Number(e.target.value),
                                sliderHi,
                            );
                            setInputLo(String(nextLo));
                            priceCommit.schedule(nextLo, sliderHi);
                        }}
                        onPointerDown={priceCommit.startDrag}
                        onKeyUp={(e) => isCommitKey(e.key) && commitSlider()}
                        className={cn(
                            PRICE_SLIDER_INPUT_CLASS,
                            'peer/lo',
                            isLoOnTop ? 'z-30' : 'z-20',
                        )}
                    />
                    <span
                        className="pointer-events-none absolute top-1/2 z-10 size-5 -translate-x-1/2 -translate-y-1/2 rounded-full border-[3px] border-[var(--color-primary)] bg-white shadow-[0_2px_8px_rgba(0,0,0,0.18)] transition-transform peer-hover/lo:scale-110 peer-focus-visible/lo:ring-4 peer-focus-visible/lo:ring-[var(--color-primary)]/20"
                        style={{ left: `${loPercent}%` }}
                    />
                    <input
                        type="range"
                        min={0}
                        max={cap}
                        step={PRICE_SLIDER_STEP}
                        value={sliderHi}
                        aria-label="Maximum price"
                        onChange={(e) => {
                            const nextHi = Math.max(
                                Number(e.target.value),
                                sliderLo,
                            );
                            setInputHi(String(nextHi));
                            priceCommit.schedule(sliderLo, nextHi);
                        }}
                        onPointerDown={priceCommit.startDrag}
                        onKeyUp={(e) => isCommitKey(e.key) && commitSlider()}
                        className={cn(
                            PRICE_SLIDER_INPUT_CLASS,
                            'peer/hi',
                            isLoOnTop ? 'z-20' : 'z-30',
                        )}
                    />
                    <span
                        className="pointer-events-none absolute top-1/2 z-10 size-5 -translate-x-1/2 -translate-y-1/2 rounded-full border-[3px] border-[var(--color-primary)] bg-white shadow-[0_2px_8px_rgba(0,0,0,0.18)] transition-transform peer-hover/hi:scale-110 peer-focus-visible/hi:ring-4 peer-focus-visible/hi:ring-[var(--color-primary)]/20"
                        style={{ left: `${hiPercent}%` }}
                    />
                </div>
                <div className="mt-2 flex justify-between text-[11px] font-semibold text-[var(--color-text-secondary)]">
                    <span>৳0</span>
                    <span>৳{cap.toLocaleString('en-BD')}</span>
                </div>
            </div>
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
            <span className="flex min-w-0 items-center gap-2.5 text-[var(--color-text-secondary)]">
                <input
                    type="checkbox"
                    checked={checked}
                    onChange={onChange}
                    className="size-4 shrink-0 rounded border-[var(--color-border)] text-[var(--color-primary)] focus-visible:ring-2 focus-visible:ring-[var(--color-primary)]"
                />
                <span className="min-w-0 break-words">{label}</span>
            </span>
            {typeof count === 'number' ? (
                <span className="shrink-0 text-xs text-[var(--color-text-secondary)]">
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
            <span className="min-w-0 px-1.5 break-words">{label}</span>
            {typeof count === 'number' ? (
                <span className="shrink-0 px-1.5 text-xs text-[var(--color-text-secondary)]">
                    {count}
                </span>
            ) : null}
        </button>
    );
}

function ProductListingPage({
    title,
    description,
    category,
    bannerColor,
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

    // The search text the live search last sent, while the visitor may already
    // have typed more: the results for the older text must not overwrite it.
    const liveSentQ = useRef<string | null>(null);
    const searchInputRef = useRef(searchInput);

    useEffect(() => {
        searchInputRef.current = searchInput;
    });

    useEffect(() => {
        // eslint-disable-next-line react-hooks/set-state-in-effect -- resets local draft/UI state when the server props or URL change
        setDraftFilters(filters);

        const stillTyping =
            liveSentQ.current !== null &&
            searchInputRef.current.trim() !== liveSentQ.current;

        if (!stillTyping) {
            setSearchInput(filters.q);
            liveSentQ.current = null;
        }

        setIsLoading(false);
    }, [filters]);

    const activeCategory = useMemo(() => category, [category]);

    const hasActiveFilters = useMemo(() => {
        return Boolean(
            category ||
            filters.q ||
            filters.brand.length > 0 ||
            filters.min_price !== null ||
            filters.max_price !== null ||
            filters.rating !== null ||
            filters.in_stock ||
            Object.values(filters.attributes).some((value) => value.length > 0),
        );
    }, [filters, category]);

    const activeFilterCount = useMemo(() => {
        return (
            (category ? 1 : 0) +
            filters.brand.length +
            (filters.min_price !== null || filters.max_price !== null ? 1 : 0) +
            (filters.rating !== null ? 1 : 0) +
            (filters.in_stock ? 1 : 0) +
            Object.values(filters.attributes).reduce(
                (sum, values) => sum + values.length,
                0,
            )
        );
    }, [filters, category]);

    const submitFilters = (
        nextFilters: ProductListingFilters,
        nextUrl: string = listingUrl,
    ): void => {
        setIsLoading(true);

        const query = buildQuery(nextFilters);
        // Filter/sort/pagination changes stay on the same category/listing
        // context (nextUrl === listingUrl) — those only need fresh
        // `products`/`filters` back, so a partial reload skips re-querying
        // and re-sending categories/brands/attributes/maxPrice entirely,
        // keeping the sidebar's own React state (and focus) untouched.
        // Switching to a different category/listing URL still needs the
        // full prop set (title, category, etc.), so it isn't scoped — `only`
        // must be omitted entirely rather than set to `undefined`, which
        // Inertia's router doesn't tolerate (it reads `visit.only.length`
        // unconditionally).
        const isSameContext = nextUrl === listingUrl;

        router.get(nextUrl, query as never, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
            // The product grid shows its own inline loading state (dimmed
            // cards/skeletons) for these scoped reloads, so the global
            // full-screen PageLoadingOverlay would otherwise cover the
            // header/sidebar/footer too and look like a full page reload.
            showProgress: false,
            ...(isSameContext ? { only: ['products', 'filters'] } : {}),
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
        // Sent from here, never from inside a state updater: React may run
        // an updater twice, which sent every filter change as two identical
        // requests against the product-browsing rate limit.
        const next = updater(draftFilters);
        setDraftFilters(next);

        if (isDesktop) {
            submitFilters(next, nextUrl);
        }
    };

    const handleSearchSubmit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        const trimmed = searchInput.trim();

        if (trimmed === '' && draftFilters.q === '') {
            return;
        }

        const next = {
            ...draftFilters,
            q: trimmed,
            page: 1,
        };
        setDraftFilters(next);
        submitFilters(next);
    };

    // Products follow the search box as you type (Enter / the Search button still apply at once).
    useLiveSearch(searchInput, filters.q, () => {
        const trimmed = searchInput.trim();

        if (trimmed === draftFilters.q) {
            return;
        }

        liveSentQ.current = trimmed;
        const next = { ...draftFilters, q: trimmed, page: 1 };
        setDraftFilters(next);
        submitFilters(next);
    });

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
        submitFilters(next, route('products.index'));
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

    // Category-page-only: the actual top-level category, not a sub-category
    // page, not the plain /products listing, and not a promotional-banner
    // filtered view. Falls back to the existing hero below if the category
    // couldn't be matched against the filter list for some reason, rather
    // than showing neither.
    const showSubcategoryShowcase =
        category !== null && !isActiveCategorySub && activeTopCategory !== null;

    const visibleBrands = showAllBrands ? brands : brands.slice(0, 6);

    const sidebarControls = (
        <div>
            <FilterSection title="Category" defaultOpen={false}>
                <div className="space-y-0.5">
                    {category ? (
                        <>
                            <button
                                type="button"
                                onClick={clearCategory}
                                className="mb-2 inline-flex items-center gap-1 px-1.5 text-xs font-medium text-[var(--color-text-secondary)] transition hover:text-[var(--color-primary)]"
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

            <div className="space-y-6 py-6 lg:py-8">
                {showSubcategoryShowcase && activeTopCategory ? (
                    <section className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <h1 className="font-display text-2xl leading-tight font-semibold tracking-tight break-words text-[var(--color-text-primary)] sm:text-3xl">
                            {title}
                        </h1>
                        {description ? (
                            <p className="mt-1.5 max-w-2xl text-sm leading-6 text-[var(--color-text-secondary)] sm:text-base">
                                {description}
                            </p>
                        ) : null}
                        <SubcategoryShowcase
                            subCategories={
                                activeTopCategory.sub_categories ?? []
                            }
                            onNavigate={handleCategoryNavigate}
                            className="mt-4"
                        />
                    </section>
                ) : (
                    <section className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        {/* The inline colour overrides the class; if it's missing or not
                            a colour the browser understands, the theme colour remains. */}
                        <div
                            className="relative isolate overflow-hidden rounded-[1.75rem] bg-[var(--color-primary)] px-5 py-10 text-center shadow-[var(--shadow-card)] sm:px-10 sm:py-12"
                            style={
                                bannerColor
                                    ? { backgroundColor: bannerColor }
                                    : undefined
                            }
                        >
                            {category?.image ? (
                                <img
                                    src={category.image}
                                    alt=""
                                    aria-hidden="true"
                                    className="absolute inset-0 -z-20 size-full object-cover"
                                />
                            ) : null}
                            {/* Category-colour overlay: lets the photo show through, while
                                the dark scrim below keeps white text readable on any
                                photo and with any category colour (incl. light ones). */}
                            {category?.image ? (
                                <span
                                    aria-hidden="true"
                                    className="pointer-events-none absolute inset-0 -z-10 bg-[var(--color-primary)] opacity-75"
                                    style={
                                        bannerColor
                                            ? { backgroundColor: bannerColor }
                                            : undefined
                                    }
                                />
                            ) : null}
                            <span
                                aria-hidden="true"
                                className="pointer-events-none absolute inset-0 -z-10 bg-gradient-to-b from-black/15 via-black/25 to-black/50"
                            />
                            <span
                                aria-hidden="true"
                                className="pointer-events-none absolute -top-24 left-1/2 -z-10 size-80 -translate-x-1/2 rounded-full bg-white opacity-10 blur-3xl"
                            />

                            <div className="mx-auto flex max-w-3xl flex-col items-center">
                                <div className="flex flex-wrap items-center justify-center gap-x-2.5 gap-y-1.5">
                                    <span className="inline-flex items-center gap-1 rounded-full border border-white/25 bg-white/15 px-2.5 py-0.5 text-[11px] font-semibold text-white backdrop-blur-sm">
                                        <SlidersHorizontal className="size-3" />
                                        Catalog filters
                                    </span>
                                    <span className="text-[11px] font-semibold tracking-[0.22em] text-white/80 uppercase">
                                        {filters.banner_id
                                            ? 'Collection'
                                            : category
                                              ? 'Category browse'
                                              : 'All products'}
                                    </span>
                                </div>
                                <h1 className="mt-3 line-clamp-2 font-display text-3xl leading-tight font-semibold tracking-tight break-words text-white drop-shadow-sm sm:text-4xl">
                                    {title}
                                </h1>
                                {description ? (
                                    <p className="mt-2 line-clamp-2 max-w-2xl text-sm leading-6 text-white/85 sm:text-base">
                                        {description}
                                    </p>
                                ) : null}
                            </div>
                        </div>
                    </section>
                )}

                <section
                    className={cn(
                        SIDEBAR_LAYOUT_CLASS,
                        'mx-auto max-w-7xl px-4 sm:px-6 lg:px-8',
                    )}
                >
                    <aside className="hidden lg:block">
                        {/* Sticks below the header and scrolls on its own, independent of the page. */}
                        <div className="sticky top-28 flex max-h-[calc(100dvh-8.5rem)] flex-col rounded-[2rem] border border-[var(--color-border)] bg-[var(--color-surface)] py-5 pr-2 pl-5 shadow-[var(--shadow-card)]">
                            <div className="mr-3 mb-4 flex shrink-0 items-center justify-between gap-3 border-b border-[var(--color-border)] pb-4">
                                <h2 className="text-base font-bold text-[var(--color-text-primary)]">
                                    Filters
                                    {activeFilterCount > 0 ? (
                                        <span className="ml-1.5 text-xs font-normal text-[var(--color-text-secondary)]">
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
                            <div className="min-h-0 flex-1 overflow-x-hidden overflow-y-auto overscroll-contain pr-3 [scrollbar-width:thin]">
                                {sidebarControls}
                            </div>
                        </div>
                    </aside>

                    <div className="min-w-0 space-y-6 lg:col-span-3">
                        <div className="rounded-[1.5rem] border border-[var(--color-border)] bg-[var(--color-surface)] p-2 shadow-[var(--shadow-card)] sm:p-2.5">
                            <div className="grid grid-cols-1 gap-2 sm:gap-3 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
                                <form
                                    onSubmit={handleSearchSubmit}
                                    className="flex min-w-0 gap-2"
                                >
                                    <div className="relative min-w-0 flex-1">
                                        <Search className="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-[var(--color-text-muted)]" />
                                        <Input
                                            maxLength={64}
                                            value={searchInput}
                                            onChange={(event) =>
                                                setSearchInput(
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="Search products, brands, or SKUs"
                                            className="h-10 rounded-full border-[var(--color-border)] pl-11"
                                        />
                                    </div>
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        className="hidden h-10 sm:inline-flex"
                                    >
                                        Search
                                    </Button>
                                </form>

                                <div className="flex min-w-0 items-center gap-2 sm:gap-3">
                                    <Button
                                        variant="outline"
                                        className="h-10 shrink-0 lg:hidden"
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
                                        selectClassName="h-10 truncate sm:min-w-52"
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
                                                'inline-flex size-10 items-center justify-center transition',
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
                                                'inline-flex size-10 items-center justify-center transition',
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

                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <p className="text-sm text-[var(--color-text-secondary)]">
                                Showing {products.from ?? 0}-{products.to ?? 0}{' '}
                                of {products.total} products
                            </p>
                            <p className="text-sm text-[var(--color-text-secondary)]">
                                {page.props.settings.site_name} marketplace
                            </p>
                        </div>

                        {products.data.length > 0 || isLoading ? (
                            <div
                                className={cn(
                                    draftFilters.view === 'list'
                                        ? 'grid grid-cols-1 gap-3 md:gap-4'
                                        : PRODUCT_GRID_BESIDE_SIDEBAR_CLASS,
                                    isLoading &&
                                        'pointer-events-none opacity-50 transition-opacity',
                                )}
                            >
                                {products.data.length > 0
                                    ? products.data.map((product, index) => (
                                          <ProductCard
                                              key={product.id}
                                              product={product}
                                              index={index}
                                              view={draftFilters.view}
                                              openCartOnAdd
                                          />
                                      ))
                                    : Array.from(
                                          { length: DEFAULT_GRID_COUNT },
                                          (_, index) => (
                                              <ProductCardSkeleton
                                                  key={index}
                                                  view={draftFilters.view}
                                              />
                                          ),
                                      )}
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
