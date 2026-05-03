import { type ChangeEvent, useMemo } from "react";

import { cn } from "@/lib/utils";

type RangeSliderProps = {
    min: number;
    max: number;
    value: [number, number];
    onChange: (value: [number, number]) => void;
    step?: number;
    label?: string;
    hint?: string;
    error?: string;
    className?: string;
};

function RangeSlider({
    min,
    max,
    value,
    onChange,
    step = 1,
    label,
    hint,
    error,
    className,
}: RangeSliderProps) {
    const [lower, upper] = value;
    const denominator = max - min;
    const lowerPercent = useMemo(
        () => (denominator <= 0 ? 0 : ((lower - min) / denominator) * 100),
        [denominator, lower, max, min],
    );
    const upperPercent = useMemo(
        () => (denominator <= 0 ? 100 : ((upper - min) / denominator) * 100),
        [denominator, max, min, upper],
    );

    const handleLowerChange = (event: ChangeEvent<HTMLInputElement>): void => {
        const nextLower = Math.min(Number(event.target.value), upper);
        onChange([nextLower, upper]);
    };

    const handleUpperChange = (event: ChangeEvent<HTMLInputElement>): void => {
        const nextUpper = Math.max(Number(event.target.value), lower);
        onChange([lower, nextUpper]);
    };

    return (
        <div className={cn("space-y-2", className)}>
            {label ? <p className="text-sm font-medium text-text-primary">{label}</p> : null}
            <div className="space-y-4">
                <div className="relative pt-6">
                    <div className="absolute top-8 left-0 right-0 h-1 rounded-full bg-surface-alt">
                        <div
                            className="absolute h-1 rounded-full bg-primary"
                            style={{
                                left: `${lowerPercent}%`,
                                width: `${upperPercent - lowerPercent}%`,
                            }}
                        />
                    </div>
                    <div className="relative h-8">
                        <input
                            type="range"
                            min={min}
                            max={max}
                            step={step}
                            value={lower}
                            onChange={handleLowerChange}
                            aria-label="Minimum value"
                            className={cn(
                                "absolute inset-x-0 top-0 z-20 h-8 w-full appearance-none bg-transparent",
                                "[&::-webkit-slider-thumb]:pointer-events-auto [&::-webkit-slider-thumb]:size-4 [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:border-2 [&::-webkit-slider-thumb]:border-primary [&::-webkit-slider-thumb]:bg-surface [&::-webkit-slider-thumb]:shadow-sm",
                                "[&::-moz-range-thumb]:pointer-events-auto [&::-moz-range-thumb]:size-4 [&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:border-2 [&::-moz-range-thumb]:border-primary [&::-moz-range-thumb]:bg-surface [&::-moz-range-thumb]:shadow-sm",
                                "[&::-webkit-slider-runnable-track]:h-1 [&::-webkit-slider-runnable-track]:rounded-full [&::-webkit-slider-runnable-track]:bg-transparent",
                                "[&::-moz-range-track]:h-1 [&::-moz-range-track]:rounded-full [&::-moz-range-track]:bg-transparent",
                            )}
                        />
                        <input
                            type="range"
                            min={min}
                            max={max}
                            step={step}
                            value={upper}
                            onChange={handleUpperChange}
                            aria-label="Maximum value"
                            className={cn(
                                "absolute inset-x-0 top-0 z-10 h-8 w-full appearance-none bg-transparent",
                                "[&::-webkit-slider-thumb]:pointer-events-auto [&::-webkit-slider-thumb]:size-4 [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:border-2 [&::-webkit-slider-thumb]:border-primary [&::-webkit-slider-thumb]:bg-surface [&::-webkit-slider-thumb]:shadow-sm",
                                "[&::-moz-range-thumb]:pointer-events-auto [&::-moz-range-thumb]:size-4 [&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:border-2 [&::-moz-range-thumb]:border-primary [&::-moz-range-thumb]:bg-surface [&::-moz-range-thumb]:shadow-sm",
                                "[&::-webkit-slider-runnable-track]:h-1 [&::-webkit-slider-runnable-track]:rounded-full [&::-webkit-slider-runnable-track]:bg-transparent",
                                "[&::-moz-range-track]:h-1 [&::-moz-range-track]:rounded-full [&::-moz-range-track]:bg-transparent",
                            )}
                        />
                    </div>
                </div>
                <div className="flex items-center justify-between text-sm font-medium text-text-primary">
                    <span>৳{lower.toLocaleString("en-BD")}</span>
                    <span>৳{upper.toLocaleString("en-BD")}</span>
                </div>
            </div>
            {hint ? <p className="text-xs text-text-secondary">{hint}</p> : null}
            {error ? <p className="text-xs text-destructive">{error}</p> : null}
        </div>
    );
}

export { RangeSlider };
