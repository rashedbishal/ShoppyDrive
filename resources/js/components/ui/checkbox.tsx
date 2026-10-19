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
    /** Called with the new checked state, as an alternative to `onChange`. */
    onCheckedChange?: (checked: boolean) => void;
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
    onChange,
    onCheckedChange,
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
            onChange={(event) => {
                onChange?.(event);
                onCheckedChange?.(event.target.checked);
            }}
            // Box, tick and states come from the shared themed control styles in app.css.
            className={cn("shadow-sm", className, checkboxClassName)}
            {...props}
        />
    );

    if (!label && !hint && !error) {
        return checkbox;
    }

    return (
        <div className={cn("space-y-1.5", containerClassName)}>
            <label htmlFor={checkboxId} className="flex cursor-pointer items-start gap-3">
                <span className="mt-0.5 flex">{checkbox}</span>
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
