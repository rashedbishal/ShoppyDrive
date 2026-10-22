import { Head, router, usePage } from '@inertiajs/react';
import {
    Banknote,
    Check,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    Filter,
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
import {
    ProductCard,
    ProductCardSkeleton,
} from '@/components/shared/ProductCard';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Drawer } from '@/components/ui/drawer';
import { EmptyState } from '@/components/ui/empty-state';
import { Input } from '@/components/ui/input';
import { Pagination } from '@/components/ui/pagination';
import { Select } from '@/components/ui/select';
import {
    PRODUCT_GRID_BESIDE_SIDEBAR_CLASS,
    SIDEBAR_LAYOUT_CLASS,
} from '@/lib/constants';
import { cn } from '@/lib/utils';
import { useMediaQuery } from '@/lib/hooks/useMediaQuery';
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

    const commitSlider = (): void => onApply(sliderLo, sliderHi);
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

    return (
        <div className="space-y-4">
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
                <span className="text-sm text-[var(--color-text-muted)]">
                    —
                </span>
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

            {/* Dual-handle slider. The native range inputs are invisible and ignore
                pointer events except on their own thumb, so each handle is only
                draggable where it is; dragging updates the inputs live and the
                filter applies on release. */}
            <div className="relative mx-2.5 h-6">
                <div className="absolute inset-x-0 top-1/2 h-1.5 -translate-y-1/2 rounded-full bg-[var(--color-surface-alt)] shadow-inner" />
                <div
                    className="absolute top-1/2 h-1.5 -translate-y-1/2 rounded-full bg-[var(--color-primary)]"
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
                    onChange={(e) =>
                        setInputLo(
                            String(Math.min(Number(e.target.value), sliderHi)),
                        )
                    }
                    onPointerUp={commitSlider}
                    onKeyUp={(e) => isCommitKey(e.key) && commitSlider()}
                    className={cn(
                        PRICE_SLIDER_INPUT_CLASS,
                        'peer/lo',
                        isLoOnTop ? 'z-30' : 'z-20',
                    )}
                />
                <span
                    className="pointer-events-none absolute top-1/2 z-10 size-5 -translate-x-1/2 -translate-y-1/2 rounded-full border-[3px] border-[var(--color-primary)] bg-white shadow-[0_2px_6px_rgba(0,0,0,0.18)] transition-shadow peer-focus-visible/lo:ring-4 peer-focus-visible/lo:ring-[var(--color-primary)]/20"
                    style={{ left: `${loPercent}%` }}
                />
                <input
                    type="range"
                    min={0}
                    max={cap}
                    step={PRICE_SLIDER_STEP}
                    value={sliderHi}
                    aria-label="Maximum price"
                    onChange={(e) =>
                        setInputHi(
                            String(Math.max(Number(e.target.value), sliderLo)),
                        )
                    }
                    onPointerUp={commitSlider}
                    onKeyUp={(e) => isCommitKey(e.key) && commitSlider()}
                    className={cn(
                        PRICE_SLIDER_INPUT_CLASS,
                        'peer/hi',
                        isLoOnTop ? 'z-20' : 'z-30',
                    )}
                />
                <span
                    className="pointer-events-none absolute top-1/2 z-10 size-5 -translate-x-1/2 -translate-y-1/2 rounded-full border-[3px] border-[var(--color-primary)] bg-white shadow-[0_2px_6px_rgba(0,0,0,0.18)] transition-shadow peer-focus-visible/hi:ring-4 peer-focus-visible/hi:ring-[var(--color-primary)]/20"
                    style={{ left: `${hiPercent}%` }}
                />
            </div>
            <div className="-mt-1 flex justify-between text-[11px] font-medium text-[var(--color-text-muted)]">
                <span>৳0</span>
                <span>৳{cap.toLocaleString('en-BD')}</span>
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

            <div className="space-y-6 py-6 lg:py-8">
                <section className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="grid grid-cols-1 gap-4 rounded-[2rem] border border-[var(--color-border)] bg-[var(--color-surface)] p-4 shadow-[var(--shadow-card)] sm:p-5 lg:grid-cols-[1.1fr_0.9fr] lg:gap-6 lg:px-6 lg:py-5">
                        <div className="space-y-3">
                            <Badge variant="amber" className="w-fit">
                                <SlidersHorizontal className="size-3.5" />
                                Catalog filters
                            </Badge>
                            <div className="space-y-1.5">
                                <p className="text-xs font-semibold tracking-[0.22em] text-[var(--color-text-muted)] uppercase">
                                    {filters.banner_id
                                        ? 'Collection'
                                        : category
                                          ? 'Category browse'
                                          : 'All products'}
                                </p>
                                <h1 className="font-display text-2xl font-semibold tracking-tight text-[var(--color-text-primary)] sm:text-3xl">
                                    {title}
                                </h1>
                                <p className="line-clamp-2 max-w-2xl text-sm leading-6 text-[var(--color-text-secondary)]">
                                    {description}
                                </p>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <div className="inline-flex items-center gap-2 rounded-full border border-[var(--color-border)] bg-[var(--color-surface-alt)] px-3 py-1.5 text-xs text-[var(--color-text-secondary)] sm:text-sm">
                                    <ShieldCheck className="size-4 text-[var(--color-success)]" />
                                    Secure checkout ready
                                </div>
                                <div className="inline-flex items-center gap-2 rounded-full border border-[var(--color-border)] bg-[var(--color-surface-alt)] px-3 py-1.5 text-xs text-[var(--color-text-secondary)] sm:text-sm">
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
                                        className="group relative isolate flex min-w-0 flex-col overflow-hidden rounded-2xl border border-[var(--color-border)] bg-gradient-to-br from-[var(--color-surface)] to-[color-mix(in_srgb,var(--stat-color)_6%,var(--color-surface))] p-3 shadow-sm transition duration-300 hover:-translate-y-0.5 hover:border-[color-mix(in_srgb,var(--stat-color)_35%,transparent)] hover:shadow-[var(--shadow-card-hover)]"
                                    >
                                        {/* Soft corner glow in the stat's accent colour */}
                                        <span className="pointer-events-none absolute -top-8 -right-8 -z-10 size-24 rounded-full bg-[var(--stat-color)] opacity-[0.08] blur-2xl transition-opacity duration-300 group-hover:opacity-[0.16]" />
                                        <span className="flex size-8 items-center justify-center rounded-xl bg-[color-mix(in_srgb,var(--stat-color)_12%,transparent)] text-[var(--stat-color)] ring-1 ring-[color-mix(in_srgb,var(--stat-color)_18%,transparent)] sm:size-9">
                                            <Icon className="size-4 sm:size-5" />
                                        </span>
                                        <p className="mt-2.5 truncate font-display text-lg leading-none font-bold tracking-tight text-[var(--color-text-primary)] sm:text-xl">
                                            {value}
                                        </p>
                                        <p className="mt-1 truncate text-[11px] text-[var(--color-text-secondary)] sm:text-xs">
                                            {detail}
                                        </p>
                                        <p className="mt-2 truncate border-t border-dashed border-[var(--color-border)] pt-1.5 text-[10px] font-semibold tracking-[0.16em] text-[var(--color-text-muted)] uppercase">
                                            {label}
                                        </p>
                                    </div>
                                ),
                            )}
                        </div>
                    </div>
                </section>

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
                            <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain pr-3 [scrollbar-width:thin]">
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
                                className={
                                    draftFilters.view === 'list'
                                        ? 'grid grid-cols-1 gap-3 md:gap-4'
                                        : PRODUCT_GRID_BESIDE_SIDEBAR_CLASS
                                }
                            >
                                {Array.from(
                                    { length: DEFAULT_GRID_COUNT },
                                    (_, index) => (
                                        <ProductCardSkeleton
                                            key={index}
                                            view={draftFilters.view}
                                        />
                                    ),
                                )}
                            </div>
                        ) : products.data.length > 0 ? (
                            <div
                                className={
                                    draftFilters.view === 'list'
                                        ? 'grid grid-cols-1 gap-3 md:gap-4'
                                        : PRODUCT_GRID_BESIDE_SIDEBAR_CLASS
                                }
                            >
                                {products.data.map((product, index) => (
                                    <ProductCard
                                        key={product.id}
                                        product={product}
                                        index={index}
                                        view={draftFilters.view}
                                        openCartOnAdd
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
