import * as React from "react";
import { type TextareaHTMLAttributes, useId, type ReactNode } from "react";

import { CharCount } from "@/components/ui/char-count";
import { Label } from "@/components/ui/label";
import { cn } from "@/lib/utils";

type TextareaProps = TextareaHTMLAttributes<HTMLTextAreaElement> & {
    label?: string;
    hint?: string;
    error?: string;
    required?: boolean;
    containerClassName?: string;
    labelClassName?: string;
    textareaClassName?: string;
    /** Shows a live "length/maxLength" counter under the field. Needs `maxLength` and a controlled `value`. */
    showCount?: boolean;
};

function Textarea({
    className,
    id,
    label,
    hint,
    error,
    required = false,
    containerClassName,
    labelClassName,
    textareaClassName,
    rows = 5,
    showCount = false,
    ...props
}: TextareaProps) {
    const generatedId = useId();
    const textareaId = id ?? generatedId;
    const hintId = hint ? `${textareaId}-hint` : undefined;
    const errorId = error ? `${textareaId}-error` : undefined;
    const describedBy = [hintId, errorId].filter(Boolean).join(" ") || undefined;

    return (
        <div className={cn("space-y-1.5", containerClassName)}>
            {label ? (
                <Label
                    htmlFor={textareaId}
                    className={cn("text-sm font-medium text-text-primary", labelClassName)}
                >
                    {label}
                    {required ? <span className="ml-1 text-destructive">*</span> : null}
                </Label>
            ) : null}
            <textarea
                id={textareaId}
                rows={rows}
                aria-invalid={Boolean(error) || undefined}
                aria-describedby={describedBy}
                className={cn(
                    "flex min-h-32 w-full rounded-lg border border-border bg-surface px-4 py-3 text-sm text-text-primary shadow-sm transition-all placeholder:text-text-muted focus-visible:border-transparent focus-visible:ring-2 focus-visible:ring-primary disabled:cursor-not-allowed disabled:opacity-60",
                    error ? "border-destructive focus-visible:ring-destructive" : "",
                    className,
                    textareaClassName,
                )}
                {...props}
            />
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
}

export { Textarea };

