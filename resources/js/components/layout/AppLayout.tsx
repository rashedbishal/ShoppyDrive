import { usePage } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import { useEffect } from 'react';
import type { ReactNode } from 'react';
import AuroraBackdrop from '@/components/layout/AuroraBackdrop';
import CartDrawer from '@/components/layout/CartDrawer';
import CookieConsent from '@/components/layout/CookieConsent';
import Footer from '@/components/layout/Footer';
import Header from '@/components/layout/Header';
import SocialChatWidget from '@/components/layout/SocialChatWidget';
import { MOTION } from '@/lib/constants';
import { useWishlistStore } from '@/stores/wishlistStore';
import type { SharedPageProps } from '@/types';

type AppLayoutProps = {
    children: ReactNode;
};

export default function AppLayout({
    children,
}: AppLayoutProps): React.JSX.Element {
    const page = usePage<SharedPageProps>();
    const setWishlist = useWishlistStore((state) => state.setWishlist);

    // Sync wishlist from server; merge guest wishlist into account on login
    useEffect(() => {
        const user = page.props.auth?.user;

        // Guests: never override localStorage wishlist from server
        if (!user) {
            return;
        }

        const serverIds: number[] = Array.isArray(page.props.wishlist_ids)
            ? (page.props.wishlist_ids as number[])
            : [];

        const pendingRaw = localStorage.getItem(
            'shoppydrive.wishlist.pending_merge',
        );

        if (pendingRaw) {
            localStorage.removeItem('shoppydrive.wishlist.pending_merge');

            try {
                const guestIds: number[] = JSON.parse(pendingRaw);
                const toMerge = guestIds.filter(
                    (id) => !serverIds.includes(id),
                );

                if (toMerge.length > 0) {
                    // Optimistically show merged list while API call is in flight
                    setWishlist([...new Set([...serverIds, ...guestIds])]);
                    fetch(route('wishlist.sync'), {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-XSRF-TOKEN': decodeURIComponent(
                                document.cookie.match(
                                    /XSRF-TOKEN=([^;]+)/,
                                )?.[1] ?? '',
                            ),
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({ ids: toMerge }),
                    })
                        .then((res) => (res.ok ? res.json() : null))
                        .then((data) => {
                            if (data?.wishlist_ids) {
                                useWishlistStore
                                    .getState()
                                    .setWishlist(data.wishlist_ids);
                            }
                        })
                        .catch(() => {});

                    return;
                }
            } catch {
                // malformed JSON — ignore and fall through to normal sync
            }
        }

        setWishlist(serverIds);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [page.props.wishlist_ids, page.props.auth?.user?.id]);

    return (
        <div className="flex min-h-screen flex-col bg-[var(--color-surface)] text-[var(--color-text-primary)]">
            <AuroraBackdrop />
            <Header />

            <AnimatePresence mode="wait" initial={false}>
                <motion.main
                    // Keyed by pathname only: query-string changes (filters,
                    // sort, pagination) must update in place, not remount and
                    // re-animate the whole page. The product listing shares
                    // one key across /products and /category/* so switching
                    // category or clearing filters only refreshes the grid.
                    key={
                        page.component === 'catalog/ProductListing'
                            ? page.component
                            : page.url.split('?')[0]
                    }
                    className="flex-1"
                    initial={{ opacity: 0, y: MOTION.page?.distance ?? 8 }}
                    animate={{ opacity: 1, y: 0 }}
                    exit={{ opacity: 0, y: -(MOTION.page?.distance ?? 8) }}
                    transition={{
                        duration: MOTION.page?.duration ?? 0.22,
                        ease: 'easeOut',
                    }}
                >
                    {children}
                </motion.main>
            </AnimatePresence>

            <Footer />
            <CartDrawer />
            <CookieConsent />
            <SocialChatWidget />
        </div>
    );
}
