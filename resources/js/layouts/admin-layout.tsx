import { Link, router, usePage } from '@inertiajs/react';
import {
    Award,
    Folder,
    FolderTree,
    Inbox,
    Info,
    LayoutDashboard,
    LayoutTemplate,
    LogOut,
    Mail,
    Menu,
    MessageSquare,
    MonitorPlay,
    Newspaper,
    Package,
    Palette,
    Search,
    Settings,
    Share2,
    ShoppingCart,
    Smartphone,
    Star,
    Tag,
    Trash2,
    Truck,
    UserCog,
    Users,
    X,
    Zap,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { useSmsPolling } from '@/hooks/useSmsPolling';
import type { SmsToast } from '@/hooks/useSmsPolling';
import { dashboard as adminDashboard } from '@/routes/admin';
import type { AppSettings, SharedPageProps } from '@/types';

type NavItem = {
    label: string;
    href: string;
    icon: LucideIcon;
    exact?: boolean;
    extraMatches: string[];
};

type NavSection = {
    label: string;
    items: NavItem[];
};

const navSections: NavSection[] = [
    {
        label: 'Overview',
        items: [
            {
                label: 'Dashboard',
                href: '/admin/dashboard',
                icon: LayoutDashboard,
                extraMatches: [] as string[],
            },
        ],
    },
    {
        label: 'Catalog',
        items: [
            {
                label: 'Products',
                href: '/admin/products',
                icon: Package,
                extraMatches: [] as string[],
            },
            {
                label: 'Categories',
                href: '/admin/categories',
                icon: Folder,
                extraMatches: [] as string[],
            },
            {
                label: 'Sub-categories',
                href: '/admin/sub-categories',
                icon: FolderTree,
                extraMatches: [] as string[],
            },
            {
                label: 'Brands',
                href: '/admin/brands',
                icon: Award,
                extraMatches: [] as string[],
            },
            {
                label: 'Attributes',
                href: '/admin/attributes',
                icon: Palette,
                extraMatches: [] as string[],
            },
        ],
    },
    {
        label: 'Sales',
        items: [
            {
                label: 'Orders',
                href: '/admin/orders',
                icon: ShoppingCart,
                extraMatches: [] as string[],
            },
            {
                label: 'Trashed Orders',
                href: '/admin/orders/trashed',
                icon: Trash2,
                extraMatches: [] as string[],
            },
            {
                label: 'Coupons',
                href: '/admin/coupons',
                icon: Tag,
                extraMatches: [] as string[],
            },
            {
                label: 'Flash Sales',
                href: '/admin/flash-sales',
                icon: Zap,
                extraMatches: [] as string[],
            },
            {
                label: 'Received SMS',
                href: '/admin/received-sms',
                icon: MessageSquare,
                extraMatches: [] as string[],
            },
            {
                label: 'SMS Patterns',
                href: '/admin/sms-patterns',
                icon: Settings,
                extraMatches: [] as string[],
            },
        ],
    },
    {
        label: 'Content',
        items: [
            {
                label: 'Homepage',
                href: '/admin/homepage',
                icon: LayoutTemplate,
                exact: false,
                extraMatches: [
                    '/admin/hero-slides',
                    '/admin/promotional-banners',
                ],
            },
            {
                label: 'About Page',
                href: '/admin/settings/about-page',
                icon: Info,
                exact: false,
                extraMatches: [] as string[],
            },
            {
                label: 'Contact Page',
                href: '/admin/settings/contact-page',
                icon: MessageSquare,
                exact: false,
                extraMatches: [] as string[],
            },
            {
                label: 'Landing Pages',
                href: '/admin/landing-pages',
                icon: MonitorPlay,
                exact: false,
                extraMatches: [] as string[],
            },
            {
                label: 'Blog',
                href: '/admin/blogs',
                icon: Newspaper,
                exact: false,
                extraMatches: [] as string[],
            },
            {
                label: 'Search Keywords',
                href: '/admin/popular-keywords',
                icon: Search,
                exact: false,
                extraMatches: [] as string[],
            },
        ],
    },
    {
        label: 'Users',
        items: [
            {
                label: 'Customers',
                href: '/admin/customers',
                icon: Users,
                extraMatches: [] as string[],
            },
            {
                label: 'Reviews',
                href: '/admin/reviews',
                icon: Star,
                extraMatches: [] as string[],
            },
            {
                label: 'Contact Messages',
                href: '/admin/contact-messages',
                icon: Inbox,
                extraMatches: [] as string[],
            },
        ],
    },
    {
        label: 'Marketing',
        items: [
            {
                label: 'Newsletter',
                href: '/admin/newsletter',
                icon: Mail,
                extraMatches: ['/admin/newsletter/compose'] as string[],
            },
        ],
    },
    {
        label: 'System',
        items: [
            {
                label: 'Admins',
                href: '/admin/admins',
                icon: UserCog,
                exact: false,
                extraMatches: [] as string[],
            },
            {
                label: 'Settings',
                href: '/admin/settings',
                icon: Settings,
                exact: true,
                extraMatches: [] as string[],
            },
            {
                label: 'Social Config',
                href: '/admin/settings/social',
                icon: Share2,
                exact: false,
                extraMatches: [] as string[],
            },
            {
                label: 'Courier Config',
                href: '/admin/settings/courier',
                icon: Truck,
                exact: false,
                extraMatches: [] as string[],
            },
            {
                label: 'SMS Forwarder',
                href: '/admin/settings/sms-forwarder',
                icon: Smartphone,
                exact: false,
                extraMatches: [] as string[],
            },
        ],
    },
];

const allNavItems = navSections.flatMap((section) => section.items);

/**
 * The nav item whose href is the longest (most specific) prefix of the
 * current URL — e.g. for /admin/orders/trashed this picks "Trashed Orders"
 * over "Orders", so only one item highlights instead of both.
 */
function findMostSpecificMatch(currentUrl: string): NavItem | undefined {
    return allNavItems
        .filter(
            (candidate) =>
                !candidate.exact && currentUrl.startsWith(candidate.href),
        )
        .sort((a, b) => b.href.length - a.href.length)[0];
}

function SidebarContent({
    currentUrl,
    logoUrl,
    onClose,
}: {
    currentUrl: string;
    logoUrl?: string | null;
    onClose?: () => void;
}) {
    const mostSpecificMatch = findMostSpecificMatch(currentUrl);

    return (
        <div className="flex h-full flex-col">
            {/* Logo */}
            <div className="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                <Link
                    href="/admin/dashboard"
                    className="flex items-center gap-2.5"
                    onClick={onClose}
                >
                    {logoUrl ? (
                        <img
                            src={logoUrl}
                            alt="Logo"
                            className="size-8 rounded-xl object-contain"
                        />
                    ) : (
                        <span className="flex size-8 items-center justify-center rounded-xl bg-[#c8102e]">
                            <AppLogoIcon className="size-4 fill-current text-white" />
                        </span>
                    )}
                    <div className="leading-none">
                        <p className="text-[14px] font-bold text-slate-900">
                            ShoppyDrive
                        </p>
                        <p className="text-[11px] text-slate-400">
                            Admin Panel
                        </p>
                    </div>
                </Link>
                {onClose && (
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                    >
                        <X className="size-4" />
                    </button>
                )}
            </div>

            {/* Nav */}
            <nav className="flex-1 overflow-y-auto px-3 py-4">
                {navSections.map((section) => (
                    <div key={section.label} className="mb-5">
                        <p className="mb-1.5 px-2 text-[10px] font-semibold tracking-widest text-slate-400 uppercase">
                            {section.label}
                        </p>
                        {section.items.map((item) => {
                            const isActive =
                                (item.exact
                                    ? currentUrl === item.href
                                    : mostSpecificMatch?.href === item.href) ||
                                item.extraMatches.some((m) =>
                                    currentUrl.startsWith(m),
                                );
                            const Icon = item.icon;

                            return (
                                <Link
                                    key={item.href}
                                    href={item.href}
                                    onClick={onClose}
                                    className={`mb-0.5 flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium transition-colors ${
                                        isActive
                                            ? 'bg-[#c8102e]/8 text-[#c8102e]'
                                            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
                                    }`}
                                >
                                    <Icon className="size-4 shrink-0" />
                                    {item.label}
                                    {isActive && (
                                        <span className="ml-auto h-1.5 w-1.5 rounded-full bg-[#c8102e]" />
                                    )}
                                </Link>
                            );
                        })}
                    </div>
                ))}
            </nav>
        </div>
    );
}

function SmsToastItem({
    toast,
    onDismiss,
}: {
    toast: SmsToast;
    onDismiss: () => void;
}) {
    useEffect(() => {
        const id = setTimeout(onDismiss, 8000);

        return () => clearTimeout(id);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []); // run once on mount — dismiss is stable via useCallback in parent

    return (
        <div className="flex w-80 animate-in items-start gap-3 rounded-xl bg-slate-900 px-4 py-3 shadow-xl ring-1 ring-white/10 duration-300 fade-in slide-in-from-right-4">
            <div className="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-pink-600">
                <MessageSquare className="size-3.5 text-white" />
            </div>
            <div className="min-w-0 flex-1">
                <p className="text-xs font-bold text-white">New SMS Received</p>
                <p className="truncate text-xs text-slate-400">
                    From: {toast.sender}
                </p>
                {toast.parsed_transaction_id && (
                    <p className="mt-0.5 font-mono text-xs font-semibold text-pink-400">
                        TrxID: {toast.parsed_transaction_id}
                        {toast.parsed_amount !== null && (
                            <span className="font-sans font-normal text-slate-300">
                                {' '}
                                · ৳{toast.parsed_amount.toLocaleString()}
                            </span>
                        )}
                    </p>
                )}
                <p className="mt-1 line-clamp-2 text-[11px] leading-relaxed text-slate-400">
                    {toast.content}
                </p>
                <Link
                    href="/admin/received-sms"
                    className="mt-1.5 inline-block text-[11px] font-semibold text-pink-400 hover:text-pink-300"
                >
                    View all SMS →
                </Link>
            </div>
            <button
                type="button"
                onClick={onDismiss}
                className="mt-0.5 shrink-0 text-slate-500 transition-colors hover:text-slate-300"
            >
                <X className="size-3.5" />
            </button>
        </div>
    );
}

export default function AdminLayout({
    children,
}: {
    children: React.ReactNode;
}) {
    const page = usePage<SharedPageProps>();
    const admin = page.props.auth?.admin;
    const currentUrl = page.url;
    const logoUrl = (page.props.settings as AppSettings)?.logo ?? null;
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [hasMobileLogoError, setHasMobileLogoError] = useState(false);
    const { toasts, dismiss } = useSmsPolling();
    const handleDismiss = useCallback((key: number) => dismiss(key), [dismiss]);

    const handleLogout = () => router.post(route('admin.logout'));

    return (
        <div className="flex min-h-screen bg-slate-50">
            {/* Desktop sidebar */}
            <aside className="sticky top-0 hidden h-screen w-56 shrink-0 flex-col border-r border-slate-200 bg-white lg:flex">
                <SidebarContent currentUrl={currentUrl} logoUrl={logoUrl} />
            </aside>

            {/* Mobile sidebar overlay */}
            {sidebarOpen && (
                <div className="fixed inset-0 z-50 lg:hidden">
                    <div
                        className="absolute inset-0 bg-black/40"
                        onClick={() => setSidebarOpen(false)}
                    />
                    <aside className="absolute top-0 left-0 h-full w-56 bg-white shadow-xl">
                        <SidebarContent
                            currentUrl={currentUrl}
                            logoUrl={logoUrl}
                            onClose={() => setSidebarOpen(false)}
                        />
                    </aside>
                </div>
            )}

            {/* Main area */}
            <div className="flex min-w-0 flex-1 flex-col">
                {/* Top bar */}
                <header className="sticky top-0 z-40 flex h-14 items-center gap-3 border-b border-slate-200 bg-white px-4">
                    <button
                        type="button"
                        className="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 lg:hidden"
                        onClick={() => setSidebarOpen(true)}
                    >
                        <Menu className="size-5" />
                    </button>

                    <Link
                        href={adminDashboard()}
                        aria-label="Admin dashboard home"
                        className="absolute left-1/2 flex size-9 -translate-x-1/2 items-center justify-center overflow-hidden rounded-xl bg-[#c8102e] shadow-sm lg:hidden"
                    >
                        {logoUrl && !hasMobileLogoError ? (
                            <img
                                src={logoUrl}
                                alt="ShoppyDrive"
                                onError={() => setHasMobileLogoError(true)}
                                className="h-full w-full bg-white object-contain p-1"
                            />
                        ) : (
                            <AppLogoIcon className="size-5 fill-current text-white" />
                        )}
                    </Link>

                    <div className="flex-1" />

                    {admin && (
                        <span className="hidden text-sm font-medium text-slate-700 sm:block">
                            {admin.name}
                        </span>
                    )}
                    <button
                        type="button"
                        onClick={handleLogout}
                        className="flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-600 transition hover:border-red-200 hover:bg-red-50 hover:text-red-600"
                    >
                        <LogOut className="size-4" />
                        <span className="hidden sm:inline">Logout</span>
                    </button>
                </header>

                {/* Page content */}
                <main className="flex-1 p-6">{children}</main>
            </div>

            {/* SMS toast notifications */}
            {toasts.length > 0 && (
                <div className="fixed right-5 bottom-5 z-50 flex flex-col items-end gap-2.5">
                    {toasts.map((toast) => (
                        <SmsToastItem
                            key={toast.key}
                            toast={toast}
                            onDismiss={() => handleDismiss(toast.key)}
                        />
                    ))}
                </div>
            )}
        </div>
    );
}
