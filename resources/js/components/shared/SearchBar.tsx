import { Link, router, usePage } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import { ArrowRight, Layers, LayoutGrid, Search as SearchIcon, Tag, TrendingUp, X } from 'lucide-react';
import React, { useCallback, useEffect, useRef, useState } from 'react';

import { useDebounce } from '@/lib/hooks/useDebounce';
import { formatCurrency } from '@/lib/utils';

interface SuggestionProduct {
    id: number;
    name: string;
    slug: string;
    price: number;
    compare_price?: number | null;
    primary_image_url?: string | null;
    images?: { path: string }[];
}

interface SuggestionBrand {
    id: number;
    name: string;
    slug: string;
}

interface SuggestionCategory {
    id: number;
    name: string;
    slug: string;
}

interface SuggestionSubCategory {
    id: number;
    name: string;
    slug: string;
    category_name?: string | null;
}

type FlatItem =
    | { kind: 'product'; slug: string }
    | { kind: 'brand'; slug: string }
    | { kind: 'category'; slug: string }
    | { kind: 'subcategory'; slug: string }
    | { kind: 'seeAll' };

const POPULAR_FALLBACK = ['Smartphone', 'Headphones', 'Laptop', 'Watch', 'Speaker', 'Accessories'];

interface SearchBarProps {
    className?: string;
    isMobile?: boolean;
    onSearch?: () => void;
    autoFocus?: boolean;
}

export function SearchBar({ className, isMobile = false, onSearch, autoFocus = false }: SearchBarProps): React.JSX.Element {
    const popularKeywords = (usePage().props as any).popular_keywords as string[] | undefined;
    const popular = popularKeywords && popularKeywords.length > 0 ? popularKeywords : POPULAR_FALLBACK;
    const [query, setQuery] = useState('');
    const [products, setProducts] = useState<SuggestionProduct[]>([]);
    const [brands, setBrands] = useState<SuggestionBrand[]>([]);
    const [categories, setCategories] = useState<SuggestionCategory[]>([]);
    const [subcategories, setSubcategories] = useState<SuggestionSubCategory[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [isFocused, setIsFocused] = useState(false);
    const [activeIndex, setActiveIndex] = useState(-1);

    const containerRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);
    const debouncedQuery = useDebounce(query, 280);

    const hasResults = products.length > 0 || brands.length > 0 || categories.length > 0 || subcategories.length > 0;
    const showPanel = isFocused && (query.trim().length >= 2 || query.trim().length === 0);

    // Build flat navigable list for keyboard nav
    const flatItems: FlatItem[] = [
        ...products.map((p): FlatItem => ({ kind: 'product', slug: p.slug })),
        ...brands.map((b): FlatItem => ({ kind: 'brand', slug: b.slug })),
        ...categories.map((c): FlatItem => ({ kind: 'category', slug: c.slug })),
        ...subcategories.map((s): FlatItem => ({ kind: 'subcategory', slug: s.slug })),
        ...(hasResults ? [{ kind: 'seeAll' } as FlatItem] : []),
    ];

    // Fetch suggestions
    useEffect(() => {
        if (debouncedQuery.trim().length < 2) {
            setProducts([]);
            setBrands([]);
            setCategories([]);
            setSubcategories([]);
            setIsLoading(false);
            return;
        }
        setIsLoading(true);
        fetch(`/api/search/suggestions?q=${encodeURIComponent(debouncedQuery)}`)
            .then(r => (r.ok ? r.json() : { products: [], brands: [], categories: [], subcategories: [] }))
            .then(data => {
                setProducts(data.products ?? []);
                setBrands(data.brands ?? []);
                setCategories(data.categories ?? []);
                setSubcategories(data.subcategories ?? []);
            })
            .catch(() => {
                setProducts([]);
                setBrands([]);
                setCategories([]);
                setSubcategories([]);
            })
            .finally(() => setIsLoading(false));
    }, [debouncedQuery]);

    // Close on outside click
    useEffect(() => {
        const handler = (e: MouseEvent) => {
            if (containerRef.current && !containerRef.current.contains(e.target as Node)) {
                setIsFocused(false);
            }
        };
        document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, []);

    const navigateToQuery = useCallback(
        (q: string) => {
            if (!q.trim()) return;
            setIsFocused(false);
            onSearch?.();
            router.get(route('search'), { q: q.trim() });
        },
        [onSearch],
    );

    const navigateToItem = useCallback(
        (item: FlatItem) => {
            setIsFocused(false);
            onSearch?.();
            if (item.kind === 'seeAll') {
                router.get(route('search'), { q: query.trim() });
            } else if (item.kind === 'product') {
                router.get(route('product.show', item.slug));
            } else if (item.kind === 'brand') {
                router.get(route('brand.show', item.slug));
            } else {
                // category and subcategory both use category.show
                router.get(route('category.show', item.slug));
            }
        },
        [query, onSearch],
    );

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        navigateToQuery(query);
    };

    const handleKeyDown = (e: React.KeyboardEvent) => {
        if (!showPanel) return;
        const total = flatItems.length;
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setActiveIndex(i => (i < total - 1 ? i + 1 : 0));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setActiveIndex(i => (i > 0 ? i - 1 : total - 1));
        } else if (e.key === 'Enter') {
            if (activeIndex >= 0 && activeIndex < flatItems.length) {
                navigateToItem(flatItems[activeIndex]);
            } else {
                navigateToQuery(query);
            }
        } else if (e.key === 'Escape') {
            setIsFocused(false);
            inputRef.current?.blur();
        }
    };

    const imgSrc = (p: SuggestionProduct) => p.primary_image_url || p.images?.[0]?.path || null;

    // Compute per-section starting indices for activeIndex highlighting
    const productStart = 0;
    const brandStart = products.length;
    const categoryStart = brandStart + brands.length;
    const subcategoryStart = categoryStart + categories.length;
    const seeAllIndex = subcategoryStart + subcategories.length;

    return (
        <div ref={containerRef} className={`relative ${className ?? ''}`}>
            {/* Search input */}
            <form
                onSubmit={handleSubmit}
                className={`flex items-center gap-2 rounded-2xl border px-3.5 py-2.5 transition-all duration-200 ${
                    isFocused
                        ? 'border-[#c8102e] bg-white shadow-[0_0_0_3px_rgba(200,16,46,0.08)]'
                        : 'border-gray-200 bg-gray-50'
                }`}
            >
                <SearchIcon className="size-4 shrink-0 text-gray-400" />
                <input
                    ref={inputRef}
                    type="search"
                    value={query}
                    autoFocus={autoFocus}
                    autoComplete="off"
                    onChange={e => {
                        setQuery(e.target.value);
                        setActiveIndex(-1);
                    }}
                    onFocus={() => setIsFocused(true)}
                    onKeyDown={handleKeyDown}
                    placeholder="Search products, brands, categories…"
                    className="min-w-0 flex-1 bg-transparent text-sm text-gray-800 placeholder:text-gray-400 focus:outline-none"
                />
                {query && (
                    <button
                        type="button"
                        onClick={() => {
                            setQuery('');
                            setProducts([]);
                            setBrands([]);
                            setCategories([]);
                            setSubcategories([]);
                            inputRef.current?.focus();
                        }}
                        className="flex size-5 shrink-0 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-200 hover:text-gray-600"
                        aria-label="Clear"
                    >
                        <X className="size-3" />
                    </button>
                )}
                <button
                    type="submit"
                    className="flex size-8 shrink-0 items-center justify-center rounded-xl bg-[#c8102e] text-white transition hover:bg-[#a50d25]"
                    aria-label="Search"
                >
                    <SearchIcon className="size-3.5" />
                </button>
            </form>

            {/* Suggestion panel */}
            <AnimatePresence>
                {showPanel && (
                    <motion.div
                        initial={{ opacity: 0, y: 6, scale: 0.98 }}
                        animate={{ opacity: 1, y: 0, scale: 1 }}
                        exit={{ opacity: 0, y: 4, scale: 0.98 }}
                        transition={{ duration: 0.15, ease: 'easeOut' }}
                        className="absolute left-0 right-0 top-[calc(100%+8px)] z-[200] overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xl shadow-gray-200/80"
                    >
                        {/* Loading skeleton */}
                        {isLoading && (
                            <div className="space-y-1 p-2">
                                {[1, 2, 3].map(i => (
                                    <div key={i} className="flex items-center gap-3 rounded-xl p-2.5">
                                        <div className="size-10 shrink-0 animate-pulse rounded-xl bg-gray-100" />
                                        <div className="flex-1 space-y-1.5">
                                            <div className="h-3.5 w-3/4 animate-pulse rounded bg-gray-100" />
                                            <div className="h-3 w-1/3 animate-pulse rounded bg-gray-100" />
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}

                        {!isLoading && hasResults && (
                            <>
                                {/* Products */}
                                {products.length > 0 && (
                                    <>
                                        <SectionHeader label="Products" />
                                        <ul className="px-2 pb-1">
                                            {products.map((product, i) => (
                                                <li key={product.id}>
                                                    <Link
                                                        href={route('product.show', product.slug)}
                                                        onClick={() => setIsFocused(false)}
                                                        className={`flex items-center gap-3 rounded-xl p-2.5 transition-colors ${
                                                            activeIndex === productStart + i ? 'bg-gray-50' : 'hover:bg-gray-50'
                                                        }`}
                                                    >
                                                        <div className="size-11 shrink-0 overflow-hidden rounded-xl border border-gray-100 bg-gray-50">
                                                            {imgSrc(product) ? (
                                                                <img
                                                                    src={imgSrc(product)!}
                                                                    alt={product.name}
                                                                    className="size-full object-cover"
                                                                />
                                                            ) : (
                                                                <div className="flex size-full items-center justify-center text-gray-300">
                                                                    <SearchIcon className="size-4" />
                                                                </div>
                                                            )}
                                                        </div>
                                                        <div className="min-w-0 flex-1">
                                                            <p className="truncate text-sm font-medium text-gray-900">
                                                                {product.name}
                                                            </p>
                                                            <div className="mt-0.5 flex items-center gap-1.5">
                                                                <span className="text-sm font-bold text-[#c8102e]">
                                                                    {formatCurrency(product.price)}
                                                                </span>
                                                                {product.compare_price &&
                                                                    product.compare_price > product.price && (
                                                                        <span className="text-xs text-gray-400 line-through">
                                                                            {formatCurrency(product.compare_price)}
                                                                        </span>
                                                                    )}
                                                            </div>
                                                        </div>
                                                        <ArrowRight className="size-4 shrink-0 text-gray-300" />
                                                    </Link>
                                                </li>
                                            ))}
                                        </ul>
                                    </>
                                )}

                                {/* Brands */}
                                {brands.length > 0 && (
                                    <>
                                        <SectionHeader label="Brands" border={products.length > 0} />
                                        <ul className="px-2 pb-1">
                                            {brands.map((brand, i) => (
                                                <li key={brand.id}>
                                                    <Link
                                                        href={route('brand.show', brand.slug)}
                                                        onClick={() => setIsFocused(false)}
                                                        className={`flex items-center gap-3 rounded-xl px-3 py-2 transition-colors ${
                                                            activeIndex === brandStart + i ? 'bg-gray-50' : 'hover:bg-gray-50'
                                                        }`}
                                                    >
                                                        <div className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-500">
                                                            <Tag className="size-3.5" />
                                                        </div>
                                                        <span className="text-sm font-medium text-gray-800">
                                                            {brand.name}
                                                        </span>
                                                        <ArrowRight className="ml-auto size-4 shrink-0 text-gray-300" />
                                                    </Link>
                                                </li>
                                            ))}
                                        </ul>
                                    </>
                                )}

                                {/* Categories */}
                                {categories.length > 0 && (
                                    <>
                                        <SectionHeader
                                            label="Categories"
                                            border={products.length > 0 || brands.length > 0}
                                        />
                                        <ul className="px-2 pb-1">
                                            {categories.map((cat, i) => (
                                                <li key={cat.id}>
                                                    <Link
                                                        href={route('category.show', cat.slug)}
                                                        onClick={() => setIsFocused(false)}
                                                        className={`flex items-center gap-3 rounded-xl px-3 py-2 transition-colors ${
                                                            activeIndex === categoryStart + i ? 'bg-gray-50' : 'hover:bg-gray-50'
                                                        }`}
                                                    >
                                                        <div className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-green-50 text-green-500">
                                                            <LayoutGrid className="size-3.5" />
                                                        </div>
                                                        <span className="text-sm font-medium text-gray-800">
                                                            {cat.name}
                                                        </span>
                                                        <ArrowRight className="ml-auto size-4 shrink-0 text-gray-300" />
                                                    </Link>
                                                </li>
                                            ))}
                                        </ul>
                                    </>
                                )}

                                {/* Subcategories */}
                                {subcategories.length > 0 && (
                                    <>
                                        <SectionHeader
                                            label="Subcategories"
                                            border={
                                                products.length > 0 || brands.length > 0 || categories.length > 0
                                            }
                                        />
                                        <ul className="px-2 pb-1">
                                            {subcategories.map((sub, i) => (
                                                <li key={sub.id}>
                                                    <Link
                                                        href={route('category.show', sub.slug)}
                                                        onClick={() => setIsFocused(false)}
                                                        className={`flex items-center gap-3 rounded-xl px-3 py-2 transition-colors ${
                                                            activeIndex === subcategoryStart + i
                                                                ? 'bg-gray-50'
                                                                : 'hover:bg-gray-50'
                                                        }`}
                                                    >
                                                        <div className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-purple-50 text-purple-500">
                                                            <Layers className="size-3.5" />
                                                        </div>
                                                        <div className="min-w-0 flex-1">
                                                            <p className="text-sm font-medium text-gray-800">
                                                                {sub.name}
                                                            </p>
                                                            {sub.category_name && (
                                                                <p className="text-xs text-gray-400">
                                                                    in {sub.category_name}
                                                                </p>
                                                            )}
                                                        </div>
                                                        <ArrowRight className="size-4 shrink-0 text-gray-300" />
                                                    </Link>
                                                </li>
                                            ))}
                                        </ul>
                                    </>
                                )}

                                {/* See all results */}
                                <div className="border-t border-gray-100 p-2">
                                    <button
                                        onClick={() => navigateToQuery(query)}
                                        onMouseEnter={() => setActiveIndex(seeAllIndex)}
                                        className={`flex w-full items-center justify-between rounded-xl px-4 py-3 text-sm font-semibold transition-colors ${
                                            activeIndex === seeAllIndex
                                                ? 'bg-red-50 text-[#c8102e]'
                                                : 'text-[#c8102e] hover:bg-red-50'
                                        }`}
                                    >
                                        <span>See all results for "{query}"</span>
                                        <ArrowRight className="size-4" />
                                    </button>
                                </div>
                            </>
                        )}

                        {/* No results */}
                        {!isLoading && !hasResults && query.trim().length >= 2 && (
                            <div className="px-4 py-6 text-center">
                                <p className="text-sm text-gray-500">
                                    No results found for{' '}
                                    <span className="font-semibold text-gray-800">"{query}"</span>
                                </p>
                                <button
                                    onClick={() => navigateToQuery(query)}
                                    className="mt-3 text-sm font-semibold text-[#c8102e] hover:underline"
                                >
                                    Search anyway →
                                </button>
                            </div>
                        )}

                        {/* Popular searches */}
                        {!isLoading && query.trim().length < 2 && (
                            <div className="p-4">
                                <div className="mb-3 flex items-center gap-2">
                                    <TrendingUp className="size-4 text-gray-400" />
                                    <p className="text-[11px] font-bold uppercase tracking-widest text-gray-400">
                                        Popular searches
                                    </p>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    {popular.map(term => (
                                        <button
                                            key={term}
                                            onClick={() => {
                                                setQuery(term);
                                                navigateToQuery(term);
                                            }}
                                            className="rounded-full border border-gray-200 bg-gray-50 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:border-[#c8102e]/30 hover:bg-red-50 hover:text-[#c8102e]"
                                        >
                                            {term}
                                        </button>
                                    ))}
                                </div>
                            </div>
                        )}
                    </motion.div>
                )}
            </AnimatePresence>
        </div>
    );
}

function SectionHeader({ label, border = false }: { label: string; border?: boolean }) {
    return (
        <div className={`px-4 pb-1 pt-3 ${border ? 'border-t border-gray-100' : ''}`}>
            <p className="text-[10px] font-bold uppercase tracking-widest text-gray-400">{label}</p>
        </div>
    );
}
