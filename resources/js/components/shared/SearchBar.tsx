import { Link, router } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import { ArrowRight, Search as SearchIcon, TrendingUp, X } from 'lucide-react';
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

const POPULAR = ['Smartphone', 'Headphones', 'Laptop', 'Watch', 'Speaker', 'Accessories'];

interface SearchBarProps {
    className?: string;
    isMobile?: boolean;
    onSearch?: () => void;
}

export function SearchBar({ className, isMobile = false, onSearch }: SearchBarProps): React.JSX.Element {
    const [query, setQuery] = useState('');
    const [suggestions, setSuggestions] = useState<SuggestionProduct[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [isFocused, setIsFocused] = useState(false);
    const [activeIndex, setActiveIndex] = useState(-1);

    const containerRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);
    const debouncedQuery = useDebounce(query, 280);

    const showPanel = isFocused && (query.trim().length >= 2 || query.trim().length === 0);
    const hasSuggestions = suggestions.length > 0;

    // Fetch suggestions
    useEffect(() => {
        if (debouncedQuery.trim().length < 2) {
            setSuggestions([]);
            setIsLoading(false);
            return;
        }
        setIsLoading(true);
        fetch(`/api/search/suggestions?q=${encodeURIComponent(debouncedQuery)}`)
            .then(r => r.ok ? r.json() : { products: [] })
            .then(data => setSuggestions(data.products || []))
            .catch(() => setSuggestions([]))
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

    const navigate = useCallback((q: string) => {
        if (!q.trim()) return;
        setIsFocused(false);
        onSearch?.();
        router.get(route('search'), { q: q.trim() });
    }, [onSearch]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        navigate(query);
    };

    const handleKeyDown = (e: React.KeyboardEvent) => {
        if (!showPanel) return;
        const total = hasSuggestions ? suggestions.length + 1 : 0; // +1 for "see all"
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setActiveIndex(i => (i < total - 1 ? i + 1 : 0));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setActiveIndex(i => (i > 0 ? i - 1 : total - 1));
        } else if (e.key === 'Enter') {
            if (activeIndex >= 0 && activeIndex < suggestions.length) {
                router.get(route('product.show', suggestions[activeIndex].slug));
                setIsFocused(false);
            } else {
                navigate(query);
            }
        } else if (e.key === 'Escape') {
            setIsFocused(false);
            inputRef.current?.blur();
        }
    };

    const imgSrc = (p: SuggestionProduct) =>
        p.primary_image_url || p.images?.[0]?.path || null;

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
                    autoComplete="off"
                    onChange={e => { setQuery(e.target.value); setActiveIndex(-1); }}
                    onFocus={() => setIsFocused(true)}
                    onKeyDown={handleKeyDown}
                    placeholder="Search products, brands, categories…"
                    className="min-w-0 flex-1 bg-transparent text-sm text-gray-800 placeholder:text-gray-400 focus:outline-none"
                />
                {query && (
                    <button
                        type="button"
                        onClick={() => { setQuery(''); setSuggestions([]); inputRef.current?.focus(); }}
                        className="flex size-5 shrink-0 items-center justify-center rounded-full text-gray-400 hover:bg-gray-200 hover:text-gray-600 transition"
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
                                        <div className="size-12 shrink-0 animate-pulse rounded-xl bg-gray-100" />
                                        <div className="flex-1 space-y-1.5">
                                            <div className="h-3.5 w-3/4 animate-pulse rounded bg-gray-100" />
                                            <div className="h-3 w-1/3 animate-pulse rounded bg-gray-100" />
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}

                        {/* Product suggestions */}
                        {!isLoading && hasSuggestions && (
                            <>
                                <div className="px-4 pt-3 pb-1">
                                    <p className="text-[10px] font-bold uppercase tracking-widest text-gray-400">
                                        Products
                                    </p>
                                </div>
                                <ul className="p-2">
                                    {suggestions.map((product, i) => (
                                        <li key={product.id}>
                                            <Link
                                                href={route('product.show', product.slug)}
                                                onClick={() => setIsFocused(false)}
                                                className={`flex items-center gap-3 rounded-xl p-2.5 transition-colors ${
                                                    activeIndex === i ? 'bg-gray-50' : 'hover:bg-gray-50'
                                                }`}
                                            >
                                                {/* Thumbnail */}
                                                <div className="size-12 shrink-0 overflow-hidden rounded-xl border border-gray-100 bg-gray-50">
                                                    {imgSrc(product) ? (
                                                        <img
                                                            src={imgSrc(product)!}
                                                            alt={product.name}
                                                            className="size-full object-cover"
                                                        />
                                                    ) : (
                                                        <div className="flex size-full items-center justify-center text-gray-300">
                                                            <SearchIcon className="size-5" />
                                                        </div>
                                                    )}
                                                </div>
                                                {/* Info */}
                                                <div className="min-w-0 flex-1">
                                                    <p className="truncate text-sm font-medium text-gray-900">
                                                        {product.name}
                                                    </p>
                                                    <div className="mt-0.5 flex items-center gap-1.5">
                                                        <span className="text-sm font-bold text-[#c8102e]">
                                                            {formatCurrency(product.price)}
                                                        </span>
                                                        {product.compare_price && product.compare_price > product.price && (
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

                                {/* See all results */}
                                <div className="border-t border-gray-100 p-2">
                                    <button
                                        onClick={() => navigate(query)}
                                        onMouseEnter={() => setActiveIndex(suggestions.length)}
                                        className={`flex w-full items-center justify-between rounded-xl px-4 py-3 text-sm font-semibold transition-colors ${
                                            activeIndex === suggestions.length
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
                        {!isLoading && !hasSuggestions && query.trim().length >= 2 && (
                            <div className="px-4 py-6 text-center">
                                <p className="text-sm text-gray-500">
                                    No products found for <span className="font-semibold text-gray-800">"{query}"</span>
                                </p>
                                <button
                                    onClick={() => navigate(query)}
                                    className="mt-3 text-sm font-semibold text-[#c8102e] hover:underline"
                                >
                                    Search anyway →
                                </button>
                            </div>
                        )}

                        {/* Empty state — popular searches */}
                        {!isLoading && query.trim().length < 2 && (
                            <div className="p-4">
                                <div className="mb-3 flex items-center gap-2">
                                    <TrendingUp className="size-4 text-gray-400" />
                                    <p className="text-[11px] font-bold uppercase tracking-widest text-gray-400">
                                        Popular searches
                                    </p>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    {POPULAR.map(term => (
                                        <button
                                            key={term}
                                            onClick={() => { setQuery(term); navigate(term); }}
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
