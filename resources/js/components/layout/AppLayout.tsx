import { AnimatePresence, motion } from 'framer-motion';
import { usePage } from '@inertiajs/react';
import { useEffect, type ReactNode } from 'react';
import Footer from '@/components/layout/Footer';
import Header from '@/components/layout/Header';
import CartDrawer from '@/components/layout/CartDrawer';
import CookieConsent from '@/components/layout/CookieConsent';
import NProgress from '@/components/layout/NProgress';
import { CompareBar } from '@/components/shared/CompareBar';
import { useUiStore } from '@/stores/uiStore';
import { useWishlistStore } from '@/stores/wishlistStore';
import { MOTION } from '@/lib/constants';
import type { SharedPageProps } from '@/types';

type AppLayoutProps = {
    children: ReactNode;
};

export default function AppLayout({ children }: AppLayoutProps): React.JSX.Element {
    const page = usePage<SharedPageProps>();
    const compareItems = useUiStore((state) => state.compareItems);
    const setWishlist = useWishlistStore((state) => state.setWishlist);

    // Sync wishlist from server on every navigation
    useEffect(() => {
        const ids = page.props.wishlist_ids;
        if (Array.isArray(ids)) {
            setWishlist(ids);
        }
    }, [page.props.wishlist_ids]);

    return (
        <div className="flex min-h-screen flex-col bg-[var(--color-surface)] text-[var(--color-text-primary)]">
            <NProgress />
            <Header />

            <AnimatePresence mode="wait" initial={false}>
                <motion.main
                    key={page.url}
                    className="flex-1"
                    initial={{ opacity: 0, y: MOTION.page?.distance ?? 8 }}
                    animate={{ opacity: 1, y: 0 }}
                    exit={{ opacity: 0, y: -(MOTION.page?.distance ?? 8) }}
                    transition={{ duration: MOTION.page?.duration ?? 0.22, ease: 'easeOut' }}
                >
                    {children}
                </motion.main>
            </AnimatePresence>

            <Footer />
            <CartDrawer />
            <CompareBar products={compareItems} />
            <CookieConsent />
        </div>
    );
}
