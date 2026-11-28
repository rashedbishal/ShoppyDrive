import { usePage } from "@inertiajs/react";
import { ChevronLeft, ChevronRight } from "lucide-react";
import type React from "react";
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

/**
 * SEO-AUDIT-01: the URL a page link points to, so crawlers (which follow
 * links, never clicks) can reach every page. It is the current URL with only
 * `page` changed, so filters, sort and search are kept exactly as the
 * server's `?page=N` pagination reads them; page 1 drops the parameter,
 * matching the server's canonical URL for the first page.
 */
function pageHref(currentUrl: string, page: number): string {
    const url = new URL(currentUrl, "http://localhost");

    if (page <= 1) {
        url.searchParams.delete("page");
    } else {
        url.searchParams.set("page", String(page));
    }

    return url.pathname + url.search;
}

/**
 * A plain click keeps the page's own in-app visit (onPageChange: partial
 * reloads, preserved state). Ctrl/Cmd/Shift/Alt or middle clicks are left to
 * the browser, so the link can still open in a new tab.
 */
function followInApp(event: React.MouseEvent<HTMLAnchorElement>, page: number, onPageChange: (page: number) => void): void {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
    }

    event.preventDefault();
    onPageChange(page);
}

const PREV_NEXT_CLASS =
    "inline-flex h-10 items-center gap-2 rounded-full px-4 text-sm font-medium text-text-primary transition-colors hover:bg-surface-alt disabled:cursor-not-allowed disabled:opacity-40";

function Pagination({ data, onPageChange, className }: PaginationProps) {
    const { url: currentUrl } = usePage();
    const pageItems = buildPageItems(data.current_page, data.last_page);

    if (data.last_page <= 1) {
        return null;
    }

    const previousPage = data.current_page - 1;
    const nextPage = data.current_page + 1;

    return (
        <nav aria-label="Pagination" className={cn("flex items-center justify-center", className)}>
            <div className="inline-flex items-center gap-2 rounded-full border border-border bg-surface p-2 shadow-sm">
                {data.current_page <= 1 ? (
                    <button type="button" disabled aria-label="Previous page" className={PREV_NEXT_CLASS}>
                        <ChevronLeft className="size-4" />
                        Prev
                    </button>
                ) : (
                    <a
                        href={pageHref(currentUrl, previousPage)}
                        onClick={(event) => followInApp(event, previousPage, onPageChange)}
                        aria-label="Previous page"
                        className={PREV_NEXT_CLASS}
                    >
                        <ChevronLeft className="size-4" />
                        Prev
                    </a>
                )}
                <div className="flex items-center gap-1">
                    {pageItems.map((item, index) =>
                        item === "..." ? (
                            <span key={`ellipsis-${index}`} className="px-2 text-sm text-text-secondary">
                                ...
                            </span>
                        ) : (
                            <a
                                key={item}
                                href={pageHref(currentUrl, item)}
                                onClick={(event) => followInApp(event, item, onPageChange)}
                                aria-current={item === data.current_page ? "page" : undefined}
                                className={cn(
                                    "inline-flex size-10 items-center justify-center rounded-full text-sm font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary",
                                    item === data.current_page
                                        ? "bg-primary text-primary-foreground"
                                        : "text-text-primary hover:bg-surface-alt",
                                )}
                            >
                                {item}
                            </a>
                        ),
                    )}
                </div>
                {data.current_page >= data.last_page ? (
                    <button type="button" disabled aria-label="Next page" className={PREV_NEXT_CLASS}>
                        Next
                        <ChevronRight className="size-4" />
                    </button>
                ) : (
                    <a
                        href={pageHref(currentUrl, nextPage)}
                        onClick={(event) => followInApp(event, nextPage, onPageChange)}
                        aria-label="Next page"
                        className={PREV_NEXT_CLASS}
                    >
                        Next
                        <ChevronRight className="size-4" />
                    </a>
                )}
            </div>
        </nav>
    );
}

export { Pagination };

