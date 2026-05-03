import * as DialogPrimitive from "@radix-ui/react-dialog";
import { X } from "lucide-react";
import { AnimatePresence, motion } from "framer-motion";
import type { ReactNode } from "react";

import { cn } from "@/lib/utils";

type DrawerSide = "left" | "right";
type DrawerSize = "sm" | "md" | "lg";

type DrawerProps = {
    isOpen: boolean;
    onClose: () => void;
    title: string;
    children: ReactNode;
    side?: DrawerSide;
    size?: DrawerSize;
    description?: string;
    footer?: React.ReactNode;
    closeLabel?: string;
};

const sizeClasses: Record<DrawerSize, string> = {
    sm: "w-[min(100vw,22rem)]",
    md: "w-[min(100vw,28rem)]",
    lg: "w-[min(100vw,36rem)]",
};

function Drawer({
    isOpen,
    onClose,
    title,
    children,
    side = "right",
    size = "md",
    description,
    footer,
    closeLabel = "Close drawer",
}: DrawerProps) {
    return (
        <AnimatePresence>
            {isOpen ? (
                <DialogPrimitive.Root open={isOpen} onOpenChange={(open) => !open && onClose()}>
                    <DialogPrimitive.Portal>
                        <DialogPrimitive.Overlay asChild>
                            <motion.div
                                className="fixed inset-0 z-50 bg-[var(--color-overlay)] backdrop-blur-sm"
                                initial={{ opacity: 0 }}
                                animate={{ opacity: 1 }}
                                exit={{ opacity: 0 }}
                                transition={{ duration: 0.2 }}
                            />
                        </DialogPrimitive.Overlay>
                        <DialogPrimitive.Content asChild>
                            <motion.aside
                                role="dialog"
                                aria-modal="true"
                                aria-labelledby="drawer-title"
                                className={cn(
                                    "fixed top-0 z-50 flex h-full flex-col overflow-hidden border-border bg-surface shadow-[0_20px_60px_rgba(0,0,0,0.2)]",
                                    side === "right" ? "right-0 border-l" : "left-0 border-r",
                                    sizeClasses[size],
                                )}
                                initial={{ x: side === "right" ? "100%" : "-100%" }}
                                animate={{ x: 0 }}
                                exit={{ x: side === "right" ? "100%" : "-100%" }}
                                transition={{ duration: 0.35, ease: "easeOut" }}
                            >
                                <div className="flex shrink-0 items-start justify-between gap-4 border-b border-border px-6 py-4">
                                    <div className="space-y-1">
                                        <h2 id="drawer-title" className="text-lg font-semibold text-text-primary">
                                            {title}
                                        </h2>
                                        {description ? (
                                            <p className="text-sm text-text-secondary">{description}</p>
                                        ) : null}
                                    </div>
                                    <button
                                        type="button"
                                        onClick={onClose}
                                        className="inline-flex size-10 items-center justify-center rounded-full text-text-secondary transition-colors hover:bg-surface-alt hover:text-text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                                        aria-label={closeLabel}
                                    >
                                        <X className="size-5" />
                                    </button>
                                </div>
                                <div className="flex-1 overflow-y-auto px-6 py-5">
                                    {children}
                                </div>
                                {footer ? (
                                    <div className="shrink-0 border-t border-border px-6 py-4">
                                        {footer}
                                    </div>
                                ) : null}
                            </motion.aside>
                        </DialogPrimitive.Content>
                    </DialogPrimitive.Portal>
                </DialogPrimitive.Root>
            ) : null}
        </AnimatePresence>
    );
}

export { Drawer };
