import { Head, Link, router } from '@inertiajs/react';
import { ArrowRight, Heart, Search, ShoppingCart, Sparkles, Trash2 } from 'lucide-react';
import React from 'react';

import AccountLayout from '@/components/layout/AccountLayout';
import { ProductCard } from '@/components/shared/ProductCard';
import { Button } from '@/components/ui/button';
import { useCartStore } from '@/stores/cartStore';
import { useUiStore } from '@/stores/uiStore';
import { useWishlistStore } from '@/stores/wishlistStore';
import { PRODUCT_GRID_BESIDE_SIDEBAR_CLASS } from '@/lib/constants';
import { formatCurrency } from '@/lib/utils';
import type { Product } from '@/types';

interface WishlistProps {
    wishlist: Product[];
}

export default function Wishlist({ wishlist = [] }: WishlistProps): React.JSX.Element {
    const toggleWishlist = useWishlistStore((state) => state.toggleWishlist);
    const clearWishlist = useWishlistStore((state) => state.clearWishlist);
    const addItem = useCartStore((state) => state.addItem);
    const openCart = useUiStore((state) => state.openCart);
    const addToast = useUiStore((state) => state.addToast);

    const handleAddToCart = (product: Product) => {
        addItem({ product, quantity: 1 });
        addToast({ type: 'success', message: `${product.name} added to cart` });
    };

    const hydrateCart = useCartStore((state) => state.hydrateCart);

    return (
        <AccountLayout>
            <Head title="My Wishlist" />

            <div className="space-y-8">
                {/* Header */}
                <div className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div>
                        <h1 className="text-3xl font-bold text-[var(--color-text-primary)]">My Wishlist</h1>
                        <p className="mt-1 text-[var(--color-text-secondary)]">
                            {wishlist.length > 0
                                ? `${wishlist.length} ${wishlist.length === 1 ? 'item' : 'items'} saved — add them to cart when you're ready.`
                                : 'Save products you love and come back to them anytime.'}
                        </p>
                    </div>
                    {wishlist.length > 0 && (
                        <Button asChild className="rounded-xl font-semibold">
                            <Link href={route('products.index')}>
                                <Search className="mr-2 size-4" />
                                Discover More Products
                            </Link>
                        </Button>
                    )}
                </div>

                {wishlist.length > 0 ? (
                    <>
                        {/* Summary strip */}
                        <div className="flex items-center gap-4 rounded-2xl border border-[var(--color-border)] bg-[var(--color-primary)]/5 px-5 py-3">
                            <Heart className="size-5 shrink-0 fill-[var(--color-primary)] text-[var(--color-primary)]" />
                            <p className="flex-1 text-sm text-[var(--color-text-secondary)]">
                                <span className="font-semibold text-[var(--color-text-primary)]">{wishlist.length} saved {wishlist.length === 1 ? 'item' : 'items'}</span>
                                {' · '}
                                Total value:{' '}
                                <span className="font-semibold text-[var(--color-primary)]">
                                    {formatCurrency(wishlist.reduce((s, p) => s + p.price, 0))}
                                </span>
                            </p>
                            <Button
                                size="sm"
                                variant="outline"
                                className="shrink-0 rounded-xl text-xs"
                                onClick={() => {
                                    router.post(route('wishlist.add-all-to-cart'), {}, {
                                        preserveScroll: true,
                                        onSuccess: (page) => {
                                            const props = page.props as unknown as SharedPageProps;
                                            if (props.cart) {
                                                hydrateCart(props.cart);
                                            }
                                            addToast({ type: 'success', message: 'All items added to cart' });
                                        }
                                    });
                                }}
                            >
                                <ShoppingCart className="mr-1.5 size-3.5" />
                                Add All to Cart
                            </Button>
                        </div>

                        {/* Product grid with per-item remove */}
                        <div className={PRODUCT_GRID_BESIDE_SIDEBAR_CLASS}>
                            {wishlist.map((product, index) => (
                                <div key={product.id} className="group relative">
                                    <ProductCard product={product} index={index} />
                                    <button
                                        className="absolute left-3 top-3 z-20 flex size-8 items-center justify-center rounded-full bg-white/95 text-rose-500 opacity-0 shadow-md transition-all hover:bg-rose-500 hover:text-white group-hover:opacity-100"
                                        onClick={() => toggleWishlist(product.id)}
                                        title="Remove from wishlist"
                                    >
                                        <Trash2 className="size-3.5" />
                                    </button>
                                </div>
                            ))}
                        </div>

                    </>
                ) : (
                    /* Empty state */
                    <div className="relative overflow-hidden rounded-3xl border border-[var(--color-border)] bg-[var(--color-surface-alt)] px-6 py-20 text-center">
                        {/* Decorative blobs */}
                        <div className="pointer-events-none absolute -top-12 left-1/4 h-48 w-48 rounded-full bg-[var(--color-primary)] opacity-[0.04] blur-3xl" />
                        <div className="pointer-events-none absolute -bottom-8 right-1/4 h-40 w-40 rounded-full bg-[var(--color-accent)] opacity-[0.04] blur-3xl" />

                        <div className="relative mx-auto mb-6 flex size-24 items-center justify-center rounded-full bg-[var(--color-primary)]/10">
                            <div className="flex size-16 items-center justify-center rounded-full bg-[var(--color-primary)]/15">
                                <Heart className="size-8 text-[var(--color-primary)]" />
                            </div>
                        </div>

                        <h3 className="text-2xl font-bold text-[var(--color-text-primary)]">Your wishlist is empty</h3>
                        <p className="mx-auto mt-3 max-w-sm text-[var(--color-text-secondary)]">
                            Tap the heart icon on any product to save it here. Never lose track of items you love.
                        </p>

                        {/* Feature highlights */}
                        <div className="mx-auto mt-8 grid max-w-md grid-cols-3 gap-4 text-center">
                            {[
                                { icon: Heart, text: 'Save favorites', sub: 'Tap ♡ on any product' },
                                { icon: Sparkles, text: 'Track prices', sub: 'Get notified on deals' },
                                { icon: ShoppingCart, text: 'Easy checkout', sub: 'Add all items at once' },
                            ].map(({ icon: Icon, text, sub }) => (
                                <div key={text} className="space-y-1.5 rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)] p-4">
                                    <Icon className="mx-auto size-5 text-[var(--color-primary)]" />
                                    <p className="text-xs font-semibold text-[var(--color-text-primary)]">{text}</p>
                                    <p className="text-[11px] text-[var(--color-text-muted)]">{sub}</p>
                                </div>
                            ))}
                        </div>

                        <Button asChild size="lg" className="mt-10 rounded-2xl px-10 font-semibold">
                            <Link href={route('products.index')}>
                                Start Exploring
                                <ArrowRight className="ml-2 size-4" />
                            </Link>
                        </Button>
                    </div>
                )}
            </div>
        </AccountLayout>
    );
}
