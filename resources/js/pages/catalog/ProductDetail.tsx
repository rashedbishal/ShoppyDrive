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
    Minus,
    Plus,
    Facebook,
    Twitter,
    Link as LinkIcon,
    Film,
    Play
} from 'lucide-react';
import React, { useEffect, useMemo, useState } from 'react';

import { CryBabyEasterEgg } from '@/components/shared/CryBabyEasterEgg';
import { ProductCarousel } from '@/components/shared/ProductCarousel';
import { RatingBreakdown } from '@/components/shared/RatingBreakdown';
import { ReviewCard } from '@/components/shared/ReviewCard';
import { FlashSaleCountdown } from '@/components/shared/FlashSaleCountdown';
import { Badge } from '@/components/ui/badge';
import { Breadcrumb } from '@/components/ui/breadcrumb';
import { Button } from '@/components/ui/button';
import { QuantityStepper } from '@/components/ui/quantity-stepper';
import { Select } from '@/components/ui/select';
import { StarRating } from '@/components/ui/star-rating';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useLocalStorage } from '@/lib/hooks/useLocalStorage';
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

interface ProductDetailProps {
    product: Product;
    relatedProducts: Product[];
    reviews: Review[];
    ratingBreakdown: Record<number, number>;
    shippingMethods: any[];
}

export default function ProductDetail({
    product,
    relatedProducts = [],
    reviews = [],
    ratingBreakdown,
    shippingMethods = [],
}: ProductDetailProps): React.JSX.Element {
    const { auth } = usePage<SharedPageProps>().props;

    // Review form
    const reviewForm = useForm({ title: '', body: '' });
    const [reviewRating, setReviewRating] = useState(0);
    const [reviewSubmitting, setReviewSubmitting] = useState(false);
    const hasReviewed = reviews.some((r) => r.user?.id === auth.user?.id);

    const submitReview = (e: React.FormEvent) => {
        e.preventDefault();
        if (reviewRating === 0 || reviewSubmitting) return;
        setReviewSubmitting(true);
        router.post(
            route('product.reviews.store', product.slug),
            { rating: reviewRating, title: reviewForm.data.title, body: reviewForm.data.body },
            {
                preserveScroll: true,
                onSuccess: () => { reviewForm.reset(); setReviewRating(0); },
                onError: (errors) => reviewForm.setError(errors as any),
                onFinish: () => setReviewSubmitting(false),
            },
        );
    };

    // Stores
    const addItem = useCartStore((state) => state.addItem);
    const openCart = useUiStore((state) => state.openCart);
    const addToast = useUiStore((state) => state.addToast);
    const toggleWishlist = useWishlistStore((state) => state.toggleWishlist);
    const isWishlisted = useWishlistStore((state) => state.isWishlisted(product.id));

    // State
    const [selectedAttributes, setSelectedAttributes] = useState<Record<string, string>>({});
    const [quantity, setQuantity] = useState(1);
    const [currentImageIndex, setCurrentImageIndex] = useState(0);
    const [isLightboxOpen, setIsLightboxOpen] = useState(false);
    const [recentlyViewed, setRecentlyViewed] = useLocalStorage<Product[]>('recently_viewed', []);

    // Derived State
    const allMedia = product.media ?? [];
    const attributes = product.attributes ?? [];
    const variants = product.variants ?? [];

    const selectedVariant = useMemo(() => {
        if (variants.length === 0) return null;
        return variants.find(variant => {
            return Object.entries(selectedAttributes).every(([key, value]) => {
                return variant.attributes[key] === value;
            });
        }) || null;
    }, [variants, selectedAttributes]);

    // Prefer media tagged to the selected variant; fall back to general (untagged) media.
    const media = useMemo(() => {
        if (selectedVariant) {
            const scoped = allMedia.filter(m => m.variant_id === selectedVariant.id);
            if (scoped.length > 0) return scoped;
        }
        return allMedia.filter(m => !m.variant_id);
    }, [allMedia, selectedVariant]);

    const primaryMedia = media.find(m => m.is_primary) ?? media[0] ?? null;
    const currentMedia = media[currentImageIndex] ?? primaryMedia ?? null;

    const currentPrice = product.in_flash_sale && product.flash_sale_price
        ? product.flash_sale_price
        : (selectedVariant?.price || product.price);

    const comparePrice = selectedVariant ? selectedVariant.compare_price : product.compare_price;
    const hasDiscount = comparePrice && comparePrice > currentPrice;
    const discountPercent = hasDiscount ? getDiscountPercent(currentPrice, comparePrice!) : 0;

    const stockQuantity = selectedVariant ? selectedVariant.stock_quantity : product.stock_quantity;
    const lowStockThreshold = selectedVariant?.low_stock_threshold ?? product.low_stock_threshold;
    const isOutOfStock = stockQuantity <= 0;

    // Effects
    useEffect(() => {
        // Default to the variant marked as default; fall back to the first value
        // per attribute (only reachable for legacy data with no default flagged).
        const defaultVariant = variants.find(v => v.is_default);

        if (defaultVariant) {
            setSelectedAttributes(defaultVariant.attributes);
        } else if (attributes.length > 0) {
            const defaults: Record<string, string> = {};
            attributes.forEach(attr => {
                if (attr.values.length > 0) {
                    defaults[attr.name] = attr.values[0].value;
                }
            });
            setSelectedAttributes(defaults);
        }

        // Handle recently viewed
        const updatedRecentlyViewed = [
            product,
            ...recentlyViewed.filter(p => p.id !== product.id)
        ].slice(0, 10);
        setRecentlyViewed(updatedRecentlyViewed);
    }, [product.id]);

    // Reset to the first image whenever the selected variant's media set changes.
    useEffect(() => {
        setCurrentImageIndex(0);
    }, [selectedVariant?.id]);

    // Handlers
    const handleAttributeSelect = (name: string, value: string) => {
        setSelectedAttributes(prev => ({ ...prev, [name]: value }));
    };

    const handleAddToCart = () => {
        if (isOutOfStock) return;
        
        addItem({ 
            product, 
            quantity, 
            variant: selectedVariant 
        });
        addToast({
            type: 'success',
            title: 'Added to cart',
            message: `${product.name} added to your cart.`
        });
    };

    const handleBuyNow = () => {
        handleAddToCart();
        // redirect to checkout logic would go here
    };

    const shareProduct = (platform: 'facebook' | 'twitter' | 'copy') => {
        const url = window.location.href;
        if (platform === 'copy') {
            navigator.clipboard.writeText(url);
            addToast({ type: 'info', message: 'Link copied to clipboard!' });
        } else if (platform === 'facebook') {
            window.open(`https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`, '_blank');
        } else if (platform === 'twitter') {
            window.open(`https://twitter.com/intent/tweet?url=${encodeURIComponent(url)}&text=${encodeURIComponent(product.name)}`, '_blank');
        }
    };

    const breadcrumbItems = [
        { title: 'Home', href: route('home') as string },
        ...(product.category ? [{ title: product.category.name, href: route('category.show', product.category.slug) as string }] : []),
        { title: product.name, href: '#' },
    ];

    return (
        <div className="bg-[var(--color-surface-alt)] min-h-screen pb-20">
            <Head title={product.meta_title || product.name}>
                <meta name="description" content={(product.meta_description || product.short_description) ?? undefined} />
            </Head>

            <div className="container mx-auto px-4 py-6">
                <Breadcrumb items={breadcrumbItems} className="mb-8" />

                <div className="grid grid-cols-1 lg:grid-cols-12 gap-4 lg:gap-8 xl:gap-12">
                    {/* Left Column: Media Gallery */}
                    <div className="lg:col-span-7 xl:col-span-8">
                        <div className="flex flex-col md:flex-row gap-4">
                            {/* Thumbnails - Left on desktop */}
                            <div className="order-2 md:order-1 flex md:flex-col gap-3 overflow-x-auto md:overflow-y-auto max-h-[600px] scrollbar-hide py-1">
                                {media.map((item, idx) => (
                                    <button
                                        key={item.id}
                                        onClick={() => setCurrentImageIndex(idx)}
                                        className={`relative shrink-0 w-20 aspect-[4/3] rounded-xl overflow-hidden border-2 transition-all ${
                                            currentImageIndex === idx 
                                                ? 'border-[var(--color-primary)] ring-2 ring-[var(--color-primary-light)]' 
                                                : 'border-transparent hover:border-[var(--color-border)]'
                                        }`}
                                    >
                                        {item.type === 'video' ? (
                                            <div className="w-full h-full bg-slate-900 flex items-center justify-center relative">
                                                <video src={item.path} className="w-full h-full object-cover opacity-50" />
                                                <Film className="size-6 text-white absolute z-10" />
                                            </div>
                                        ) : (
                                            <img src={item.path} alt={item.alt || product.name} className="w-full h-full object-cover" />
                                        )}
                                    </button>
                                ))}
                            </div>

                            {/* Main Media */}
                            <div className="order-1 md:order-2 flex-1 relative aspect-[4/3] rounded-3xl overflow-hidden bg-white shadow-sm group">
                                <AnimatePresence mode="wait">
                                    {currentMedia ? (
                                        <motion.div
                                            key={currentMedia.id}
                                            initial={{ opacity: 0 }}
                                            animate={{ opacity: 1 }}
                                            exit={{ opacity: 0 }}
                                            transition={{ duration: 0.3 }}
                                            className="w-full h-full"
                                        >
                                            {currentMedia.type === 'video' ? (
                                                <video
                                                    src={currentMedia.path}
                                                    className="w-full h-full object-cover"
                                                    controls
                                                    autoPlay
                                                    muted
                                                />
                                            ) : (
                                                <img
                                                    src={currentMedia.path}
                                                    alt={currentMedia.alt || product.name}
                                                    className="w-full h-full object-cover cursor-zoom-in"
                                                    onClick={() => setIsLightboxOpen(true)}
                                                />
                                            )}
                                        </motion.div>
                                    ) : (
                                        <div className="w-full h-full flex flex-col items-center justify-center gap-3 bg-slate-50">
                                            <svg className="size-20 text-slate-200" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                            <span className="text-sm text-slate-400">No media available</span>
                                        </div>
                                    )}
                                </AnimatePresence>

                                {discountPercent > 0 && (
                                    <Badge variant="amber" className="absolute top-6 left-6 px-4 py-1.5 text-sm font-bold shadow-lg">
                                        SALE {discountPercent}% OFF
                                    </Badge>
                                )}

                                {product.in_flash_sale && (
                                    <div className="absolute top-6 right-6 z-10">
                                        <div className="bg-black/70 backdrop-blur-md rounded-2xl p-3 border border-white/20">
                                            <p className="text-[10px] uppercase tracking-widest font-bold text-amber-400 mb-2">⚡ Flash Sale Ends In</p>
                                            <FlashSaleCountdown ends_at={new Date(Date.now() + 86400000).toISOString()} />
                                        </div>
                                    </div>
                                )}

                                {media.length > 0 && currentMedia?.type === 'image' && (
                                    <button
                                        onClick={() => setIsLightboxOpen(true)}
                                        className="absolute bottom-6 right-6 p-3 rounded-full bg-white/90 text-[var(--color-text-primary)] shadow-lg opacity-0 group-hover:opacity-100 transition-opacity"
                                    >
                                        <Maximize2 size={20} />
                                    </button>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Right Column: Product Info */}
                    <div className="lg:col-span-5 xl:col-span-4">
                        <div className="bg-white rounded-3xl p-6 md:p-8 shadow-sm border border-[var(--color-border)] sticky top-24">
                            {/* Brand & Stats */}
                            <div className="flex justify-between items-center mb-4">
                                <Link 
                                    href={route('brand.show', product.brand?.slug || '#')} 
                                    className="text-sm font-bold text-[var(--color-primary)] uppercase tracking-widest hover:underline"
                                >
                                    {product.brand?.name || 'GENUINE PRODUCT'}
                                </Link>
                                <div className="flex items-center gap-4">
                                    <div className="flex items-center gap-1">
                                        <Star size={16} className="fill-[var(--color-accent)] text-[var(--color-accent)]" />
                                        <span className="font-bold text-sm">{product.average_rating?.toFixed(1) || '5.0'}</span>
                                    </div>
                                    <div className="h-4 w-px bg-[var(--color-border)]" />
                                    <span className="text-xs font-medium text-[var(--color-text-muted)] uppercase tracking-wide">
                                        SKU: {selectedVariant?.sku || product.sku || 'N/A'}
                                    </span>
                                </div>
                            </div>

                            <h1 className="text-3xl font-serif font-bold text-[var(--color-text-primary)] leading-tight mb-4">
                                {product.name}
                            </h1>

                            <div className="flex items-baseline gap-4 mb-6">
                                <span className="text-4xl font-bold text-[var(--color-primary)]">
                                    {formatCurrency(currentPrice)}
                                </span>
                                {hasDiscount && (
                                    <span className="text-xl text-[var(--color-text-muted)] line-through">
                                        {formatCurrency(comparePrice!)}
                                    </span>
                                )}
                            </div>

                            <p className="text-[var(--color-text-secondary)] text-sm leading-relaxed mb-8 line-clamp-3">
                                {product.short_description}
                            </p>

                            <div className="h-px bg-[var(--color-border)] w-full mb-8" />

                            {/* Variant Selectors */}
                            <div className="space-y-6 mb-8">
                                {attributes.map((attr) => (
                                    <div key={attr.id} className="space-y-3">
                                        <div className="flex justify-between items-center">
                                            <span className="text-sm font-bold text-[var(--color-text-primary)] uppercase tracking-wide">
                                                Select {attr.name}
                                            </span>
                                            <span className="text-xs text-[var(--color-text-muted)] font-medium">
                                                {selectedAttributes[attr.name]}
                                            </span>
                                        </div>
                                        <div className="flex flex-wrap gap-2">
                                            {attr.values.map((val: AttributeValue) => {
                                                const isSelected = selectedAttributes[attr.name] === val.value;
                                                
                                                if (attr.type === 'color') {
                                                    return (
                                                        <button
                                                            key={val.id}
                                                            onClick={() => handleAttributeSelect(attr.name, val.value)}
                                                            className={`w-10 h-10 rounded-full border-2 transition-all flex items-center justify-center ${
                                                                isSelected 
                                                                    ? 'border-[var(--color-primary)] ring-2 ring-[var(--color-primary-light)]' 
                                                                    : 'border-transparent'
                                                            }`}
                                                            title={val.value}
                                                        >
                                                            <span 
                                                                className="w-7 h-7 rounded-full shadow-inner border border-black/5" 
                                                                style={{ backgroundColor: val.color_hex || '#ccc' }} 
                                                            />
                                                            {isSelected && <Check size={12} className="absolute text-white drop-shadow-md" />}
                                                        </button>
                                                    );
                                                }

                                                return (
                                                    <button
                                                        key={val.id}
                                                        onClick={() => handleAttributeSelect(attr.name, val.value)}
                                                        className={`px-4 py-2 rounded-xl border-2 text-sm font-bold transition-all ${
                                                            isSelected 
                                                                ? 'border-[var(--color-primary)] bg-[var(--color-primary-light)] text-[var(--color-primary)]' 
                                                                : 'border-[var(--color-border)] text-[var(--color-text-secondary)] hover:border-[var(--color-text-primary)]'
                                                        }`}
                                                    >
                                                        {val.value}
                                                    </button>
                                                );
                                            })}
                                        </div>
                                    </div>
                                ))}
                            </div>

                            {/* Stock & Quantity */}
                            <div className="space-y-4 mb-8">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <div className={`w-2.5 h-2.5 rounded-full ${isOutOfStock ? 'bg-[var(--color-error)]' : stockQuantity <= lowStockThreshold ? 'bg-[var(--color-warning)] animate-pulse' : 'bg-[var(--color-success)]'}`} />
                                        <span className={`text-sm font-bold ${isOutOfStock ? 'text-[var(--color-error)]' : stockQuantity <= lowStockThreshold ? 'text-[var(--color-warning)]' : 'text-[var(--color-success)]'}`}>
                                            {isOutOfStock 
                                                ? 'Out of Stock' 
                                                : stockQuantity <= lowStockThreshold 
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
                            <div className="relative flex flex-col gap-3 mb-8">
                                <CryBabyEasterEgg
                                    key={product.id}
                                    productId={product.id}
                                    enabled={Boolean(product.cry_baby_enabled) && !isOutOfStock}
                                />
                                <Button
                                    data-cry-baby-target
                                    onClick={handleAddToCart}
                                    disabled={isOutOfStock}
                                    size="lg" 
                                    className="w-full h-14 rounded-2xl font-bold text-lg flex gap-3 shadow-lg shadow-[var(--color-primary)]/20"
                                >
                                    <ShoppingCart size={22} />
                                    Add to Cart
                                </Button>
                                <Button 
                                    onClick={handleBuyNow}
                                    disabled={isOutOfStock}
                                    variant="secondary" 
                                    size="lg" 
                                    className="w-full h-14 rounded-2xl font-bold text-lg"
                                >
                                    Buy Now
                                </Button>
                                
                                <div className="grid grid-cols-2 gap-3 mt-2">
                                    <Button 
                                        variant="outline" 
                                        onClick={() => toggleWishlist(product.id)}
                                        className={`h-12 rounded-xl flex gap-2 font-semibold ${isWishlisted ? 'text-[var(--color-primary)] border-[var(--color-primary)] bg-[var(--color-primary-light)]' : ''}`}
                                    >
                                        <Heart size={18} fill={isWishlisted ? "currentColor" : "none"} />
                                        {isWishlisted ? 'Wishlisted' : 'Wishlist'}
                                    </Button>
                                    <div className="flex gap-2">
                                        <Button 
                                            variant="outline" 
                                            className="h-12 w-full rounded-xl flex gap-2 font-semibold"
                                            onClick={() => shareProduct('copy')}
                                        >
                                            <Share2 size={18} />
                                            Share
                                        </Button>
                                    </div>
                                </div>
                            </div>

                            {/* Trust Badges */}
                            <div className="grid grid-cols-3 gap-2 pt-6 border-t border-[var(--color-border)]">
                                <div className="flex flex-col items-center text-center gap-1.5">
                                    <div className="w-10 h-10 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center">
                                        <ShieldCheck size={20} />
                                    </div>
                                    <span className="text-[10px] font-bold text-[var(--color-text-primary)] uppercase tracking-tighter">100% Genuine</span>
                                </div>
                                <div className="flex flex-col items-center text-center gap-1.5">
                                    <div className="w-10 h-10 rounded-full bg-green-50 text-green-600 flex items-center justify-center">
                                        <Truck size={20} />
                                    </div>
                                    <span className="text-[10px] font-bold text-[var(--color-text-primary)] uppercase tracking-tighter">Fast Delivery</span>
                                </div>
                                <div className="flex flex-col items-center text-center gap-1.5">
                                    <div className="w-10 h-10 rounded-full bg-amber-50 text-amber-600 flex items-center justify-center">
                                        <Undo2 size={20} />
                                    </div>
                                    <span className="text-[10px] font-bold text-[var(--color-text-primary)] uppercase tracking-tighter">7 Days Return</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Tabs Section */}
                <div className="mt-10 md:mt-16 bg-white rounded-3xl p-4 sm:p-6 md:p-10 shadow-sm border border-[var(--color-border)]">
                    <Tabs defaultValue="description" className="w-full">
                        {/* Phones: three equal columns with short labels. sm+: full labels, left-aligned.
                            The active underline comes from TabsTrigger itself. */}
                        <TabsList className="grid w-full grid-cols-3 border-b border-[var(--color-border)] mb-6 sm:mb-8 sm:flex sm:justify-start sm:gap-8">
                            <TabsTrigger
                                value="description"
                                className="px-1 py-3 text-sm font-bold sm:px-0 sm:py-4 sm:text-lg"
                            >
                                <span className="sm:hidden">Description</span>
                                <span className="hidden sm:inline">Product Description</span>
                            </TabsTrigger>
                            <TabsTrigger
                                value="reviews"
                                className="px-1 py-3 text-sm font-bold sm:px-0 sm:py-4 sm:text-lg"
                            >
                                Reviews
                                <span className="ml-1.5 inline-flex min-w-5 items-center justify-center rounded-full bg-[var(--color-surface-alt)] px-1.5 text-xs font-bold text-[var(--color-text-secondary)] sm:text-sm">
                                    {reviews.length}
                                </span>
                            </TabsTrigger>
                            <TabsTrigger
                                value="shipping"
                                className="px-1 py-3 text-sm font-bold sm:px-0 sm:py-4 sm:text-lg"
                            >
                                <span className="sm:hidden">Shipping</span>
                                <span className="hidden sm:inline">Shipping & Returns</span>
                            </TabsTrigger>
                        </TabsList>

                        <TabsContent value="description" className="mt-0">
                            {/* Admin-authored HTML: keep images, tables and code inside the card on small screens */}
                            <div
                                className="prose prose-sm md:prose-base max-w-none break-words text-[var(--color-text-secondary)] leading-relaxed prose-headings:text-[var(--color-text-primary)] prose-strong:text-[var(--color-text-primary)] prose-a:text-[var(--color-primary)] [&_img]:h-auto [&_img]:max-w-full [&_img]:rounded-xl [&_iframe]:max-w-full [&_video]:max-w-full [&_table]:block [&_table]:max-w-full [&_table]:overflow-x-auto [&_pre]:overflow-x-auto"
                                dangerouslySetInnerHTML={{ __html: product.description || '<p>No description available.</p>' }}
                            />
                        </TabsContent>

                        <TabsContent value="reviews" className="mt-0">
                            <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12">
                                <div className="min-w-0 lg:col-span-4">
                                    <RatingBreakdown breakdown={ratingBreakdown} />
                                    <div className="mt-6 sm:mt-8 rounded-2xl border border-[var(--color-border)] bg-white p-4 sm:p-6">
                                        <h4 className="font-bold text-[var(--color-text-primary)] mb-1 flex items-center gap-2">
                                            <MessageSquare size={16} className="text-[var(--color-primary)]" />
                                            Write a Review
                                        </h4>

                                        {!auth.user ? (
                                            <div className="mt-3">
                                                <p className="text-sm text-[var(--color-text-secondary)] mb-3">
                                                    Please log in to share your experience.
                                                </p>
                                                <Link
                                                    href={route('login')}
                                                    className="inline-flex items-center gap-1.5 rounded-xl bg-[var(--color-primary)] px-4 py-2 text-sm font-bold text-white hover:bg-[var(--color-primary-hover)] transition-colors"
                                                >
                                                    Log in to review
                                                </Link>
                                            </div>
                                        ) : hasReviewed ? (
                                            <p className="mt-3 text-sm text-[var(--color-success)] flex items-center gap-1.5">
                                                <Check size={14} />
                                                You have already reviewed this product.
                                            </p>
                                        ) : (
                                            <form onSubmit={submitReview} className="mt-4 space-y-4">
                                                {/* Star picker */}
                                                <div className="space-y-1">
                                                    <span className="text-xs font-semibold text-[var(--color-text-muted)] uppercase tracking-wider">Your Rating *</span>
                                                    <StarRating
                                                        rating={reviewRating}
                                                        interactive
                                                        size="lg"
                                                        onChange={setReviewRating}
                                                    />
                                                    {reviewForm.errors.rating && (
                                                        <p className="text-xs text-red-500">{reviewForm.errors.rating}</p>
                                                    )}
                                                </div>

                                                <div className="space-y-1">
                                                    <label className="text-xs font-semibold text-[var(--color-text-muted)] uppercase tracking-wider">
                                                        Title <span className="normal-case font-normal">(optional)</span>
                                                    </label>
                                                    <input
                                                        type="text"
                                                        value={reviewForm.data.title}
                                                        onChange={(e) => reviewForm.setData('title', e.target.value)}
                                                        placeholder="Summarise your experience"
                                                        maxLength={255}
                                                        className="h-10 w-full rounded-xl border border-[var(--color-border)] px-3 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]/40"
                                                    />
                                                </div>

                                                <div className="space-y-1">
                                                    <label className="text-xs font-semibold text-[var(--color-text-muted)] uppercase tracking-wider">
                                                        Review *
                                                    </label>
                                                    <textarea
                                                        value={reviewForm.data.body}
                                                        onChange={(e) => reviewForm.setData('body', e.target.value)}
                                                        placeholder="Tell others what you think about this product…"
                                                        rows={4}
                                                        maxLength={2000}
                                                        className="w-full rounded-xl border border-[var(--color-border)] px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]/40 resize-none"
                                                    />
                                                    {reviewForm.errors.body && (
                                                        <p className="text-xs text-red-500">{reviewForm.errors.body}</p>
                                                    )}
                                                    {reviewForm.errors.review && (
                                                        <p className="text-xs text-red-500">{reviewForm.errors.review}</p>
                                                    )}
                                                </div>

                                                <Button
                                                    type="submit"
                                                    disabled={reviewSubmitting || reviewRating === 0}
                                                    className="w-full rounded-xl font-bold"
                                                >
                                                    {reviewSubmitting ? 'Submitting…' : 'Submit Review'}
                                                </Button>
                                            </form>
                                        )}
                                    </div>
                                </div>
                                <div className="min-w-0 lg:col-span-8">
                                    <div className="flex flex-wrap items-center justify-between gap-3 mb-6 sm:mb-8 pb-4 border-b border-[var(--color-border)]">
                                        <h3 className="text-lg sm:text-xl font-bold text-[var(--color-text-primary)]">User Reviews</h3>
                                        <div className="flex items-center gap-2">
                                            <span className="hidden sm:inline text-sm text-[var(--color-text-muted)] font-medium">Sort by:</span>
                                            <Select
                                                aria-label="Sort reviews"
                                                containerClassName="min-w-36"
                                                selectClassName="h-9 rounded-full px-3.5 text-sm font-bold"
                                            >
                                                <option>Newest First</option>
                                                <option>Highest Rated</option>
                                                <option>Lowest Rated</option>
                                                <option>Most Helpful</option>
                                            </Select>
                                        </div>
                                    </div>

                                    {reviews.length > 0 ? (
                                        <div className="space-y-2">
                                            {reviews.map(review => (
                                                <ReviewCard key={review.id} review={review} />
                                            ))}
                                        </div>
                                    ) : (
                                        <div className="text-center px-4 py-12 sm:py-20 bg-[var(--color-surface-alt)] rounded-3xl">
                                            <div className="size-14 sm:size-16 rounded-full bg-white flex items-center justify-center mx-auto mb-4 text-[var(--color-text-muted)]">
                                                <MessageSquare className="size-7 sm:size-8" />
                                            </div>
                                            <h4 className="font-bold text-[var(--color-text-primary)] mb-1">No reviews yet</h4>
                                            <p className="text-sm text-[var(--color-text-muted)]">Be the first to review this product!</p>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </TabsContent>

                        <TabsContent value="shipping" className="mt-0">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-8 md:gap-12">
                                <div className="min-w-0">
                                    <h4 className="text-lg sm:text-xl font-bold text-[var(--color-text-primary)] mb-4 sm:mb-6 flex items-center gap-2">
                                        <Truck className="size-5 sm:size-6 shrink-0 text-[var(--color-primary)]" />
                                        Delivery Options
                                    </h4>
                                    <div className="space-y-4">
                                        {shippingMethods.map(method => (
                                            <div key={method.id} className="p-4 rounded-2xl border border-[var(--color-border)] flex justify-between items-center">
                                                <div>
                                                    <p className="font-bold text-[var(--color-text-primary)]">{method.name}</p>
                                                    <p className="text-xs text-[var(--color-text-muted)]">{method.estimated_days} business days</p>
                                                </div>
                                                <span className="font-bold text-[var(--color-primary)]">{formatCurrency(method.cost)}</span>
                                            </div>
                                        ))}
                                        {shippingMethods.length === 0 && (
                                            <>
                                                <div className="p-4 rounded-2xl border border-[var(--color-border)] flex justify-between items-center">
                                                    <div>
                                                        <p className="font-bold text-[var(--color-text-primary)]">Inside Dhaka</p>
                                                        <p className="text-xs text-[var(--color-text-muted)]">1-2 business days</p>
                                                    </div>
                                                    <span className="font-bold text-[var(--color-primary)]">৳60.00</span>
                                                </div>
                                                <div className="p-4 rounded-2xl border border-[var(--color-border)] flex justify-between items-center">
                                                    <div>
                                                        <p className="font-bold text-[var(--color-text-primary)]">Outside Dhaka</p>
                                                        <p className="text-xs text-[var(--color-text-muted)]">3-5 business days</p>
                                                    </div>
                                                    <span className="font-bold text-[var(--color-primary)]">৳120.00</span>
                                                </div>
                                            </>
                                        )}
                                    </div>
                                </div>
                                <div className="min-w-0">
                                    <h4 className="text-lg sm:text-xl font-bold text-[var(--color-text-primary)] mb-4 sm:mb-6 flex items-center gap-2">
                                        <Undo2 className="size-5 sm:size-6 shrink-0 text-[var(--color-primary)]" />
                                        Return Policy
                                    </h4>
                                    <div className="space-y-5 sm:space-y-6 text-[var(--color-text-secondary)] text-sm">
                                        <p>You can return the item within <strong>7 days</strong> of delivery if it's in original condition with tags and packaging.</p>
                                        <div className="grid grid-cols-2 gap-4">
                                            <div className="flex gap-2">
                                                <div className="shrink-0 w-5 h-5 rounded-full bg-green-100 text-green-600 flex items-center justify-center">
                                                    <Check size={12} />
                                                </div>
                                                <span>Original Tags</span>
                                            </div>
                                            <div className="flex gap-2">
                                                <div className="shrink-0 w-5 h-5 rounded-full bg-green-100 text-green-600 flex items-center justify-center">
                                                    <Check size={12} />
                                                </div>
                                                <span>Unwashed</span>
                                            </div>
                                            <div className="flex gap-2">
                                                <div className="shrink-0 w-5 h-5 rounded-full bg-green-100 text-green-600 flex items-center justify-center">
                                                    <Check size={12} />
                                                </div>
                                                <span>Not Used</span>
                                            </div>
                                            <div className="flex gap-2">
                                                <div className="shrink-0 w-5 h-5 rounded-full bg-green-100 text-green-600 flex items-center justify-center">
                                                    <Check size={12} />
                                                </div>
                                                <span>Sealed Box</span>
                                            </div>
                                        </div>
                                        <p className="p-4 rounded-2xl bg-amber-50 border border-amber-200 text-amber-800 text-xs italic">
                                            * Change of mind is not applicable for electronics and hygiene products once opened.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </TabsContent>
                    </Tabs>
                </div>

                {/* Carousels */}
                <ProductCarousel title="You May Also Like" products={relatedProducts} className="mt-16" />
                
                {recentlyViewed.length > 1 && (
                    <ProductCarousel 
                        title="Recently Viewed" 
                        products={recentlyViewed.filter(p => p.id !== product.id)} 
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
                        <div className="flex items-center justify-between p-6 text-white" onClick={e => e.stopPropagation()}>
                            <span className="font-bold">{currentImageIndex + 1} / {media.length}</span>
                            <button onClick={() => setIsLightboxOpen(false)} className="p-2 rounded-full hover:bg-white/10 transition-colors">
                                <X size={28} />
                            </button>
                        </div>

                        <div className="flex flex-1 items-center justify-center relative px-4 sm:px-16" onClick={e => e.stopPropagation()}>
                            <button
                                onClick={() => setCurrentImageIndex(prev => (prev > 0 ? prev - 1 : media.length - 1))}
                                className="absolute left-4 sm:left-6 p-3 rounded-full bg-white/10 hover:bg-white/20 text-white transition-colors"
                            >
                                <ChevronLeft size={32} />
                            </button>
                            {currentMedia?.type === 'video' ? (
                                <video
                                    src={currentMedia.path}
                                    className="max-w-full max-h-[80vh] object-contain"
                                    controls
                                    autoPlay
                                />
                            ) : (
                                <motion.img
                                    key={currentMedia?.id}
                                    src={currentMedia?.path}
                                    alt={currentMedia?.alt || product.name}
                                    className="max-w-full max-h-[80vh] object-contain"
                                    initial={{ opacity: 0, scale: 0.95 }}
                                    animate={{ opacity: 1, scale: 1 }}
                                    transition={{ type: 'spring', damping: 25, stiffness: 300 }}
                                />
                            )}
                            <button
                                onClick={() => setCurrentImageIndex(prev => (prev < media.length - 1 ? prev + 1 : 0))}
                                className="absolute right-4 sm:right-6 p-3 rounded-full bg-white/10 hover:bg-white/20 text-white transition-colors"
                            >
                                <ChevronRight size={32} />
                            </button>
                        </div>

                        <div className="flex justify-center gap-2 p-6 overflow-x-auto" onClick={e => e.stopPropagation()}>
                            {media.map((item, idx) => (
                                <button
                                    key={item.id}
                                    onClick={() => setCurrentImageIndex(idx)}
                                    className={`relative w-14 h-14 rounded-lg overflow-hidden border-2 shrink-0 transition-all ${currentImageIndex === idx ? 'border-[var(--color-primary)]' : 'border-white/20'}`}
                                >
                                    {item.type === 'video' ? (
                                        <div className="w-full h-full bg-slate-900 flex items-center justify-center">
                                            <video src={item.path} className="w-full h-full object-cover opacity-50" />
                                            <Film size={16} className="text-white absolute z-10" />
                                        </div>
                                    ) : (
                                        <img src={item.path} alt={item.alt || product.name} className="w-full h-full object-cover" />
                                    )}
                                </button>
                            ))}
                        </div>
                    </motion.div>
                )}
            </AnimatePresence>
        </div>
    );
}
