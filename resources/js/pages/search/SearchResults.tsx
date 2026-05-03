import { Head, router } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { Filter, Grid, List, Search as SearchIcon, SlidersHorizontal, X } from 'lucide-react';
import React, { useState } from 'react';

import { ProductCard } from '@/components/shared/ProductCard';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Drawer } from '@/components/ui/drawer';
import { EmptyState } from '@/components/ui/empty-state';
import { RangeSlider } from '@/components/ui/range-slider';
import { Skeleton } from '@/components/ui/skeleton';
import { useMediaQuery } from '@/lib/hooks/useMediaQuery';
import type { Category, PaginatedData, Product, ProductFilters } from '@/types';

interface SearchResultsProps {
    query: string;
    products: PaginatedData<Product>;
    filters: ProductFilters;
    categories: Category[];
    maxPrice: number;
}

export default function SearchResults({
    query,
    products,
    filters,
    categories = [],
    maxPrice = 50000,
}: SearchResultsProps): React.JSX.Element {
    const isDesktop = useMediaQuery('(min-width: 1024px)');
    const [isFilterDrawerOpen, setIsFilterDrawerOpen] = useState(false);
    const [viewMode, setViewMode] = useState<'grid' | 'list'>('grid');
    
    // Filter states
    const [priceRange, setPriceRange] = useState<[number, number]>([
        filters.min_price || 0,
        filters.max_price || maxPrice
    ]);

    const handleFilterChange = (newFilters: Partial<ProductFilters>) => {
        router.get(route('search'), { 
            ...filters, 
            ...newFilters, 
            q: query 
        }, {
            preserveState: true,
            preserveScroll: true,
            only: ['products', 'filters'],
        });
    };

    const clearFilters = () => {
        router.get(route('search'), { q: query });
    };

    const activeFilterCount = Object.keys(filters).filter(k => k !== 'q' && k !== 'page' && k !== 'sort').length;

    const FilterContent = () => (
        <div className="space-y-8">
            {/* Category Filter */}
            <div className="space-y-4">
                <h3 className="text-sm font-bold text-[var(--color-text-primary)] uppercase tracking-widest">
                    Categories
                </h3>
                <div className="space-y-2">
                    {categories.map((cat) => (
                        <div key={cat.id} className="flex items-center gap-3">
                            <Checkbox
                                id={`cat-${cat.id}`}
                                checked={filters.category === cat.slug}
                                onCheckedChange={(checked) => {
                                    handleFilterChange({ category: checked ? cat.slug : undefined });
                                }}
                            />
                            <label htmlFor={`cat-${cat.id}`} className="text-sm font-medium text-[var(--color-text-secondary)] cursor-pointer">
                                {cat.name}
                            </label>
                        </div>
                    ))}
                </div>
            </div>

            {/* Price Filter */}
            <div className="space-y-4">
                <h3 className="text-sm font-bold text-[var(--color-text-primary)] uppercase tracking-widest">
                    Price Range
                </h3>
                <div className="px-2">
                    <RangeSlider
                        min={0}
                        max={maxPrice}
                        step={100}
                        value={priceRange}
                        onValueChange={setPriceRange}
                        onValueCommit={(val) => handleFilterChange({ min_price: val[0], max_price: val[1] })}
                    />
                    <div className="flex justify-between mt-4">
                        <span className="text-xs font-bold font-mono">৳{priceRange[0]}</span>
                        <span className="text-xs font-bold font-mono">৳{priceRange[1]}</span>
                    </div>
                </div>
            </div>

            {/* Availability */}
            <div className="space-y-4">
                <h3 className="text-sm font-bold text-[var(--color-text-primary)] uppercase tracking-widest">
                    Availability
                </h3>
                <div className="flex items-center gap-3">
                    <Checkbox
                        id="in-stock"
                        checked={filters.in_stock}
                        onCheckedChange={(checked) => handleFilterChange({ in_stock: !!checked })}
                    />
                    <label htmlFor="in-stock" className="text-sm font-medium text-[var(--color-text-secondary)] cursor-pointer">
                        In Stock Only
                    </label>
                </div>
            </div>
        </div>
    );

    return (
        <div className="bg-[var(--color-surface-alt)] min-h-screen">
            <Head title={`Search results for "${query}"`} />

            <div className="container mx-auto px-4 py-8">
                {/* Header */}
                <div className="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-12">
                    <div>
                        <h1 className="text-3xl md:text-4xl font-serif font-bold text-[var(--color-text-primary)] mb-2">
                            Search Results
                        </h1>
                        <p className="text-[var(--color-text-muted)]">
                            Showing {products.total} products for <span className="text-[var(--color-text-primary)] font-bold">"{query}"</span>
                        </p>
                    </div>

                    <div className="flex items-center gap-3">
                        <div className="flex bg-white rounded-xl border border-[var(--color-border)] p-1">
                            <button 
                                onClick={() => setViewMode('grid')}
                                className={`p-2 rounded-lg transition-all ${viewMode === 'grid' ? 'bg-[var(--color-primary-light)] text-[var(--color-primary)]' : 'text-[var(--color-text-muted)] hover:text-[var(--color-text-primary)]'}`}
                            >
                                <Grid size={18} />
                            </button>
                            <button 
                                onClick={() => setViewMode('list')}
                                className={`p-2 rounded-lg transition-all ${viewMode === 'list' ? 'bg-[var(--color-primary-light)] text-[var(--color-primary)]' : 'text-[var(--color-text-muted)] hover:text-[var(--color-text-primary)]'}`}
                            >
                                <List size={18} />
                            </button>
                        </div>

                        <select 
                            className="bg-white border-[var(--color-border)] rounded-xl px-4 py-2.5 text-sm font-bold text-[var(--color-text-primary)] focus:ring-2 focus:ring-[var(--color-primary)]"
                            value={filters.sort || 'relevance'}
                            onChange={(e) => handleFilterChange({ sort: e.target.value })}
                        >
                            <option value="relevance">Relevance</option>
                            <option value="price_asc">Price: Low to High</option>
                            <option value="price_desc">Price: High to Low</option>
                            <option value="newest">Newest Arrivals</option>
                            <option value="rating">Top Rated</option>
                        </select>

                        {!isDesktop && (
                            <Button 
                                variant="outline" 
                                className="rounded-xl font-bold flex gap-2 relative"
                                onClick={() => setIsFilterDrawerOpen(true)}
                            >
                                <SlidersHorizontal size={18} />
                                Filters
                                {activeFilterCount > 0 && (
                                    <span className="absolute -top-1 -right-1 w-5 h-5 rounded-full bg-[var(--color-primary)] text-white text-[10px] flex items-center justify-center border-2 border-[var(--color-surface-alt)]">
                                        {activeFilterCount}
                                    </span>
                                )}
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                    {/* Sidebar Filters */}
                    {isDesktop && (
                        <aside className="lg:col-span-3 bg-white p-8 rounded-3xl border border-[var(--color-border)] shadow-sm sticky top-24">
                            <div className="flex items-center justify-between mb-8">
                                <div className="flex items-center gap-2">
                                    <Filter size={18} className="text-[var(--color-primary)]" />
                                    <h2 className="text-lg font-bold text-[var(--color-text-primary)]">Filters</h2>
                                </div>
                                {activeFilterCount > 0 && (
                                    <button 
                                        onClick={clearFilters}
                                        className="text-xs font-bold text-[var(--color-primary)] hover:underline"
                                    >
                                        Clear All
                                    </button>
                                )}
                            </div>

                            <FilterContent />
                        </aside>
                    )}

                    {/* Main Content */}
                    <div className="lg:col-span-9">
                        {/* Filter Chips */}
                        {activeFilterCount > 0 && (
                            <div className="flex flex-wrap gap-2 mb-6">
                                {filters.category && (
                                    <Badge variant="secondary" className="pl-3 pr-1 py-1 flex items-center gap-1 bg-white border-[var(--color-border)] text-[var(--color-text-primary)] rounded-full">
                                        Category: {filters.category}
                                        <button onClick={() => handleFilterChange({ category: undefined })} className="p-1 hover:bg-[var(--color-surface-alt)] rounded-full">
                                            <X size={12} />
                                        </button>
                                    </Badge>
                                )}
                                {(filters.min_price || filters.max_price) && (
                                    <Badge variant="secondary" className="pl-3 pr-1 py-1 flex items-center gap-1 bg-white border-[var(--color-border)] text-[var(--color-text-primary)] rounded-full">
                                        Price: ৳{filters.min_price || 0} - ৳{filters.max_price || maxPrice}
                                        <button onClick={() => handleFilterChange({ min_price: undefined, max_price: undefined })} className="p-1 hover:bg-[var(--color-surface-alt)] rounded-full">
                                            <X size={12} />
                                        </button>
                                    </Badge>
                                )}
                                {filters.in_stock && (
                                    <Badge variant="secondary" className="pl-3 pr-1 py-1 flex items-center gap-1 bg-white border-[var(--color-border)] text-[var(--color-text-primary)] rounded-full">
                                        In Stock
                                        <button onClick={() => handleFilterChange({ in_stock: undefined })} className="p-1 hover:bg-[var(--color-surface-alt)] rounded-full">
                                            <X size={12} />
                                        </button>
                                    </Badge>
                                )}
                            </div>
                        )}

                        {products.data.length > 0 ? (
                            <div className={viewMode === 'grid' ? 'grid grid-cols-2 md:grid-cols-3 gap-6' : 'space-y-6'}>
                                {products.data.map((product) => (
                                    <ProductCard 
                                        key={product.id} 
                                        product={product} 
                                        view={viewMode}
                                    />
                                ))}
                            </div>
                        ) : (
                            <div className="bg-white rounded-3xl p-20 border border-[var(--color-border)]">
                                <EmptyState
                                    icon={SearchIcon}
                                    title="No results found"
                                    description={`We couldn't find anything matching "${query}". Try adjusting your search or browse all products.`}
                                    action={{
                                        label: "Browse all products",
                                        href: route('products.index'),
                                    }}
                                />
                            </div>
                        )}

                        {/* Pagination would go here */}
                    </div>
                </div>
            </div>

            {/* Mobile Filter Drawer */}
            <Drawer 
                isOpen={isFilterDrawerOpen} 
                onClose={() => setIsFilterDrawerOpen(false)}
                title="Filter Products"
                side="right"
                size="md"
            >
                <div className="p-6 h-full flex flex-col">
                    <div className="flex-1 overflow-y-auto">
                        <FilterContent />
                    </div>
                    <div className="pt-6 border-t border-[var(--color-border)] grid grid-cols-2 gap-4">
                        <Button variant="outline" onClick={clearFilters}>Clear All</Button>
                        <Button onClick={() => setIsFilterDrawerOpen(false)}>Apply Filters</Button>
                    </div>
                </div>
            </Drawer>
        </div>
    );
}
