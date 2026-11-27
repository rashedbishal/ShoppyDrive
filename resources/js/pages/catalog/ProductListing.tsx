import { Head, router, usePage } from '@inertiajs/react';
import {
    Check,
    ChevronLeft,
    Filter,
    LayoutGrid,
    List,
    Search,
    SlidersHorizontal,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { route } from 'ziggy-js';
import {
    ProductCard,
    ProductCardSkeleton,
} from '@/components/shared/ProductCard';
import {
    BrandFilterList,
    CategoryRow,
    FilterCheckboxRow,
    FilterPanel,
    FilterSection,
    PriceRangeFilter,
} from '@/components/shared/ProductFilters';
import { SubcategoryShowcase } from '@/components/shared/SubcategoryShowcase';
import { Button } from '@/components/ui/button';
import { Drawer } from '@/components/ui/drawer';
import { EmptyState } from '@/components/ui/empty-state';
import { Input } from '@/components/ui/input';
import { LazySelect } from '@/components/ui/lazy-select';
import { Pagination } from '@/components/ui/pagination';
import { useLiveSearch } from '@/hooks/use-live-search';
import {
    PRODUCT_GRID_BESIDE_SIDEBAR_CLASS,
    SIDEBAR_LAYOUT_CLASS,
} from '@/lib/constants';
import { useMediaQuery } from '@/lib/hooks/useMediaQuery';
import { IMAGE_SIZES } from '@/lib/image-sizes';
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
    /** DISC-88: the server's <title> for a category page (its SEO title, else its name); null elsewhere. */
    seoTitle?: string | null;
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

function ProductListingPage({
    title,
    description,
    seoTitle,
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

    const sidebarControls = (
        <div>
            <FilterSection title="Category" defaultOpen={false}>
                <div className="space-y-0.5">
                    {category ? (
                        <>
                            <button
                                type="button"
                                onClick={clearCategory}
                                className="mb-1 inline-flex items-center gap-1 px-1 text-xs font-medium text-[var(--color-text-secondary)] transition hover:text-[var(--color-primary)]"
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
                    <BrandFilterList
                        brands={brands}
                        selected={draftFilters.brand}
                        onToggle={(slug) => {
                            applyImmediateChange((current) => ({
                                ...current,
                                brand: current.brand.includes(slug)
                                    ? current.brand.filter(
                                          (item) => item !== slug,
                                      )
                                    : [...current.brand, slug],
                                page: 1,
                            }));
                        }}
                    />
                </FilterSection>
            ) : null}

            {attributes.map((attribute) => (
                <FilterSection key={attribute.id} title={attribute.name}>
                    {attribute.type === 'color' ? (
                        <div className="flex flex-wrap gap-1">
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
                                            'relative flex size-8 items-center justify-center rounded-full border-2 transition',
                                            checked
                                                ? 'border-[var(--color-primary)]'
                                                : 'border-transparent hover:border-[var(--color-border)]',
                                        )}
                                    >
                                        <span
                                            className="size-6 rounded-full border border-black/10 shadow-sm"
                                            style={{
                                                backgroundColor:
                                                    value.color_hex ??
                                                    '#e5e7eb',
                                            }}
                                        />
                                        {checked ? (
                                            <span className="absolute inset-0 flex items-center justify-center">
                                                <Check className="size-3.5 text-white mix-blend-difference" />
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
            <Head title={seoTitle || title} />

            <div className="space-y-6 py-6 lg:py-8">
                {showSubcategoryShowcase && activeTopCategory ? (
                    <section className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <h1 className="font-display text-2xl leading-tight font-semibold tracking-tight break-words text-[var(--color-text-primary)] sm:text-3xl">
                            {title}
                        </h1>
                        {description ? (
                            <p className="mt-1.5 text-sm leading-6 text-[var(--color-text-secondary)] sm:text-base">
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
                                    srcSet={category.image_srcset ?? undefined}
                                    sizes={
                                        category.image_srcset
                                            ? IMAGE_SIZES.fullWidth
                                            : undefined
                                    }
                                    decoding="async"
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

                            {/* Full banner width (owner request 2026-11-15): the old max-w-3xl/2xl text block left the sides of a wide banner empty. */}
                            <div className="flex flex-col items-center">
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
                                    <p className="mt-2 line-clamp-2 text-sm leading-6 text-white/85 sm:text-base">
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
                        <FilterPanel
                            activeCount={activeFilterCount}
                            canClear={hasActiveFilters}
                            onClearAll={handleClearAll}
                        >
                            {sidebarControls}
                        </FilterPanel>
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

                                    <LazySelect
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
