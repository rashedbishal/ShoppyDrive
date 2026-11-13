import * as React from "react";
import { forwardRef, type InputHTMLAttributes, useId, type ReactNode } from "react";

import { CharCount } from "@/components/ui/char-count";
import { Label } from "@/components/ui/label";
import { cn } from "@/lib/utils";

type InputProps = InputHTMLAttributes<HTMLInputElement> & {
    label?: string;
    hint?: string;
    error?: string;
    leftIcon?: ReactNode;
    rightIcon?: ReactNode;
    required?: boolean;
    containerClassName?: string;
    labelClassName?: string;
    inputClassName?: string;
    /** Shows a live "length/maxLength" counter under the field. Needs `maxLength` and a controlled `value`. */
    showCount?: boolean;
};

const inputBaseClassName =
    "flex h-11 w-full min-w-0 rounded-lg border border-border bg-surface px-4 py-3 text-sm text-text-primary shadow-sm transition-all placeholder:text-text-muted focus-visible:border-transparent focus-visible:ring-2 focus-visible:ring-primary disabled:cursor-not-allowed disabled:opacity-60";

const Input = forwardRef<HTMLInputElement, InputProps>(function Input(
{
    className,
    type = "text",
    id,
    label,
    hint,
    error,
    leftIcon,
    rightIcon,
    required = false,
    containerClassName,
    labelClassName,
    inputClassName,
    showCount = false,
    ...props
}: InputProps,
ref,
) {
    const generatedId = useId();
    const inputId = id ?? generatedId;
    const hintId = hint ? `${inputId}-hint` : undefined;
    const errorId = error ? `${inputId}-error` : undefined;
    const describedBy = [hintId, errorId].filter(Boolean).join(" ") || undefined;

    return (
        <div className={cn("space-y-1.5", containerClassName)}>
            {label ? (
                <Label
                    htmlFor={inputId}
                    className={cn("text-sm font-medium text-text-primary", labelClassName)}
                >
                    {label}
                    {required ? <span className="ml-1 text-destructive">*</span> : null}
                </Label>
            ) : null}
            <div className="relative">
                {leftIcon ? (
                    <span className="pointer-events-none absolute inset-y-0 left-4 flex items-center text-text-secondary">
                        {leftIcon}
                    </span>
                ) : null}
                <input
                    ref={ref}
                    id={inputId}
                    type={type}
                    data-slot="input"
                    aria-invalid={Boolean(error) || undefined}
                    aria-describedby={describedBy}
                    className={cn(
                        inputBaseClassName,
                        leftIcon ? "pl-11" : "",
                        rightIcon ? "pr-11" : "",
                        error ? "border-destructive focus-visible:ring-destructive" : "",
                        className,
                        inputClassName,
                    )}
                    {...props}
                />
                {rightIcon ? (
                    <span className="pointer-events-none absolute inset-y-0 right-4 flex items-center text-text-secondary">
                        {rightIcon}
                    </span>
                ) : null}
            </div>
            {hint || (showCount && props.maxLength) ? (
                <div className="flex items-start justify-between gap-3">
                    {hint ? (
                        <p id={hintId} className="text-xs text-text-secondary">
                            {hint}
                        </p>
                    ) : (
                        <span />
                    )}
                    {showCount && props.maxLength ? (
                        <CharCount value={props.value as string} max={props.maxLength} />
                    ) : null}
                </div>
            ) : null}
            {error ? (
                <p id={errorId} className="text-xs text-destructive">
                    {error}
                </p>
            ) : null}
        </div>
    );
});

export { Input };
