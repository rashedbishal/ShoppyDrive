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
                className="flex gap-3 md:gap-4 overflow-x-auto scrollbar-hide snap-x snap-mandatory pb-4"
                style={{ scrollbarWidth: 'none', msOverflowStyle: 'none' }}
            >
                {/* 2 cards per view on mobile, 3 on tablet, 4 on desktop — same sizing as other product rows */}
                {products.map((product, index) => (
                    <div
                        key={product.id}
                        className="w-[calc((100%-0.75rem)/2)] shrink-0 snap-start sm:w-[calc((100%-1.5rem)/3)] md:w-[calc((100%-2rem)/3)] lg:w-[calc((100%-3rem)/4)]"
                    >
                        <ProductCard product={product} index={index} />
                    </div>
                ))}
            </div>
        </section>
    );
}
