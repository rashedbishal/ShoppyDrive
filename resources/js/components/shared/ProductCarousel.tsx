import { ChevronLeft, ChevronRight } from 'lucide-react';
import React, { useCallback, useEffect, useRef, useState } from 'react';

import { ProductCard } from '@/components/shared/ProductCard';
import { Button } from '@/components/ui/button';
import type { Product } from '@/types';

interface ProductCarouselProps {
    title?: string;
    products: Product[];
    className?: string;
}

export function ProductCarousel({
    title,
    products,
    className,
}: ProductCarouselProps): React.JSX.Element {
    const scrollContainerRef = useRef<HTMLDivElement>(null);
    const [canScrollLeft, setCanScrollLeft] = useState(false);
    const [canScrollRight, setCanScrollRight] = useState(false);

    const checkScroll = useCallback(() => {
        const container = scrollContainerRef.current;
        if (container) {
            setCanScrollLeft(container.scrollLeft > 0);
            setCanScrollRight(
                container.scrollLeft < container.scrollWidth - container.clientWidth - 10
            );
        }
    }, []);

    useEffect(() => {
        checkScroll();
        window.addEventListener('resize', checkScroll);
        return () => window.removeEventListener('resize', checkScroll);
    }, [checkScroll, products]);

    const scroll = (direction: 'left' | 'right') => {
        const container = scrollContainerRef.current;
        if (container) {
            const scrollAmount = container.clientWidth * 0.8;
            container.scrollBy({
                left: direction === 'left' ? -scrollAmount : scrollAmount,
                behavior: 'smooth',
            });
        }
    };

    if (products.length === 0) return <></>;

    return (
        <section className={`py-12 ${className}`}>
            <div className="flex items-center justify-between mb-8">
                {title && (
                    <h2 className="text-2xl md:text-3xl font-serif font-bold text-[var(--color-text-primary)]">
                        {title}
                    </h2>
                )}
                
                <div className="flex gap-2">
                    <Button
                        variant="outline"
                        size="icon"
                        onClick={() => scroll('left')}
                        disabled={!canScrollLeft}
                        className="rounded-full h-10 w-10 border-[var(--color-border)] disabled:opacity-30"
                        aria-label="Scroll left"
                    >
                        <ChevronLeft size={20} />
                    </Button>
                    <Button
                        variant="outline"
                        size="icon"
                        onClick={() => scroll('right')}
                        disabled={!canScrollRight}
                        className="rounded-full h-10 w-10 border-[var(--color-border)] disabled:opacity-30"
                        aria-label="Scroll right"
                    >
                        <ChevronRight size={20} />
                    </Button>
                </div>
            </div>

            <div 
                ref={scrollContainerRef}
                onScroll={checkScroll}
                className="flex gap-4 md:gap-6 overflow-x-auto scrollbar-hide snap-x snap-mandatory pb-4 -mx-4 px-4 sm:mx-0 sm:px-0"
                style={{ scrollbarWidth: 'none', msOverflowStyle: 'none' }}
            >
                {products.map((product) => (
                    <div 
                        key={product.id} 
                        className="min-w-[280px] sm:min-w-[300px] md:min-w-[320px] lg:min-w-[280px] snap-start"
                    >
                        <ProductCard product={product} />
                    </div>
                ))}
            </div>
        </section>
    );
}
