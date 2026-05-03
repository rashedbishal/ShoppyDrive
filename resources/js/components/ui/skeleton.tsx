import * as React from "react";

import { cn } from "@/lib/utils";

type SkeletonProps = React.ComponentProps<"div"> & {
    variant?: "text" | "circle" | "rect";
    animate?: boolean;
};

function Skeleton({
    className,
    variant = "rect",
    animate = true,
    ...props
}: SkeletonProps) {
    return (
        <div
            data-slot="skeleton"
            aria-hidden="true"
            className={cn(
                "bg-surface-alt",
                animate ? "animate-pulse" : "",
                variant === "text" ? "h-4 rounded-full" : "",
                variant === "circle" ? "rounded-full" : "rounded-2xl",
                className,
            )}
            {...props}
        />
    );
}

export { Skeleton };
