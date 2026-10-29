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
                container.scrollLeft <
                    container.scrollWidth - container.clientWidth - 10,
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

    if (products.length === 0) {
        return <></>;
    }

    return (
        <section className={`py-12 ${className}`}>
            <div className="mb-8 flex items-center justify-between">
                {title && (
                    <h2 className="font-serif text-2xl font-bold text-[var(--color-text-primary)] md:text-3xl">
                        {title}
                    </h2>
                )}

                <div className="flex gap-2">
                    <Button
                        variant="outline"
                        size="icon"
                        onClick={() => scroll('left')}
                        disabled={!canScrollLeft}
                        className="h-10 w-10 rounded-full border-[var(--color-border)] disabled:opacity-30"
                        aria-label="Scroll left"
                    >
                        <ChevronLeft size={20} />
                    </Button>
                    <Button
                        variant="outline"
                        size="icon"
                        onClick={() => scroll('right')}
                        disabled={!canScrollRight}
                        className="h-10 w-10 rounded-full border-[var(--color-border)] disabled:opacity-30"
                        aria-label="Scroll right"
                    >
                        <ChevronRight size={20} />
                    </Button>
                </div>
            </div>

            <div
                ref={scrollContainerRef}
                onScroll={checkScroll}
                className="scrollbar-hide flex snap-x snap-mandatory gap-3 overflow-x-auto pb-4 md:gap-4"
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
