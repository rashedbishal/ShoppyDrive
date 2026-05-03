import { Check } from "lucide-react";
import { type InputHTMLAttributes, useId, type ReactNode } from "react";

import { Label } from "@/components/ui/label";
import { cn } from "@/lib/utils";

type CheckboxProps = Omit<InputHTMLAttributes<HTMLInputElement>, "type"> & {
    label?: string;
    hint?: string;
    error?: string;
    required?: boolean;
    containerClassName?: string;
    labelClassName?: string;
    checkboxClassName?: string;
};

function Checkbox({
    className,
    id,
    label,
    hint,
    error,
    required = false,
    containerClassName,
    labelClassName,
    checkboxClassName,
    ...props
}: CheckboxProps) {
    const generatedId = useId();
    const checkboxId = id ?? generatedId;
    const hintId = hint ? `${checkboxId}-hint` : undefined;
    const errorId = error ? `${checkboxId}-error` : undefined;
    const describedBy = [hintId, errorId].filter(Boolean).join(" ") || undefined;

    const checkbox = (
        <input
            id={checkboxId}
            type="checkbox"
            aria-invalid={Boolean(error) || undefined}
            aria-describedby={describedBy}
            className={cn(
                "peer size-4 shrink-0 rounded-[4px] border border-border bg-surface text-primary shadow-sm transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary disabled:cursor-not-allowed disabled:opacity-60 data-[checked=true]:bg-primary",
                className,
                checkboxClassName,
            )}
            {...props}
        />
    );

    if (!label && !hint && !error) {
        return checkbox;
    }

    return (
        <div className={cn("space-y-1.5", containerClassName)}>
            <label htmlFor={checkboxId} className="flex cursor-pointer items-start gap-3">
                <span className="relative mt-0.5">
                    {checkbox}
                    <span className="pointer-events-none absolute inset-0 flex items-center justify-center text-white opacity-0 peer-checked:opacity-100">
                        <Check className="size-3.5" />
                    </span>
                </span>
                <span className="space-y-1">
                    <span
                        className={cn(
                            "block text-sm font-medium text-text-primary",
                            labelClassName,
                        )}
                    >
                        {label}
                        {required ? <span className="ml-1 text-destructive">*</span> : null}
                    </span>
                    {hint ? <span className="block text-xs text-text-secondary">{hint}</span> : null}
                    {error ? <span className="block text-xs text-destructive">{error}</span> : null}
                </span>
            </label>
        </div>
    );
}

export { Checkbox };
