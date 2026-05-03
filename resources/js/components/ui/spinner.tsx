import * as React from "react";
import { LoaderCircle } from "lucide-react";

import { cn } from "@/lib/utils";

type SpinnerProps = React.ComponentProps<"svg"> & {
    size?: "sm" | "md" | "lg";
};

function Spinner({ className, size = "md", ...props }: SpinnerProps) {
    const sizeClassName = size === "sm" ? "size-4" : size === "lg" ? "size-6" : "size-5";

    return (
        <LoaderCircle
            role="status"
            aria-label="Loading"
            className={cn(sizeClassName, "animate-spin", className)}
            {...props}
        />
    );
}

export { Spinner };
