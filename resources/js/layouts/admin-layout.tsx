import { Link, router, usePage } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import {
    Award,
    Ban,
    Folder,
    FolderTree,
    Gift,
    History,
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
    PackagePlus,
    Palette,
    PhoneMissed,
    Search,
    Settings,
    Share2,
    ShieldCheck,
    ShoppingCart,
    Smartphone,
    Star,
    Tag,
    Trash2,
    TriangleAlert,
    Truck,
    Undo2,
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
    /** Omitted for items every admin can always see (Dashboard, Admins). */
    section?: string;
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
                section: 'products',
            },
            {
                label: 'Categories',
                href: '/admin/categories',
                icon: Folder,
                extraMatches: [] as string[],
                section: 'categories',
            },
            {
                label: 'Sub-categories',
                href: '/admin/sub-categories',
                icon: FolderTree,
                extraMatches: [] as string[],
                section: 'sub_categories',
            },
            {
                label: 'Brands',
                href: '/admin/brands',
                icon: Award,
                extraMatches: [] as string[],
                section: 'brands',
            },
            {
                label: 'Attributes',
                href: '/admin/attributes',
                icon: Palette,
                extraMatches: [] as string[],
                section: 'attributes',
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
                section: 'orders',
            },
            {
                label: 'Trashed Orders',
                href: '/admin/orders/trashed',
                icon: Trash2,
                extraMatches: [] as string[],
                section: 'trashed_orders',
            },
            {
                label: 'Incomplete Orders',
                href: '/admin/orders/incomplete',
                icon: PhoneMissed,
                extraMatches: [] as string[],
                section: 'incomplete_orders',
            },
            {
                label: 'Returns',
                href: '/admin/returns',
                icon: Undo2,
                extraMatches: [] as string[],
                section: 'returns',
            },
            {
                label: 'Coupons',
                href: '/admin/coupons',
                icon: Tag,
                extraMatches: [] as string[],
                section: 'coupons',
            },
            {
                label: 'Flash Sales',
                href: '/admin/flash-sales',
                icon: Zap,
                extraMatches: [] as string[],
                section: 'flash_sales',
            },
            {
                label: 'Bundles',
                href: '/admin/product-bundles',
                icon: PackagePlus,
                extraMatches: [] as string[],
                section: 'bundles',
            },
            {
                label: 'Received SMS',
                href: '/admin/received-sms',
                icon: MessageSquare,
                extraMatches: [] as string[],
                section: 'received_sms',
            },
            {
                label: 'SMS Patterns',
                href: '/admin/sms-patterns',
                icon: Settings,
                extraMatches: [] as string[],
                section: 'sms_patterns',
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
                section: 'homepage',
            },
            {
                label: 'About Page',
                href: '/admin/settings/about-page',
                icon: Info,
                exact: false,
                extraMatches: [] as string[],
                section: 'about_page',
            },
            {
                label: 'Contact Page',
                href: '/admin/settings/contact-page',
                icon: MessageSquare,
                exact: false,
                extraMatches: [] as string[],
                section: 'contact_page',
            },
            {
                label: 'Landing Pages',
                href: '/admin/landing-pages',
                icon: MonitorPlay,
                exact: false,
                extraMatches: [] as string[],
                section: 'landing_pages',
            },
            {
                label: 'Blog',
                href: '/admin/blogs',
                icon: Newspaper,
                exact: false,
                extraMatches: [] as string[],
                section: 'blog',
            },
            {
                label: 'Search Keywords',
                href: '/admin/popular-keywords',
                icon: Search,
                exact: false,
                extraMatches: [] as string[],
                section: 'search_keywords',
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
                section: 'customers',
            },
            {
                label: 'Reviews',
                href: '/admin/reviews',
                icon: Star,
                extraMatches: [] as string[],
                section: 'reviews',
            },
            {
                label: 'Contact Messages',
                href: '/admin/contact-messages',
                icon: Inbox,
                extraMatches: [] as string[],
                section: 'contact_messages',
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
                section: 'newsletter',
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
                label: 'Audit Trail',
                href: '/admin/audit-trail',
                icon: History,
                exact: false,
                extraMatches: [] as string[],
                section: 'audit_trail',
            },
            {
                label: 'Failed Jobs',
                href: '/admin/failed-jobs',
                icon: TriangleAlert,
                exact: false,
                extraMatches: [] as string[],
                section: 'failed_jobs',
            },
            {
                label: 'Blocked IPs',
                href: '/admin/blocked-ips',
                icon: Ban,
                extraMatches: [] as string[],
                section: 'blocked_ips',
            },
            {
                label: 'Settings',
                href: '/admin/settings',
                icon: Settings,
                exact: true,
                extraMatches: [] as string[],
                section: 'settings',
            },
            {
                label: 'Social Config',
                href: '/admin/settings/social',
                icon: Share2,
                exact: false,
                extraMatches: [] as string[],
                section: 'social_config',
            },
            {
                label: 'Courier Config',
                href: '/admin/settings/courier',
                icon: Truck,
                exact: false,
                extraMatches: [] as string[],
                section: 'courier_config',
            },
            {
                label: 'SMS Forwarder',
                href: '/admin/settings/sms-forwarder',
                icon: Smartphone,
                exact: false,
                extraMatches: [] as string[],
                section: 'sms_forwarder',
            },
            {
                label: 'Referral Program',
                href: '/admin/settings/referral',
                icon: Gift,
                exact: false,
                extraMatches: [] as string[],
                section: 'referral_program',
            },
            {
                label: 'Security',
                href: '/admin/settings/security',
                icon: ShieldCheck,
                exact: false,
                extraMatches: [] as string[],
                section: 'security',
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

type CurrentAdmin = {
    name: string;
    is_mother: boolean;
    permissions: Record<string, string[]> | null;
};

/** Items with no `section` (Dashboard, Admins) are always visible. */
function canViewSection(admin: CurrentAdmin | undefined, section?: string) {
    if (!section) {
        return true;
    }

    if (!admin) {
        return false;
    }

    return (
        admin.is_mother ||
        (admin.permissions?.[section]?.includes('view') ?? false)
    );
}

function SidebarContent({
    currentUrl,
    logoUrl,
    admin,
    unseenOrdersCount,
    unmatchedSmsCount,
    forwarderSeemsDown,
    onClose,
}: {
    currentUrl: string;
    logoUrl?: string | null;
    admin?: CurrentAdmin;
    unseenOrdersCount?: number | null;
    unmatchedSmsCount?: number;
    forwarderSeemsDown?: boolean;
    onClose?: () => void;
}) {
    const mostSpecificMatch = findMostSpecificMatch(currentUrl);
    const visibleNavSections = navSections
        .map((section) => ({
            ...section,
            items: section.items.filter((item) =>
                canViewSection(admin, item.section),
            ),
        }))
        .filter((section) => section.items.length > 0);

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
                {visibleNavSections.map((section) => (
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
                            const unseenCount =
                                item.href === '/admin/orders'
                                    ? (unseenOrdersCount ?? 0)
                                    : item.href === '/admin/received-sms'
                                      ? (unmatchedSmsCount ?? 0)
                                      : 0;

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
                                    <span className="ml-auto flex items-center gap-1.5">
                                        {/* OPS-005: the forwarder phone is a single point of failure with no other liveness signal. */}
                                        {item.href === '/admin/received-sms' &&
                                            forwarderSeemsDown && (
                                                <span title="No payment SMS received in a while — check the forwarder phone">
                                                    <TriangleAlert className="size-3.5 shrink-0 text-amber-500" />
                                                </span>
                                            )}
                                        {unseenCount > 0 && (
                                            <span className="flex h-5 min-w-5 items-center justify-center rounded-full bg-[#c8102e] px-1.5 text-[11px] font-bold text-white">
                                                {unseenCount > 99
                                                    ? '99+'
                                                    : unseenCount}
                                            </span>
                                        )}
                                        {isActive && (
                                            <span className="h-1.5 w-1.5 shrink-0 rounded-full bg-[#c8102e]" />
                                        )}
                                    </span>
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
    const admin = page.props.auth?.admin as CurrentAdmin | null;
    const unseenOrdersCount = page.props.unseen_orders_count;
    const currentUrl = page.url;
    const logoUrl = (page.props.settings as AppSettings)?.logo ?? null;
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [hasMobileLogoError, setHasMobileLogoError] = useState(false);
    const { toasts, dismiss, unmatchedCount, forwarderSeemsDown } =
        useSmsPolling();
    const handleDismiss = useCallback((key: number) => dismiss(key), [dismiss]);

    // Prefetched pages (e.g. Add Product) hold form options such as categories.
    // Any admin write can change those, so drop what's cached instead of
    // showing a form with stale choices.
    useEffect(
        () =>
            router.on('before', (event) => {
                if (event.detail.visit.method !== 'get') {
                    router.flushAll();
                }
            }),
        [],
    );

    const handleLogout = () => router.post(route('admin.logout'));

    return (
        <div className="admin-shell flex min-h-screen bg-slate-50">
            {/* Desktop sidebar */}
            <aside className="sticky top-0 hidden h-screen w-56 shrink-0 flex-col border-r border-slate-200 bg-white lg:flex">
                <SidebarContent
                    currentUrl={currentUrl}
                    logoUrl={logoUrl}
                    admin={admin ?? undefined}
                    unseenOrdersCount={unseenOrdersCount}
                    unmatchedSmsCount={unmatchedCount}
                    forwarderSeemsDown={forwarderSeemsDown}
                />
            </aside>

            {/* Mobile sidebar overlay */}
            <AnimatePresence>
                {sidebarOpen && (
                    <div
                        key="mobile-sidebar"
                        className="fixed inset-0 z-50 lg:hidden"
                    >
                        <motion.div
                            className="absolute inset-0 bg-black/40"
                            onClick={() => setSidebarOpen(false)}
                            initial={{ opacity: 0 }}
                            animate={{ opacity: 1 }}
                            exit={{ opacity: 0 }}
                            transition={{ duration: 0.25 }}
                        />
                        <motion.aside
                            className="absolute top-0 left-0 h-full w-56 bg-white shadow-xl"
                            initial={{ x: '-100%' }}
                            animate={{ x: 0 }}
                            exit={{ x: '-100%' }}
                            transition={{
                                duration: 0.32,
                                ease: [0.22, 1, 0.36, 1],
                            }}
                        >
                            <SidebarContent
                                currentUrl={currentUrl}
                                logoUrl={logoUrl}
                                admin={admin ?? undefined}
                                unseenOrdersCount={unseenOrdersCount}
                                unmatchedSmsCount={unmatchedCount}
                                forwarderSeemsDown={forwarderSeemsDown}
                                onClose={() => setSidebarOpen(false)}
                            />
                        </motion.aside>
                    </div>
                )}
            </AnimatePresence>

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
                <main className="flex-1 p-6">
                    <div key={page.component} className="admin-content">
                        {children}
                    </div>
                </main>
            </div>

            {/* SMS toast notifications */}
            <div className="pointer-events-none fixed right-5 bottom-5 z-50 flex flex-col items-end gap-2.5">
                <AnimatePresence>
                    {toasts.map((toast) => (
                        <motion.div
                            key={toast.key}
                            className="pointer-events-auto"
                            layout
                            exit={{ opacity: 0, x: 48, scale: 0.96 }}
                            transition={{ duration: 0.25, ease: 'easeIn' }}
                        >
                            <SmsToastItem
                                toast={toast}
                                onDismiss={() => handleDismiss(toast.key)}
                            />
                        </motion.div>
                    ))}
                </AnimatePresence>
            </div>
        </div>
    );
}
