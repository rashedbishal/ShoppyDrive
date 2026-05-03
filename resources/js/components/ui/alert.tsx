import { cva, type VariantProps } from "class-variance-authority";
import * as React from "react";

import { cn } from "@/lib/utils";

const alertVariants = cva(
    "relative w-full rounded-2xl border px-4 py-3 text-sm shadow-sm",
    {
        variants: {
            variant: {
                default: "border-border bg-surface text-text-primary",
                success: "border-success/20 bg-success/10 text-success",
                warning: "border-warning/20 bg-warning/10 text-warning",
                error: "border-destructive/20 bg-destructive/10 text-destructive",
                info: "border-secondary/20 bg-secondary/5 text-secondary",
                destructive: "border-destructive/20 bg-destructive/10 text-destructive",
            },
        },
        defaultVariants: {
            variant: "default",
        },
    },
);

function Alert({
    className,
    variant,
    ...props
}: React.ComponentProps<"div"> & VariantProps<typeof alertVariants>) {
    return (
        <div
            data-slot="alert"
            role="alert"
            className={cn(alertVariants({ variant }), className)}
            {...props}
        />
    );
}

function AlertTitle({ className, ...props }: React.ComponentProps<"div">) {
    return (
        <div
            data-slot="alert-title"
            className={cn("font-semibold tracking-tight", className)}
            {...props}
        />
    );
}

function AlertDescription({
    className,
    ...props
}: React.ComponentProps<"div">) {
    return (
        <div
            data-slot="alert-description"
            className={cn("mt-1 text-sm leading-relaxed opacity-90", className)}
            {...props}
        />
    );
}

export { Alert, AlertTitle, AlertDescription };
