import * as DialogPrimitive from '@radix-ui/react-dialog';
import { AnimatePresence, motion, useReducedMotion } from 'framer-motion';
import { Check, X } from 'lucide-react';
import { useId } from 'react';
import { Button } from '@/components/ui/button';
import { QuantityStepper } from '@/components/ui/quantity-stepper';
import { cn, formatCurrency, getDiscountPercent } from '@/lib/utils';
import type { Attribute, AttributeValue, ProductVariant } from '@/types';

type VariantSelectionModalProps = {
    isOpen: boolean;
    onClose: () => void;
    onConfirm: () => void;
    productName: string;
    thumbnailUrl: string | null;
    attributes: Attribute[];
    selectedAttributes: Record<string, string>;
    onAttributeSelect: (name: string, value: string) => void;
    isAttributeValueAvailable: (attrName: string, value: string) => boolean;
    selectedVariant: ProductVariant | null;
    quantity: number;
    onQuantityChange: (value: number) => void;
    currentPrice: number;
    comparePrice?: number | null;
    hasDiscount: boolean;
    stockQuantity: number;
    lowStockThreshold: number;
    isOutOfStock: boolean;
    confirmLabel: string;
};

/**
 * Gates Add to Cart / Buy Now on the product detail page for variant-based
 * products: the confirm button here is the only thing that ever calls
 * `onConfirm` (which the caller wires to the *existing*, unmodified
 * handleAddToCart/handleBuyNow — this component never talks to the cart
 * store or the server itself). Closing — via the X button, Escape, or a
 * backdrop click — only ever calls `onClose`, never `onConfirm`, so there's
 * no path from "dismiss the modal" to "add to cart" or "check out".
 *
 * All variant state (selected attributes, availability, the resolved
 * variant, price/stock) is owned by the caller and passed in as props —
 * this is a presentational layer over the exact same selection logic
 * already driving the inline selectors on the page, not a second
 * implementation of it. The inline selectors are deliberately left as-is;
 * this renders its own equivalent markup rather than extracting a shared
 * component, so nothing about the existing page's output can be disturbed
 * by this addition.
 *
 * Built directly on Radix Dialog + framer-motion (the same two libraries
 * `components/ui/modal.tsx` already uses) rather than that shared
 * component, because this needs to respect `prefers-reduced-motion` and
 * that component's transition is hardcoded — editing a primitive used
 * elsewhere (Addresses.tsx, sonner.tsx) to add that is a bigger change than
 * this feature should make. Radix's Dialog.Content supplies the focus trap,
 * Escape-to-close, backdrop-click-to-close and focus restoration to the
 * triggering button for free.
 */
export function VariantSelectionModal({
    isOpen,
    onClose,
    onConfirm,
    productName,
    thumbnailUrl,
    attributes,
    selectedAttributes,
    onAttributeSelect,
    isAttributeValueAvailable,
    selectedVariant,
    quantity,
    onQuantityChange,
    currentPrice,
    comparePrice,
    hasDiscount,
    stockQuantity,
    lowStockThreshold,
    isOutOfStock,
    confirmLabel,
}: VariantSelectionModalProps): React.JSX.Element {
    const titleId = useId();
    const descriptionId = useId();
    const prefersReducedMotion = useReducedMotion();
    const canConfirm = selectedVariant !== null && !isOutOfStock;
    const discountPercent =
        hasDiscount && comparePrice
            ? getDiscountPercent(currentPrice, comparePrice)
            : 0;

    const overlayTransition = { duration: prefersReducedMotion ? 0 : 0.2 };
    const contentInitial = prefersReducedMotion
        ? { opacity: 0 }
        : { opacity: 0, scale: 0.95, y: 16 };
    const contentAnimate = prefersReducedMotion
        ? { opacity: 1 }
        : { opacity: 1, scale: 1, y: 0 };
    const contentTransition = prefersReducedMotion
        ? { duration: 0 }
        : { duration: 0.28, ease: [0.16, 1, 0.3, 1] as const };

    return (
        <DialogPrimitive.Root
            open={isOpen}
            onOpenChange={(open) => {
                if (!open) {
                    onClose();
                }
            }}
        >
            <AnimatePresence>
                {isOpen ? (
                    <DialogPrimitive.Portal>
                        <DialogPrimitive.Overlay asChild>
                            <motion.div
                                className="fixed inset-0 z-50 bg-[var(--color-overlay)] backdrop-blur-sm"
                                initial={{ opacity: 0 }}
                                animate={{ opacity: 1 }}
                                exit={{ opacity: 0 }}
                                transition={overlayTransition}
                            />
                        </DialogPrimitive.Overlay>
                        <DialogPrimitive.Content
                            asChild
                            aria-describedby={descriptionId}
                        >
                            <motion.div
                                aria-labelledby={titleId}
                                className="fixed top-1/2 left-1/2 z-50 flex max-h-[min(640px,90vh)] w-[calc(100vw-2rem)] max-w-md -translate-x-1/2 -translate-y-1/2 flex-col overflow-hidden rounded-3xl border border-[var(--color-border)] bg-[var(--color-surface)] shadow-[0_24px_70px_-20px_rgba(0,0,0,0.35)]"
                                initial={contentInitial}
                                animate={contentAnimate}
                                exit={contentInitial}
                                transition={contentTransition}
                            >
                                <DialogPrimitive.Close
                                    aria-label="Close"
                                    className="absolute top-4 right-4 z-10 inline-flex size-9 items-center justify-center rounded-full bg-[var(--color-surface-alt)] text-[var(--color-text-secondary)] transition-colors hover:bg-[var(--color-border)] hover:text-[var(--color-text-primary)] focus-visible:ring-2 focus-visible:ring-[var(--color-primary)] focus-visible:outline-none"
                                >
                                    <X className="size-4" />
                                </DialogPrimitive.Close>

                                <div className="flex-1 space-y-6 overflow-y-auto p-6">
                                    <div className="flex items-center gap-4 pr-8">
                                        <div className="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-[var(--color-surface-alt)]">
                                            {thumbnailUrl ? (
                                                <img
                                                    src={thumbnailUrl}
                                                    alt=""
                                                    className="size-full object-cover"
                                                />
                                            ) : null}
                                        </div>
                                        <div className="min-w-0">
                                            <DialogPrimitive.Title
                                                id={titleId}
                                                className="line-clamp-2 font-display text-lg font-bold text-[var(--color-text-primary)]"
                                            >
                                                {productName}
                                            </DialogPrimitive.Title>
                                            <p
                                                id={descriptionId}
                                                className="mt-0.5 text-sm text-[var(--color-text-secondary)]"
                                            >
                                                Choose your options and
                                                quantity, then confirm.
                                            </p>
                                        </div>
                                    </div>

                                    {attributes.map((attr) => (
                                        <div
                                            key={attr.id}
                                            className="space-y-3"
                                        >
                                            <div className="flex items-center justify-between">
                                                <span className="text-sm font-bold tracking-wide text-[var(--color-text-primary)] uppercase">
                                                    Select {attr.name}
                                                </span>
                                                <span className="text-xs font-medium text-[var(--color-text-muted)]">
                                                    {selectedAttributes[
                                                        attr.name
                                                    ] ?? ''}
                                                </span>
                                            </div>
                                            <div className="flex flex-wrap gap-2">
                                                {attr.values.map(
                                                    (val: AttributeValue) => {
                                                        const isSelected =
                                                            selectedAttributes[
                                                                attr.name
                                                            ] === val.value;
                                                        const isAvailable =
                                                            isAttributeValueAvailable(
                                                                attr.name,
                                                                val.value,
                                                            );

                                                        if (
                                                            attr.type ===
                                                            'color'
                                                        ) {
                                                            return (
                                                                <button
                                                                    type="button"
                                                                    key={val.id}
                                                                    onClick={() =>
                                                                        isAvailable &&
                                                                        onAttributeSelect(
                                                                            attr.name,
                                                                            val.value,
                                                                        )
                                                                    }
                                                                    disabled={
                                                                        !isAvailable
                                                                    }
                                                                    className={cn(
                                                                        'relative flex size-10 items-center justify-center rounded-full border-2 transition-all',
                                                                        isSelected
                                                                            ? 'border-[var(--color-primary)] ring-2 ring-[var(--color-primary-light)]'
                                                                            : 'border-transparent',
                                                                        !isAvailable &&
                                                                            'cursor-not-allowed opacity-30',
                                                                    )}
                                                                    title={
                                                                        isAvailable
                                                                            ? val.value
                                                                            : `${val.value} — not available in this combination`
                                                                    }
                                                                >
                                                                    <span
                                                                        className="size-7 rounded-full border border-black/5 shadow-inner"
                                                                        style={{
                                                                            backgroundColor:
                                                                                val.color_hex ||
                                                                                '#ccc',
                                                                        }}
                                                                    />
                                                                    {isSelected ? (
                                                                        <Check
                                                                            size={
                                                                                12
                                                                            }
                                                                            className="absolute text-white drop-shadow-md"
                                                                        />
                                                                    ) : null}
                                                                    {!isAvailable ? (
                                                                        <span className="absolute inset-0 rotate-45 rounded-full border-t-2 border-[var(--color-text-muted)]" />
                                                                    ) : null}
                                                                </button>
                                                            );
                                                        }

                                                        return (
                                                            <button
                                                                type="button"
                                                                key={val.id}
                                                                onClick={() =>
                                                                    isAvailable &&
                                                                    onAttributeSelect(
                                                                        attr.name,
                                                                        val.value,
                                                                    )
                                                                }
                                                                disabled={
                                                                    !isAvailable
                                                                }
                                                                className={cn(
                                                                    'rounded-xl border-2 px-4 py-2 text-sm font-bold transition-all',
                                                                    isSelected
                                                                        ? 'border-[var(--color-primary)] bg-[var(--color-primary-light)] text-[var(--color-primary)]'
                                                                        : 'border-[var(--color-border)] text-[var(--color-text-secondary)] hover:border-[var(--color-text-primary)]',
                                                                    !isAvailable &&
                                                                        'cursor-not-allowed line-through opacity-40',
                                                                )}
                                                                title={
                                                                    isAvailable
                                                                        ? undefined
                                                                        : `${val.value} — not available in this combination`
                                                                }
                                                            >
                                                                {val.value}
                                                            </button>
                                                        );
                                                    },
                                                )}
                                            </div>
                                        </div>
                                    ))}

                                    <div className="flex items-end justify-between gap-4 rounded-2xl bg-[var(--color-surface-alt)] p-4">
                                        <div>
                                            <div className="flex items-baseline gap-2">
                                                <span className="text-2xl font-extrabold text-[var(--color-text-primary)]">
                                                    {formatCurrency(
                                                        currentPrice,
                                                    )}
                                                </span>
                                                {hasDiscount && comparePrice ? (
                                                    <span className="text-sm font-medium text-[var(--color-text-muted)] line-through">
                                                        {formatCurrency(
                                                            comparePrice,
                                                        )}
                                                    </span>
                                                ) : null}
                                            </div>
                                            {selectedVariant?.sku ? (
                                                <p className="mt-1 text-xs text-[var(--color-text-muted)]">
                                                    SKU: {selectedVariant.sku}
                                                </p>
                                            ) : null}
                                        </div>
                                        {discountPercent > 0 ? (
                                            <span className="rounded-full bg-[var(--color-error)]/10 px-2.5 py-1 text-xs font-bold text-[var(--color-error)]">
                                                -{discountPercent}%
                                            </span>
                                        ) : null}
                                    </div>

                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <span
                                                className={cn(
                                                    'h-2.5 w-2.5 rounded-full',
                                                    isOutOfStock
                                                        ? 'bg-[var(--color-error)]'
                                                        : stockQuantity <=
                                                            lowStockThreshold
                                                          ? 'animate-pulse bg-[var(--color-warning)]'
                                                          : 'bg-[var(--color-success)]',
                                                )}
                                            />
                                            <span
                                                className={cn(
                                                    'text-sm font-bold',
                                                    isOutOfStock
                                                        ? 'text-[var(--color-error)]'
                                                        : stockQuantity <=
                                                            lowStockThreshold
                                                          ? 'text-[var(--color-warning)]'
                                                          : 'text-[var(--color-success)]',
                                                )}
                                            >
                                                {isOutOfStock
                                                    ? 'Out of Stock'
                                                    : stockQuantity <=
                                                        lowStockThreshold
                                                      ? `Hurry! Only ${stockQuantity} left`
                                                      : 'In Stock'}
                                            </span>
                                        </div>
                                        {!isOutOfStock ? (
                                            <QuantityStepper
                                                value={quantity}
                                                onChange={onQuantityChange}
                                                max={Math.max(1, stockQuantity)}
                                            />
                                        ) : null}
                                    </div>
                                </div>

                                <div className="shrink-0 border-t border-[var(--color-border)] p-4">
                                    <Button
                                        onClick={onConfirm}
                                        disabled={!canConfirm}
                                        size="lg"
                                        className="h-14 w-full rounded-2xl text-lg font-bold"
                                    >
                                        {confirmLabel}
                                    </Button>
                                </div>
                            </motion.div>
                        </DialogPrimitive.Content>
                    </DialogPrimitive.Portal>
                ) : null}
            </AnimatePresence>
        </DialogPrimitive.Root>
    );
}
