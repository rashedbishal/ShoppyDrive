import * as React from "react";
import { forwardRef, type InputHTMLAttributes, useId, type ReactNode } from "react";

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
                    <span className="pointer-events-none absolute inset-y-0 left-4 flex items-center text-text-muted">
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
                    <span className="pointer-events-none absolute inset-y-0 right-4 flex items-center text-text-muted">
                        {rightIcon}
                    </span>
                ) : null}
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
});

export { Input };
