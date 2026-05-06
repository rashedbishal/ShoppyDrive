import { Link, router, usePage } from '@inertiajs/react';
import {
    ChevronRight,
    Heart,
    LayoutDashboard,
    Lock,
    LogOut,
    MapPin,
    Package,
    Settings,
    User,
} from 'lucide-react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { cn, getInitials } from '@/lib/utils';
import type { SharedPageProps } from '@/types';

const navItems = [
    { label: 'Dashboard', href: '/account', icon: LayoutDashboard },
    { label: 'My Orders', href: '/account/orders', icon: Package },
    { label: 'Wishlist', href: '/account/wishlist', icon: Heart },
    { label: 'Addresses', href: '/account/addresses', icon: MapPin },
    { label: 'Profile', href: '/account/profile', icon: User },
    { label: 'Change Password', href: '/account/password', icon: Lock },
];

export default function AccountLayout({ children }: { children: React.ReactNode }): React.JSX.Element {
    const page = usePage<SharedPageProps>();
    const { auth } = page.props;
    const currentPath = page.url.split('?')[0];

    return (
        <div className="min-h-screen bg-[var(--color-surface)] py-8 lg:py-12">
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div className="flex flex-col gap-6 lg:flex-row lg:items-start lg:gap-8">

                    {/* Sidebar */}
                    <aside className="w-full lg:sticky lg:top-24 lg:w-72 lg:shrink-0">
                        {/* User card */}
                        <div className="relative overflow-hidden rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface-alt)] p-5">
                            <div className="pointer-events-none absolute inset-x-0 top-0 h-24 bg-gradient-to-br from-[var(--color-primary)]/15 via-[var(--color-primary)]/5 to-transparent" />

                            <div className="relative flex flex-col items-center gap-3 text-center">
                                <div className="relative">
                                    <Avatar className="size-16 ring-2 ring-[var(--color-primary)]/30 ring-offset-2 ring-offset-[var(--color-surface-alt)]">
                                        <AvatarImage src={auth.user?.avatar ?? undefined} />
                                        <AvatarFallback className="bg-[var(--color-primary)] text-lg font-bold text-white">
                                            {getInitials(auth.user?.name ?? '')}
                                        </AvatarFallback>
                                    </Avatar>
                                    <span className="absolute -bottom-0.5 -right-0.5 flex size-4 items-center justify-center rounded-full bg-[var(--color-success)] ring-2 ring-[var(--color-surface-alt)]" />
                                </div>

                                <div className="space-y-0.5">
                                    <p className="font-semibold text-[var(--color-text-primary)]">{auth.user?.name}</p>
                                    <p className="text-xs text-[var(--color-text-muted)]">{auth.user?.email}</p>
                                </div>

                                <div className="mt-1 flex w-full divide-x divide-[var(--color-border)] rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)]">
                                    <div className="flex-1 py-2 text-center">
                                        <p className="text-sm font-bold text-[var(--color-text-primary)]">12</p>
                                        <p className="text-[11px] text-[var(--color-text-muted)]">Orders</p>
                                    </div>
                                    <div className="flex-1 py-2 text-center">
                                        <p className="text-sm font-bold text-[var(--color-text-primary)]">4</p>
                                        <p className="text-[11px] text-[var(--color-text-muted)]">Wishlist</p>
                                    </div>
                                    <div className="flex-1 py-2 text-center">
                                        <p className="text-sm font-bold text-[var(--color-text-primary)]">2</p>
                                        <p className="text-[11px] text-[var(--color-text-muted)]">Addresses</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Nav */}
                        <nav className="mt-3 overflow-hidden rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface-alt)]">
                            <div className="space-y-0.5 p-2">
                                {navItems.map(({ label, href, icon: Icon }) => {
                                    const isActive = href === '/account'
                                        ? currentPath === '/account'
                                        : currentPath === href || currentPath.startsWith(href + '/');
                                    return (
                                        <Link
                                            key={href}
                                            href={href}
                                            className={cn(
                                                'flex items-center justify-between rounded-xl px-3.5 py-2.5 text-sm font-medium transition',
                                                isActive
                                                    ? 'bg-[var(--color-primary)] text-white'
                                                    : 'text-[var(--color-text-secondary)] hover:bg-[var(--color-surface)] hover:text-[var(--color-text-primary)]',
                                            )}
                                        >
                                            <span className="flex items-center gap-3">
                                                <Icon className="size-4 shrink-0" />
                                                {label}
                                            </span>
                                            {isActive ? <ChevronRight className="size-3.5" /> : null}
                                        </Link>
                                    );
                                })}
                            </div>

                            <div className="border-t border-[var(--color-border)] p-2">
                                <button
                                    type="button"
                                    onClick={() => router.post(route('logout'))}
                                    className="flex w-full items-center gap-3 rounded-xl px-3.5 py-2.5 text-sm font-medium text-[var(--color-error)] transition hover:bg-[var(--color-error)]/10"
                                >
                                    <LogOut className="size-4 shrink-0" />
                                    Sign out
                                </button>
                            </div>
                        </nav>
                    </aside>

                    {/* Main content */}
                    <main className="min-w-0 flex-1">
                        {children}
                    </main>
                </div>
            </div>
        </div>
    );
}
