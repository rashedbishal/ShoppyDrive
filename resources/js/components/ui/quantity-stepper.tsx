import { Minus, Plus } from "lucide-react";

import { cn } from "@/lib/utils";

type QuantityStepperProps = {
    value: number;
    min?: number;
    max?: number;
    disabled?: boolean;
    onChange: (value: number) => void;
    className?: string;
    /** "sm" is the compact 32px stepper (cart side panel); the default stays 44px. */
    size?: "md" | "sm";
};

function QuantityStepper({
    value,
    min = 1,
    max = Number.POSITIVE_INFINITY,
    disabled = false,
    onChange,
    className,
    size = "md",
}: QuantityStepperProps) {
    const isSmall = size === "sm";
    const buttonSize = isSmall ? "size-8" : "size-11";
    const canDecrement = !disabled && value > min;
    const canIncrement = !disabled && value < max;

    return (
        <div
            className={cn(
                "inline-flex items-center overflow-hidden border border-border bg-surface shadow-sm",
                isSmall ? "h-8 rounded-lg" : "h-11 rounded-xl",
                className,
            )}
        >
            <button
                type="button"
                onClick={() => onChange(Math.max(min, value - 1))}
                disabled={!canDecrement}
                aria-label="Decrease quantity"
                className={cn(
                    "flex items-center justify-center border-r border-border text-text-primary transition-colors hover:bg-surface-alt disabled:cursor-not-allowed disabled:opacity-40",
                    buttonSize,
                )}
            >
                <Minus className={isSmall ? "size-3.5" : "size-4"} />
            </button>
            <span
                className={cn(
                    "text-center font-semibold text-text-primary",
                    isSmall ? "min-w-8 px-2 text-xs" : "min-w-12 px-4 text-sm",
                )}
            >
                {value}
            </span>
            <button
                type="button"
                onClick={() => onChange(Math.min(max, value + 1))}
                disabled={!canIncrement}
                aria-label="Increase quantity"
                className={cn(
                    "flex items-center justify-center border-l border-border text-text-primary transition-colors hover:bg-surface-alt disabled:cursor-not-allowed disabled:opacity-40",
                    buttonSize,
                )}
            >
                <Plus className={isSmall ? "size-3.5" : "size-4"} />
            </button>
        </div>
    );
}

export { QuantityStepper };

