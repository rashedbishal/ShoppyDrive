import { CheckCircle2, Star, ThumbsDown, ThumbsUp } from 'lucide-react';
import React from 'react';

import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { formatDate, getInitials } from '@/lib/utils';
import type { Review } from '@/types';

interface ReviewCardProps {
    review: Review;
    onVote?: (reviewId: number, type: 'helpful' | 'unhelpful') => void;
}

export function ReviewCard({
    review,
    onVote,
}: ReviewCardProps): React.JSX.Element {
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

            <p className="mb-4 text-sm leading-relaxed text-[var(--color-text-secondary)]">
                {review.body}
            </p>

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

            <div className="flex items-center gap-6">
                <span className="text-xs font-medium text-[var(--color-text-muted)]">
                    Was this review helpful?
                </span>
                <div className="flex items-center gap-4">
                    <button
                        onClick={() => onVote?.(review.id, 'helpful')}
                        className={`flex items-center gap-1.5 text-xs font-bold transition-colors ${
                            review.user_voted === true
                                ? 'text-[var(--color-primary)]'
                                : 'text-[var(--color-text-muted)] hover:text-[var(--color-text-primary)]'
                        }`}
                    >
                        <ThumbsUp
                            size={14}
                            className={
                                review.user_voted === true ? 'fill-current' : ''
                            }
                        />
                        Helpful ({review.helpful_count})
                    </button>
                    <button
                        onClick={() => onVote?.(review.id, 'unhelpful')}
                        className={`flex items-center gap-1.5 text-xs font-bold transition-colors ${
                            review.user_voted === false
                                ? 'text-[var(--color-error)]'
                                : 'text-[var(--color-text-muted)] hover:text-[var(--color-error)]'
                        }`}
                    >
                        <ThumbsDown
                            size={14}
                            className={
                                review.user_voted === false
                                    ? 'fill-current'
                                    : ''
                            }
                        />
                        Not Helpful ({review.not_helpful_count})
                    </button>
                </div>
            </div>
        </div>
    );
}
