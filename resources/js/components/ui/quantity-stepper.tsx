import { Minus, Plus } from "lucide-react";

import { cn } from "@/lib/utils";

type QuantityStepperProps = {
    value: number;
    min?: number;
    max?: number;
    disabled?: boolean;
    onChange: (value: number) => void;
    className?: string;
};

function QuantityStepper({
    value,
    min = 1,
    max = Number.POSITIVE_INFINITY,
    disabled = false,
    onChange,
    className,
}: QuantityStepperProps) {
    const canDecrement = !disabled && value > min;
    const canIncrement = !disabled && value < max;

    return (
        <div
            className={cn(
                "inline-flex h-11 items-center overflow-hidden rounded-xl border border-border bg-surface shadow-sm",
                className,
            )}
        >
            <button
                type="button"
                onClick={() => onChange(Math.max(min, value - 1))}
                disabled={!canDecrement}
                aria-label="Decrease quantity"
                className="flex size-11 items-center justify-center border-r border-border text-text-primary transition-colors hover:bg-surface-alt disabled:cursor-not-allowed disabled:opacity-40"
            >
                <Minus className="size-4" />
            </button>
            <span className="min-w-12 px-4 text-center text-sm font-semibold text-text-primary">
                {value}
            </span>
            <button
                type="button"
                onClick={() => onChange(Math.min(max, value + 1))}
                disabled={!canIncrement}
                aria-label="Increase quantity"
                className="flex size-11 items-center justify-center border-l border-border text-text-primary transition-colors hover:bg-surface-alt disabled:cursor-not-allowed disabled:opacity-40"
            >
                <Plus className="size-4" />
            </button>
        </div>
    );
}

export { QuantityStepper };

