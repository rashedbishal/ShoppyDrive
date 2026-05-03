import * as DialogPrimitive from "@radix-ui/react-dialog";
import { X } from "lucide-react";
import { AnimatePresence, motion } from "framer-motion";

import { cn } from "@/lib/utils";
import { useEffect, useId, type ReactNode } from "react";

type ModalSize = "sm" | "md" | "lg" | "xl" | "full";

type ModalProps = {
    isOpen: boolean;
    onClose: () => void;
    title: string;
    children: ReactNode;
    description?: string;
    footer?: React.ReactNode;
    size?: ModalSize;
    closeLabel?: string;
};

const sizeClasses: Record<ModalSize, string> = {
    sm: "max-w-md",
    md: "max-w-xl",
    lg: "max-w-2xl",
    xl: "max-w-4xl",
    full: "max-w-[calc(100vw-2rem)]",
};

function Modal({
    isOpen,
    onClose,
    title,
    children,
    description,
    footer,
    size = "md",
    closeLabel = "Close modal",
}: ModalProps) {
    const titleId = useId();
    const descriptionId = description ? useId() : undefined;

    useEffect(() => {
        if (!isOpen) {
            return;
        }

        const onKeyDown = (event: KeyboardEvent): void => {
            if (event.key === "Escape") {
                onClose();
            }
        };

        window.addEventListener("keydown", onKeyDown);

        return () => {
            window.removeEventListener("keydown", onKeyDown);
        };
    }, [isOpen, onClose]);

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
                                transition={{ duration: 0.25 }}
                            />
                        </DialogPrimitive.Overlay>
                        <DialogPrimitive.Content asChild>
                            <motion.div
                                role="dialog"
                                aria-modal="true"
                                aria-labelledby={titleId}
                                aria-describedby={descriptionId}
                                className={cn(
                                    "fixed left-1/2 top-1/2 z-50 flex w-[calc(100vw-2rem)] max-h-[calc(100vh-2rem)] -translate-x-1/2 -translate-y-1/2 flex-col overflow-hidden rounded-2xl border border-border bg-surface shadow-[0_20px_60px_rgba(0,0,0,0.2)]",
                                    sizeClasses[size],
                                )}
                                initial={{ opacity: 0, scale: 0.95, y: 8 }}
                                animate={{ opacity: 1, scale: 1, y: 0 }}
                                exit={{ opacity: 0, scale: 0.95, y: 8 }}
                                transition={{ duration: 0.25 }}
                            >
                                <div className="flex shrink-0 items-start justify-between gap-4 border-b border-border px-6 py-4">
                                    <div className="space-y-1">
                                        <h2 id={titleId} className="text-xl font-semibold text-text-primary">
                                            {title}
                                        </h2>
                                        {description ? (
                                            <p id={descriptionId} className="text-sm text-text-secondary">
                                                {description}
                                            </p>
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
                            </motion.div>
                        </DialogPrimitive.Content>
                    </DialogPrimitive.Portal>
                </DialogPrimitive.Root>
            ) : null}
        </AnimatePresence>
    );
}

export { Modal };
