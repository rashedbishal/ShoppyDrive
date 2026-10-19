import { Link, router, usePage } from '@inertiajs/react';
import { FolderOpen, LayoutGrid, LogIn, LogOut, ShoppingBag, UserCircle2 } from 'lucide-react';
import { lazy, Suspense } from 'react';
import { route } from 'ziggy-js';
import { primaryLinks } from '@/components/layout/navigation';
import { Accordion } from '@/components/ui/accordion';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Drawer } from '@/components/ui/drawer';
import { useInitials } from '@/hooks/use-initials';
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

export default function MobileMenu({ isOpen, onClose }: MobileMenuProps): React.JSX.Element {
    const page = usePage<SharedPageProps>();
    const getInitials = useInitials();
    const user = page.props.auth?.user ?? null;
    const navCategories = page.props.navCategories ?? [];

    return (
        <Drawer
            isOpen={isOpen}
            onClose={onClose}
            title="Menu"
            description="Browse categories, track orders, and access your account."
            side="left"
            size="md"
        >
            <div className="flex h-full flex-col gap-5">
                <div className="flex items-center gap-3 rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface-alt)] p-4">
                    <Avatar className="size-12 border border-[var(--color-border)]">
                        <AvatarImage src={user?.avatar ?? undefined} alt={user?.name ?? 'Guest user'} />
                        <AvatarFallback className="bg-[var(--color-secondary)] text-white">
                            {getInitials(user?.name ?? 'Guest')}
                        </AvatarFallback>
                    </Avatar>
                    <div className="min-w-0 flex-1">
                        <p className="truncate text-sm font-semibold text-[var(--color-text-primary)]">
                            {user?.name ?? 'Guest shopper'}
                        </p>
                        <p className="truncate text-sm text-[var(--color-text-secondary)]">
                            {user?.email ?? 'Sign in to sync your cart and wishlist'}
                        </p>
                    </div>
                </div>

                <div className="grid grid-cols-3 gap-3">
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
                    <Link
                        href="/account"
                        onClick={onClose}
                        className="flex flex-col items-center justify-center rounded-2xl border border-[var(--color-border)] bg-surface p-3 text-center text-sm font-medium text-[var(--color-text-primary)] shadow-[var(--shadow-card)]"
                    >
                        <UserCircle2 className="mb-2 size-5 text-[var(--color-primary)]" />
                        Account
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
                                        <div className="flex items-center gap-2 px-3 py-3 text-sm text-[var(--color-text-muted)]">
                                            <FolderOpen className="size-4 opacity-50" />
                                            No categories yet
                                        </div>
                                    ) : navCategories.map((category, index) => (
                                        <Link
                                            key={category.id}
                                            href={`/category/${category.slug}`}
                                            onClick={onClose}
                                            style={{ '--category-color': resolveCategoryColor(category.color, index) } as React.CSSProperties}
                                            className="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-[var(--color-text-primary)] transition-colors hover:bg-[var(--color-primary-light)] active:bg-[var(--color-primary-light)]"
                                        >
                                            {/* Same colour-tinted icon tile as the desktop mega menu */}
                                            <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-[color-mix(in_srgb,var(--category-color)_12%,white)] text-[var(--category-color)] ring-1 ring-[color-mix(in_srgb,var(--category-color)_18%,white)]">
                                                <Suspense fallback={<ShoppingBag className="size-4" strokeWidth={1.75} />}>
                                                    <CategoryIcon icon={category.icon} className="size-4" />
                                                </Suspense>
                                            </span>
                                            <span className="min-w-0 flex-1">
                                                <span className="block truncate font-medium">
                                                    {category.name}
                                                </span>
                                                {category.description && (
                                                    <span className="block truncate text-xs text-[var(--color-text-secondary)]">
                                                        {category.description}
                                                    </span>
                                                )}
                                            </span>
                                        </Link>
                                    ))}
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

                <div className="mt-auto space-y-3 border-t border-[var(--color-border)] pt-4">
                    {user ? (
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
                    ) : (
                        <div>
                            <Button asChild fullWidth leftIcon={<LogIn className="size-4" />}>
                                <Link href={route('login')} onClick={onClose}>
                                    Log in
                                </Link>
                            </Button>
                        </div>
                    )}

                    <div className="flex items-center justify-between gap-3 rounded-2xl bg-[var(--color-accent-light)] px-4 py-3">
                        <div>
                            <p className="text-sm font-semibold text-[var(--color-secondary)]">
                                BDT pricing
                            </p>
                            <p className="text-xs text-[var(--color-text-secondary)]">
                                All prices are displayed in Bangladeshi Taka.
                            </p>
                        </div>
                        <Badge variant="amber">৳</Badge>
                    </div>
                </div>
            </div>
        </Drawer>
    );
}
