import { Star } from 'lucide-react';
import React from 'react';

interface RatingBreakdownProps {
    breakdown: Record<number, number>;
    className?: string;
}

export function RatingBreakdown({ breakdown, className }: RatingBreakdownProps): React.JSX.Element {
    const ratings = [5, 4, 3, 2, 1] as const;
    const total = Object.values(breakdown).reduce((s, c) => s + c, 0);
    const average = total > 0
        ? Object.entries(breakdown).reduce((s, [star, count]) => s + Number(star) * count, 0) / total
        : 0;

    return (
        <div className={`flex flex-col items-center gap-8 md:flex-row ${className ?? ''}`}>
            {/* Average score */}
            <div className="flex min-w-[140px] flex-col items-center justify-center rounded-2xl bg-[var(--color-surface-alt)] p-6">
                <span className="mb-2 text-5xl font-bold text-[var(--color-text-primary)]">
                    {average.toFixed(1)}
                </span>
                <div className="mb-2 flex items-center gap-1">
                    {[1, 2, 3, 4, 5].map((i) => (
                        <Star
                            key={i}
                            size={16}
                            className={i <= Math.round(average)
                                ? 'fill-[var(--color-accent)] text-[var(--color-accent)]'
                                : 'text-[var(--color-border)]'}
                        />
                    ))}
                </div>
                <span className="text-sm font-medium text-[var(--color-text-muted)]">{total} Reviews</span>
            </div>

            {/* Bars */}
            <div className="w-full flex-1 space-y-3">
                {ratings.map((star) => {
                    const count = breakdown[star] ?? 0;
                    const pct = total > 0 ? (count / total) * 100 : 0;
                    return (
                        <div key={star} className="flex items-center gap-4">
                            <div className="flex w-8 items-center gap-1">
                                <span className="text-sm font-bold text-[var(--color-text-secondary)]">{star}</span>
                                <Star size={11} className="fill-[var(--color-text-muted)] text-[var(--color-text-muted)]" />
                            </div>
                            <div className="h-2 flex-1 overflow-hidden rounded-full bg-[var(--color-border)]">
                                <div
                                    className="h-full bg-[var(--color-accent)] transition-all duration-500"
                                    style={{ width: `${pct}%` }}
                                />
                            </div>
                            <span className="w-8 text-right text-xs font-medium text-[var(--color-text-muted)]">{count}</span>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
