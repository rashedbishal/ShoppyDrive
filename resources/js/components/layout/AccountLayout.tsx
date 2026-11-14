import { Link, router, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import {
    ChevronRight,
    Gift,
    Heart,
    LayoutDashboard,
    Link2,
    Lock,
    MapPin,
    Package,
    User,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { SIDEBAR_LAYOUT_CLASS } from '@/lib/constants';
import { cn, getInitials } from '@/lib/utils';
import type { SharedPageProps } from '@/types';

const navItems = [
    { label: 'Dashboard', href: '/account', icon: LayoutDashboard },
    { label: 'My Orders', href: '/account/orders', icon: Package },
    { label: 'Wishlist', href: '/account/wishlist', icon: Heart },
    { label: 'Referral & Points', href: '/account/referral', icon: Gift },
    { label: 'Addresses', href: '/account/addresses', icon: MapPin },
    { label: 'Profile', href: '/account/profile', icon: User },
    { label: 'Change Password', href: '/account/password', icon: Lock },
    { label: 'Connected Accounts', href: '/account/connections', icon: Link2 },
];

export default function AccountLayout({
    children,
}: {
    children: React.ReactNode;
}): React.JSX.Element {
    const page = usePage<SharedPageProps>();
    const { auth, account_stats } = page.props;
    const currentPath = page.url.split('?')[0];
    // DISC-31: a customer who signed in only with Google/Facebook has no
    // password to change; they set one in Connected Accounts instead.
    const visibleNavItems = navItems.filter(
        ({ href }) =>
            href !== '/account/password' || auth.user?.has_password !== false,
    );
    const [loading, setLoading] = useState(false);

    // The sidebar stays put while a tab loads; only the content dims.
    useEffect(() => {
        const offStart = router.on('start', (event) =>
            setLoading(
                event.detail.visit.url.pathname !== window.location.pathname,
            ),
        );
        const offFinish = router.on('finish', () => setLoading(false));

        return () => {
            offStart();
            offFinish();
        };
    }, []);

    return (
        <div className="min-h-screen bg-[var(--color-surface)] py-8 lg:py-12">
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div className={cn(SIDEBAR_LAYOUT_CLASS, 'lg:items-start')}>
                    {/* Sidebar */}
                    <aside className="w-full lg:sticky lg:top-24">
                        {/* User card */}
                        <div className="relative overflow-hidden rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface-alt)] p-5">
                            <div className="pointer-events-none absolute inset-x-0 top-0 h-24 bg-gradient-to-br from-[var(--color-primary)]/15 via-[var(--color-primary)]/5 to-transparent" />

                            <div className="relative flex flex-col items-center gap-3 text-center">
                                <div className="relative">
                                    <Avatar className="size-16 ring-2 ring-[var(--color-primary)]/30 ring-offset-2 ring-offset-[var(--color-surface-alt)]">
                                        <AvatarImage
                                            src={auth.user?.avatar ?? undefined}
                                        />
                                        <AvatarFallback className="bg-[var(--color-primary)] text-lg font-bold text-white">
                                            {getInitials(auth.user?.name ?? '')}
                                        </AvatarFallback>
                                    </Avatar>
                                    <span className="absolute -right-0.5 -bottom-0.5 flex size-4 items-center justify-center rounded-full bg-[var(--color-success)] ring-2 ring-[var(--color-surface-alt)]" />
                                </div>

                                <div className="space-y-0.5">
                                    <p className="font-semibold text-[var(--color-text-primary)]">
                                        {auth.user?.name}
                                    </p>
                                    <p className="text-xs text-[var(--color-text-secondary)]">
                                        {auth.user?.email}
                                    </p>
                                </div>

                                <div className="mt-1 flex w-full divide-x divide-[var(--color-border)] rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)]">
                                    <div className="flex-1 py-2 text-center">
                                        <p className="text-sm font-bold text-[var(--color-text-primary)]">
                                            {account_stats?.orders_count ?? 0}
                                        </p>
                                        <p className="text-[11px] text-[var(--color-text-secondary)]">
                                            Orders
                                        </p>
                                    </div>
                                    <div className="flex-1 py-2 text-center">
                                        <p className="text-sm font-bold text-[var(--color-text-primary)]">
                                            {account_stats?.wishlist_count ?? 0}
                                        </p>
                                        <p className="text-[11px] text-[var(--color-text-secondary)]">
                                            Wishlist
                                        </p>
                                    </div>
                                    <div className="flex-1 py-2 text-center">
                                        <p className="text-sm font-bold text-[var(--color-text-primary)]">
                                            {account_stats?.addresses_count ??
                                                0}
                                        </p>
                                        <p className="text-[11px] text-[var(--color-text-secondary)]">
                                            Addresses
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Nav */}
                        {/* DISC-48: below lg (stacked layout) the menu is compact
                            wrapping pills, never scrolling; from lg it is the
                            sidebar list. Logging out is done from the site header and
                            mobile menu. */}
                        <nav className="mt-3 lg:overflow-hidden lg:rounded-2xl lg:border lg:border-[var(--color-border)] lg:bg-[var(--color-surface-alt)]">
                            <div className="flex flex-wrap gap-2 lg:block lg:space-y-0.5 lg:p-2">
                                {visibleNavItems.map(
                                    ({ label, href, icon: Icon }) => {
                                        const isActive =
                                            href === '/account'
                                                ? currentPath === '/account'
                                                : currentPath === href ||
                                                  currentPath.startsWith(
                                                      href + '/',
                                                  );

                                        return (
                                            <Link
                                                key={href}
                                                href={href}
                                                aria-current={
                                                    isActive
                                                        ? 'page'
                                                        : undefined
                                                }
                                                className={cn(
                                                    'inline-flex items-center rounded-full border px-3 py-1.5 text-xs font-medium transition lg:flex lg:justify-between lg:rounded-xl lg:border-0 lg:px-3.5 lg:py-2.5 lg:text-sm',
                                                    isActive
                                                        ? 'border-[var(--color-primary)] bg-[var(--color-primary)] text-white'
                                                        : 'border-[var(--color-border)] bg-[var(--color-surface)] text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)] lg:bg-transparent lg:hover:bg-[var(--color-surface)]',
                                                )}
                                            >
                                                <span className="flex items-center gap-1.5 lg:gap-3">
                                                    <Icon className="size-3.5 shrink-0 lg:size-4" />
                                                    {label}
                                                </span>
                                                {isActive ? (
                                                    <ChevronRight className="hidden size-3.5 lg:block" />
                                                ) : null}
                                            </Link>
                                        );
                                    },
                                )}
                            </div>
                        </nav>
                    </aside>

                    {/* Main content */}
                    <main
                        className={cn(
                            'min-w-0 transition-opacity duration-150 lg:col-span-3',
                            loading && 'opacity-60',
                        )}
                    >
                        <motion.div
                            key={currentPath}
                            initial={{ opacity: 0, y: 6 }}
                            animate={{ opacity: 1, y: 0 }}
                            transition={{ duration: 0.2, ease: 'easeOut' }}
                        >
                            {children}
                        </motion.div>
                    </main>
                </div>
            </div>
        </div>
    );
}
