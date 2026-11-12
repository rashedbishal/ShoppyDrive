import { Link, router, usePage } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import {
    Activity,
    ArrowUpDown,
    Award,
    Ban,
    Bot,
    ChevronDown,
    DatabaseBackup,
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
    Megaphone,
    Menu,
    MessageSquare,
    MonitorPlay,
    Newspaper,
    Package,
    PackagePlus,
    Palette,
    PanelLeftClose,
    PanelLeftOpen,
    PhoneMissed,
    Radar,
    ScrollText,
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
import { useWebPushSms } from '@/hooks/useWebPushSms';
import type { SmsToast } from '@/hooks/useWebPushSms';
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
    motherOnly?: boolean;
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
            {
                label: 'Display Order',
                href: '/admin/settings/display-order',
                icon: ArrowUpDown,
                exact: false,
                extraMatches: [] as string[],
                section: 'display_order',
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
                label: 'Database Backup',
                href: '/admin/database-backup',
                icon: DatabaseBackup,
                exact: false,
                extraMatches: [] as string[],
                motherOnly: true,
            },
            {
                label: 'Error Log',
                href: '/admin/error-log',
                icon: ScrollText,
                exact: false,
                extraMatches: [] as string[],
                motherOnly: true,
            },
            {
                label: 'System Monitor',
                href: '/admin/monitor',
                icon: Activity,
                exact: false,
                extraMatches: [] as string[],
                section: 'failed_jobs',
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
                label: 'AI Assistant',
                href: '/admin/settings/ai-assistant',
                icon: Bot,
                exact: false,
                extraMatches: [] as string[],
                section: 'ai_assistant',
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
                label: 'Marketing & Tracking',
                href: '/admin/settings/marketing',
                icon: Megaphone,
                exact: false,
                extraMatches: [] as string[],
                section: 'marketing_tracking',
            },
            {
                label: 'Pixel & CAPI Log',
                href: '/admin/pixel-capi-log',
                icon: Radar,
                exact: false,
                extraMatches: [] as string[],
                section: 'pixel_capi_log',
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

const SIDEBAR_COLLAPSED_KEY = 'admin.sidebar.collapsed';
const SIDEBAR_CLOSED_SECTIONS_KEY = 'admin.sidebar.closedSections';
/** PERF-006 (web push follow-up): dismissing the "enable notifications" banner is per-browser, not per-session, so it doesn't reappear on every page load. */
const PUSH_BANNER_DISMISSED_KEY = 'admin.sms.pushBannerDismissed';

function readStored<T>(key: string, fallback: T): T {
    try {
        const raw = window.localStorage.getItem(key);

        return raw === null ? fallback : (JSON.parse(raw) as T);
    } catch {
        return fallback;
    }
}

function writeStored(key: string, value: unknown): void {
    try {
        window.localStorage.setItem(key, JSON.stringify(value));
    } catch {
        // Private mode / blocked storage: the sidebar still works, it just forgets.
    }
}

function SidebarContent({
    currentUrl,
    logoUrl,
    admin,
    unseenOrdersCount,
    unmatchedSmsCount,
    forwarderSeemsDown,
    collapsed = false,
    onToggleCollapsed,
    onLogout,
    onClose,
}: {
    currentUrl: string;
    logoUrl?: string | null;
    admin?: CurrentAdmin;
    unseenOrdersCount?: number | null;
    unmatchedSmsCount?: number;
    forwarderSeemsDown?: boolean;
    collapsed?: boolean;
    onToggleCollapsed?: () => void;
    onLogout?: () => void;
    onClose?: () => void;
}) {
    const mostSpecificMatch = findMostSpecificMatch(currentUrl);
    const [closedSections, setClosedSections] = useState<
        Record<string, boolean>
    >(() => readStored(SIDEBAR_CLOSED_SECTIONS_KEY, {}));
    const visibleNavSections = navSections
        .map((section) => ({
            ...section,
            items: section.items.filter((item) =>
                item.motherOnly
                    ? Boolean(admin?.is_mother)
                    : canViewSection(admin, item.section),
            ),
        }))
        .filter((section) => section.items.length > 0);

    const isItemActive = (item: NavItem): boolean =>
        (item.exact
            ? currentUrl === item.href
            : mostSpecificMatch?.href === item.href) ||
        item.extraMatches.some((m) => currentUrl.startsWith(m));

    const toggleSection = (label: string) => {
        setClosedSections((previous) => {
            const next = { ...previous, [label]: !previous[label] };
            writeStored(SIDEBAR_CLOSED_SECTIONS_KEY, next);

            return next;
        });
    };

    const initials = (admin?.name ?? '?')
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase())
        .join('');

    return (
        <div className="flex h-full flex-col">
            {/* Logo */}
            <div
                className={`flex h-14 shrink-0 items-center border-b border-slate-200/80 ${
                    collapsed ? 'justify-center px-2' : 'justify-between px-4'
                }`}
            >
                <Link
                    href="/admin/dashboard"
                    className="flex min-w-0 items-center gap-2.5"
                    onClick={onClose}
                    title={collapsed ? 'ShoppyDrive — Admin Panel' : undefined}
                >
                    {logoUrl ? (
                        <img
                            src={logoUrl}
                            alt="Logo"
                            className="size-8 shrink-0 rounded-xl object-contain"
                        />
                    ) : (
                        <span className="flex size-8 shrink-0 items-center justify-center rounded-xl bg-[#c8102e]">
                            <AppLogoIcon className="size-4 fill-current text-white" />
                        </span>
                    )}
                    {!collapsed && (
                        <div className="leading-none">
                            <p className="text-[14px] font-bold text-slate-900">
                                ShoppyDrive
                            </p>
                            <p className="text-[11px] text-slate-400">
                                Admin Panel
                            </p>
                        </div>
                    )}
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

            {onToggleCollapsed && (
                <div className={`shrink-0 pt-3 ${collapsed ? 'px-2' : 'px-3'}`}>
                    <button
                        type="button"
                        onClick={onToggleCollapsed}
                        title={collapsed ? 'Expand sidebar' : undefined}
                        aria-label={
                            collapsed ? 'Expand sidebar' : 'Collapse sidebar'
                        }
                        className={`flex w-full items-center rounded-lg py-1.5 text-xs font-medium text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-800 ${
                            collapsed ? 'justify-center' : 'gap-2.5 px-2.5'
                        }`}
                    >
                        {collapsed ? (
                            <PanelLeftOpen className="size-4" />
                        ) : (
                            <>
                                <PanelLeftClose className="size-4" />
                                Collapse
                            </>
                        )}
                    </button>
                </div>
            )}

            {/* Nav */}
            <nav
                className={`flex-1 overflow-x-hidden overflow-y-auto py-3 ${
                    collapsed ? 'px-2' : 'px-3'
                }`}
            >
                {visibleNavSections.map((section, sectionIndex) => {
                    const hasActive = section.items.some(isItemActive);
                    const collapsible = !collapsed && section.items.length > 1;
                    const isOpen =
                        collapsed ||
                        !collapsible ||
                        hasActive ||
                        !closedSections[section.label];

                    return (
                        <div
                            key={section.label}
                            className={
                                sectionIndex > 0
                                    ? collapsed
                                        ? 'mt-2 border-t border-slate-100 pt-2'
                                        : 'mt-1 border-t border-slate-100 pt-2'
                                    : ''
                            }
                        >
                            {!collapsed &&
                                (collapsible ? (
                                    <button
                                        type="button"
                                        onClick={() =>
                                            toggleSection(section.label)
                                        }
                                        disabled={hasActive}
                                        aria-expanded={isOpen}
                                        className="group mb-1 flex w-full items-center justify-between rounded-md px-2.5 py-1 text-[10px] font-semibold tracking-widest text-slate-400 uppercase transition-colors hover:text-slate-600 disabled:cursor-default disabled:hover:text-slate-400"
                                    >
                                        {section.label}
                                        <ChevronDown
                                            className={`size-3.5 transition-transform duration-200 ${
                                                isOpen ? '' : '-rotate-90'
                                            } ${hasActive ? 'opacity-30' : ''}`}
                                        />
                                    </button>
                                ) : (
                                    <p className="mb-1 px-2.5 py-1 text-[10px] font-semibold tracking-widest text-slate-400 uppercase">
                                        {section.label}
                                    </p>
                                ))}
                            <div
                                className={`grid transition-[grid-template-rows] duration-200 ease-out ${
                                    isOpen
                                        ? 'grid-rows-[1fr]'
                                        : 'grid-rows-[0fr]'
                                }`}
                            >
                                <div className="min-h-0 space-y-0.5 overflow-hidden">
                                    {section.items.map((item) => {
                                        const isActive = isItemActive(item);
                                        const Icon = item.icon;
                                        const unseenCount =
                                            item.href === '/admin/orders'
                                                ? (unseenOrdersCount ?? 0)
                                                : item.href ===
                                                    '/admin/received-sms'
                                                  ? (unmatchedSmsCount ?? 0)
                                                  : 0;
                                        const showSmsWarning =
                                            item.href ===
                                                '/admin/received-sms' &&
                                            forwarderSeemsDown;

                                        return (
                                            <Link
                                                key={item.href}
                                                href={item.href}
                                                onClick={onClose}
                                                title={
                                                    collapsed
                                                        ? item.label
                                                        : undefined
                                                }
                                                aria-current={
                                                    isActive
                                                        ? 'page'
                                                        : undefined
                                                }
                                                className={`group relative flex items-center rounded-lg text-sm font-medium transition-colors duration-150 ${
                                                    collapsed
                                                        ? 'h-9 justify-center'
                                                        : 'gap-2.5 px-2.5 py-2'
                                                } ${
                                                    isActive
                                                        ? 'bg-[#c8102e]/8 font-semibold text-[#c8102e]'
                                                        : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
                                                }`}
                                            >
                                                {isActive && (
                                                    <span className="absolute top-1.5 bottom-1.5 left-0 w-[3px] rounded-r-full bg-[#c8102e]" />
                                                )}
                                                <span className="relative flex shrink-0">
                                                    <Icon
                                                        className={`size-4 transition-colors duration-150 ${
                                                            isActive
                                                                ? ''
                                                                : 'text-slate-400 group-hover:text-slate-700'
                                                        }`}
                                                    />
                                                    {collapsed &&
                                                        (unseenCount > 0 ||
                                                            showSmsWarning) && (
                                                            <span
                                                                className={`absolute -top-1 -right-1 size-2 rounded-full ring-2 ring-white ${
                                                                    unseenCount >
                                                                    0
                                                                        ? 'bg-[#c8102e]'
                                                                        : 'bg-amber-500'
                                                                }`}
                                                            />
                                                        )}
                                                </span>
                                                {!collapsed && (
                                                    <>
                                                        <span className="truncate">
                                                            {item.label}
                                                        </span>
                                                        <span className="ml-auto flex items-center gap-1.5">
                                                            {/* OPS-005: the forwarder phone is a single point of failure with no other liveness signal. */}
                                                            {showSmsWarning && (
                                                                <span title="No payment SMS received in a while — check the forwarder phone">
                                                                    <TriangleAlert className="size-3.5 shrink-0 text-amber-500" />
                                                                </span>
                                                            )}
                                                            {unseenCount >
                                                                0 && (
                                                                <span className="flex h-5 min-w-5 items-center justify-center rounded-full bg-[#c8102e] px-1.5 text-[11px] font-bold text-white">
                                                                    {unseenCount >
                                                                    99
                                                                        ? '99+'
                                                                        : unseenCount}
                                                                </span>
                                                            )}
                                                        </span>
                                                    </>
                                                )}
                                            </Link>
                                        );
                                    })}
                                </div>
                            </div>
                        </div>
                    );
                })}
            </nav>

            {/* Footer: profile */}
            <div className="shrink-0 border-t border-slate-200/80 p-3">
                {admin && (
                    <div
                        className={`flex items-center rounded-xl bg-slate-50 ${
                            collapsed ? 'flex-col gap-1.5 p-1.5' : 'gap-2.5 p-2'
                        }`}
                    >
                        <span
                            title={collapsed ? admin.name : undefined}
                            className="flex size-8 shrink-0 items-center justify-center rounded-full bg-[#c8102e]/10 text-xs font-bold text-[#c8102e]"
                        >
                            {initials}
                        </span>
                        {!collapsed && (
                            <div className="min-w-0 flex-1 leading-tight">
                                <p className="truncate text-sm font-semibold text-slate-800">
                                    {admin.name}
                                </p>
                                <p className="text-[11px] text-slate-400">
                                    {admin.is_mother ? 'Super admin' : 'Admin'}
                                </p>
                            </div>
                        )}
                        {onLogout && (
                            <button
                                type="button"
                                onClick={onLogout}
                                title="Log out"
                                aria-label="Log out"
                                className="shrink-0 rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-red-50 hover:text-red-600"
                            >
                                <LogOut className="size-4" />
                            </button>
                        )}
                    </div>
                )}
            </div>
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
    const unmatchedCount = page.props.unmatched_sms_count ?? 0;
    const forwarderSeemsDown = !page.props.mobile_banking_available;
    const currentUrl = page.url;
    const logoUrl = (page.props.settings as AppSettings)?.logo ?? null;
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [sidebarCollapsed, setSidebarCollapsed] = useState<boolean>(() =>
        readStored(SIDEBAR_COLLAPSED_KEY, false),
    );
    const [hasMobileLogoError, setHasMobileLogoError] = useState(false);
    const canReceiveSmsPush = canViewSection(
        admin ?? undefined,
        'received_sms',
    );
    const { toasts, dismiss, permission, isSubscribed, enableNotifications } =
        useWebPushSms(canReceiveSmsPush ? page.props.vapid_public_key : null);
    const handleDismiss = useCallback((key: number) => dismiss(key), [dismiss]);
    const [pushBannerDismissed, setPushBannerDismissed] = useState<boolean>(
        () => readStored(PUSH_BANNER_DISMISSED_KEY, false),
    );
    const showPushBanner =
        canReceiveSmsPush &&
        !!page.props.vapid_public_key &&
        permission === 'default' &&
        !isSubscribed &&
        !pushBannerDismissed;
    const dismissPushBanner = () => {
        writeStored(PUSH_BANNER_DISMISSED_KEY, true);
        setPushBannerDismissed(true);
    };

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

    const toggleSidebarCollapsed = () => {
        setSidebarCollapsed((previous) => {
            writeStored(SIDEBAR_COLLAPSED_KEY, !previous);

            return !previous;
        });
    };

    return (
        <div className="admin-shell flex min-h-screen bg-slate-50">
            {/* Desktop sidebar */}
            <aside
                className={`sticky top-0 hidden h-screen shrink-0 flex-col border-r border-slate-200/80 bg-white transition-[width] duration-200 ease-out lg:flex ${
                    sidebarCollapsed ? 'w-16' : 'w-56'
                }`}
            >
                <SidebarContent
                    currentUrl={currentUrl}
                    logoUrl={logoUrl}
                    admin={admin ?? undefined}
                    unseenOrdersCount={unseenOrdersCount}
                    unmatchedSmsCount={unmatchedCount}
                    forwarderSeemsDown={forwarderSeemsDown}
                    collapsed={sidebarCollapsed}
                    onToggleCollapsed={toggleSidebarCollapsed}
                    onLogout={handleLogout}
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
                                onLogout={handleLogout}
                                onClose={() => setSidebarOpen(false)}
                            />
                        </motion.aside>
                    </div>
                )}
            </AnimatePresence>

            {/* Main area */}
            <div className="flex min-w-0 flex-1 flex-col">
                {/* Top bar */}
                <header className="sticky top-0 z-40 flex h-14 items-center gap-3 border-b border-slate-200 bg-white px-4 lg:hidden">
                    <button
                        type="button"
                        className="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100"
                        onClick={() => setSidebarOpen(true)}
                    >
                        <Menu className="size-5" />
                    </button>

                    <Link
                        href={adminDashboard()}
                        aria-label="Admin dashboard home"
                        className="absolute left-1/2 flex size-9 -translate-x-1/2 items-center justify-center overflow-hidden rounded-xl bg-[#c8102e] shadow-sm"
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
                <main className="flex-1 p-5">
                    <div key={page.component} className="admin-content">
                        {children}
                    </div>
                </main>
            </div>

            {/* SMS toast notifications */}
            <div className="pointer-events-none fixed right-5 bottom-5 z-50 flex flex-col items-end gap-2.5">
                {showPushBanner && (
                    <div className="pointer-events-auto flex w-80 items-start gap-3 rounded-xl bg-slate-900 px-4 py-3 shadow-xl ring-1 ring-white/10">
                        <div className="min-w-0 flex-1">
                            <p className="text-xs font-bold text-white">
                                Enable SMS notifications
                            </p>
                            <p className="mt-1 text-[11px] leading-relaxed text-slate-400">
                                Get notified instantly when a new payment SMS
                                arrives, without keeping this page open.
                            </p>
                            <div className="mt-2 flex gap-2">
                                <button
                                    type="button"
                                    onClick={() =>
                                        void enableNotifications().then(
                                            (result) => {
                                                if (!result.ok) {
                                                    window.alert(
                                                        result.message,
                                                    );
                                                }
                                            },
                                        )
                                    }
                                    className="rounded-md bg-pink-600 px-2.5 py-1 text-[11px] font-semibold text-white hover:bg-pink-500"
                                >
                                    Enable
                                </button>
                                <button
                                    type="button"
                                    onClick={dismissPushBanner}
                                    className="rounded-md px-2.5 py-1 text-[11px] font-semibold text-slate-400 hover:text-slate-200"
                                >
                                    Not now
                                </button>
                            </div>
                        </div>
                    </div>
                )}
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
