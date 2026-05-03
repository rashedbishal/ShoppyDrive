import { ChevronDown } from "lucide-react";
import { type ReactNode, type SelectHTMLAttributes, useId } from "react";

import { Label } from "@/components/ui/label";
import { cn } from "@/lib/utils";

type SelectOption = {
    label: string;
    value: string;
    disabled?: boolean;
};

type SelectProps = SelectHTMLAttributes<HTMLSelectElement> & {
    label?: string;
    hint?: string;
    error?: string;
    options?: SelectOption[];
    required?: boolean;
    containerClassName?: string;
    labelClassName?: string;
    selectClassName?: string;
    children?: ReactNode;
};

function Select({
    className,
    id,
    label,
    hint,
    error,
    options = [],
    required = false,
    containerClassName,
    labelClassName,
    selectClassName,
    children,
    ...props
}: SelectProps) {
    const generatedId = useId();
    const selectId = id ?? generatedId;
    const hintId = hint ? `${selectId}-hint` : undefined;
    const errorId = error ? `${selectId}-error` : undefined;
    const describedBy = [hintId, errorId].filter(Boolean).join(" ") || undefined;

    return (
        <div className={cn("space-y-1.5", containerClassName)}>
            {label ? (
                <Label
                    htmlFor={selectId}
                    className={cn("text-sm font-medium text-text-primary", labelClassName)}
                >
                    {label}
                    {required ? <span className="ml-1 text-destructive">*</span> : null}
                </Label>
            ) : null}
            <div className="relative">
                <select
                    id={selectId}
                    aria-invalid={Boolean(error) || undefined}
                    aria-describedby={describedBy}
                    className={cn(
                        "flex h-11 w-full appearance-none rounded-lg border border-border bg-surface px-4 py-3 pr-11 text-sm text-text-primary shadow-sm transition-all focus-visible:border-transparent focus-visible:ring-2 focus-visible:ring-primary disabled:cursor-not-allowed disabled:opacity-60",
                        error ? "border-destructive focus-visible:ring-destructive" : "",
                        className,
                        selectClassName,
                    )}
                    {...props}
                >
                    {children}
                    {options.map((option) => (
                        <option key={option.value} value={option.value} disabled={option.disabled}>
                            {option.label}
                        </option>
                    ))}
                </select>
                <ChevronDown className="pointer-events-none absolute inset-y-0 right-4 my-auto size-4 text-text-muted" />
            </div>
            {hint ? (
                <p id={hintId} className="text-xs text-text-secondary">
                    {hint}
                </p>
            ) : null}
            {error ? (
                <p id={errorId} className="text-xs text-destructive">
                    {error}
                </p>
            ) : null}
        </div>
    );
}

export { Select };
