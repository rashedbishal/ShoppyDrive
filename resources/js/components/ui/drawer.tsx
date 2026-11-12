import * as DialogPrimitive from "@radix-ui/react-dialog";
import { X } from "lucide-react";
import { AnimatePresence, motion } from "framer-motion";
import { useEffect, type ReactNode } from "react";

import { cn } from "@/lib/utils";
import { useUiStore } from "@/stores/uiStore";

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
    /** Replaces the visible title/description with custom content (e.g. a logo), while keeping `title`/`description` for screen readers. */
    header?: ReactNode;
};

/** iOS-style deceleration — quick start, soft landing. */
const DRAWER_EASE = [0.32, 0.72, 0, 1] as const;

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
    header,
}: DrawerProps) {
    const registerDrawerOpen = useUiStore((state) => state.registerDrawerOpen);

    // Tell the rest of the page a panel is open, so floating buttons (the chat
    // button) hide instead of sitting on top of the panel's own buttons.
    useEffect(() => (isOpen ? registerDrawerOpen() : undefined), [isOpen, registerDrawerOpen]);

    return (
        <AnimatePresence>
            {isOpen ? (
                <DialogPrimitive.Root open={isOpen} onOpenChange={(open) => !open && onClose()}>
                    <DialogPrimitive.Portal>
                        <DialogPrimitive.Overlay asChild>
                            {/* Backdrop blur is GPU-heavy on phones (re-blurred every frame of
                                the slide), so it's only applied from the `sm` breakpoint up. */}
                            <motion.div
                                className="fixed inset-0 z-50 bg-[var(--color-overlay)] will-change-[opacity] sm:backdrop-blur-sm"
                                initial={{ opacity: 0 }}
                                animate={{ opacity: 1 }}
                                exit={{ opacity: 0 }}
                                transition={{ duration: 0.25, ease: "easeOut" }}
                            />
                        </DialogPrimitive.Overlay>
                        <DialogPrimitive.Content asChild>
                            <motion.aside
                                aria-modal="true"
                                className={cn(
                                    "fixed top-0 z-50 flex h-full flex-col overflow-hidden border-border bg-surface backdrop-blur-[var(--ui-blur)] shadow-[0_20px_60px_rgba(0,0,0,0.2)] will-change-transform",
                                    side === "right" ? "right-0 border-l" : "left-0 border-r",
                                    sizeClasses[size],
                                )}
                                initial={{ x: side === "right" ? "100%" : "-100%" }}
                                animate={{ x: 0 }}
                                exit={{ x: side === "right" ? "100%" : "-100%" }}
                                transition={{ duration: 0.32, ease: DRAWER_EASE }}
                            >
                                <div className="flex shrink-0 items-center justify-between gap-4 border-b border-border px-6 py-4">
                                    {header ? (
                                        <>
                                            <DialogPrimitive.Title className="sr-only">{title}</DialogPrimitive.Title>
                                            {description ? (
                                                <DialogPrimitive.Description className="sr-only">
                                                    {description}
                                                </DialogPrimitive.Description>
                                            ) : null}
                                            {header}
                                        </>
                                    ) : (
                                        <div className="space-y-1">
                                            <DialogPrimitive.Title asChild>
                                                <h2 className="text-lg font-semibold text-text-primary">
                                                    {title}
                                                </h2>
                                            </DialogPrimitive.Title>
                                            {description ? (
                                                <DialogPrimitive.Description asChild>
                                                    <p className="text-sm text-text-secondary">{description}</p>
                                                </DialogPrimitive.Description>
                                            ) : null}
                                        </div>
                                    )}
                                    <button
                                        type="button"
                                        onClick={onClose}
                                        className="inline-flex size-10 items-center justify-center rounded-full text-text-secondary transition-colors hover:bg-surface-alt hover:text-text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                                        aria-label={closeLabel}
                                    >
                                        <X className="size-5" />
                                    </button>
                                </div>
                                <div className="flex-1 overflow-y-auto overscroll-contain px-6 py-5">
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
