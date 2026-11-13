import { Link, router, usePage } from '@inertiajs/react';
import {
    Check,
    FolderOpen,
    LayoutGrid,
    LogIn,
    LogOut,
    Palette,
    ShoppingBag,
    UserRound,
} from 'lucide-react';
import { lazy, Suspense } from 'react';
import { route } from 'ziggy-js';
import { primaryLinks } from '@/components/layout/navigation';
import { SiteLogo } from '@/components/shared/SiteLogo';
import { Accordion } from '@/components/ui/accordion';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Drawer } from '@/components/ui/drawer';
import { useInitials } from '@/hooks/use-initials';
import { SITE_THEMES, useSiteTheme } from '@/hooks/use-theme';
import { resolveCategoryColor } from '@/lib/category-colors';
import type { SharedPageProps } from '@/types';

// Lazy-loaded like in MegaMenu: the dynamic icon registry is large, so it's
// only fetched once the menu actually renders category rows.
const CategoryIcon = lazy(() =>
    import('@/lib/category-icons').then((m) => ({ default: m.CategoryIcon })),
);

type MobileMenuProps = {
    isOpen: boolean;
    onClose: () => void;
};

export default function MobileMenu({
    isOpen,
    onClose,
}: MobileMenuProps): React.JSX.Element {
    const page = usePage<SharedPageProps>();
    const getInitials = useInitials();
    const user = page.props.auth?.user ?? null;
    const navCategories = page.props.navCategories ?? [];
    const { theme, setTheme } = useSiteTheme();

    return (
        <Drawer
            isOpen={isOpen}
            onClose={onClose}
            title="Menu"
            description="Browse categories, track orders, and access your account."
            header={
                <Link
                    href="/"
                    onClick={onClose}
                    className="flex shrink-0 items-center"
                >
                    <SiteLogo plate="dark" className="h-10" />
                </Link>
            }
            side="left"
            size="md"
        >
            <div className="flex h-full flex-col gap-5">
                <div className="rounded-2xl border border-[var(--color-border)] bg-gradient-to-br from-[var(--color-primary-light)] to-[var(--color-surface)] p-4 shadow-[var(--shadow-card)]">
                    <div className="flex items-center gap-3.5">
                        {/* The avatar is the way to the account page. */}
                        <Link
                            href="/account"
                            onClick={onClose}
                            aria-label="Open my account"
                            className="shrink-0 rounded-full ring-2 ring-[var(--color-primary)]/30 ring-offset-2 ring-offset-[var(--color-surface)] transition active:scale-95"
                        >
                            <Avatar className="size-14 border border-[var(--color-border)]">
                                <AvatarImage
                                    src={user?.avatar ?? undefined}
                                    alt={user?.name ?? 'Shopper'}
                                />
                                <AvatarFallback className="bg-[var(--color-secondary)] text-white">
                                    {user ? (
                                        getInitials(user.name)
                                    ) : (
                                        <UserRound className="size-6" />
                                    )}
                                </AvatarFallback>
                            </Avatar>
                        </Link>
                        <div className="min-w-0 flex-1">
                            <p className="truncate text-base font-bold text-[var(--color-text-primary)]">
                                {user?.name ?? 'Shopper'}
                            </p>
                            <p className="mt-0.5 text-sm leading-snug [overflow-wrap:anywhere] text-[var(--color-text-secondary)]">
                                {user?.email ??
                                    'Sign in to sync your cart and wishlist'}
                            </p>
                        </div>
                    </div>
                    {!user && (
                        <Button
                            asChild
                            fullWidth
                            className="mt-3.5"
                            leftIcon={<LogIn className="size-4" />}
                        >
                            <Link href={route('login')} onClick={onClose}>
                                Log in
                            </Link>
                        </Button>
                    )}
                </div>

                <div className="grid grid-cols-2 gap-3">
                    <Link
                        href={route('home')}
                        onClick={onClose}
                        className="flex flex-col items-center justify-center rounded-2xl border border-[var(--color-border)] bg-surface p-3 text-center text-sm font-medium text-[var(--color-text-primary)] shadow-[var(--shadow-card)]"
                    >
                        <LayoutGrid className="mb-2 size-5 text-[var(--color-primary)]" />
                        Home
                    </Link>
                    <Link
                        href="/cart"
                        onClick={onClose}
                        className="flex flex-col items-center justify-center rounded-2xl border border-[var(--color-border)] bg-surface p-3 text-center text-sm font-medium text-[var(--color-text-primary)] shadow-[var(--shadow-card)]"
                    >
                        <ShoppingBag className="mb-2 size-5 text-[var(--color-primary)]" />
                        Cart
                    </Link>
                </div>

                <Accordion
                    multiple
                    className="w-full"
                    items={[
                        {
                            id: 'categories',
                            title: 'Categories',
                            content: (
                                <div className="space-y-2">
                                    {navCategories.length === 0 ? (
                                        <div className="flex items-center gap-2 px-3 py-3 text-sm text-[var(--color-text-secondary)]">
                                            <FolderOpen className="size-4 opacity-50" />
                                            No categories yet
                                        </div>
                                    ) : (
                                        navCategories.map((category, index) => (
                                            <Link
                                                key={category.id}
                                                href={`/category/${category.slug}`}
                                                onClick={onClose}
                                                style={
                                                    {
                                                        '--category-color':
                                                            resolveCategoryColor(
                                                                category.color,
                                                                index,
                                                            ),
                                                    } as React.CSSProperties
                                                }
                                                className="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-[var(--color-text-primary)] transition-colors hover:bg-[var(--color-primary-light)] active:bg-[var(--color-primary-light)]"
                                            >
                                                {/* Same colour-tinted icon tile as the desktop mega menu */}
                                                <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-[color-mix(in_srgb,var(--category-color)_12%,white)] text-[var(--category-color)] ring-1 ring-[color-mix(in_srgb,var(--category-color)_18%,white)]">
                                                    <Suspense
                                                        fallback={
                                                            <ShoppingBag
                                                                className="size-4"
                                                                strokeWidth={
                                                                    1.75
                                                                }
                                                            />
                                                        }
                                                    >
                                                        <CategoryIcon
                                                            icon={category.icon}
                                                            className="size-4"
                                                        />
                                                    </Suspense>
                                                </span>
                                                <span className="min-w-0 flex-1">
                                                    <span className="block truncate font-medium">
                                                        {category.name}
                                                    </span>
                                                    {category.description && (
                                                        <span className="block truncate text-xs text-[var(--color-text-secondary)]">
                                                            {
                                                                category.description
                                                            }
                                                        </span>
                                                    )}
                                                </span>
                                            </Link>
                                        ))
                                    )}
                                </div>
                            ),
                        },
                        {
                            id: 'links',
                            title: 'Quick links',
                            content: (
                                <div className="space-y-1">
                                    {primaryLinks.map((link) => (
                                        <Link
                                            key={link.label}
                                            href={link.href}
                                            onClick={onClose}
                                            className="block rounded-xl px-3 py-3 text-sm font-medium text-[var(--color-text-primary)] transition-colors hover:bg-[var(--color-primary-light)] active:bg-[var(--color-primary-light)]"
                                        >
                                            {link.label}
                                        </Link>
                                    ))}
                                </div>
                            ),
                        },
                    ]}
                />

                <div className="space-y-2 rounded-2xl border border-[var(--color-border)] p-4">
                    <div className="flex items-center gap-2 text-sm font-semibold text-[var(--color-text-primary)]">
                        <Palette className="size-4 text-[var(--color-primary)]" />
                        Theme
                    </div>
                    <div className="grid grid-cols-2 gap-2">
                        {SITE_THEMES.map((option) => (
                            <button
                                key={option.value}
                                type="button"
                                onClick={() => setTheme(option.value)}
                                className={`flex items-center justify-between rounded-xl border px-3 py-2.5 text-sm font-medium transition-colors ${
                                    theme === option.value
                                        ? 'border-[var(--color-primary)] bg-[var(--color-primary-light)] text-[var(--color-primary)]'
                                        : 'border-[var(--color-border)] text-[var(--color-text-primary)] hover:bg-[var(--color-surface-alt)]'
                                }`}
                            >
                                {option.label}
                                {theme === option.value ? (
                                    <Check className="size-4" />
                                ) : null}
                            </button>
                        ))}
                    </div>
                </div>

                {user && (
                    <div className="mt-auto space-y-3 border-t border-[var(--color-border)] pt-4">
                        <Button
                            type="button"
                            variant="outline"
                            fullWidth
                            leftIcon={<LogOut className="size-4" />}
                            onClick={() => {
                                onClose();
                                router.post(route('logout'));
                            }}
                        >
                            Log out
                        </Button>
                    </div>
                )}
            </div>
        </Drawer>
    );
}
