import { Link, router } from '@inertiajs/react';
import { m as motion, useInView, useReducedMotion } from 'framer-motion';
import { Heart, ShoppingBag } from 'lucide-react';
import React, { useRef, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { StarRating } from '@/components/ui/star-rating';
import { useCanHover } from '@/hooks/use-can-hover';
import { MOTION, PRODUCT_CARD_HEIGHT } from '@/lib/constants';
import { IMAGE_SIZES } from '@/lib/image-sizes';
import { isMotionReady } from '@/lib/motion-ready';
import { cn, formatCurrency, getDiscountPercent } from '@/lib/utils';
import productRoutes from '@/routes/product';
import { useCartStore } from '@/stores/cartStore';
import { useUiStore } from '@/stores/uiStore';
import { useWishlistStore } from '@/stores/wishlistStore';
import type { Product } from '@/types';

type ProductCardProps = {
    product: Product;
    view?: 'grid' | 'list';
    className?: string;
    /** Position in its list, used to stagger the reveal animation. */
    index?: number;
    /** 0–100 fill for the flash-sale progress bar along the image's bottom edge. */
    saleProgress?: number;
    saleProgressLabel?: string;
    /** Open the cart drawer after adding, instead of only showing a toast. */
    openCartOnAdd?: boolean;
};

type VariantOption = [name: string, values: string[]];

/** Each variant attribute with its distinct values, in first-seen order, e.g. [["Color", ["Red", "Blue"]]]. */
function variantOptions(product: Product): VariantOption[] {
    const options = new Map<string, string[]>();

    for (const variant of product.variants ?? []) {
        for (const [name, value] of Object.entries(variant.attributes ?? {})) {
            const values = options.get(name) ?? [];

            if (value && !values.includes(value)) {
                values.push(value);
            }

            options.set(name, values);
        }
    }

    return [...options.entries()].filter(([, values]) => values.length > 0);
}

/** "Color: Red, Blue +2 · Size: L, XL" — the list card's detail line when a product has no description. */
function optionsSummary(options: VariantOption[]): string {
    return options
        .map(([name, values]) => {
            const shown = values.slice(0, 2).join(', ');
            const more = values.length > 2 ? ` +${values.length - 2}` : '';

            return `${name}: ${shown}${more}`;
        })
        .join(' · ');
}

type Highlight = { label: string; tone: 'sale' | 'delivery' | 'neutral' };

const HIGHLIGHT_TONES: Record<Highlight['tone'], string> = {
    sale: 'bg-[var(--color-accent)]/15 text-[var(--color-secondary)]',
    delivery: 'bg-emerald-50 text-emerald-700',
    neutral: 'bg-[var(--color-surface-alt)] text-[var(--color-text-secondary)]',
};

/**
 * The chips under the list card's image, from data the card already has.
 * The sale end is a fixed Dhaka date, not "ends in 5h": these pages are
 * server-rendered, and a relative time would differ between server and
 * browser and break hydration.
 */
function listHighlights(
    product: Product,
    options: VariantOption[],
    isFlashPriced: boolean,
): Highlight[] {
    const highlights: Highlight[] = [];

    if (isFlashPriced && product.flash_sale_ends_at) {
        const ends = new Date(product.flash_sale_ends_at).toLocaleDateString(
            'en-GB',
            { day: 'numeric', month: 'short', timeZone: 'Asia/Dhaka' },
        );
        highlights.push({ label: `Sale ends ${ends}`, tone: 'sale' });
    }

    if (product.free_delivery) {
        const minQty = product.free_delivery_min_qty ?? 1;
        highlights.push({
            label: minQty > 1 ? `Free delivery on ${minQty}+` : 'Free delivery',
            tone: 'delivery',
        });
    }

    for (const [name, values] of options) {
        const noun = name.toLowerCase();
        const plural =
            values.length === 1 || noun.endsWith('s') ? noun : `${noun}s`;
        highlights.push({
            label: `${values.length} ${plural}`,
            tone: 'neutral',
        });
    }

    if (product.category?.name) {
        highlights.push({ label: product.category.name, tone: 'neutral' });
    }

    return highlights;
}

/**
 * The single storefront product card used everywhere products are listed.
 * The grid view has a fixed height per device tier (PRODUCT_CARD_HEIGHT):
 * every text row has a reserved height and the image absorbs the remaining
 * space, so optional content never changes the card's size.
 */
export function ProductCard({
    product,
    view = 'grid',
    className,
    index = 0,
    saleProgress,
    saleProgressLabel,
    openCartOnAdd = false,
}: ProductCardProps): React.JSX.Element {
    const prefersReducedMotion = useReducedMotion();
    const cardRef = useRef<HTMLElement>(null);
    const isInView = useInView(cardRef, { once: true, amount: 0.2 });
    // F1-02: the scroll-in reveal needs the lazily loaded animation features
    // (lib/motion-ready.ts). A card on screen before they arrive just shows,
    // as with reduced motion; cards mounted afterwards keep the reveal.
    const [canReveal] = useState(isMotionReady);
    const [isHovered, setIsHovered] = useState(false);
    // The slow, staggered reveal belongs to the first appearance only; once
    // it has played, hovering in and out uses the quick hover timing.
    const [hasRevealed, setHasRevealed] = useState(false);

    const addItem = useCartStore((state) => state.addItem);
    const openCart = useUiStore((state) => state.openCart);
    const addToast = useUiStore((state) => state.addToast);
    const toggleWishlist = useWishlistStore((state) => state.toggleWishlist);
    // Read from `state`, not a store method: during hydration zustand passes
    // the store's initial (empty) state, so the first render matches the
    // server's, which can't see the browser's saved wishlist.
    const isWishlisted = useWishlistStore((state) =>
        state.productIds.includes(product.id),
    );

    const imageSet = Array.isArray(product.images) ? product.images : [];
    const primaryImage =
        imageSet.find((image) => image.is_primary) ?? imageSet[0];
    const secondaryImage = imageSet.find(
        (image) => image.is_hover && !image.is_primary,
    );
    const primaryUrl = primaryImage?.path ?? product.primary_image_url ?? '';
    // IMG-F7: the hover photo only exists for a pointer that can hover;
    // phones never download a photo they could never show.
    const canHover = useCanHover();
    const secondaryUrl = canHover ? (secondaryImage?.path ?? '') : '';
    const productUrl = productRoutes.show.url(product.slug);

    const isFlashPriced =
        Boolean(product.in_flash_sale) &&
        typeof product.flash_sale_price === 'number';
    const currentPrice = isFlashPriced
        ? (product.flash_sale_price as number)
        : product.price;
    const originalPrice =
        product.compare_price && product.compare_price > currentPrice
            ? product.compare_price
            : product.price > currentPrice
              ? product.price
              : null;
    const discount = originalPrice
        ? getDiscountPercent(currentPrice, originalPrice)
        : 0;

    const availableStock = product.available_stock ?? product.stock_quantity;
    const isOutOfStock = availableStock <= 0;
    const isLowStock =
        !isOutOfStock && availableStock <= product.low_stock_threshold;
    const stockLabel = isOutOfStock
        ? 'Out of stock'
        : isLowStock
          ? `Only ${availableStock} left`
          : null;

    const handleAddToCart = (event: React.MouseEvent): void => {
        event.preventDefault();
        event.stopPropagation();

        if (isOutOfStock) {
            addToast({
                type: 'warning',
                title: 'Out of stock',
                message: `${product.name} is currently unavailable.`,
            });

            return;
        }

        // A variant product always has exactly one default variant (enforced
        // on save — see ProductVariantManagementTest). Quick-adding without
        // resolving it would add a variant-less line that InventoryService
        // rejects as out of stock at checkout, even when the default variant
        // itself has plenty of stock.
        const variants = product.variants ?? [];
        const defaultVariant =
            variants.find((variant) => variant.is_default) ?? null;

        // Other variants are in stock but the default isn't, so the customer
        // chooses one rather than getting a size or colour they never picked.
        if (variants.length > 0 && (defaultVariant?.stock_quantity ?? 0) <= 0) {
            router.visit(route('product.show', product.slug));

            return;
        }

        const added = addItem({
            product,
            quantity: 1,
            variant: defaultVariant,
        });

        if (added === 0) {
            addToast({
                type: 'warning',
                title: 'No more stock',
                message: `Your cart already has all available ${product.name}.`,
            });

            return;
        }

        if (openCartOnAdd) {
            openCart();
        }

        addToast({
            type: 'success',
            title: 'Added to cart',
            message: `${product.name} was added to your cart.`,
        });
    };

    const handleWishlist = (event: React.MouseEvent): void => {
        event.preventDefault();
        event.stopPropagation();

        toggleWishlist(product.id, {
            name: product.name,
            price: product.flash_sale_price ?? product.price,
        });
        addToast({
            type: isWishlisted ? 'info' : 'success',
            title: isWishlisted ? 'Removed from wishlist' : 'Saved to wishlist',
            message: isWishlisted
                ? `${product.name} removed from your wishlist.`
                : `${product.name} added to your wishlist.`,
        });
    };

    /**
     * Sends a click on any otherwise-inert part of the card to the product
     * page. Links and buttons inside the card keep their own behaviour.
     */
    const handleCardClick = (event: React.MouseEvent<HTMLElement>): void => {
        if ((event.target as Element).closest('a, button')) {
            return;
        }

        // A disabled button ignores pointer events, so its click lands on the
        // card instead. It must stay inert rather than act as a link.
        const disabledButton = event.currentTarget
            .querySelector('button:disabled')
            ?.getBoundingClientRect();

        if (
            disabledButton &&
            event.clientX >= disabledButton.left &&
            event.clientX <= disabledButton.right &&
            event.clientY >= disabledButton.top &&
            event.clientY <= disabledButton.bottom
        ) {
            return;
        }

        // Dragging over the text to copy it ends in a click; that isn't navigation.
        if (window.getSelection()?.toString()) {
            return;
        }

        if (event.ctrlKey || event.metaKey || event.shiftKey) {
            window.open(productUrl, '_blank', 'noopener');

            return;
        }

        router.visit(productUrl);
    };

    const media = (
        <div className="relative h-full overflow-hidden bg-[var(--color-surface-alt)]">
            {primaryUrl ? (
                <img
                    src={primaryUrl}
                    srcSet={primaryImage?.srcset ?? undefined}
                    sizes={primaryImage?.srcset ? IMAGE_SIZES.card : undefined}
                    width={400}
                    height={300}
                    alt={primaryImage?.alt || product.name}
                    loading="lazy"
                    decoding="async"
                    className={cn(
                        'h-full w-full object-cover transition duration-500',
                        isHovered && secondaryUrl
                            ? 'scale-105 opacity-0'
                            : 'opacity-100',
                    )}
                />
            ) : (
                <div className="flex h-full items-center justify-center text-[var(--color-text-muted)]">
                    <ShoppingBag className="size-10" />
                </div>
            )}

            {secondaryUrl ? (
                <img
                    src={secondaryUrl}
                    srcSet={secondaryImage?.srcset ?? undefined}
                    sizes={
                        secondaryImage?.srcset ? IMAGE_SIZES.card : undefined
                    }
                    width={400}
                    height={300}
                    alt=""
                    aria-hidden="true"
                    loading="lazy"
                    decoding="async"
                    className={cn(
                        'absolute inset-0 h-full w-full object-cover transition duration-500',
                        isHovered ? 'scale-105 opacity-100' : 'opacity-0',
                    )}
                />
            ) : null}

            <div className="absolute inset-0 bg-gradient-to-t from-black/10 via-transparent to-transparent" />

            {isFlashPriced ? (
                <Badge className="absolute top-3 left-3 bg-[var(--color-accent)] text-[var(--color-secondary)]">
                    FLASH {discount > 0 ? `${discount}%` : 'DEAL'}
                </Badge>
            ) : discount > 0 ? (
                <Badge variant="amber" className="absolute top-3 left-3">
                    SALE {discount}%
                </Badge>
            ) : null}

            <div className="absolute top-3 right-3 z-10 flex flex-col gap-1.5">
                <button
                    type="button"
                    onClick={handleWishlist}
                    aria-label={
                        isWishlisted
                            ? `Remove ${product.name} from wishlist`
                            : `Add ${product.name} to wishlist`
                    }
                    className={cn(
                        'inline-flex size-9 items-center justify-center rounded-full border border-[var(--color-border)] bg-white/95 shadow-sm transition hover:scale-105',
                        isWishlisted
                            ? 'text-[var(--color-primary)]'
                            : 'text-[var(--color-text-secondary)] hover:text-[var(--color-primary)]',
                    )}
                >
                    <Heart
                        className={cn('size-4', isWishlisted && 'fill-current')}
                    />
                </button>
            </div>

            {isOutOfStock ? (
                <div className="absolute inset-0 flex items-center justify-center bg-[rgba(26,26,46,0.6)]">
                    <Badge
                        variant="error"
                        className="border-0 bg-[rgba(220,38,38,0.95)] text-white"
                    >
                        Out of stock
                    </Badge>
                </div>
            ) : null}

            {typeof saleProgress === 'number' ? (
                <div
                    className="absolute inset-x-0 bottom-0 h-1.5 bg-black/20"
                    aria-label={`${saleProgressLabel ?? 'Sale progress'} ${saleProgress}%`}
                >
                    <div
                        className="h-full bg-[var(--color-accent)] transition-all"
                        style={{
                            width: `${Math.max(0, Math.min(100, saleProgress))}%`,
                        }}
                    />
                </div>
            ) : null}
        </div>
    );

    const brandName = product.brand?.name ?? 'ShoppyDrive Select';

    const rating = (
        <div className="flex h-4 items-center gap-1.5 overflow-hidden whitespace-nowrap">
            <StarRating
                rating={product.average_rating ?? 0}
                size="sm"
                className="shrink-0 text-[var(--color-accent)]"
            />
            <span className="text-xs text-[var(--color-text-secondary)]">
                ({product.reviews_count ?? 0})
            </span>
        </div>
    );

    const stockClassName = isOutOfStock
        ? 'text-[var(--color-error)]'
        : isLowStock
          ? 'font-medium text-[var(--color-warning)]'
          : 'text-[var(--color-text-secondary)]';

    if (view === 'list') {
        // From sm the list card has one fixed height (DISC-57): every text
        // slot is reserved, and the space under the 4:3 image holds the
        // product's highlights, so no card has a gap under its image and no
        // card is shorter than another. Phones keep the stacked layout.
        const options = variantOptions(product);
        const detailFallback =
            optionsSummary(options) ||
            [product.category?.name, product.sku ? `SKU ${product.sku}` : null]
                .filter(Boolean)
                .join(' · ');
        const highlights = listHighlights(product, options, isFlashPriced);

        return (
            <article
                onMouseEnter={() => setIsHovered(true)}
                onMouseLeave={() => setIsHovered(false)}
                onClick={handleCardClick}
                className={cn(
                    'group grid cursor-pointer grid-cols-1 gap-4 overflow-hidden rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)] p-3 shadow-[var(--shadow-card)] transition hover:shadow-[var(--shadow-card-hover)] sm:h-56 sm:grid-cols-[200px_minmax(0,1fr)]',
                    className,
                )}
            >
                <div className="flex min-h-0 flex-col gap-2">
                    <Link
                        href={productUrl}
                        prefetch
                        className="block aspect-[4/3] shrink-0 overflow-hidden rounded-xl"
                    >
                        {media}
                    </Link>

                    {/* Under the image (sm+): the rating, which every product has, then one row of chips (150 + 8 + 16 + 8 + 18 = the 200px inner height). Chips that don't fit the row are clipped whole. */}
                    <div className="hidden sm:block">{rating}</div>
                    {highlights.length > 0 ? (
                        <ul
                            aria-label="Product highlights"
                            className="hidden h-[18px] shrink-0 flex-wrap content-start gap-1 overflow-hidden sm:flex"
                        >
                            {highlights.map((highlight) => (
                                <li
                                    key={highlight.label}
                                    className={cn(
                                        'block h-[18px] max-w-full truncate rounded-full px-1.5 text-[10px] leading-[18px] font-semibold',
                                        HIGHLIGHT_TONES[highlight.tone],
                                    )}
                                >
                                    {highlight.label}
                                </li>
                            ))}
                        </ul>
                    ) : null}
                </div>

                <div className="flex min-w-0 flex-col gap-2.5 sm:gap-2">
                    <div className="space-y-1">
                        <p className="truncate text-[11px] leading-4 font-semibold tracking-[0.22em] text-[var(--color-text-secondary)] uppercase">
                            {brandName}
                        </p>
                        <Link
                            href={productUrl}
                            prefetch
                            className="line-clamp-2 text-base font-semibold text-[var(--color-text-primary)] transition hover:text-[var(--color-primary)] sm:h-12"
                        >
                            {product.name}
                        </Link>
                        {product.short_description ? (
                            <p className="line-clamp-2 text-sm leading-6 text-[var(--color-text-secondary)] sm:h-10 sm:leading-5">
                                {product.short_description}
                            </p>
                        ) : (
                            // No description: the product's options (or category and SKU) fill the same two lines, from sm only.
                            <p className="hidden text-sm leading-5 text-[var(--color-text-secondary)] sm:line-clamp-2 sm:h-10">
                                {detailFallback}
                            </p>
                        )}
                    </div>

                    <div className="sm:hidden">{rating}</div>

                    <div className="flex flex-wrap items-baseline gap-x-2 gap-y-0.5 sm:h-7 sm:flex-nowrap sm:items-center">
                        <p className="text-xl font-bold tracking-tight whitespace-nowrap text-[var(--color-primary)]">
                            {formatCurrency(currentPrice)}
                        </p>
                        {originalPrice ? (
                            <p className="text-sm whitespace-nowrap text-[var(--color-text-muted)] line-through">
                                {formatCurrency(originalPrice)}
                            </p>
                        ) : null}
                        {stockLabel ? (
                            <span
                                className={cn(
                                    'text-xs sm:min-w-0 sm:truncate',
                                    stockClassName,
                                )}
                            >
                                {stockLabel}
                            </span>
                        ) : null}
                    </div>

                    <div className="mt-auto flex flex-wrap gap-2">
                        <Button
                            type="button"
                            disabled={isOutOfStock}
                            onClick={handleAddToCart}
                            className="h-10 flex-1 sm:flex-none"
                        >
                            {isOutOfStock ? 'Out of Stock' : 'Add to Cart'}
                        </Button>
                        <Button
                            asChild
                            variant="outline"
                            className="h-10 flex-1 sm:flex-none"
                        >
                            <Link href={productUrl} prefetch>
                                View Details
                            </Link>
                        </Button>
                    </div>
                </div>
            </article>
        );
    }

    return (
        <motion.article
            ref={cardRef}
            onClick={handleCardClick}
            className={cn(
                'group flex w-full cursor-pointer flex-col overflow-hidden rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)] shadow-[var(--shadow-card)] transition-shadow duration-300 hover:shadow-[var(--shadow-card-hover)]',
                PRODUCT_CARD_HEIGHT,
                className,
            )}
            initial={false}
            animate={
                prefersReducedMotion || !canReveal
                    ? undefined
                    : isInView
                      ? { opacity: 1, y: 0, scale: 1 }
                      : {
                            opacity: 0,
                            y: MOTION.reveal.distance,
                            scale: MOTION.reveal.scale,
                        }
            }
            transition={
                hasRevealed
                    ? { duration: MOTION.cardHover.duration, ease: 'easeOut' }
                    : {
                          duration: MOTION.reveal.duration,
                          ease: MOTION.reveal.ease,
                          delay: Math.min(
                              index * MOTION.reveal.stagger,
                              MOTION.reveal.maxDelay,
                          ),
                      }
            }
            onAnimationComplete={() => {
                if (isInView) {
                    setHasRevealed(true);
                }
            }}
            whileHover={{
                y: -4,
                scale: 1.01,
                transition: {
                    duration: MOTION.cardHover.duration,
                    ease: 'easeOut',
                },
            }}
            onHoverStart={() => setIsHovered(true)}
            onHoverEnd={() => setIsHovered(false)}
        >
            <Link href={productUrl} prefetch className="block min-h-0 flex-1">
                {media}
            </Link>

            {/* Every row below has a fixed height so the card never changes size. */}
            <div className="flex shrink-0 flex-col gap-1.5 p-2.5 sm:p-3">
                <div className="space-y-0.5">
                    <p className="truncate text-[11px] leading-4 font-semibold tracking-[0.22em] text-[var(--color-text-secondary)] uppercase">
                        {brandName}
                    </p>
                    <Link
                        href={productUrl}
                        prefetch
                        className="line-clamp-2 h-9 text-sm leading-[1.125rem] font-semibold text-[var(--color-text-primary)] transition hover:text-[var(--color-primary)]"
                    >
                        {product.name}
                    </Link>
                </div>

                {rating}

                <div className="flex h-9 min-w-0 flex-col justify-center overflow-hidden sm:h-5 sm:flex-row sm:items-baseline sm:justify-start sm:gap-1.5">
                    <p className="shrink-0 text-base leading-5 font-bold tracking-tight whitespace-nowrap text-[var(--color-primary)]">
                        {formatCurrency(currentPrice)}
                    </p>
                    {originalPrice ? (
                        <p className="min-w-0 truncate text-xs leading-4 text-[var(--color-text-muted)] line-through">
                            {formatCurrency(originalPrice)}
                        </p>
                    ) : null}
                </div>

                <div className="flex h-4 items-center justify-between gap-2 text-xs leading-4 whitespace-nowrap">
                    <span className={cn('min-w-0 truncate', stockClassName)}>
                        {stockLabel}
                    </span>
                    {saleProgressLabel ? (
                        <span className="shrink-0 text-[var(--color-text-secondary)]">
                            {saleProgressLabel}
                        </span>
                    ) : null}
                </div>

                <Button
                    type="button"
                    fullWidth
                    disabled={isOutOfStock}
                    onClick={handleAddToCart}
                    className="h-9 sm:h-10"
                >
                    {isOutOfStock ? 'Out of Stock' : 'Add to Cart'}
                </Button>
            </div>
        </motion.article>
    );
}

export function ProductCardSkeleton({
    view = 'grid',
}: {
    view?: 'grid' | 'list';
}): React.JSX.Element {
    if (view === 'list') {
        return (
            <Card className="grid grid-cols-1 gap-4 overflow-hidden rounded-2xl border-[var(--color-border)] bg-[var(--color-surface)] p-3 shadow-[var(--shadow-card)] sm:h-56 sm:grid-cols-[200px_minmax(0,1fr)]">
                <Skeleton className="aspect-[4/3] w-full rounded-xl" />
                <div className="space-y-2.5">
                    <Skeleton className="h-4 w-24" />
                    <Skeleton className="h-5 w-4/5" />
                    <Skeleton className="h-4 w-full" />
                    <Skeleton className="h-4 w-28" />
                    <Skeleton className="h-7 w-28" />
                    <Skeleton className="h-10 w-40 rounded-xl" />
                </div>
            </Card>
        );
    }

    return (
        <Card
            className={cn(
                'flex w-full flex-col gap-0 overflow-hidden rounded-2xl border-[var(--color-border)] bg-[var(--color-surface)] p-0 shadow-[var(--shadow-card)]',
                PRODUCT_CARD_HEIGHT,
            )}
        >
            <Skeleton className="min-h-0 w-full flex-1 rounded-none" />
            <div className="flex shrink-0 flex-col gap-1.5 p-2.5 sm:p-3">
                <div className="space-y-0.5">
                    <Skeleton className="h-4 w-24" />
                    <Skeleton className="h-9 w-full" />
                </div>
                <Skeleton className="h-4 w-28" />
                <Skeleton className="h-9 w-24 sm:h-5" />
                <div className="h-4" />
                <Skeleton className="h-9 w-full rounded-xl sm:h-10" />
            </div>
        </Card>
    );
}
