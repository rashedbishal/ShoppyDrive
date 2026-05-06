import { Link } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import { Heart, ShoppingCart, Star, GitCompare } from 'lucide-react';
import React, { useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatCurrency, getDiscountPercent } from '@/lib/utils';
import { useCartStore } from '@/stores/cartStore';
import { useUiStore } from '@/stores/uiStore';
import { useWishlistStore } from '@/stores/wishlistStore';
import type { Product } from '@/types';

interface ProductCardProps {
    product: Product;
    view?: 'grid' | 'list';
    className?: string;
}

export function ProductCard({
    product,
    view = 'grid',
    className,
}: ProductCardProps): React.JSX.Element {
    const [isHovered, setIsHovered] = useState(false);
    
    const addItem = useCartStore((state) => state.addItem);
    const openCart = useUiStore((state) => state.openCart);
    const addToast = useUiStore((state) => state.addToast);
    const toggleWishlist = useWishlistStore((state) => state.toggleWishlist);
    const isWishlisted = useWishlistStore((state) => state.isWishlisted(product.id));
    
    const addCompareItem = useUiStore((state) => state.addCompareItem);
    const removeCompareItem = useUiStore((state) => state.removeCompareItem);
    const isComparing = useUiStore((state) => state.hasCompareItem(product.id));

    const images = Array.isArray(product.images) ? product.images : [];
    const primaryImage = images.find((img) => img.is_primary) ?? images[0];
    const secondaryImage = images.length > 1 ? images.find((img) => !img.is_primary) : null;
    
    const currentPrice = product.in_flash_sale && product.flash_sale_price 
        ? product.flash_sale_price 
        : product.price;
        
    const hasDiscount = product.compare_price && product.compare_price > currentPrice;
    const discountPercent = hasDiscount 
        ? getDiscountPercent(currentPrice, product.compare_price!) 
        : 0;

    const isOutOfStock = product.stock_quantity <= 0;

    const handleAddToCart = (e: React.MouseEvent) => {
        e.preventDefault();
        e.stopPropagation();
        
        if (isOutOfStock) return;

        addItem({ product, quantity: 1 });
        openCart();
        addToast({
            type: 'success',
            title: 'Added to cart',
            message: `${product.name} has been added to your cart.`,
        });
    };

    const handleWishlist = (e: React.MouseEvent) => {
        e.preventDefault();
        e.stopPropagation();
        
        toggleWishlist(product.id);
        addToast({
            type: isWishlisted ? 'info' : 'success',
            title: isWishlisted ? 'Removed from wishlist' : 'Added to wishlist',
            message: isWishlisted 
                ? `${product.name} removed from your wishlist.` 
                : `${product.name} added to your wishlist.`,
        });
    };

    const handleCompare = (e: React.MouseEvent) => {
        e.preventDefault();
        e.stopPropagation();
        
        if (isComparing) {
            removeCompareItem(product.id);
        } else {
            addCompareItem(product);
            addToast({
                type: 'success',
                title: 'Added to compare',
                message: `${product.name} added to comparison list.`,
            });
        }
    };

    if (view === 'list') {
        return (
            <motion.article
                layout
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                className={`group relative flex flex-col sm:flex-row gap-6 p-4 rounded-2xl bg-[var(--color-surface)] border border-[var(--color-border)] shadow-[var(--shadow-card)] hover:shadow-[var(--shadow-card-hover)] transition-all duration-300 ${className}`}
            >
                <Link 
                    href={route('product.show', product.slug)}
                    className="relative w-full sm:w-64 aspect-[4/3] rounded-xl overflow-hidden bg-[var(--color-surface-alt)] shrink-0"
                >
                    <AnimatePresence mode="wait">
                        <motion.img
                            key={isHovered && secondaryImage ? secondaryImage.path : primaryImage?.path}
                            src={isHovered && secondaryImage ? secondaryImage.path : primaryImage?.path}
                            alt={product.name}
                            className="w-full h-full object-cover"
                            initial={{ opacity: 0 }}
                            animate={{ opacity: 1 }}
                            exit={{ opacity: 0 }}
                            transition={{ duration: 0.3 }}
                        />
                    </AnimatePresence>

                    {discountPercent > 0 && (
                        <Badge 
                            variant="amber" 
                            className="absolute top-3 left-3 z-10"
                        >
                            SALE {discountPercent}%
                        </Badge>
                    )}

                    <div className="absolute top-3 right-3 z-10 flex flex-col gap-2">
                        <button
                            onClick={handleWishlist}
                            className={`p-2 rounded-full shadow-sm transition-all duration-200 ${
                                isWishlisted 
                                    ? 'bg-[var(--color-primary)] text-white' 
                                    : 'bg-white/80 text-[var(--color-text-secondary)] hover:text-[var(--color-primary)]'
                            }`}
                            aria-label={isWishlisted ? "Remove from wishlist" : "Add to wishlist"}
                        >
                            <Heart size={18} fill={isWishlisted ? "currentColor" : "none"} />
                        </button>
                        <button
                            onClick={handleCompare}
                            className={`p-2 rounded-full shadow-sm transition-all duration-200 ${
                                isComparing 
                                    ? 'bg-amber-400 text-[var(--color-secondary)]' 
                                    : 'bg-white/80 text-[var(--color-text-secondary)] hover:text-amber-500'
                            }`}
                            aria-label={isComparing ? "Remove from compare" : "Add to compare"}
                        >
                            <GitCompare size={18} />
                        </button>
                    </div>

                    {isOutOfStock && (
                        <div className="absolute inset-0 z-20 bg-black/40 flex items-center justify-center backdrop-blur-[2px]">
                            <Badge variant="error" className="px-3 py-1 text-sm uppercase tracking-wider font-bold">
                                Out of Stock
                            </Badge>
                        </div>
                    )}
                </Link>

                <div className="flex flex-col flex-1 justify-between py-1">
                    <div>
                        <div className="flex justify-between items-start mb-2">
                            <span className="text-xs text-[var(--color-text-muted)] uppercase tracking-widest font-medium">
                                {product.brand?.name || 'Generic'}
                            </span>
                            <div className="flex items-center gap-1">
                                <Star size={14} className="fill-[var(--color-accent)] text-[var(--color-accent)]" />
                                <span className="text-sm font-semibold text-[var(--color-text-primary)]">
                                    {product.average_rating || '5.0'}
                                </span>
                                <span className="text-xs text-[var(--color-text-muted)]">
                                    ({product.reviews_count || 0})
                                </span>
                            </div>
                        </div>

                        <Link href={route('product.show', product.slug)}>
                            <h3 className="text-lg font-bold text-[var(--color-text-primary)] hover:text-[var(--color-primary)] transition-colors line-clamp-2 mb-2">
                                {product.name}
                            </h3>
                        </Link>
                        
                        <p className="text-sm text-[var(--color-text-secondary)] line-clamp-3 mb-4">
                            {product.short_description}
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center justify-between gap-4 mt-auto pt-4 border-t border-[var(--color-border)]">
                        <div className="flex flex-col">
                            <div className="flex items-center gap-2">
                                <span className="text-2xl font-bold text-[var(--color-primary)]">
                                    {formatCurrency(currentPrice)}
                                </span>
                                {hasDiscount && (
                                    <span className="text-sm text-[var(--color-text-muted)] line-through">
                                        {formatCurrency(product.compare_price!)}
                                    </span>
                                )}
                            </div>
                            <span className={`text-xs font-medium mt-0.5 ${isOutOfStock ? 'text-[var(--color-error)]' : 'text-[var(--color-success)]'}`}>
                                {isOutOfStock ? 'Currently Unavailable' : 'In Stock - Ready to Ship'}
                            </span>
                        </div>

                        <Button
                            onClick={handleAddToCart}
                            disabled={isOutOfStock}
                            className="px-8 h-12 rounded-xl font-bold flex gap-2 items-center"
                        >
                            <ShoppingCart size={18} />
                            Add to Cart
                        </Button>
                    </div>
                </div>
            </motion.article>
        );
    }

    // Grid View (Default)
    return (
        <motion.article
            onMouseEnter={() => setIsHovered(true)}
            onMouseLeave={() => setIsHovered(false)}
            initial={{ opacity: 0, scale: 0.95 }}
            animate={{ opacity: 1, scale: 1 }}
            whileHover={{ y: -8 }}
            className={`group relative rounded-2xl bg-[var(--color-surface)] shadow-[var(--shadow-card)] hover:shadow-[var(--shadow-card-hover)] transition-all duration-300 overflow-hidden border border-[var(--color-border)] flex flex-col h-full ${className}`}
        >
            <Link 
                href={route('product.show', product.slug)}
                className="relative aspect-[4/3] overflow-hidden bg-[var(--color-surface-alt)]"
            >
                <AnimatePresence mode="wait">
                    <motion.img
                        key={isHovered && secondaryImage ? secondaryImage.path : primaryImage?.path}
                        src={isHovered && secondaryImage ? secondaryImage.path : primaryImage?.path}
                        alt={product.name}
                        className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110"
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        exit={{ opacity: 0 }}
                        transition={{ duration: 0.3 }}
                    />
                </AnimatePresence>

                {discountPercent > 0 && (
                    <Badge 
                        variant="amber" 
                        className="absolute top-3 left-3 z-10"
                    >
                        SALE {discountPercent}%
                    </Badge>
                )}

                <div className="absolute top-3 right-3 z-10 flex flex-col gap-2">
                    <button
                        onClick={handleWishlist}
                        className={`p-2 rounded-full shadow-sm transition-all duration-200 ${
                            isWishlisted 
                                ? 'bg-[var(--color-primary)] text-white' 
                                : 'bg-white/80 text-[var(--color-text-secondary)] hover:text-[var(--color-primary)]'
                        }`}
                        aria-label={isWishlisted ? "Remove from wishlist" : "Add to wishlist"}
                    >
                        <Heart size={18} fill={isWishlisted ? "currentColor" : "none"} />
                    </button>
                    <button
                        onClick={handleCompare}
                        className={`p-2 rounded-full shadow-sm transition-all duration-200 ${
                            isComparing 
                                ? 'bg-amber-400 text-[var(--color-secondary)]' 
                                : 'bg-white/80 text-[var(--color-text-secondary)] hover:text-amber-500'
                        }`}
                        aria-label={isComparing ? "Remove from compare" : "Add to compare"}
                    >
                        <GitCompare size={18} />
                    </button>
                </div>

                {isOutOfStock && (
                    <div className="absolute inset-0 z-20 bg-black/40 flex items-center justify-center backdrop-blur-[2px]">
                        <Badge variant="error" className="px-3 py-1 text-sm uppercase tracking-wider font-bold">
                            Out of Stock
                        </Badge>
                    </div>
                )}
                
                {/* Quick add button that slides up on hover */}
                <div className="absolute bottom-0 inset-x-0 p-3 translate-y-full group-hover:translate-y-0 transition-transform duration-300 z-10">
                    <Button
                        onClick={handleAddToCart}
                        disabled={isOutOfStock}
                        className="w-full shadow-lg h-11 rounded-xl font-bold flex gap-2 items-center justify-center"
                    >
                        <ShoppingCart size={16} />
                        Quick Add
                    </Button>
                </div>
            </Link>

            <div className="p-4 flex flex-col flex-1">
                <div className="flex justify-between items-start mb-1">
                    <span className="text-[10px] text-[var(--color-text-muted)] uppercase tracking-widest font-bold">
                        {product.brand?.name || 'Generic'}
                    </span>
                    <div className="flex items-center gap-1">
                        <Star size={12} className="fill-[var(--color-accent)] text-[var(--color-accent)]" />
                        <span className="text-xs font-bold text-[var(--color-text-primary)]">
                            {product.average_rating || '5.0'}
                        </span>
                    </div>
                </div>

                <Link href={route('product.show', product.slug)} className="mb-2 block group-hover:text-[var(--color-primary)] transition-colors">
                    <h3 className="text-sm font-semibold text-[var(--color-text-primary)] line-clamp-2 leading-snug">
                        {product.name}
                    </h3>
                </Link>

                <div className="mt-auto flex items-center justify-between gap-2">
                    <div className="flex flex-col">
                        <span className="text-lg font-bold text-[var(--color-primary)] leading-tight">
                            {formatCurrency(currentPrice)}
                        </span>
                        {hasDiscount && (
                            <span className="text-xs text-[var(--color-text-muted)] line-through">
                                {formatCurrency(product.compare_price!)}
                            </span>
                        )}
                    </div>
                    {!isHovered && (
                        <div className="h-8 w-8 rounded-full border border-[var(--color-border)] flex items-center justify-center text-[var(--color-text-muted)]">
                           <ShoppingCart size={14} />
                        </div>
                    )}
                </div>
            </div>
        </motion.article>
    );
}

