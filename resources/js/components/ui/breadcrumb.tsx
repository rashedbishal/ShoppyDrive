import { ChevronRight } from "lucide-react";
import { Link } from "@inertiajs/react";
import { Slot } from "@radix-ui/react-slot";
import * as React from "react";

import { cn, toUrl } from "@/lib/utils";
import type { BreadcrumbItem } from "@/types";

type BreadcrumbProps = {
    items?: BreadcrumbItem[];
    className?: string;
    children?: React.ReactNode;
};

function Breadcrumb({ items, className, children }: BreadcrumbProps) {
    if (items && items.length > 0) {
        return (
            <nav aria-label="Breadcrumb" className={cn("min-w-0", className)}>
                <ol className="flex min-w-0 items-center gap-2 text-sm text-text-secondary">
                    {items.map((item, index) => {
                        const isLast = index === items.length - 1;
                        return (
                            <li key={`${item.title}-${index}`} className="flex min-w-0 items-center gap-2">
                                {index > 0 ? <ChevronRight className="size-4 shrink-0" /> : null}
                                {isLast ? (
                                    <span className="truncate font-medium text-text-primary">{item.title}</span>
                                ) : (
                                    <Link
                                        href={toUrl(item.href)}
                                        className="truncate transition-colors hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                                    >
                                        {item.title}
                                    </Link>
                                )}
                            </li>
                        );
                    })}
                </ol>
            </nav>
        );
    }

    return (
        <nav aria-label="Breadcrumb" className={cn("min-w-0", className)}>
            {children}
        </nav>
    );
}

function BreadcrumbList({
    className,
    ...props
}: React.ComponentProps<"ol">) {
    return (
        <ol
            className={cn("flex min-w-0 items-center gap-2 text-sm text-text-secondary", className)}
            {...props}
        />
    );
}

function BreadcrumbItem({
    className,
    ...props
}: React.ComponentProps<"li">) {
    return <li className={cn("flex min-w-0 items-center gap-2", className)} {...props} />;
}

function BreadcrumbLink({
    asChild = false,
    className,
    ...props
}: React.ComponentProps<"a"> & { asChild?: boolean }) {
    const Comp = asChild ? Slot : "a";

    return (
        <Comp
            className={cn(
                "truncate transition-colors hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary",
                className,
            )}
            {...props}
        />
    );
}

function BreadcrumbPage({
    className,
    ...props
}: React.ComponentProps<"span">) {
    return (
        <span className={cn("truncate font-medium text-text-primary", className)} {...props} />
    );
}

function BreadcrumbSeparator({
    children,
    className,
    ...props
}: React.ComponentProps<"li">) {
    return (
        <li aria-hidden="true" className={cn("flex items-center", className)} {...props}>
            {children ?? <ChevronRight className="size-4 shrink-0" />}
        </li>
    );
}

export {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
};
