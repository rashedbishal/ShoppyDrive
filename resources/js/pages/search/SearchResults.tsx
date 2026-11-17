import { Head, router } from '@inertiajs/react';
import {
    Filter,
    LayoutGrid,
    List,
    Package,
    Search as SearchIcon,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { route } from 'ziggy-js';

import { PageHero } from '@/components/shared/PageHero';
import {
    ProductCard,
    ProductCardSkeleton,
} from '@/components/shared/ProductCard';
import {
    BrandFilterList,
    FilterCheckboxRow,
    FilterPanel,
    FilterSection,
    PriceRangeFilter,
} from '@/components/shared/ProductFilters';
import { Button } from '@/components/ui/button';
import { Drawer } from '@/components/ui/drawer';
import { EmptyState } from '@/components/ui/empty-state';
import { Pagination } from '@/components/ui/pagination';
import { Select } from '@/components/ui/select';
import { SIDEBAR_LAYOUT_CLASS } from '@/lib/constants';
import { useMediaQuery } from '@/lib/hooks/useMediaQuery';
import { trackMetaSearch } from '@/lib/meta-tracking';
import { cn } from '@/lib/utils';
import type { Brand, Category, PaginatedData, Product } from '@/types';

type SearchFilters = {
    q: string;
    sort: string;
    view: 'grid' | 'list';
    brand: string[];
    min_price: number | null;
    max_price: number | null;
    rating: number | null;
    in_stock: boolean;
    category: string | null;
    page: number;
};

type SearchResultsProps = {
    query: string;
    products: PaginatedData<Product>;
    filters: SearchFilters;
    categories: Category[];
    brands: (Brand & { products_count?: number })[];
    maxPrice: number;
};

type QueryValue = string | number | boolean | string[];

const SORT_OPTIONS = [
    { label: 'Relevance', value: 'relevance' },
    { label: 'Price: Low to High', value: 'price_asc' },
    { label: 'Price: High to Low', value: 'price_desc' },
    { label: 'Newest Arrivals', value: 'newest' },
    { label: 'Top Rated', value: 'rating' },
] as const;

// ── Helpers ────────────────────────────────────────────────────────────────────
function buildQuery(f: SearchFilters): Record<string, QueryValue> {
    const q: Record<string, QueryValue> = {
        q: f.q,
        sort: f.sort,
        view: f.view,
        page: f.page,
    };

    if (f.category) {
        q.category = f.category;
    }

    if (f.brand.length > 0) {
        q.brand = f.brand;
    }

    if (f.min_price !== null) {
        q.min_price = f.min_price;
    }

    if (f.max_price !== null) {
        q.max_price = f.max_price;
    }

    if (f.rating !== null) {
        q.rating = f.rating;
    }

    if (f.in_stock) {
        q.in_stock = true;
    }

    return q;
}

// ── Main Component ─────────────────────────────────────────────────────────────
export default function SearchResults({
    query,
    products,
    filters,
    categories = [],
    brands = [],
    maxPrice = 50000,
}: SearchResultsProps): React.JSX.Element {
    const isDesktop = useMediaQuery('(min-width: 1024px)');
    const [isFiltersOpen, setIsFiltersOpen] = useState(false);
    const [isLoading, setIsLoading] = useState(false);
    const [draft, setDraft] = useState<SearchFilters>(filters);

    useEffect(() => {
        // eslint-disable-next-line react-hooks/set-state-in-effect -- resets local draft/UI state when the server props or URL change
        setDraft(filters);
        setIsLoading(false);
    }, [filters]);

    // Meta Search: once per submitted search text, with the products found
    // on this page. Changing filters, sort or page keeps the same text and
    // so doesn't repeat it; searching again for something else does.
    useEffect(() => {
        if (query.trim() === '') {
            return;
        }

        trackMetaSearch(
            query,
            products.data.map((product) => Number(product.id)),
        );
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [query]);

    const submitFilters = (next: SearchFilters): void => {
        setIsLoading(true);
        router.get(route('search'), buildQuery(next) as never, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onFinish: () => {
                setIsLoading(false);
                setIsFiltersOpen(false);
            },
        });
    };

    const applyChange = (
        updater: (c: SearchFilters) => SearchFilters,
    ): void => {
        // Sent from here, never from inside a state updater: React may run
        // an updater twice, which sent every filter change as two identical
        // requests against the product-browsing rate limit.
        const next = updater(draft);
        setDraft(next);

        if (isDesktop) {
            submitFilters(next);
        }
    };

    const handleClearAll = (): void => {
        const next: SearchFilters = {
            q: query,
            sort: 'relevance',
            view: draft.view,
            brand: [],
            min_price: null,
            max_price: null,
            rating: null,
            in_stock: false,
            category: null,
            page: 1,
        };
        setDraft(next);
        submitFilters(next);
    };

    const handlePageChange = (pageNumber: number): void => {
        const next = { ...draft, page: pageNumber };
        setDraft(next);
        submitFilters(next);
    };

    // Active filter chips
    type Chip = { key: string; label: string; onRemove: () => void };
    const chips: Chip[] = [];

    if (draft.category) {
        const cat = categories.find((c) => c.slug === draft.category);
        chips.push({
            key: 'category',
            label: `Category: ${cat?.name ?? draft.category}`,
            onRemove: () =>
                applyChange((c) => ({ ...c, category: null, page: 1 })),
        });
    }

    draft.brand.forEach((slug) => {
        const b = brands.find((x) => x.slug === slug);
        chips.push({
            key: `brand-${slug}`,
            label: `Brand: ${b?.name ?? slug}`,
            onRemove: () =>
                applyChange((c) => ({
                    ...c,
                    brand: c.brand.filter((s) => s !== slug),
                    page: 1,
                })),
        });
    });

    if (draft.min_price !== null || draft.max_price !== null) {
        chips.push({
            key: 'price',
            label: `Price: ৳${(draft.min_price ?? 0).toLocaleString('en-BD')} – ৳${(draft.max_price ?? maxPrice).toLocaleString('en-BD')}`,
            onRemove: () =>
                applyChange((c) => ({
                    ...c,
                    min_price: null,
                    max_price: null,
                    page: 1,
                })),
        });
    }

    if (draft.rating !== null) {
        chips.push({
            key: 'rating',
            label: `${draft.rating}★ & up`,
            onRemove: () =>
                applyChange((c) => ({ ...c, rating: null, page: 1 })),
        });
    }

    if (draft.in_stock) {
        chips.push({
            key: 'stock',
            label: 'In stock only',
            onRemove: () =>
                applyChange((c) => ({ ...c, in_stock: false, page: 1 })),
        });
    }

    // The same filter building blocks as the catalog sidebar (DISC-56).
    const sidebarControls = (
        <div>
            {categories.length > 0 && (
                <FilterSection title="Category">
                    <div className="space-y-0.5">
                        {categories.map((cat) => (
                            <FilterCheckboxRow
                                key={cat.id}
                                label={cat.name}
                                count={cat.products_count}
                                checked={draft.category === cat.slug}
                                onChange={() =>
                                    applyChange((c) => ({
                                        ...c,
                                        category:
                                            c.category === cat.slug
                                                ? null
                                                : cat.slug,
                                        page: 1,
                                    }))
                                }
                            />
                        ))}
                    </div>
                </FilterSection>
            )}

            <FilterSection title="Price Range">
                <PriceRangeFilter
                    lo={draft.min_price ?? 0}
                    hi={draft.max_price ?? Math.max(1000, maxPrice)}
                    cap={Math.max(1000, maxPrice)}
                    onApply={(lo, hi) =>
                        applyChange((c) => ({
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
                    checked={draft.in_stock}
                    onChange={() =>
                        applyChange((c) => ({
                            ...c,
                            in_stock: !c.in_stock,
                            page: 1,
                        }))
                    }
                />
            </FilterSection>

            {brands.length > 0 && (
                <FilterSection title={`Brand (${brands.length})`}>
                    <BrandFilterList
                        brands={brands}
                        selected={draft.brand}
                        onToggle={(slug) =>
                            applyChange((c) => ({
                                ...c,
                                brand: c.brand.includes(slug)
                                    ? c.brand.filter((s) => s !== slug)
                                    : [...c.brand, slug],
                                page: 1,
                            }))
                        }
                    />
                </FilterSection>
            )}
        </div>
    );

    const drawerFooter = (
        <div className="flex items-center gap-3">
            <Button variant="outline" fullWidth onClick={handleClearAll}>
                Clear All
            </Button>
            <Button fullWidth onClick={() => submitFilters(draft)}>
                Apply Filters
            </Button>
        </div>
    );

    return (
        <>
            <Head title={query ? `Search: "${query}"` : 'Search'} />

            <div className="space-y-6 py-6 lg:py-8">
                <PageHero
                    badge={{ icon: SearchIcon, label: 'Search' }}
                    eyebrow="Search results"
                    title={query ? `"${query}"` : 'All Products'}
                    chips={[
                        {
                            icon: Package,
                            label: `${products.total.toLocaleString()} ${products.total === 1 ? 'product' : 'products'} found`,
                        },
                    ]}
                />

                {/* Sidebar + Grid */}
                <section
                    className={cn(
                        SIDEBAR_LAYOUT_CLASS,
                        'mx-auto max-w-7xl px-4 sm:px-6 lg:px-8',
                    )}
                >
                    {/* Desktop Sidebar */}
                    <aside className="hidden lg:block">
                        <FilterPanel
                            activeCount={chips.length}
                            canClear={chips.length > 0}
                            onClearAll={handleClearAll}
                        >
                            {sidebarControls}
                        </FilterPanel>
                    </aside>

                    {/* Main Content */}
                    <div className="min-w-0 space-y-6 lg:col-span-3">
                        {/* Toolbar */}
                        <div className="rounded-[2rem] border border-[var(--color-border)] bg-[var(--color-surface)] p-4 shadow-sm">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div className="flex items-center gap-3">
                                    <Button
                                        variant="outline"
                                        className="lg:hidden"
                                        onClick={() => setIsFiltersOpen(true)}
                                    >
                                        <Filter className="size-4" />
                                        Filters
                                        {chips.length > 0 && (
                                            <span className="ml-1 flex size-5 items-center justify-center rounded-full bg-[var(--color-primary)] text-[10px] text-white">
                                                {chips.length}
                                            </span>
                                        )}
                                    </Button>

                                    <Select
                                        value={draft.sort}
                                        onChange={(e) => {
                                            const next = {
                                                ...draft,
                                                sort: e.target.value,
                                                page: 1,
                                            };
                                            setDraft(next);
                                            submitFilters(next);
                                        }}
                                        options={SORT_OPTIONS.map((o) => ({
                                            label: o.label,
                                            value: o.value,
                                        }))}
                                        selectClassName="min-w-48"
                                    />
                                </div>

                                <div className="flex items-center gap-3">
                                    <p className="hidden text-sm text-[var(--color-text-secondary)] sm:block">
                                        {products.from ?? 0}–{products.to ?? 0}{' '}
                                        of {products.total}
                                    </p>
                                    <div className="inline-flex overflow-hidden rounded-xl border border-[var(--color-border)]">
                                        {(['grid', 'list'] as const).map(
                                            (v) => (
                                                <button
                                                    key={v}
                                                    type="button"
                                                    onClick={() => {
                                                        const next = {
                                                            ...draft,
                                                            view: v,
                                                            page: 1,
                                                        };
                                                        setDraft(next);
                                                        submitFilters(next);
                                                    }}
                                                    aria-label={`${v} view`}
                                                    className={cn(
                                                        'inline-flex size-11 items-center justify-center transition',
                                                        draft.view === v
                                                            ? 'bg-[var(--color-primary-light)] text-[var(--color-primary)]'
                                                            : 'bg-white text-[var(--color-text-secondary)] hover:bg-[var(--color-surface-alt)]',
                                                    )}
                                                >
                                                    {v === 'grid' ? (
                                                        <LayoutGrid className="size-4" />
                                                    ) : (
                                                        <List className="size-4" />
                                                    )}
                                                </button>
                                            ),
                                        )}
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Active filter chips */}
                        {chips.length > 0 && (
                            <div className="flex flex-wrap gap-2">
                                {chips.map((chip) => (
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
                        )}

                        {/* Product Grid / Skeletons / Empty */}
                        {isLoading ? (
                            <div
                                className={cn(
                                    'grid gap-3 md:gap-4',
                                    draft.view === 'list'
                                        ? 'grid-cols-1'
                                        : 'grid-cols-2 sm:grid-cols-3',
                                )}
                            >
                                {Array.from({ length: 12 }, (_, i) => (
                                    <ProductCardSkeleton
                                        key={i}
                                        view={draft.view}
                                    />
                                ))}
                            </div>
                        ) : products.data.length > 0 ? (
                            <div
                                className={cn(
                                    'grid gap-3 md:gap-4',
                                    draft.view === 'list'
                                        ? 'grid-cols-1'
                                        : 'grid-cols-2 sm:grid-cols-3',
                                )}
                            >
                                {products.data.map((product, index) => (
                                    <ProductCard
                                        key={product.id}
                                        product={product}
                                        index={index}
                                        view={draft.view}
                                    />
                                ))}
                            </div>
                        ) : (
                            <EmptyState
                                icon={SearchIcon}
                                title="No results found"
                                description={
                                    chips.length > 0
                                        ? 'Try removing some filters to see more results.'
                                        : `We couldn't find anything matching "${query}". Try a different search term.`
                                }
                                action={
                                    chips.length > 0
                                        ? {
                                              label: 'Clear filters',
                                              onClick: handleClearAll,
                                          }
                                        : {
                                              label: 'Browse all products',
                                              href: route('products.index'),
                                          }
                                }
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

            {/* Mobile Filter Drawer */}
            <Drawer
                isOpen={isFiltersOpen}
                onClose={() => setIsFiltersOpen(false)}
                title="Filters"
                description="Refine your search results."
                side="left"
                footer={drawerFooter}
            >
                <div className="space-y-6">{sidebarControls}</div>
            </Drawer>
        </>
    );
}
