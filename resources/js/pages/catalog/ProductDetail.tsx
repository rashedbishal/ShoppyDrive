import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import {
    ChevronLeft,
    ChevronRight,
    Heart,
    Maximize2,
    MessageSquare,
    Share2,
    ShieldCheck,
    ShoppingCart,
    Star,
    Truck,
    Undo2,
    Check,
    X,
    Film,
} from 'lucide-react';
import React, { useEffect, useMemo, useState } from 'react';

import { CryBabyEasterEgg } from '@/components/shared/CryBabyEasterEgg';
import { FlashSaleCountdown } from '@/components/shared/FlashSaleCountdown';
import { ProductCarousel } from '@/components/shared/ProductCarousel';
import { RatingBreakdown } from '@/components/shared/RatingBreakdown';
import { ReviewCard } from '@/components/shared/ReviewCard';
import { ReviewImagePicker } from '@/components/shared/ReviewImagePicker';
import { VariantSelectionModal } from '@/components/shared/VariantSelectionModal';
import { Badge } from '@/components/ui/badge';
import { Breadcrumb } from '@/components/ui/breadcrumb';
import { Button } from '@/components/ui/button';
import { CharCount } from '@/components/ui/char-count';
import { QuantityStepper } from '@/components/ui/quantity-stepper';
import { Select } from '@/components/ui/select';
import { StarRating } from '@/components/ui/star-rating';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useLocalStorage } from '@/lib/hooks/useLocalStorage';
import { trackMetaProductEvent } from '@/lib/meta-tracking';
import { formatCurrency, getDiscountPercent } from '@/lib/utils';
import { useCartStore } from '@/stores/cartStore';
import { useUiStore } from '@/stores/uiStore';
import { useWishlistStore } from '@/stores/wishlistStore';
import type {
    Product,
    ProductVariant,
    Review,
    AttributeValue,
    SharedPageProps,
} from '@/types';

/** A variant's value ids; a variant saved before custom attributes has only Color/Size. */
function variantValueIds(variant: ProductVariant): number[] {
    return (
        variant.attribute_value_ids ??
        [variant.color_value_id, variant.size_value_id].filter(
            (id): id is number => id != null,
        )
    ).map(Number);
}

interface ProductDetailProps {
    product: Product;
    relatedProducts: Product[];
    reviews: Review[];
    ratingBreakdown: Record<number, number>;
    canReview: boolean;
    hasReviewed: boolean;
    shippingMethods: any[];
}

export default function ProductDetail({
    product,
    relatedProducts = [],
    reviews = [],
    ratingBreakdown,
    canReview = false,
    hasReviewed = false,
    shippingMethods = [],
}: ProductDetailProps): React.JSX.Element {
    const { auth, settings } = usePage<SharedPageProps>().props;

    // Review form
    const reviewForm = useForm({ title: '', body: '' });
    // The review endpoint also validates `rating` and `review`, which aren't
    // fields of this form, so its errors are wider than the form's own type.
    const reviewErrors = reviewForm.errors as Partial<
        Record<'title' | 'body' | 'rating' | 'review', string>
    >;
    const [reviewRating, setReviewRating] = useState(0);
    // Photos for the review, when the shop allows them (Admin > Settings).
    const [reviewImages, setReviewImages] = useState<File[]>([]);
    const reviewImagesEnabled = Boolean(settings.review_images_enabled);
    const reviewImagesMax = settings.review_images_max ?? 3;
    const reviewImagesError = Object.entries(reviewForm.errors).find(
        ([key]) => key === 'images' || key.startsWith('images.'),
    )?.[1];
    const [reviewSubmitting, setReviewSubmitting] = useState(false);
    // The server sends the newest reviews first (capped at 50); this only
    // re-orders those already on the page.
    const [reviewSort, setReviewSort] = useState<
        'newest' | 'highest' | 'lowest'
    >('newest');
    const sortedReviews = useMemo(() => {
        if (reviewSort === 'newest') {
            return reviews;
        }

        // Array.prototype.sort is stable, so equal ratings stay newest first.
        return [...reviews].sort((a, b) =>
            reviewSort === 'highest'
                ? b.rating - a.rating
                : a.rating - b.rating,
        );
    }, [reviews, reviewSort]);

    const submitReview = (e: React.FormEvent) => {
        e.preventDefault();

        if (reviewRating === 0 || reviewSubmitting) {
            return;
        }

        setReviewSubmitting(true);
        router.post(
            route('product.reviews.store', product.slug),
            {
                rating: reviewRating,
                title: reviewForm.data.title,
                body: reviewForm.data.body,
                ...(reviewImagesEnabled && reviewImages.length > 0
                    ? { images: reviewImages }
                    : {}),
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    reviewForm.reset();
                    setReviewRating(0);
                    setReviewImages([]);
                },
                onError: (errors) => reviewForm.setError(errors as any),
                onFinish: () => setReviewSubmitting(false),
            },
        );
    };

    // Stores
    const addItem = useCartStore((state) => state.addItem);
    const addToast = useUiStore((state) => state.addToast);
    const toggleWishlist = useWishlistStore((state) => state.toggleWishlist);
    // Read from `state`, not a store method: during hydration zustand passes
    // the store's initial (empty) state, so the first render matches the
    // server's, which can't see the browser's saved wishlist.
    const isWishlisted = useWishlistStore((state) =>
        state.productIds.includes(product.id),
    );

    // State
    const [selectedAttributes, setSelectedAttributes] = useState<
        Record<string, string>
    >({});
    const [quantity, setQuantity] = useState(1);
    const [currentImageIndex, setCurrentImageIndex] = useState(() =>
        Math.max(
            (product.media ?? []).findIndex((m) => m.is_primary),
            0,
        ),
    );
    const [isLightboxOpen, setIsLightboxOpen] = useState(false);
    const [recentlyViewed, setRecentlyViewed] = useLocalStorage<Product[]>(
        'recently_viewed',
        [],
    );
    // Gates Add to Cart / Buy Now behind the variant-selection modal for
    // variant-based products — see handleModalConfirm below. `null` means
    // the modal is closed; otherwise it records which action to run once
    // the customer confirms their selection inside it.
    const [pendingVariantAction, setPendingVariantAction] = useState<
        'cart' | 'buy' | null
    >(null);

    // Derived State
    const allMedia = product.media ?? [];
    const attributes = product.attributes ?? [];
    const variants = product.variants ?? [];
    const hasVariants = variants.length > 0;

    const selectedVariant = useMemo(() => {
        // No attributes picked (e.g. a general gallery image was selected,
        // clearing the selection) means no variant — not "every variant
        // vacuously matches", which `Object.entries({}).every(...)` would
        // otherwise resolve to the first one in the array.
        if (
            variants.length === 0 ||
            Object.keys(selectedAttributes).length === 0
        ) {
            return null;
        }

        return (
            variants.find((variant) => {
                return Object.entries(selectedAttributes).every(
                    ([key, value]) => {
                        return variant.attributes[key] === value;
                    },
                );
            }) || null
        );
    }, [variants, selectedAttributes]);

    // The gallery always shows every image — general shots plus every
    // variant's — so switching variants never empties it down to one photo.
    const media = allMedia;

    const primaryMedia = media.find((m) => m.is_primary) ?? media[0] ?? null;
    const currentMedia = media[currentImageIndex] ?? primaryMedia ?? null;

    // A specific variant's own flash-sale item (set via Admin ▸ Flash Sales,
    // targeting that exact combination) always wins over a whole-product
    // one, matching how ProductController::show() resolves it server-side
    // (FlashSaleItem::findActiveFor() prefers the more specific match) —
    // switching variants here can change which flash price (or whether one
    // applies at all) is currently active.
    const activeFlashSale =
        selectedVariant?.in_flash_sale &&
        typeof selectedVariant.flash_sale_price === 'number'
            ? {
                  price: selectedVariant.flash_sale_price,
                  endsAt: selectedVariant.flash_sale_ends_at ?? null,
              }
            : product.in_flash_sale &&
                typeof product.flash_sale_price === 'number'
              ? {
                    price: product.flash_sale_price,
                    endsAt: product.flash_sale_ends_at ?? null,
                }
              : null;

    const currentPrice = activeFlashSale
        ? activeFlashSale.price
        : selectedVariant?.price || product.price;

    const comparePrice = selectedVariant
        ? selectedVariant.compare_price
        : product.compare_price;
    const hasDiscount = comparePrice && comparePrice > currentPrice;
    const discountPercent = hasDiscount
        ? getDiscountPercent(currentPrice, comparePrice!)
        : 0;

    const stockQuantity = selectedVariant
        ? selectedVariant.stock_quantity
        : (product.available_stock ?? product.stock_quantity);
    const lowStockThreshold =
        selectedVariant?.low_stock_threshold ?? product.low_stock_threshold;
    const isOutOfStock = stockQuantity <= 0;

    // Effects
    useEffect(() => {
        // Default to the variant marked as default; fall back to the first value
        // per attribute (only reachable for legacy data with no default flagged).
        const defaultVariant = variants.find((v) => v.is_default);

        if (defaultVariant) {
            // eslint-disable-next-line react-hooks/set-state-in-effect -- resets local draft/UI state when the server props or URL change
            setSelectedAttributes(defaultVariant.attributes);
        } else if (attributes.length > 0) {
            const defaults: Record<string, string> = {};
            attributes.forEach((attr) => {
                if (attr.values.length > 0) {
                    defaults[attr.name] = attr.values[0].value;
                }
            });
            setSelectedAttributes(defaults);
        }

        // Handle recently viewed
        const updatedRecentlyViewed = [
            product,
            ...recentlyViewed.filter((p) => p.id !== product.id),
        ].slice(0, 10);
        setRecentlyViewed(updatedRecentlyViewed);
    }, [product.id]);

    // Meta ViewContent: once per visit to this product (keyed on its id,
    // so re-renders and variant changes don't repeat it, but opening
    // another product — or coming back to this one later — does).
    useEffect(() => {
        trackMetaProductEvent('ViewContent', [
            {
                productId: product.id,
                name: product.name,
                price: product.flash_sale_price ?? product.price,
                quantity: 1,
            },
        ]);
        // eslint-disable-next-line react-hooks/exhaustive-deps -- once per product, not per price/name change
    }, [product.id]);

    // Jump the main image to the selected variant's photo without touching
    // the rest of the gallery — the thumbnails for every other image/variant
    // stay put. Its own photo first (an older per-variant pick), otherwise
    // the photo chosen for one of its values, in attribute order: Red/XL
    // shows the Red image.
    useEffect(() => {
        if (!selectedVariant) {
            return;
        }

        const ownIdx = media.findIndex(
            (m) => m.variant_id === selectedVariant.id,
        );
        const idx =
            ownIdx !== -1
                ? ownIdx
                : (variantValueIds(selectedVariant)
                      .map((valueId) =>
                          media.findIndex(
                              (m) =>
                                  m.attribute_value_id === valueId &&
                                  m.type === 'image',
                          ),
                      )
                      .find((found) => found !== -1) ?? -1);

        if (idx !== -1) {
            // eslint-disable-next-line react-hooks/set-state-in-effect -- resets local draft/UI state when the server props or URL change
            setCurrentImageIndex(idx);
        }
    }, [selectedVariant?.id, media]);

    // Handlers
    // Picking a thumbnail that belongs to a specific variant also selects
    // that variant — the reverse of the effect above, which moves the main
    // image when a variant is picked from the attribute swatches. Picking a
    // general (untagged) image instead clears the variant selection, since
    // that photo isn't tied to any one of them.
    const handleThumbnailSelect = (idx: number) => {
        setCurrentImageIndex(idx);

        const item = media[idx];

        // A value's image (e.g. Red) selects a variant with that value,
        // keeping as many of the customer's other choices (size, set…) as
        // that value comes in; otherwise the value's first variant.
        if (item?.attribute_value_id != null) {
            const valueId = Number(item.attribute_value_id);
            const current = new Set(
                selectedVariant ? variantValueIds(selectedVariant) : [],
            );
            const variant = variants
                .filter((v) => variantValueIds(v).includes(valueId))
                .reduce<ProductVariant | null>((best, v) => {
                    const overlap = (candidate: ProductVariant) =>
                        variantValueIds(candidate).filter((id) =>
                            current.has(id),
                        ).length;

                    return best === null || overlap(v) > overlap(best)
                        ? v
                        : best;
                }, null);

            if (variant) {
                setSelectedAttributes(variant.attributes);
            }

            return;
        }

        if (!item?.variant_id) {
            setSelectedAttributes({});

            return;
        }

        const variant = variants.find((v) => v.id === item.variant_id);

        if (variant) {
            setSelectedAttributes(variant.attributes);
        }
    };

    // When the clicked value combines with every other currently-selected
    // attribute into a real variant, just update that one attribute as
    // before. Otherwise — a sparse variant grid (e.g. only Red/XL and
    // Blue/M exist, not Red/M or Blue/XL) — there's no way to reach that
    // value by changing one attribute at a time, since the in-between
    // combination doesn't exist either. Jump straight to a real variant
    // that has this value instead, adopting its full attribute set, so the
    // selector always lands on something actually purchasable.
    const handleAttributeSelect = (name: string, value: string) => {
        const matchesCurrentSelection = variants.some(
            (variant) =>
                variant.attributes[name] === value &&
                Object.entries(selectedAttributes).every(
                    ([key, selectedValue]) =>
                        key === name ||
                        variant.attributes[key] === selectedValue,
                ),
        );

        if (matchesCurrentSelection) {
            setSelectedAttributes((prev) => ({ ...prev, [name]: value }));

            return;
        }

        const fallbackVariant = variants.find(
            (variant) => variant.attributes[name] === value,
        );

        if (fallbackVariant) {
            setSelectedAttributes(fallbackVariant.attributes);
        }
    };

    // A value is pickable as long as some variant has it at all — which
    // variant that is (and what it does to the other attributes) is
    // resolved in handleAttributeSelect above.
    const isAttributeValueAvailable = (
        attrName: string,
        value: string,
    ): boolean => {
        if (variants.length === 0) {
            return true;
        }

        return variants.some(
            (variant) => variant.attributes[attrName] === value,
        );
    };

    const handleAddToCart = () => {
        if (isOutOfStock) {
            return;
        }

        addItem({
            product,
            quantity,
            variant: selectedVariant,
        });
        addToast({
            type: 'success',
            title: 'Added to cart',
            message: `${product.name} added to your cart.`,
        });
    };

    const handleBuyNow = () => {
        if (isOutOfStock) {
            return;
        }

        const params: Record<string, number> = {
            buy_now_product: product.id,
            buy_now_qty: quantity,
        };

        if (selectedVariant) {
            params.buy_now_variant = selectedVariant.id;
        }

        // A real browser navigation (not an Inertia visit) so the guest
        // case — checkout redirecting to login — is a plain 302 the browser
        // follows natively, instead of depending on Inertia's XHR client to
        // detect and follow a redirect to a page outside the current visit.
        window.location.href = route('checkout.index', params);
    };

    // For a variant-based product, Add to Cart / Buy Now open the
    // variant-selection modal instead of running the action immediately —
    // confirming inside it is the only thing that ever calls
    // handleAddToCart/handleBuyNow above, unmodified, with whatever
    // selectedVariant/quantity the modal left in place (the same page
    // state the inline selectors already drive). A product with no
    // variants skips the modal entirely, preserving the previous
    // behavior exactly.
    const closeVariantModal = (): void => {
        setPendingVariantAction(null);
    };

    const confirmVariantModal = (): void => {
        if (!selectedVariant || isOutOfStock) {
            return;
        }

        const action = pendingVariantAction;
        setPendingVariantAction(null);

        if (action === 'cart') {
            handleAddToCart();
        } else if (action === 'buy') {
            handleBuyNow();
        }
    };

    const onAddToCartClick = (): void => {
        if (isOutOfStock) {
            return;
        }

        if (hasVariants) {
            setPendingVariantAction('cart');

            return;
        }

        handleAddToCart();
    };

    const onBuyNowClick = (): void => {
        if (isOutOfStock) {
            return;
        }

        if (hasVariants) {
            setPendingVariantAction('buy');

            return;
        }

        handleBuyNow();
    };

    // `navigator.clipboard` only exists in a secure context (HTTPS, or
    // localhost) — it's `undefined` over plain HTTP on any other host,
    // which made the button throw immediately and silently do nothing
    // (the toast below never ran). This falls back to the old
    // select-and-execCommand trick when the modern API isn't there, and
    // only ever reports success once something has actually copied.
    const copyTextToClipboard = async (text: string): Promise<boolean> => {
        if (navigator.clipboard?.writeText) {
            try {
                await navigator.clipboard.writeText(text);

                return true;
            } catch {
                // Permission denied or similar — fall through to the
                // legacy fallback below instead of giving up.
            }
        }

        const textarea = document.createElement('textarea');
        textarea.value = text;
        // Off-screen rather than hidden — a hidden element can't be
        // selected, which execCommand('copy') needs.
        textarea.style.position = 'fixed';
        textarea.style.top = '-9999px';
        textarea.style.left = '-9999px';
        document.body.appendChild(textarea);
        textarea.focus();
        textarea.select();

        let succeeded = false;

        try {
            succeeded = document.execCommand('copy');
        } catch {
            succeeded = false;
        }

        document.body.removeChild(textarea);

        return succeeded;
    };

    const shareProduct = (platform: 'facebook' | 'twitter' | 'copy') => {
        const url = window.location.href;

        if (platform === 'copy') {
            copyTextToClipboard(url).then((succeeded) => {
                addToast(
                    succeeded
                        ? {
                              type: 'info',
                              message: 'Link copied to clipboard!',
                          }
                        : {
                              type: 'error',
                              message:
                                  "Couldn't copy the link — please copy it from the address bar instead.",
                          },
                );
            });
        } else if (platform === 'facebook') {
            window.open(
                `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`,
                '_blank',
            );
        } else if (platform === 'twitter') {
            window.open(
                `https://twitter.com/intent/tweet?url=${encodeURIComponent(url)}&text=${encodeURIComponent(product.name)}`,
                '_blank',
            );
        }
    };

    const breadcrumbItems = [
        { title: 'Home', href: route('home') as string },
        ...(product.category
            ? [
                  {
                      title: product.category.name,
                      href: route(
                          'category.show',
                          product.category.slug,
                      ) as string,
                  },
              ]
            : []),
        { title: product.name, href: '#' },
    ];

    return (
        <div className="min-h-screen bg-[var(--color-surface-alt)] pb-20">
            <Head title={product.meta_title || product.name}>
                <meta
                    name="description"
                    content={
                        (product.meta_description ||
                            product.short_description) ??
                        undefined
                    }
                />
            </Head>

            <div className="py-6 lg:py-10">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <Breadcrumb items={breadcrumbItems} className="mb-8" />

                    <div className="grid grid-cols-1 gap-4 lg:grid-cols-12 lg:gap-8 xl:gap-12">
                        {/* Left Column: Media Gallery */}
                        <div className="lg:col-span-7 xl:col-span-8">
                            <div className="flex flex-col gap-4 md:flex-row">
                                {/* Thumbnails - Left on desktop */}
                                <div className="scrollbar-hide order-2 flex max-h-[600px] gap-3 overflow-x-auto py-1 md:order-1 md:flex-col md:overflow-y-auto">
                                    {media.map((item, idx) => (
                                        <button
                                            key={item.id}
                                            onClick={() =>
                                                handleThumbnailSelect(idx)
                                            }
                                            className={`relative aspect-[4/3] w-20 shrink-0 overflow-hidden rounded-xl border-2 transition-all ${
                                                currentImageIndex === idx
                                                    ? 'border-[var(--color-primary)] ring-2 ring-[var(--color-primary-light)]'
                                                    : 'border-transparent hover:border-[var(--color-border)]'
                                            }`}
                                        >
                                            {item.type === 'video' ? (
                                                <div className="relative flex h-full w-full items-center justify-center bg-slate-900">
                                                    <video
                                                        src={item.path}
                                                        className="h-full w-full object-cover opacity-50"
                                                    />
                                                    <Film className="absolute z-10 size-6 text-white" />
                                                </div>
                                            ) : (
                                                <img
                                                    src={item.path}
                                                    alt={
                                                        item.alt || product.name
                                                    }
                                                    className="h-full w-full object-cover"
                                                />
                                            )}
                                        </button>
                                    ))}
                                </div>

                                {/* Main Media */}
                                <div className="group relative order-1 aspect-[4/3] flex-1 overflow-hidden rounded-3xl bg-white shadow-sm md:order-2">
                                    <AnimatePresence mode="wait">
                                        {currentMedia ? (
                                            <motion.div
                                                key={currentMedia.id}
                                                initial={{ opacity: 0 }}
                                                animate={{ opacity: 1 }}
                                                exit={{ opacity: 0 }}
                                                transition={{ duration: 0.3 }}
                                                className="h-full w-full"
                                            >
                                                {currentMedia.type ===
                                                'video' ? (
                                                    <video
                                                        src={currentMedia.path}
                                                        className="h-full w-full object-cover"
                                                        controls
                                                        autoPlay
                                                        muted
                                                    />
                                                ) : (
                                                    <img
                                                        src={currentMedia.path}
                                                        alt={
                                                            currentMedia.alt ||
                                                            product.name
                                                        }
                                                        className="h-full w-full cursor-zoom-in object-cover"
                                                        onClick={() =>
                                                            setIsLightboxOpen(
                                                                true,
                                                            )
                                                        }
                                                    />
                                                )}
                                            </motion.div>
                                        ) : (
                                            <div className="flex h-full w-full flex-col items-center justify-center gap-3 bg-slate-50">
                                                <svg
                                                    className="size-20 text-slate-200"
                                                    fill="none"
                                                    viewBox="0 0 24 24"
                                                    stroke="currentColor"
                                                >
                                                    <path
                                                        strokeLinecap="round"
                                                        strokeLinejoin="round"
                                                        strokeWidth={1}
                                                        d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                                                    />
                                                </svg>
                                                <span className="text-sm text-slate-400">
                                                    No media available
                                                </span>
                                            </div>
                                        )}
                                    </AnimatePresence>

                                    {discountPercent > 0 && (
                                        <Badge
                                            variant="amber"
                                            className="absolute top-6 left-6 px-4 py-1.5 text-sm font-bold shadow-lg"
                                        >
                                            SALE {discountPercent}% OFF
                                        </Badge>
                                    )}

                                    {activeFlashSale?.endsAt && (
                                        <div className="absolute top-6 right-6 z-10">
                                            <div className="rounded-2xl border border-white/20 bg-black/70 p-3 backdrop-blur-md">
                                                <p className="mb-2 text-[10px] font-bold tracking-widest text-amber-400 uppercase">
                                                    ⚡ Flash Sale Ends In
                                                </p>
                                                <FlashSaleCountdown
                                                    ends_at={
                                                        activeFlashSale.endsAt
                                                    }
                                                />
                                            </div>
                                        </div>
                                    )}

                                    {media.length > 0 &&
                                        currentMedia?.type === 'image' && (
                                            <button
                                                onClick={() =>
                                                    setIsLightboxOpen(true)
                                                }
                                                className="absolute right-6 bottom-6 rounded-full bg-white/90 p-3 text-[var(--color-text-primary)] opacity-0 shadow-lg transition-opacity group-hover:opacity-100"
                                            >
                                                <Maximize2 size={20} />
                                            </button>
                                        )}
                                </div>
                            </div>
                        </div>

                        {/* Right Column: Product Info */}
                        <div className="lg:col-span-5 xl:col-span-4">
                            <div className="sticky top-24 rounded-3xl border border-[var(--color-border)] bg-white p-6 shadow-sm md:p-8">
                                {/* Brand & Stats */}
                                <div className="mb-4 flex items-center justify-between">
                                    {/* A product with no brand shows no label. A brand
                                        shown here is always active: a product whose
                                        brand is inactive 404s (isPubliclyVisible). */}
                                    {product.brand && (
                                        <Link
                                            href={route(
                                                'brand.show',
                                                product.brand.slug,
                                            )}
                                            className="text-sm font-bold tracking-widest text-[var(--color-primary)] uppercase hover:underline"
                                        >
                                            {product.brand.name}
                                        </Link>
                                    )}
                                    <div className="flex items-center gap-4">
                                        {/* A rating is shown only when approved reviews exist — never a made-up default. */}
                                        {(product.reviews_count ?? 0) > 0 &&
                                        product.average_rating != null ? (
                                            <div className="flex items-center gap-1">
                                                <Star
                                                    size={16}
                                                    className="fill-[var(--color-accent)] text-[var(--color-accent)]"
                                                />
                                                <span className="text-sm font-bold">
                                                    {product.average_rating.toFixed(
                                                        1,
                                                    )}
                                                </span>
                                                <span className="text-xs text-[var(--color-text-muted)]">
                                                    ({product.reviews_count})
                                                </span>
                                            </div>
                                        ) : (
                                            <span className="text-xs font-medium text-[var(--color-text-muted)]">
                                                No reviews yet
                                            </span>
                                        )}
                                        <div className="h-4 w-px bg-[var(--color-border)]" />
                                        <span className="text-xs font-medium tracking-wide text-[var(--color-text-muted)] uppercase">
                                            SKU:{' '}
                                            {selectedVariant?.sku ||
                                                product.sku ||
                                                'N/A'}
                                        </span>
                                    </div>
                                </div>

                                <h1 className="mb-4 font-serif text-3xl leading-tight font-bold text-[var(--color-text-primary)]">
                                    {product.name}
                                </h1>

                                <div className="mb-6 flex items-baseline gap-4">
                                    <span className="text-4xl font-bold text-[var(--color-primary)]">
                                        {formatCurrency(currentPrice)}
                                    </span>
                                    {hasDiscount && (
                                        <span className="text-xl text-[var(--color-text-muted)] line-through">
                                            {formatCurrency(comparePrice!)}
                                        </span>
                                    )}
                                </div>

                                <p className="mb-8 line-clamp-3 text-sm leading-relaxed text-[var(--color-text-secondary)]">
                                    {product.short_description}
                                </p>

                                <div className="mb-8 h-px w-full bg-[var(--color-border)]" />

                                {/* Variant Selectors */}
                                <div className="mb-8 space-y-6">
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
                                                    {
                                                        selectedAttributes[
                                                            attr.name
                                                        ]
                                                    }
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
                                                                    key={val.id}
                                                                    onClick={() =>
                                                                        isAvailable &&
                                                                        handleAttributeSelect(
                                                                            attr.name,
                                                                            val.value,
                                                                        )
                                                                    }
                                                                    disabled={
                                                                        !isAvailable
                                                                    }
                                                                    className={`relative flex h-10 w-10 items-center justify-center rounded-full border-2 transition-all ${
                                                                        isSelected
                                                                            ? 'border-[var(--color-primary)] ring-2 ring-[var(--color-primary-light)]'
                                                                            : 'border-transparent'
                                                                    } ${!isAvailable ? 'cursor-not-allowed opacity-30' : ''}`}
                                                                    title={
                                                                        isAvailable
                                                                            ? val.value
                                                                            : `${val.value} — not available in this combination`
                                                                    }
                                                                >
                                                                    <span
                                                                        className="h-7 w-7 rounded-full border border-black/5 shadow-inner"
                                                                        style={{
                                                                            backgroundColor:
                                                                                val.color_hex ||
                                                                                '#ccc',
                                                                        }}
                                                                    />
                                                                    {isSelected && (
                                                                        <Check
                                                                            size={
                                                                                12
                                                                            }
                                                                            className="absolute text-white drop-shadow-md"
                                                                        />
                                                                    )}
                                                                    {!isAvailable && (
                                                                        <span className="absolute inset-0 rotate-45 rounded-full border-t-2 border-[var(--color-text-muted)]" />
                                                                    )}
                                                                </button>
                                                            );
                                                        }

                                                        return (
                                                            <button
                                                                key={val.id}
                                                                onClick={() =>
                                                                    isAvailable &&
                                                                    handleAttributeSelect(
                                                                        attr.name,
                                                                        val.value,
                                                                    )
                                                                }
                                                                disabled={
                                                                    !isAvailable
                                                                }
                                                                className={`rounded-xl border-2 px-4 py-2 text-sm font-bold transition-all ${
                                                                    isSelected
                                                                        ? 'border-[var(--color-primary)] bg-[var(--color-primary-light)] text-[var(--color-primary)]'
                                                                        : 'border-[var(--color-border)] text-[var(--color-text-secondary)] hover:border-[var(--color-text-primary)]'
                                                                } ${!isAvailable ? 'cursor-not-allowed line-through opacity-40' : ''}`}
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
                                </div>

                                {/* Stock & Quantity */}
                                <div className="mb-8 space-y-4">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <div
                                                className={`h-2.5 w-2.5 rounded-full ${isOutOfStock ? 'bg-[var(--color-error)]' : stockQuantity <= lowStockThreshold ? 'animate-pulse bg-[var(--color-warning)]' : 'bg-[var(--color-success)]'}`}
                                            />
                                            <span
                                                className={`text-sm font-bold ${isOutOfStock ? 'text-[var(--color-error)]' : stockQuantity <= lowStockThreshold ? 'text-[var(--color-warning)]' : 'text-[var(--color-success)]'}`}
                                            >
                                                {isOutOfStock
                                                    ? 'Out of Stock'
                                                    : stockQuantity <=
                                                        lowStockThreshold
                                                      ? `Hurry! Only ${stockQuantity} left`
                                                      : 'In Stock & Ready to Ship'}
                                            </span>
                                        </div>
                                        {!isOutOfStock && (
                                            <QuantityStepper
                                                value={quantity}
                                                onChange={setQuantity}
                                                max={stockQuantity}
                                                className="h-10"
                                            />
                                        )}
                                    </div>
                                </div>

                                {/* Actions */}
                                <div className="relative mb-8 flex flex-col gap-3">
                                    <CryBabyEasterEgg
                                        key={product.id}
                                        productId={product.id}
                                        enabled={
                                            Boolean(product.cry_baby_enabled) &&
                                            !isOutOfStock
                                        }
                                    />
                                    <Button
                                        data-cry-baby-target
                                        onClick={onAddToCartClick}
                                        disabled={isOutOfStock}
                                        size="lg"
                                        className="flex h-14 w-full gap-3 rounded-2xl text-lg font-bold shadow-[var(--color-primary)]/20 shadow-lg"
                                    >
                                        <ShoppingCart size={22} />
                                        Add to Cart
                                    </Button>
                                    <Button
                                        onClick={onBuyNowClick}
                                        disabled={isOutOfStock}
                                        variant="secondary"
                                        size="lg"
                                        className="h-14 w-full rounded-2xl text-lg font-bold"
                                    >
                                        Buy Now
                                    </Button>

                                    <div className="mt-2 grid grid-cols-2 gap-3">
                                        <Button
                                            variant="outline"
                                            onClick={() =>
                                                toggleWishlist(product.id, {
                                                    name: product.name,
                                                    price:
                                                        product.flash_sale_price ??
                                                        product.price,
                                                })
                                            }
                                            className={`flex h-12 gap-2 rounded-xl font-semibold ${isWishlisted ? 'border-[var(--color-primary)] bg-[var(--color-primary-light)] text-[var(--color-primary)]' : ''}`}
                                        >
                                            <Heart
                                                size={18}
                                                fill={
                                                    isWishlisted
                                                        ? 'currentColor'
                                                        : 'none'
                                                }
                                            />
                                            {isWishlisted
                                                ? 'Wishlisted'
                                                : 'Wishlist'}
                                        </Button>
                                        <div className="flex gap-2">
                                            <Button
                                                variant="outline"
                                                className="flex h-12 w-full gap-2 rounded-xl font-semibold"
                                                onClick={() =>
                                                    shareProduct('copy')
                                                }
                                            >
                                                <Share2 size={18} />
                                                Share
                                            </Button>
                                        </div>
                                    </div>
                                </div>

                                {/* Trust Badges */}
                                <div className="grid grid-cols-3 gap-2 border-t border-[var(--color-border)] pt-6">
                                    <div className="flex flex-col items-center gap-1.5 text-center">
                                        <div className="flex h-10 w-10 items-center justify-center rounded-full bg-blue-50 text-blue-600">
                                            <ShieldCheck size={20} />
                                        </div>
                                        <span className="text-[10px] font-bold tracking-tighter text-[var(--color-text-primary)] uppercase">
                                            100% Genuine
                                        </span>
                                    </div>
                                    <div className="flex flex-col items-center gap-1.5 text-center">
                                        <div className="flex h-10 w-10 items-center justify-center rounded-full bg-green-50 text-green-600">
                                            <Truck size={20} />
                                        </div>
                                        <span className="text-[10px] font-bold tracking-tighter text-[var(--color-text-primary)] uppercase">
                                            Fast Delivery
                                        </span>
                                    </div>
                                    <div className="flex flex-col items-center gap-1.5 text-center">
                                        <div className="flex h-10 w-10 items-center justify-center rounded-full bg-amber-50 text-amber-600">
                                            <Undo2 size={20} />
                                        </div>
                                        <span className="text-[10px] font-bold tracking-tighter text-[var(--color-text-primary)] uppercase">
                                            7 Days Return
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Tabs Section */}
                    <div className="mt-10 rounded-3xl border border-[var(--color-border)] bg-white p-4 shadow-sm sm:p-6 md:mt-16 md:p-10">
                        <Tabs defaultValue="description" className="w-full">
                            {/* Phones: three equal columns with short labels. sm+: full labels, left-aligned.
                            The active underline comes from TabsTrigger itself. */}
                            <TabsList className="mb-6 grid w-full grid-cols-3 border-b border-[var(--color-border)] sm:mb-8 sm:flex sm:justify-start sm:gap-8">
                                <TabsTrigger
                                    value="description"
                                    className="px-1 py-3 text-sm font-bold sm:px-0 sm:py-4 sm:text-lg"
                                >
                                    <span className="sm:hidden">
                                        Description
                                    </span>
                                    <span className="hidden sm:inline">
                                        Product Description
                                    </span>
                                </TabsTrigger>
                                <TabsTrigger
                                    value="reviews"
                                    className="px-1 py-3 text-sm font-bold sm:px-0 sm:py-4 sm:text-lg"
                                >
                                    Reviews
                                    <span className="ml-1.5 inline-flex min-w-5 items-center justify-center rounded-full bg-[var(--color-surface-alt)] px-1.5 text-xs font-bold text-[var(--color-text-secondary)] sm:text-sm">
                                        {product.reviews_count ?? 0}
                                    </span>
                                </TabsTrigger>
                                <TabsTrigger
                                    value="shipping"
                                    className="px-1 py-3 text-sm font-bold sm:px-0 sm:py-4 sm:text-lg"
                                >
                                    <span className="sm:hidden">Shipping</span>
                                    <span className="hidden sm:inline">
                                        Shipping & Returns
                                    </span>
                                </TabsTrigger>
                            </TabsList>

                            <TabsContent value="description" className="mt-0">
                                {/* Admin-authored HTML: keep images, tables and code inside the card on small screens */}
                                <div
                                    className="prose prose-sm max-w-none leading-relaxed break-words text-[var(--color-text-secondary)] md:prose-base prose-headings:text-[var(--color-text-primary)] prose-a:text-[var(--color-primary)] prose-strong:text-[var(--color-text-primary)] [&_iframe]:max-w-full [&_img]:h-auto [&_img]:max-w-full [&_img]:rounded-xl [&_pre]:overflow-x-auto [&_table]:block [&_table]:max-w-full [&_table]:overflow-x-auto [&_video]:max-w-full"
                                    dangerouslySetInnerHTML={{
                                        __html:
                                            product.description ||
                                            '<p>No description available.</p>',
                                    }}
                                />

                                {product.specifications &&
                                    product.specifications.length > 0 && (
                                        <div className="mt-8 border-t border-[var(--color-border)] pt-8">
                                            <h4 className="mb-4 text-base font-bold text-[var(--color-text-primary)] sm:text-lg">
                                                Product specifications
                                            </h4>
                                            <div className="grid grid-cols-1 overflow-hidden rounded-2xl border border-[var(--color-border)] sm:grid-cols-2">
                                                {product.specifications.map(
                                                    (spec, index) => {
                                                        const total =
                                                            product
                                                                .specifications!
                                                                .length;
                                                        // sm+ is a 2-column grid: the last
                                                        // row is just the final item when
                                                        // the count is odd, or the final
                                                        // two when it's even.
                                                        const isLastRow =
                                                            index >=
                                                            total -
                                                                (total % 2 === 0
                                                                    ? 2
                                                                    : 1);

                                                        return (
                                                            <div
                                                                key={index}
                                                                className={`flex items-center justify-between gap-4 border-b border-[var(--color-border)] px-4 py-2.5 last:border-b-0 sm:odd:border-r ${isLastRow ? 'sm:border-b-0' : ''}`}
                                                            >
                                                                <span className="text-sm text-[var(--color-text-secondary)]">
                                                                    {spec.key}
                                                                </span>
                                                                <span className="text-right text-sm font-bold text-[var(--color-text-primary)]">
                                                                    {spec.value}
                                                                </span>
                                                            </div>
                                                        );
                                                    },
                                                )}
                                            </div>
                                        </div>
                                    )}
                            </TabsContent>

                            <TabsContent value="reviews" className="mt-0">
                                <div className="grid grid-cols-1 gap-8 lg:grid-cols-12 lg:gap-12">
                                    <div className="min-w-0 lg:col-span-4">
                                        <RatingBreakdown
                                            breakdown={ratingBreakdown}
                                        />
                                        <div className="mt-6 rounded-2xl border border-[var(--color-border)] bg-white p-4 sm:mt-8 sm:p-6">
                                            <h4 className="mb-1 flex items-center gap-2 font-bold text-[var(--color-text-primary)]">
                                                <MessageSquare
                                                    size={16}
                                                    className="text-[var(--color-primary)]"
                                                />
                                                Write a Review
                                            </h4>

                                            {!auth.user ? (
                                                <div className="mt-3">
                                                    <p className="mb-3 text-sm text-[var(--color-text-secondary)]">
                                                        Please log in to share
                                                        your experience.
                                                    </p>
                                                    <Link
                                                        href={route('login')}
                                                        className="inline-flex items-center gap-1.5 rounded-xl bg-[var(--color-primary)] px-4 py-2 text-sm font-bold text-white transition-colors hover:bg-[var(--color-primary-hover)]"
                                                    >
                                                        Log in to review
                                                    </Link>
                                                </div>
                                            ) : hasReviewed ? (
                                                <p className="mt-3 flex items-center gap-1.5 text-sm text-[var(--color-success)]">
                                                    <Check size={14} />
                                                    You have already reviewed
                                                    this product.
                                                </p>
                                            ) : !canReview ? (
                                                <p className="mt-3 text-sm text-[var(--color-text-secondary)]">
                                                    Only customers who have
                                                    ordered this product can
                                                    review it.
                                                </p>
                                            ) : (
                                                <form
                                                    onSubmit={submitReview}
                                                    className="mt-4 space-y-4"
                                                >
                                                    {/* Star picker */}
                                                    <div className="space-y-1">
                                                        <span className="text-xs font-semibold tracking-wider text-[var(--color-text-muted)] uppercase">
                                                            Your Rating *
                                                        </span>
                                                        <StarRating
                                                            rating={
                                                                reviewRating
                                                            }
                                                            interactive
                                                            size="lg"
                                                            onChange={
                                                                setReviewRating
                                                            }
                                                        />
                                                        {reviewErrors.rating && (
                                                            <p className="text-xs text-red-500">
                                                                {
                                                                    reviewErrors.rating
                                                                }
                                                            </p>
                                                        )}
                                                    </div>

                                                    <div className="space-y-1">
                                                        <label className="text-xs font-semibold tracking-wider text-[var(--color-text-muted)] uppercase">
                                                            Title{' '}
                                                            <span className="font-normal normal-case">
                                                                (optional)
                                                            </span>
                                                        </label>
                                                        <input
                                                            type="text"
                                                            value={
                                                                reviewForm.data
                                                                    .title
                                                            }
                                                            onChange={(e) =>
                                                                reviewForm.setData(
                                                                    'title',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            placeholder="Summarise your experience"
                                                            maxLength={100}
                                                            className="h-10 w-full rounded-xl border border-[var(--color-border)] px-3 text-sm focus:ring-2 focus:ring-[var(--color-primary)]/40 focus:outline-none"
                                                        />
                                                    </div>

                                                    <div className="space-y-1">
                                                        <label className="text-xs font-semibold tracking-wider text-[var(--color-text-muted)] uppercase">
                                                            Review *
                                                        </label>
                                                        <textarea
                                                            value={
                                                                reviewForm.data
                                                                    .body
                                                            }
                                                            onChange={(e) =>
                                                                reviewForm.setData(
                                                                    'body',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            placeholder="Tell others what you think about this product…"
                                                            rows={4}
                                                            maxLength={2000}
                                                            className="w-full resize-none rounded-xl border border-[var(--color-border)] px-3 py-2 text-sm focus:ring-2 focus:ring-[var(--color-primary)]/40 focus:outline-none"
                                                        />
                                                        <CharCount
                                                            value={
                                                                reviewForm.data
                                                                    .body
                                                            }
                                                            max={2000}
                                                            className="block text-right"
                                                        />
                                                        {reviewForm.errors
                                                            .body && (
                                                            <p className="text-xs text-red-500">
                                                                {
                                                                    reviewForm
                                                                        .errors
                                                                        .body
                                                                }
                                                            </p>
                                                        )}
                                                        {reviewErrors.review && (
                                                            <p className="text-xs text-red-500">
                                                                {
                                                                    reviewErrors.review
                                                                }
                                                            </p>
                                                        )}
                                                    </div>

                                                    {reviewImagesEnabled && (
                                                        <ReviewImagePicker
                                                            files={reviewImages}
                                                            onChange={
                                                                setReviewImages
                                                            }
                                                            max={
                                                                reviewImagesMax
                                                            }
                                                            error={
                                                                reviewImagesError
                                                            }
                                                            disabled={
                                                                reviewSubmitting
                                                            }
                                                        />
                                                    )}

                                                    <Button
                                                        type="submit"
                                                        disabled={
                                                            reviewSubmitting ||
                                                            reviewRating === 0
                                                        }
                                                        className="w-full rounded-xl font-bold"
                                                    >
                                                        {reviewSubmitting
                                                            ? 'Submitting…'
                                                            : 'Submit Review'}
                                                    </Button>
                                                </form>
                                            )}
                                        </div>
                                    </div>
                                    <div className="min-w-0 lg:col-span-8">
                                        <div className="mb-6 flex flex-wrap items-center justify-between gap-3 border-b border-[var(--color-border)] pb-4 sm:mb-8">
                                            <h3 className="text-lg font-bold text-[var(--color-text-primary)] sm:text-xl">
                                                User Reviews
                                            </h3>
                                            <div className="flex items-center gap-2">
                                                <span className="hidden text-sm font-medium text-[var(--color-text-muted)] sm:inline">
                                                    Sort by:
                                                </span>
                                                <Select
                                                    aria-label="Sort reviews"
                                                    containerClassName="min-w-36"
                                                    selectClassName="h-9 rounded-full px-3.5 text-sm font-bold"
                                                    value={reviewSort}
                                                    onChange={(e) =>
                                                        setReviewSort(
                                                            e.target
                                                                .value as typeof reviewSort,
                                                        )
                                                    }
                                                >
                                                    <option value="newest">
                                                        Newest First
                                                    </option>
                                                    <option value="highest">
                                                        Highest Rated
                                                    </option>
                                                    <option value="lowest">
                                                        Lowest Rated
                                                    </option>
                                                </Select>
                                            </div>
                                        </div>

                                        {reviews.length > 0 ? (
                                            <div className="space-y-2">
                                                {sortedReviews.map((review) => (
                                                    <ReviewCard
                                                        key={review.id}
                                                        review={review}
                                                    />
                                                ))}
                                            </div>
                                        ) : (
                                            <div className="rounded-3xl bg-[var(--color-surface-alt)] px-4 py-12 text-center sm:py-20">
                                                <div className="mx-auto mb-4 flex size-14 items-center justify-center rounded-full bg-white text-[var(--color-text-muted)] sm:size-16">
                                                    <MessageSquare className="size-7 sm:size-8" />
                                                </div>
                                                <h4 className="mb-1 font-bold text-[var(--color-text-primary)]">
                                                    No reviews yet
                                                </h4>
                                                <p className="text-sm text-[var(--color-text-muted)]">
                                                    Be the first to review this
                                                    product!
                                                </p>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </TabsContent>

                            <TabsContent value="shipping" className="mt-0">
                                <div className="grid grid-cols-1 gap-8 md:grid-cols-2 md:gap-12">
                                    <div className="min-w-0">
                                        <h4 className="mb-4 flex items-center gap-2 text-lg font-bold text-[var(--color-text-primary)] sm:mb-6 sm:text-xl">
                                            <Truck className="size-5 shrink-0 text-[var(--color-primary)] sm:size-6" />
                                            Delivery Options
                                        </h4>
                                        <div className="space-y-4">
                                            {shippingMethods.map((method) => (
                                                <div
                                                    key={method.id}
                                                    className="flex items-center justify-between rounded-2xl border border-[var(--color-border)] p-4"
                                                >
                                                    <div>
                                                        <p className="font-bold text-[var(--color-text-primary)]">
                                                            {method.name}
                                                        </p>
                                                        <p className="text-xs text-[var(--color-text-muted)]">
                                                            {
                                                                method.estimated_days
                                                            }{' '}
                                                            business days
                                                        </p>
                                                    </div>
                                                    <span className="font-bold text-[var(--color-primary)]">
                                                        {formatCurrency(
                                                            method.cost,
                                                        )}
                                                    </span>
                                                </div>
                                            ))}
                                            {shippingMethods.length === 0 && (
                                                <>
                                                    <div className="flex items-center justify-between rounded-2xl border border-[var(--color-border)] p-4">
                                                        <div>
                                                            <p className="font-bold text-[var(--color-text-primary)]">
                                                                Inside Dhaka
                                                            </p>
                                                            <p className="text-xs text-[var(--color-text-muted)]">
                                                                1-2 business
                                                                days
                                                            </p>
                                                        </div>
                                                        <span className="font-bold text-[var(--color-primary)]">
                                                            {formatCurrency(
                                                                settings.shipping_rate_inside_dhaka ??
                                                                    60,
                                                            )}
                                                        </span>
                                                    </div>
                                                    <div className="flex items-center justify-between rounded-2xl border border-[var(--color-border)] p-4">
                                                        <div>
                                                            <p className="font-bold text-[var(--color-text-primary)]">
                                                                Outside Dhaka
                                                            </p>
                                                            <p className="text-xs text-[var(--color-text-muted)]">
                                                                3-5 business
                                                                days
                                                            </p>
                                                        </div>
                                                        <span className="font-bold text-[var(--color-primary)]">
                                                            {formatCurrency(
                                                                settings.shipping_rate_outside_dhaka ??
                                                                    120,
                                                            )}
                                                        </span>
                                                    </div>
                                                </>
                                            )}
                                        </div>
                                    </div>
                                    <div className="min-w-0">
                                        <h4 className="mb-4 flex items-center gap-2 text-lg font-bold text-[var(--color-text-primary)] sm:mb-6 sm:text-xl">
                                            <Undo2 className="size-5 shrink-0 text-[var(--color-primary)] sm:size-6" />
                                            Return Policy
                                        </h4>
                                        <div className="space-y-5 text-sm text-[var(--color-text-secondary)] sm:space-y-6">
                                            <p>
                                                You can return the item within{' '}
                                                <strong>7 days</strong> of
                                                delivery if it's in original
                                                condition with tags and
                                                packaging.
                                            </p>
                                            <div className="grid grid-cols-2 gap-4">
                                                <div className="flex gap-2">
                                                    <div className="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-green-100 text-green-600">
                                                        <Check size={12} />
                                                    </div>
                                                    <span>Original Tags</span>
                                                </div>
                                                <div className="flex gap-2">
                                                    <div className="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-green-100 text-green-600">
                                                        <Check size={12} />
                                                    </div>
                                                    <span>Unwashed</span>
                                                </div>
                                                <div className="flex gap-2">
                                                    <div className="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-green-100 text-green-600">
                                                        <Check size={12} />
                                                    </div>
                                                    <span>Not Used</span>
                                                </div>
                                                <div className="flex gap-2">
                                                    <div className="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-green-100 text-green-600">
                                                        <Check size={12} />
                                                    </div>
                                                    <span>Sealed Box</span>
                                                </div>
                                            </div>
                                            <p className="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-xs text-amber-800 italic">
                                                * Change of mind is not
                                                applicable for electronics and
                                                hygiene products once opened.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </TabsContent>
                        </Tabs>
                    </div>
                </div>
            </div>

            {/* Carousels — in the standard page container so cards match product cards elsewhere */}
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <ProductCarousel
                    title="You May Also Like"
                    products={relatedProducts}
                    className="mt-16"
                />

                {recentlyViewed.length > 1 && (
                    <ProductCarousel
                        title="Recently Viewed"
                        products={recentlyViewed.filter(
                            (p) => p.id !== product.id,
                        )}
                        className="mt-8"
                    />
                )}
            </div>

            {/* Lightbox overlay */}
            <AnimatePresence>
                {isLightboxOpen && (
                    <motion.div
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        exit={{ opacity: 0 }}
                        className="fixed inset-0 z-[100] flex flex-col bg-black/95"
                        onClick={() => setIsLightboxOpen(false)}
                    >
                        <div
                            className="flex items-center justify-between p-6 text-white"
                            onClick={(e) => e.stopPropagation()}
                        >
                            <span className="font-bold">
                                {currentImageIndex + 1} / {media.length}
                            </span>
                            <button
                                onClick={() => setIsLightboxOpen(false)}
                                className="rounded-full p-2 transition-colors hover:bg-white/10"
                            >
                                <X size={28} />
                            </button>
                        </div>

                        <div
                            className="relative flex flex-1 items-center justify-center px-4 sm:px-16"
                            onClick={(e) => e.stopPropagation()}
                        >
                            <button
                                onClick={() =>
                                    setCurrentImageIndex((prev) =>
                                        prev > 0 ? prev - 1 : media.length - 1,
                                    )
                                }
                                className="absolute left-4 rounded-full bg-white/10 p-3 text-white transition-colors hover:bg-white/20 sm:left-6"
                            >
                                <ChevronLeft size={32} />
                            </button>
                            {currentMedia?.type === 'video' ? (
                                <video
                                    src={currentMedia.path}
                                    className="max-h-[80vh] max-w-full object-contain"
                                    controls
                                    autoPlay
                                />
                            ) : (
                                <motion.img
                                    key={currentMedia?.id}
                                    src={currentMedia?.path}
                                    alt={currentMedia?.alt || product.name}
                                    className="max-h-[80vh] max-w-full object-contain"
                                    initial={{ opacity: 0, scale: 0.95 }}
                                    animate={{ opacity: 1, scale: 1 }}
                                    transition={{
                                        type: 'spring',
                                        damping: 25,
                                        stiffness: 300,
                                    }}
                                />
                            )}
                            <button
                                onClick={() =>
                                    setCurrentImageIndex((prev) =>
                                        prev < media.length - 1 ? prev + 1 : 0,
                                    )
                                }
                                className="absolute right-4 rounded-full bg-white/10 p-3 text-white transition-colors hover:bg-white/20 sm:right-6"
                            >
                                <ChevronRight size={32} />
                            </button>
                        </div>

                        <div
                            className="flex justify-center gap-2 overflow-x-auto p-6"
                            onClick={(e) => e.stopPropagation()}
                        >
                            {media.map((item, idx) => (
                                <button
                                    key={item.id}
                                    onClick={() => handleThumbnailSelect(idx)}
                                    className={`relative h-14 w-14 shrink-0 overflow-hidden rounded-lg border-2 transition-all ${currentImageIndex === idx ? 'border-[var(--color-primary)]' : 'border-white/20'}`}
                                >
                                    {item.type === 'video' ? (
                                        <div className="flex h-full w-full items-center justify-center bg-slate-900">
                                            <video
                                                src={item.path}
                                                className="h-full w-full object-cover opacity-50"
                                            />
                                            <Film
                                                size={16}
                                                className="absolute z-10 text-white"
                                            />
                                        </div>
                                    ) : (
                                        <img
                                            src={item.path}
                                            alt={item.alt || product.name}
                                            className="h-full w-full object-cover"
                                        />
                                    )}
                                </button>
                            ))}
                        </div>
                    </motion.div>
                )}
            </AnimatePresence>

            <VariantSelectionModal
                isOpen={pendingVariantAction !== null}
                onClose={closeVariantModal}
                onConfirm={confirmVariantModal}
                productName={product.name}
                thumbnailUrl={primaryMedia?.path ?? null}
                attributes={attributes}
                selectedAttributes={selectedAttributes}
                onAttributeSelect={handleAttributeSelect}
                isAttributeValueAvailable={isAttributeValueAvailable}
                selectedVariant={selectedVariant}
                quantity={quantity}
                onQuantityChange={setQuantity}
                currentPrice={currentPrice}
                comparePrice={comparePrice}
                hasDiscount={Boolean(hasDiscount)}
                stockQuantity={stockQuantity}
                lowStockThreshold={lowStockThreshold}
                isOutOfStock={isOutOfStock}
                confirmLabel={
                    pendingVariantAction === 'buy' ? 'Buy Now' : 'Add to Cart'
                }
            />
        </div>
    );
}
