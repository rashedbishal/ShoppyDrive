import { ChevronDown } from "lucide-react";
import { useMemo, useState, type ReactNode } from "react";

import { cn } from "@/lib/utils";

type AccordionItem = {
    id: string;
    title: string;
    content: ReactNode;
    disabled?: boolean;
};

type AccordionProps = {
    items: AccordionItem[];
    multiple?: boolean;
    defaultOpenIds?: string[];
    className?: string;
};

function Accordion({
    items,
    multiple = false,
    defaultOpenIds = [],
    className,
}: AccordionProps) {
    const [openIds, setOpenIds] = useState<string[]>(defaultOpenIds);

    const openSet = useMemo(() => new Set(openIds), [openIds]);

    const toggle = (id: string): void => {
        setOpenIds((current) => {
            if (current.includes(id)) {
                return current.filter((itemId) => itemId !== id);
            }

            if (multiple) {
                return [...current, id];
            }

            return [id];
        });
    };

    return (
        <div className={cn("space-y-3", className)}>
            {items.map((item) => {
                const isOpen = openSet.has(item.id);

                return (
                    <div key={item.id} className="overflow-hidden rounded-2xl border border-border bg-surface shadow-sm">
                        <button
                            type="button"
                            onClick={() => toggle(item.id)}
                            disabled={item.disabled}
                            className="flex w-full items-center justify-between gap-4 px-5 py-4 text-left text-sm font-semibold text-text-primary transition-colors hover:bg-surface-alt focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary disabled:cursor-not-allowed disabled:opacity-60"
                            aria-expanded={isOpen}
                        >
                            <span>{item.title}</span>
                            <ChevronDown
                                className={cn(
                                    "size-4 shrink-0 transition-transform duration-300",
                                    isOpen ? "rotate-180" : "",
                                )}
                            />
                        </button>
                        {/* Native CSS grid-rows transition (0fr → 1fr) instead of a JS
                            height:auto animation — no per-frame measuring, smooth on phones. */}
                        <div
                            className={cn(
                                "grid transition-[grid-template-rows,opacity] duration-300 ease-[cubic-bezier(0.32,0.72,0,1)] motion-reduce:transition-none",
                                isOpen ? "grid-rows-[1fr] opacity-100" : "grid-rows-[0fr] opacity-0",
                            )}
                            inert={!isOpen}
                        >
                            <div className="min-h-0 overflow-hidden">
                                <div className="border-t border-border px-5 py-4 text-sm text-text-secondary">
                                    {item.content}
                                </div>
                            </div>
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

export { Accordion };
