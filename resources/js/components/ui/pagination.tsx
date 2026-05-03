import { ChevronLeft, ChevronRight } from "lucide-react";
import { type PaginatedData } from "@/types";
import { cn } from "@/lib/utils";

type PaginationProps = {
    data: PaginatedData<unknown>;
    onPageChange: (page: number) => void;
    className?: string;
};

function buildPageItems(currentPage: number, lastPage: number): Array<number | "..."> {
    const pages: Array<number | "..."> = [];
    const windowSize = 1;

    const start = Math.max(1, currentPage - windowSize);
    const end = Math.min(lastPage, currentPage + windowSize);

    if (start > 1) {
        pages.push(1);
        if (start > 2) {
            pages.push("...");
        }
    }

    for (let page = start; page <= end; page += 1) {
        pages.push(page);
    }

    if (end < lastPage) {
        if (end < lastPage - 1) {
            pages.push("...");
        }
        pages.push(lastPage);
    }

    return pages;
}

function Pagination({ data, onPageChange, className }: PaginationProps) {
    const pageItems = buildPageItems(data.current_page, data.last_page);

    if (data.last_page <= 1) {
        return null;
    }

    return (
        <nav aria-label="Pagination" className={cn("flex items-center justify-center", className)}>
            <div className="inline-flex items-center gap-2 rounded-full border border-border bg-surface p-2 shadow-sm">
                <button
                    type="button"
                    onClick={() => onPageChange(data.current_page - 1)}
                    disabled={data.current_page <= 1}
                    aria-label="Previous page"
                    className="inline-flex h-10 items-center gap-2 rounded-full px-4 text-sm font-medium text-text-primary transition-colors hover:bg-surface-alt disabled:cursor-not-allowed disabled:opacity-40"
                >
                    <ChevronLeft className="size-4" />
                    Prev
                </button>
                <div className="flex items-center gap-1">
                    {pageItems.map((item, index) =>
                        item === "..." ? (
                            <span key={`ellipsis-${index}`} className="px-2 text-sm text-text-muted">
                                ...
                            </span>
                        ) : (
                            <button
                                key={item}
                                type="button"
                                onClick={() => onPageChange(item)}
                                aria-current={item === data.current_page ? "page" : undefined}
                                className={cn(
                                    "inline-flex size-10 items-center justify-center rounded-full text-sm font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary",
                                    item === data.current_page
                                        ? "bg-primary text-primary-foreground"
                                        : "text-text-primary hover:bg-surface-alt",
                                )}
                            >
                                {item}
                            </button>
                        ),
                    )}
                </div>
                <button
                    type="button"
                    onClick={() => onPageChange(data.current_page + 1)}
                    disabled={data.current_page >= data.last_page}
                    aria-label="Next page"
                    className="inline-flex h-10 items-center gap-2 rounded-full px-4 text-sm font-medium text-text-primary transition-colors hover:bg-surface-alt disabled:cursor-not-allowed disabled:opacity-40"
                >
                    Next
                    <ChevronRight className="size-4" />
                </button>
            </div>
        </nav>
    );
}

export { Pagination };

