import { CheckCircle2, Star } from 'lucide-react';
import React, { useEffect, useRef, useState } from 'react';

import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { cn, formatDate, getInitials } from '@/lib/utils';
import type { Review } from '@/types';

interface ReviewCardProps {
    review: Review;
}

export function ReviewCard({ review }: ReviewCardProps): React.JSX.Element {
    const bodyRef = useRef<HTMLParagraphElement>(null);
    const [isExpanded, setIsExpanded] = useState(false);
    const [isLong, setIsLong] = useState(false);

    // Measured rather than counted in characters: the same review fits on a
    // desktop but overflows on a phone, and the tab it sits in can be hidden.
    useEffect(() => {
        const body = bodyRef.current;

        if (!body || isExpanded) {
            return;
        }

        const measure = () =>
            setIsLong(body.scrollHeight > body.clientHeight + 1);

        measure();

        const observer = new ResizeObserver(measure);
        observer.observe(body);

        return () => observer.disconnect();
    }, [isExpanded, review.body]);

    return (
        <div className="border-b border-[var(--color-border)] py-8 last:border-0">
            <div className="mb-4 flex items-start gap-4">
                <Avatar className="h-12 w-12 border-2 border-white shadow-sm">
                    <AvatarImage
                        src={review.user.avatar || undefined}
                        alt={review.user.name}
                    />
                    <AvatarFallback className="bg-[var(--color-primary-light)] font-bold text-[var(--color-primary)]">
                        {getInitials(review.user.name)}
                    </AvatarFallback>
                </Avatar>

                <div className="min-w-0 flex-1">
                    <div className="mb-1 flex flex-wrap items-center justify-between gap-2">
                        <h4 className="truncate font-bold text-[var(--color-text-primary)]">
                            {review.user.name}
                        </h4>
                        <span className="text-xs text-[var(--color-text-muted)]">
                            {formatDate(review.created_at)}
                        </span>
                    </div>

                    <div className="flex items-center gap-3">
                        <div className="flex items-center gap-0.5">
                            {[...Array(5)].map((_, i) => (
                                <Star
                                    key={i}
                                    size={14}
                                    className={
                                        i < review.rating
                                            ? 'fill-[var(--color-accent)] text-[var(--color-accent)]'
                                            : 'text-[var(--color-border)]'
                                    }
                                />
                            ))}
                        </div>
                        <div className="flex items-center gap-1 text-xs font-bold text-[var(--color-success)]">
                            <CheckCircle2 size={12} />
                            Verified Purchase
                        </div>
                    </div>
                </div>
            </div>

            {review.title && (
                <h5 className="mb-2 font-bold text-[var(--color-text-primary)]">
                    {review.title}
                </h5>
            )}

            <div className="mb-4">
                <p
                    ref={bodyRef}
                    className={cn(
                        'text-sm leading-relaxed break-words text-[var(--color-text-secondary)]',
                        !isExpanded && 'line-clamp-4',
                    )}
                >
                    {review.body}
                </p>
                {isLong && (
                    <button
                        type="button"
                        aria-expanded={isExpanded}
                        onClick={() => setIsExpanded((expanded) => !expanded)}
                        className="mt-1 text-sm font-bold text-[var(--color-primary)] hover:underline"
                    >
                        {isExpanded ? 'Show less' : 'Read more'}
                    </button>
                )}
            </div>

            {review.images && review.images.length > 0 && (
                <div className="mb-6 flex flex-wrap gap-2">
                    {review.images.map((image, index) => (
                        <button
                            key={index}
                            className="relative h-20 w-20 overflow-hidden rounded-lg border border-[var(--color-border)] transition-opacity hover:opacity-80"
                        >
                            <img
                                src={image}
                                alt={`Review image ${index + 1}`}
                                className="h-full w-full object-cover"
                            />
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}
