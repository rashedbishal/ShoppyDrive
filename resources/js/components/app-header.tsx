import { Link, usePage } from '@inertiajs/react';
import { BookOpen, Folder, LayoutGrid, Menu, Search, ShoppingCart, Heart } from 'lucide-react';
import { useState } from 'react';
import AppLogo from '@/components/app-logo';
import AppLogoIcon from '@/components/app-logo-icon';
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
    NavigationMenu,
    NavigationMenuItem,
    NavigationMenuList,
    navigationMenuTriggerStyle,
} from '@/components/ui/navigation-menu';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { UserMenuContent } from '@/components/user-menu-content';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useInitials } from '@/hooks/use-initials';
import { cn, toUrl } from '@/lib/utils';
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

const rightNavItems: NavItem[] = [
    {
        title: 'Track Order',
        href: route('order.track'),
        icon: Folder,
    },
];

const activeItemStyles =
    'text-[var(--color-primary)] font-bold';

export function AppHeader({ breadcrumbs = [] }: Props) {
    const page = usePage();
    const { auth } = page.props;
    const getInitials = useInitials();
    const { isCurrentUrl, whenCurrentUrl } = useCurrentUrl();
    
    const [isSearchOverlayOpen, setIsSearchOverlayOpen] = useState(false);
    
    const cartCount = useCartStore((state) => state.getItemCount());
    const wishlistCount = useWishlistStore((state) => state.items?.length || 0);
    const openCart = useUiStore((state) => state.openCart);

    return (
        <>
            <div className="sticky top-0 z-[100] bg-white/95 backdrop-blur-md border-b border-[var(--color-border)] shadow-sm">
                <div className="mx-auto flex h-20 items-center px-4 md:max-w-7xl">
                    {/* Mobile Menu Trigger */}
                    <div className="lg:hidden flex items-center">
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
                                <SheetHeader className="p-6 border-b border-[var(--color-border)]">
                                    <AppLogo />
                                </SheetHeader>
                                <div className="p-4 space-y-2">
                                    {mainNavItems.map((item) => (
                                        <Link
                                            key={item.title}
                                            href={item.href}
                                            className="flex items-center gap-3 p-3 rounded-xl hover:bg-[var(--color-surface-alt)] font-bold text-[var(--color-text-primary)]"
                                        >
                                            <span>{item.title}</span>
                                        </Link>
                                    ))}
                                </div>
                            </SheetContent>
                        </Sheet>
                    </div>

                    <Link
                        href={route('home')}
                        className="flex items-center"
                    >
                        <AppLogo className="h-10 w-auto" />
                    </Link>

                    {/* Desktop Navigation */}
                    <nav className="ml-10 hidden lg:flex items-center space-x-8">
                        {mainNavItems.map((item) => (
                            <Link
                                key={item.title}
                                href={item.href}
                                className={cn(
                                    "text-sm font-bold text-[var(--color-text-primary)] hover:text-[var(--color-primary)] transition-colors",
                                    isCurrentUrl(item.href) && "text-[var(--color-primary)]"
                                )}
                            >
                                {item.title}
                            </Link>
                        ))}
                    </nav>

                    {/* Search Bar - Desktop */}
                    <div className="hidden lg:flex flex-1 justify-center px-10">
                        <SearchBar className="max-w-md w-full" />
                    </div>

                    {/* Right Actions */}
                    <div className="ml-auto flex items-center gap-1 sm:gap-3">
                        {/* Mobile Search Trigger */}
                        <Button
                            variant="ghost"
                            size="icon"
                            className="lg:hidden h-10 w-10 text-[var(--color-text-primary)]"
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
                                className="relative h-10 w-10 text-[var(--color-text-primary)] hover:text-[var(--color-primary)] hover:bg-[var(--color-primary-light)] rounded-xl"
                                aria-label={`Wishlist (${wishlistCount} items)`}
                            >
                                <Heart className="h-5 w-5" />
                                {wishlistCount > 0 && (
                                    <span className="absolute -top-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full bg-[var(--color-primary)] text-[10px] font-bold text-white border-2 border-white">
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
                            className="relative h-10 w-10 text-[var(--color-text-primary)] hover:text-[var(--color-primary)] hover:bg-[var(--color-primary-light)] rounded-xl"
                            aria-label={`Shopping Cart (${cartCount} items)`}
                        >
                            <ShoppingCart className="h-5 w-5" />
                            {cartCount > 0 && (
                                <span className="absolute -top-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full bg-[var(--color-primary)] text-[10px] font-bold text-white border-2 border-white">
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
                                            src={(auth.admin?.avatar ?? auth.user?.avatar) ?? undefined}
                                            alt={auth.admin?.name ?? auth.user?.name}
                                        />
                                        <AvatarFallback className="bg-[var(--color-primary-light)] text-[var(--color-primary)] font-bold">
                                            {getInitials(auth.admin?.name ?? auth.user?.name ?? '')}
                                        </AvatarFallback>
                                    </Avatar>
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent className="w-64 rounded-2xl p-2 shadow-[var(--shadow-dropdown)]" align="end">
                                {auth.admin || auth.user ? (
                                    <UserMenuContent 
                                        user={(auth.admin ?? auth.user)!} 
                                        isAdmin={!!auth.admin}
                                    />
                                ) : (
                                    <div className="p-2 space-y-2">
                                        <Link href={route('login')}>
                                            <Button className="w-full justify-start font-bold rounded-xl" variant="ghost">Login</Button>
                                        </Link>
                                        <Link href={route('register')}>
                                            <Button className="w-full justify-start font-bold rounded-xl bg-[var(--color-primary)] text-white hover:bg-[var(--color-primary-hover)]">Register</Button>
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
                <div className="bg-white border-b border-[var(--color-border)]">
                    <div className="mx-auto flex h-12 w-full items-center justify-start px-4 md:max-w-7xl">
                        <Breadcrumbs breadcrumbs={breadcrumbs} />
                    </div>
                </div>
            )}
        </>
    );
}

