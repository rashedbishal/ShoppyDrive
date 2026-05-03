import { Slot } from "@radix-ui/react-slot";
import { cva, type VariantProps } from "class-variance-authority";
import * as React from "react";

import { cn } from "@/lib/utils";

const badgeVariants = cva(
    "inline-flex items-center justify-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap transition-colors",
    {
        variants: {
            variant: {
                default: "border-transparent bg-primary text-primary-foreground",
                success: "border-transparent bg-success/15 text-success",
                warning: "border-transparent bg-warning/15 text-warning",
                error: "border-transparent bg-destructive/15 text-destructive",
                info: "border-transparent bg-secondary/10 text-secondary",
                amber: "border-transparent bg-accent-light text-amber-800",
                secondary: "border-transparent bg-secondary/10 text-secondary",
                destructive: "border-transparent bg-destructive/15 text-destructive",
            },
            size: {
                sm: "px-2 py-0.5 text-[11px]",
                md: "px-2.5 py-0.5 text-xs",
            },
        },
        defaultVariants: {
            variant: "default",
            size: "md",
        },
    },
);

function Badge({
    className,
    variant,
    size,
    asChild = false,
    ...props
}: React.ComponentProps<"span"> &
    VariantProps<typeof badgeVariants> & { asChild?: boolean }) {
    const Comp = asChild ? Slot : "span";

    return (
        <Comp
            data-slot="badge"
            className={cn(badgeVariants({ variant, size }), className)}
            {...props}
        />
    );
}

export { Badge, badgeVariants };
