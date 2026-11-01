import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Heart } from 'lucide-react';
import React from 'react';

import { Button } from '@/components/ui/button';
import { useWishlistStore } from '@/stores/wishlistStore';

export default function GuestWishlist(): React.JSX.Element {
    const savedCount = useWishlistStore((state) => state.productIds.length);

    return (
        <div className="min-h-screen bg-[var(--color-surface)] py-12">
            <Head title="My Wishlist" />

            <div className="mx-auto max-w-xl px-4 text-center sm:px-6">
                <div className="mx-auto mb-6 flex size-20 items-center justify-center rounded-full bg-[var(--color-primary)]/10">
                    <Heart className="size-8 text-[var(--color-primary)]" />
                </div>

                <h1 className="text-3xl font-bold text-[var(--color-text-primary)]">
                    My Wishlist
                </h1>

                <p className="mt-3 text-[var(--color-text-secondary)]">
                    {savedCount > 0
                        ? `You have ${savedCount} ${savedCount === 1 ? 'item' : 'items'} saved on this device. Sign in to see them and keep them on your account.`
                        : 'Tap the heart icon on any product to save it here, or sign in to see your saved items.'}
                </p>

                <div className="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                    {/* The protected route remembers where to return after login. */}
                    <Button
                        asChild
                        size="lg"
                        className="rounded-xl font-semibold"
                    >
                        <Link href={route('account.wishlist')}>Sign In</Link>
                    </Button>
                    <Button
                        asChild
                        size="lg"
                        variant="outline"
                        className="rounded-xl font-semibold"
                    >
                        <Link href={route('register')}>Create Account</Link>
                    </Button>
                </div>

                <Link
                    href={route('products.index')}
                    className="mt-6 inline-flex items-center text-sm font-semibold text-[var(--color-primary)] hover:underline"
                >
                    Continue Shopping
                    <ArrowRight className="ml-1.5 size-4" />
                </Link>
            </div>
        </div>
    );
}
