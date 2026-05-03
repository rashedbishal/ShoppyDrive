import { Star, StarHalf } from "lucide-react";
import { useMemo } from "react";

import { cn } from "@/lib/utils";

type StarRatingProps = {
    rating: number;
    interactive?: boolean;
    size?: "sm" | "md" | "lg";
    onChange?: (rating: number) => void;
    max?: number;
    className?: string;
    showValue?: boolean;
};

function StarRating({
    rating,
    interactive = false,
    size = "md",
    onChange,
    max = 5,
    className,
    showValue = false,
}: StarRatingProps) {
    const stars = useMemo(() => Array.from({ length: max }, (_, index) => index + 1), [max]);
    const sizeClassName = size === "sm" ? "size-4" : size === "lg" ? "size-6" : "size-5";

    return (
        <div className={cn("inline-flex items-center gap-1", className)}>
            {stars.map((star) => {
                const isFull = rating >= star;
                const isHalf = !isFull && rating >= star - 0.5;

                const Icon = isHalf ? StarHalf : Star;
                const iconClassName = cn(
                    sizeClassName,
                    isFull || isHalf ? "fill-accent text-accent" : "text-border",
                );

                if (!interactive) {
                    return <Icon key={star} className={iconClassName} />;
                }

                return (
                    <button
                        key={star}
                        type="button"
                        onClick={() => onChange?.(star)}
                        className="rounded-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                        aria-label={`Set rating to ${star} star${star > 1 ? "s" : ""}`}
                    >
                        <Icon className={cn(sizeClassName, "transition-colors", iconClassName)} />
                    </button>
                );
            })}
            {showValue ? (
                <span className="ml-1 text-sm font-medium text-text-secondary">
                    {rating.toFixed(1)}
                </span>
            ) : null}
        </div>
    );
}

export { StarRating };

