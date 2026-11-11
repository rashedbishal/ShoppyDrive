import { Link, usePage } from '@inertiajs/react';
import { LayoutGrid, Menu, Search, ShoppingCart, Heart } from 'lucide-react';
import { useState } from 'react';
import AppLogo from '@/components/app-logo';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { SearchBar } from '@/components/shared/SearchBar';
import { SearchOverlay } from '@/components/shared/SearchOverlay';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTrigger,
} from '@/components/ui/sheet';
import { UserMenuContent } from '@/components/user-menu-content';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';
import { useCartStore } from '@/stores/cartStore';
import { useUiStore } from '@/stores/uiStore';
import { useWishlistStore } from '@/stores/wishlistStore';
import type { BreadcrumbItem, NavItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

const mainNavItems: NavItem[] = [
    {
        title: 'Shop',
        href: route('products.index'),
        icon: LayoutGrid,
    },
    {
        title: 'Flash Sale',
        href: route('flash-sales.index'),
        icon: LayoutGrid, // Replace with appropriate icon if available
    },
];

export function AppHeader({ breadcrumbs = [] }: Props) {
    const page = usePage();
    const { auth } = page.props;
    const getInitials = useInitials();
    const { isCurrentUrl } = useCurrentUrl();

    const [isSearchOverlayOpen, setIsSearchOverlayOpen] = useState(false);

    // From `state`, not getItemCount() (reads live state): hydration-safe.
    const cartCount = useCartStore((state) =>
        state.items.reduce((total, item) => total + item.quantity, 0),
    );
    const wishlistCount = useWishlistStore((state) => state.productIds.length);
    const openCart = useUiStore((state) => state.openCart);

    return (
        <>
            <div className="sticky top-0 z-[100] border-b border-[var(--color-border)] bg-white/95 shadow-sm backdrop-blur-md">
                <div className="mx-auto flex h-20 items-center px-4 md:max-w-7xl">
                    {/* Mobile Menu Trigger */}
                    <div className="flex items-center lg:hidden">
                        <Sheet>
                            <SheetTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="mr-2 h-10 w-10 text-[var(--color-text-primary)]"
                                >
                                    <Menu className="h-6 w-6" />
                                </Button>
                            </SheetTrigger>
                            {/* Mobile Sidebar Content - Simplified for now */}
                            <SheetContent side="left" className="w-80 p-0">
                                <SheetHeader className="border-b border-[var(--color-border)] p-6">
                                    <AppLogo />
                                </SheetHeader>
                                <div className="space-y-2 p-4">
                                    {mainNavItems.map((item) => (
                                        <Link
                                            key={item.title}
                                            href={item.href}
                                            className="flex items-center gap-3 rounded-xl p-3 font-bold text-[var(--color-text-primary)] hover:bg-[var(--color-surface-alt)]"
                                        >
                                            <span>{item.title}</span>
                                        </Link>
                                    ))}
                                </div>
                            </SheetContent>
                        </Sheet>
                    </div>

                    <Link href={route('home')} className="flex items-center">
                        <AppLogo />
                    </Link>

                    {/* Desktop Navigation */}
                    <nav className="ml-10 hidden items-center space-x-8 lg:flex">
                        {mainNavItems.map((item) => (
                            <Link
                                key={item.title}
                                href={item.href}
                                className={cn(
                                    'text-sm font-bold text-[var(--color-text-primary)] transition-colors hover:text-[var(--color-primary)]',
                                    isCurrentUrl(item.href) &&
                                        'text-[var(--color-primary)]',
                                )}
                            >
                                {item.title}
                            </Link>
                        ))}
                    </nav>

                    {/* Search Bar - Desktop */}
                    <div className="hidden flex-1 justify-center px-10 lg:flex">
                        <SearchBar className="w-full max-w-md" />
                    </div>

                    {/* Right Actions */}
                    <div className="ml-auto flex items-center gap-1 sm:gap-3">
                        {/* Mobile Search Trigger */}
                        <Button
                            variant="ghost"
                            size="icon"
                            className="h-10 w-10 text-[var(--color-text-primary)] lg:hidden"
                            onClick={() => setIsSearchOverlayOpen(true)}
                            aria-label="Search products"
                        >
                            <Search className="h-5 w-5" />
                        </Button>

                        {/* Wishlist */}
                        <Link href={route('wishlist.index')}>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="relative h-10 w-10 rounded-xl text-[var(--color-text-primary)] hover:bg-[var(--color-primary-light)] hover:text-[var(--color-primary)]"
                                aria-label={`Wishlist (${wishlistCount} items)`}
                            >
                                <Heart className="h-5 w-5" />
                                {wishlistCount > 0 && (
                                    <span className="absolute -top-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full border-2 border-white bg-[var(--color-primary)] text-[10px] font-bold text-white">
                                        {wishlistCount}
                                    </span>
                                )}
                            </Button>
                        </Link>

                        {/* Cart */}
                        <Button
                            variant="ghost"
                            size="icon"
                            onClick={openCart}
                            className="relative h-10 w-10 rounded-xl text-[var(--color-text-primary)] hover:bg-[var(--color-primary-light)] hover:text-[var(--color-primary)]"
                            aria-label={`Shopping Cart (${cartCount} items)`}
                        >
                            <ShoppingCart className="h-5 w-5" />
                            {cartCount > 0 && (
                                <span className="absolute -top-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full border-2 border-white bg-[var(--color-primary)] text-[10px] font-bold text-white">
                                    {cartCount}
                                </span>
                            )}
                        </Button>

                        {/* User Dropdown */}
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="ghost"
                                    className="h-10 w-10 rounded-xl p-0 hover:bg-[var(--color-surface-alt)]"
                                    aria-label="User account menu"
                                >
                                    <Avatar className="h-8 w-8">
                                        <AvatarImage
                                            src={
                                                auth.admin?.avatar ??
                                                auth.user?.avatar ??
                                                undefined
                                            }
                                            alt={
                                                auth.admin?.name ??
                                                auth.user?.name
                                            }
                                        />
                                        <AvatarFallback className="bg-[var(--color-primary-light)] font-bold text-[var(--color-primary)]">
                                            {getInitials(
                                                auth.admin?.name ??
                                                    auth.user?.name ??
                                                    '',
                                            )}
                                        </AvatarFallback>
                                    </Avatar>
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent
                                className="w-64 rounded-2xl p-2 shadow-[var(--shadow-dropdown)]"
                                align="end"
                            >
                                {auth.admin || auth.user ? (
                                    <UserMenuContent
                                        user={(auth.admin ?? auth.user)!}
                                        isAdmin={!!auth.admin}
                                    />
                                ) : (
                                    <div className="p-2">
                                        <Link href={route('login')}>
                                            <Button className="w-full justify-start rounded-xl bg-[var(--color-primary)] font-bold text-white hover:bg-[var(--color-primary-hover)]">
                                                Login
                                            </Button>
                                        </Link>
                                    </div>
                                )}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>
            </div>

            <SearchOverlay
                isOpen={isSearchOverlayOpen}
                onClose={() => setIsSearchOverlayOpen(false)}
            />

            {breadcrumbs.length > 1 && (
                <div className="border-b border-[var(--color-border)] bg-white">
                    <div className="mx-auto flex h-12 w-full items-center justify-start px-4 md:max-w-7xl">
                        <Breadcrumbs breadcrumbs={breadcrumbs} />
                    </div>
                </div>
            )}
        </>
    );
}
