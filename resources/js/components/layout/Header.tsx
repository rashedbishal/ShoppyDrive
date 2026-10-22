import { Link, router, usePage } from '@inertiajs/react';
import {
    Heart,
    LogIn,
    LogOut,
    Menu,
    Package,
    Search,
    ShoppingBag,
    User,
    UserCircle2,
    X,
} from 'lucide-react';
import { AnimatePresence, motion } from 'framer-motion';
import { useEffect, useMemo, useRef, useState } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useInitials } from '@/hooks/use-initials';
import { usePendingCartArrivals } from '@/lib/add-to-cart-feedback';
import { cn } from '@/lib/utils';
import { useCartStore } from '@/stores/cartStore';
import { useUiStore } from '@/stores/uiStore';
import { useWishlistStore } from '@/stores/wishlistStore';
import type { SharedPageProps } from '@/types';
import MegaMenu from '@/components/layout/MegaMenu';
import MobileMenu from '@/components/layout/MobileMenu';
import { SearchBar } from '@/components/shared/SearchBar';

const supportItems = [
    { label: 'Track Order', href: '/track-order' },
    { label: 'Help Center', href: '/faq' },
];

const primaryNavLinks = [
    { label: 'Home', href: '/' },
    { label: 'Flash Sale', href: '/flash-sales' },
    { label: 'Brands', href: '/brands' },
    { label: 'About', href: '/about' },
];

/**
 * Soft rounded highlight behind the hovered nav item. Shares a layoutId so it
 * glides between items instead of blinking on/off per item.
 */
function NavHoverPill(): React.JSX.Element {
    return (
        <motion.span
            layoutId="nav-hover-pill"
            className="absolute inset-0 rounded-full bg-[var(--color-primary-light)] ring-1 ring-[var(--color-primary)]/10"
            transition={{ type: 'spring', stiffness: 420, damping: 34 }}
        />
    );
}

export default function Header(): React.JSX.Element {
    const page = usePage<SharedPageProps>();
    const getInitials = useInitials();
    const user = page.props.auth?.user ?? null;
    const settings = page.props.settings;
    const openCart = useUiStore((state) => state.openCart);
    const openMobileMenu = useUiStore((state) => state.openMobileMenu);
    const closeMobileMenu = useUiStore((state) => state.closeMobileMenu);
    const mobileMenuOpen = useUiStore((state) => state.mobileMenuOpen);
    const wishlistCount = useWishlistStore((state) => state.productIds.length);
    const cartItems = useCartStore((state) => state.items);
    const [isScrolled, setIsScrolled] = useState(false);
    const [isMegaMenuOpen, setIsMegaMenuOpen] = useState(false);
    const [mobileSearchOpen, setMobileSearchOpen] = useState(false);
    const [hoveredNavKey, setHoveredNavKey] = useState<string | null>(null);
    // The sliding hover pill follows the cursor; while the mega menu is open it
    // rests on "All Categories" so the open trigger stays highlighted.
    const navPillKey = hoveredNavKey ?? (isMegaMenuOpen ? 'categories' : null);
    const currentPath = page.url.split('?')[0];
    const isNavLinkActive = (href: string): boolean =>
        href === '/' ? currentPath === '/' : currentPath === href || currentPath.startsWith(`${href}/`);
    const megaMenuCloseTimeout = useRef<ReturnType<typeof setTimeout> | null>(null);

    const openMegaMenu = () => {
        if (megaMenuCloseTimeout.current) {
            clearTimeout(megaMenuCloseTimeout.current);
            megaMenuCloseTimeout.current = null;
        }

        setIsMegaMenuOpen(true);
    };

    const scheduleMegaMenuClose = () => {
        // Small grace period so moving the cursor through the gap between the
        // button and the dropdown panel below it doesn't close the menu.
        megaMenuCloseTimeout.current = setTimeout(() => setIsMegaMenuOpen(false), 150);
    };

    useEffect(() => {
        return () => {
            if (megaMenuCloseTimeout.current) {
                clearTimeout(megaMenuCloseTimeout.current);
            }
        };
    }, []);

    // Items still flying into the cart aren't counted until they land.
    const pendingCartArrivals = usePendingCartArrivals();
    const itemCount = useMemo(
        () => Math.max(0, cartItems.reduce((count, item) => count + item.quantity, 0) - pendingCartArrivals),
        [cartItems, pendingCartArrivals],
    );

    useEffect(() => {
        // Shrinking the header changes its height, which shifts the scroll position.
        // A single threshold therefore flip-flops (the header shakes) right at that
        // point, so compact and expanded use separate thresholds whose gap is far
        // larger than the header's height change: between them the current state holds.
        const COMPACT_AFTER_PX = 80;
        const EXPAND_BEFORE_PX = 16;

        const handleScroll = (): void => {
            const scrollY = window.scrollY;

            setIsScrolled((wasScrolled) => {
                if (scrollY > COMPACT_AFTER_PX) {
                    return true;
                }

                if (scrollY < EXPAND_BEFORE_PX) {
                    return false;
                }

                return wasScrolled;
            });
        };
        handleScroll();
        window.addEventListener('scroll', handleScroll, { passive: true });
        return () => window.removeEventListener('scroll', handleScroll);
    }, []);

    useEffect(() => {
        setMobileSearchOpen(false);
    }, [page.url]);

    useEffect(() => {
        if (!mobileSearchOpen) return;
        const onKey = (e: KeyboardEvent) => { if (e.key === 'Escape') setMobileSearchOpen(false); };
        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    }, [mobileSearchOpen]);

    const siteName = settings?.site_name ?? 'ShoppyDrive';
    const announcementBar = page.props.homepage_settings?.['announcement_bar'];

    return (
        <>
            <header
                className={cn(
                    'sticky top-0 z-50 w-full transition-shadow duration-300',
                    isScrolled ? 'shadow-[var(--shadow-card-hover)]' : '',
                )}
            >
                {/* Top utility bar */}
                {announcementBar?.is_visible !== false && (
                <div className="hidden bg-[var(--color-secondary)] text-white lg:block">
                    <div className="mx-auto flex max-w-7xl items-center justify-between px-4 py-2 text-xs sm:px-6 lg:px-8">
                        <div className="flex items-center gap-5">
                            {announcementBar?.title ? (
                                <span className="flex items-center gap-1.5 text-white/70">
                                    <Package className="size-3.5" />
                                    {announcementBar.title}
                                </span>
                            ) : null}
                            {announcementBar?.metadata?.phone ? (
                                <a
                                    href={`tel:${announcementBar.metadata.phone}`}
                                    className="text-white/70 transition hover:text-[var(--color-accent)]"
                                >
                                    {announcementBar.metadata.phone}
                                </a>
                            ) : null}
                        </div>
                        <div className="flex items-center gap-5">
                            {supportItems.map((item) => (
                                <Link
                                    key={item.label}
                                    href={item.href}
                                    className="text-white/70 transition hover:text-white"
                                >
                                    {item.label}
                                </Link>
                            ))}
                            {announcementBar?.metadata?.email ? (
                                <a
                                    href={`mailto:${announcementBar.metadata.email}`}
                                    className="text-white/70 transition hover:text-white"
                                >
                                    {announcementBar.metadata.email}
                                </a>
                            ) : null}
                        </div>
                    </div>
                </div>
                )}

                {/* Main header bar */}
                <div className="relative z-20 border-b border-[var(--color-border)] bg-white/95 lg:backdrop-blur-xl">
                    <div
                        className={cn(
                            'relative mx-auto flex max-w-7xl items-center gap-4 px-4 transition-[padding] duration-300 sm:px-6 lg:px-8',
                            isScrolled ? 'py-3' : 'py-4',
                        )}
                    >
                        {/* Mobile search overlay */}
                        <AnimatePresence>
                            {mobileSearchOpen && (
                                <motion.div
                                    key="mobile-search"
                                    initial={{ opacity: 0 }}
                                    animate={{ opacity: 1 }}
                                    exit={{ opacity: 0 }}
                                    transition={{ duration: 0.22, ease: [0.4, 0, 0.2, 1] }}
                                    className="absolute inset-0 z-20 flex items-center gap-2 bg-white px-4 sm:px-6 lg:hidden"
                                >
                                    {/* Search bar slides in from the right */}
                                    <motion.div
                                        className="min-w-0 flex-1"
                                        initial={{ opacity: 0, x: 32 }}
                                        animate={{ opacity: 1, x: 0 }}
                                        exit={{ opacity: 0, x: 32 }}
                                        transition={{ duration: 0.28, ease: [0.25, 0.46, 0.45, 0.94], delay: 0.05 }}
                                    >
                                        <SearchBar
                                            autoFocus
                                            onSearch={() => setMobileSearchOpen(false)}
                                        />
                                    </motion.div>

                                    {/* Close button springs in with rotation */}
                                    <motion.button
                                        type="button"
                                        initial={{ scale: 0, opacity: 0, rotate: -90 }}
                                        animate={{ scale: 1, opacity: 1, rotate: 0 }}
                                        exit={{ scale: 0, opacity: 0, rotate: 90 }}
                                        transition={{ type: 'spring', stiffness: 500, damping: 26, delay: 0.1 }}
                                        onClick={() => setMobileSearchOpen(false)}
                                        className="inline-flex size-10 shrink-0 items-center justify-center rounded-xl text-[var(--color-text-secondary)] transition hover:bg-[var(--color-surface-alt)] hover:text-[var(--color-text-primary)]"
                                        aria-label="Close search"
                                    >
                                        <X className="size-5" />
                                    </motion.button>
                                </motion.div>
                            )}
                        </AnimatePresence>
                        {/* Mobile menu toggle */}
                        <button
                            type="button"
                            className="inline-flex size-10 items-center justify-center rounded-xl text-[var(--color-text-secondary)] transition hover:bg-[var(--color-surface-alt)] hover:text-[var(--color-text-primary)] lg:hidden"
                            aria-label="Open menu"
                            onClick={openMobileMenu}
                        >
                            <Menu className="size-5" />
                        </button>

                        {/* Logo */}
                        <Link href="/" className="flex shrink-0 items-center gap-3">
                            {settings?.logo ? (
                                <img
                                    src={settings.logo}
                                    alt={siteName}
                                    className="h-14 w-auto object-contain"
                                />
                            ) : (
                                <span className="flex size-10 items-center justify-center rounded-xl bg-[var(--color-secondary)] text-white shadow-sm">
                                    <AppLogoIcon className="size-5 fill-current text-white" />
                                </span>
                            )}
                            <span className="hidden flex-col leading-none sm:flex">
                                <span className="text-[17px] font-bold tracking-tight text-[var(--color-text-primary)]">
                                    {siteName}
                                </span>
                                <span className="mt-0.5 text-[11px] font-medium text-[var(--color-text-muted)]">
                                    Best Deals in Bangladesh
                                </span>
                            </span>
                        </Link>

                        {/* Search bar */}
                        <SearchBar className="hidden flex-1 lg:block" />

                        {/* Right-side icons */}
                        <div className="ml-auto flex items-center gap-1">
                            {/* Mobile search */}
                            <button
                                type="button"
                                className="inline-flex size-10 items-center justify-center rounded-xl text-[var(--color-text-secondary)] transition hover:bg-[var(--color-surface-alt)] hover:text-[var(--color-text-primary)] lg:hidden"
                                aria-label="Search"
                                onClick={() => setMobileSearchOpen(true)}
                            >
                                <Search className="size-5" />
                            </button>

                            {/* Wishlist */}
                            <Link
                                href="/account/wishlist"
                                aria-label="Wishlist"
                                className="relative inline-flex size-10 items-center justify-center rounded-xl text-[var(--color-text-secondary)] transition hover:bg-[var(--color-surface-alt)] hover:text-[var(--color-text-primary)]"
                            >
                                <Heart className="size-5" />
                                {wishlistCount > 0 ? (
                                    <span className="absolute -right-0.5 -top-0.5 flex size-4 items-center justify-center rounded-full bg-[var(--color-primary)] text-[10px] font-bold text-white">
                                        {wishlistCount > 9 ? '9+' : wishlistCount}
                                    </span>
                                ) : null}
                            </Link>

                            {/* Cart */}
                            <button
                                type="button"
                                aria-label="Cart"
                                onClick={openCart}
                                data-cart-target
                                className="relative inline-flex size-10 items-center justify-center rounded-xl text-[var(--color-text-secondary)] transition hover:bg-[var(--color-surface-alt)] hover:text-[var(--color-text-primary)]"
                            >
                                <ShoppingBag className="size-5" />
                                {itemCount > 0 ? (
                                    <span data-cart-count className="absolute -right-0.5 -top-0.5 flex size-4 items-center justify-center rounded-full bg-[var(--color-primary)] text-[10px] font-bold text-white">
                                        {itemCount > 9 ? '9+' : itemCount}
                                    </span>
                                ) : null}
                            </button>

                            {/* Auth */}
                            {user ? (
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <button
                                            type="button"
                                            className="ml-1 flex items-center gap-2.5 rounded-2xl border border-[var(--color-border)] bg-white px-2.5 py-1.5 transition hover:border-[var(--color-primary)] hover:shadow-sm"
                                        >
                                            <Avatar className="size-8">
                                                <AvatarImage src={user.avatar ?? undefined} alt={user.name} />
                                                <AvatarFallback className="bg-[var(--color-secondary)] text-[11px] text-white">
                                                    {getInitials(user.name)}
                                                </AvatarFallback>
                                            </Avatar>
                                            <span className="hidden max-w-[120px] flex-col text-left leading-none md:flex">
                                                <span className="truncate text-[13px] font-semibold text-[var(--color-text-primary)]">
                                                    {user.name}
                                                </span>
                                                <span className="mt-0.5 text-[11px] text-[var(--color-text-muted)]">
                                                    My account
                                                </span>
                                            </span>
                                        </button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end" className="w-56 rounded-2xl p-2 shadow-[var(--shadow-dropdown)]">
                                        <DropdownMenuLabel className="px-3 py-2">
                                            <p className="text-sm font-semibold text-[var(--color-text-primary)]">{user.name}</p>
                                            <p className="text-xs text-[var(--color-text-muted)]">{user.email}</p>
                                        </DropdownMenuLabel>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem
                                            onSelect={(e) => { e.preventDefault(); router.get('/account'); }}
                                            className="cursor-pointer rounded-xl px-3 py-2.5"
                                        >
                                            <UserCircle2 className="size-4" />
                                            My account
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            onSelect={(e) => { e.preventDefault(); router.get('/account/orders'); }}
                                            className="cursor-pointer rounded-xl px-3 py-2.5"
                                        >
                                            <Package className="size-4" />
                                            My orders
                                        </DropdownMenuItem>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem
                                            onSelect={(e) => { e.preventDefault(); router.post(route('logout')); }}
                                            className="cursor-pointer rounded-xl px-3 py-2.5 text-[var(--color-primary)]"
                                        >
                                            <LogOut className="size-4" />
                                            Log out
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            ) : (
                                <div className="ml-1 hidden items-center gap-2 md:flex">
                                    <Link
                                        href="/login"
                                        className="inline-flex h-9 items-center gap-1.5 rounded-xl bg-[var(--color-primary)] px-4 text-sm font-semibold text-white transition hover:bg-[var(--color-primary)]/90"
                                    >
                                        <LogIn className="size-4" />
                                        Log in
                                    </Link>
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                {/* Navigation bar */}
                <div className="relative z-10 hidden border-b border-[var(--color-border)] bg-white/95 lg:block">
                    <nav
                        className="mx-auto flex h-12 max-w-7xl items-center gap-1 px-4 sm:px-6 lg:px-8"
                        onMouseLeave={() => setHoveredNavKey(null)}
                    >
                        <div
                            className="relative"
                            onMouseEnter={() => {
                                setHoveredNavKey('categories');
                                openMegaMenu();
                            }}
                            onMouseLeave={scheduleMegaMenuClose}
                        >
                            <button
                                type="button"
                                onClick={() => setIsMegaMenuOpen((prev) => !prev)}
                                aria-expanded={isMegaMenuOpen}
                                className={cn(
                                    'relative flex h-9 items-center gap-2 rounded-full px-4 text-sm font-semibold transition-colors duration-200',
                                    navPillKey === 'categories'
                                        ? 'text-[var(--color-primary)]'
                                        : 'text-[var(--color-text-primary)]',
                                )}
                            >
                                {navPillKey === 'categories' && <NavHoverPill />}
                                <Menu
                                    className={cn(
                                        'relative z-10 size-4 transition-transform duration-300',
                                        isMegaMenuOpen && 'rotate-90',
                                    )}
                                />
                                <span className="relative z-10">All Categories</span>
                            </button>
                            <MegaMenu isOpen={isMegaMenuOpen} onClose={() => setIsMegaMenuOpen(false)} />
                        </div>
                        <div className="mx-2 h-5 w-px bg-[var(--color-border)]" />
                        {primaryNavLinks.map((item) => {
                            const isActive = isNavLinkActive(item.href);

                            return (
                                <Link
                                    key={item.label}
                                    href={item.href}
                                    onMouseEnter={() => setHoveredNavKey(item.href)}
                                    onFocus={() => setHoveredNavKey(item.href)}
                                    onBlur={() => setHoveredNavKey(null)}
                                    className={cn(
                                        'relative flex h-9 items-center rounded-full px-4 text-sm font-medium transition-colors duration-200',
                                        isActive || navPillKey === item.href
                                            ? 'text-[var(--color-primary)]'
                                            : 'text-[var(--color-text-secondary)]',
                                    )}
                                >
                                    {navPillKey === item.href && <NavHoverPill />}
                                    <span className="relative z-10">{item.label}</span>
                                    {isActive && (
                                        <motion.span
                                            layoutId="nav-active-indicator"
                                            className="absolute inset-x-4 -bottom-[7px] h-0.5 rounded-full bg-[var(--color-primary)]"
                                            transition={{ type: 'spring', stiffness: 500, damping: 40 }}
                                        />
                                    )}
                                </Link>
                            );
                        })}
                    </nav>
                </div>
            </header>

            <MobileMenu isOpen={mobileMenuOpen} onClose={closeMobileMenu} />
        </>
    );
}
