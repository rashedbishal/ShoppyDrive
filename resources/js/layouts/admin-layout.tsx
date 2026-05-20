import { Link, router, usePage } from '@inertiajs/react';
import type { AppSettings } from '@/types';
import {
    Award,
    Folder,
    FolderTree,
    LayoutDashboard,
    LayoutTemplate,
    LogOut,
    Mail,
    Menu,
    MessageSquare,
    MonitorPlay,
    Package,
    Search,
    Settings,
    ShoppingCart,
    Smartphone,
    Star,
    Tag,
    UserCog,
    Users,
    X,
    Zap,
} from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { useSmsPolling, type SmsToast } from '@/hooks/useSmsPolling';
import type { SharedPageProps } from '@/types';

const navSections = [
    {
        label: 'Overview',
        items: [
            { label: 'Dashboard', href: '/admin/dashboard', icon: LayoutDashboard, extraMatches: [] as string[] },
        ],
    },
    {
        label: 'Catalog',
        items: [
            { label: 'Products', href: '/admin/products', icon: Package, extraMatches: [] as string[] },
            { label: 'Categories', href: '/admin/categories', icon: Folder, extraMatches: [] as string[] },
            { label: 'Sub-categories', href: '/admin/sub-categories', icon: FolderTree, extraMatches: [] as string[] },
            { label: 'Brands', href: '/admin/brands', icon: Award, extraMatches: [] as string[] },
        ],
    },
    {
        label: 'Sales',
        items: [
            { label: 'Orders', href: '/admin/orders', icon: ShoppingCart, extraMatches: [] as string[] },
            { label: 'Coupons', href: '/admin/coupons', icon: Tag, extraMatches: [] as string[] },
            { label: 'Flash Sales', href: '/admin/flash-sales', icon: Zap, extraMatches: [] as string[] },
            { label: 'Received SMS', href: '/admin/received-sms', icon: MessageSquare, extraMatches: [] as string[] },
            { label: 'SMS Patterns', href: '/admin/sms-patterns', icon: Settings, extraMatches: [] as string[] },
        ],
    },
    {
        label: 'Content',
        items: [
            { label: 'Homepage', href: '/admin/homepage', icon: LayoutTemplate, exact: false, extraMatches: ['/admin/hero-slides', '/admin/promotional-banners'] },
            { label: 'Landing Pages', href: '/admin/landing-pages', icon: MonitorPlay, exact: false, extraMatches: [] as string[] },
            { label: 'Search Keywords', href: '/admin/popular-keywords', icon: Search, exact: false, extraMatches: [] as string[] },
        ],
    },
    {
        label: 'Users',
        items: [
            { label: 'Customers', href: '/admin/customers', icon: Users, extraMatches: [] as string[] },
            { label: 'Reviews', href: '/admin/reviews', icon: Star, extraMatches: [] as string[] },
        ],
    },
    {
        label: 'Marketing',
        items: [
            { label: 'Newsletter', href: '/admin/newsletter', icon: Mail, extraMatches: ['/admin/newsletter/compose'] as string[] },
        ],
    },
    {
        label: 'System',
        items: [
            { label: 'Admins', href: '/admin/admins', icon: UserCog, exact: false, extraMatches: [] as string[] },
            { label: 'Settings', href: '/admin/settings', icon: Settings, exact: true, extraMatches: [] as string[] },
            { label: 'SMS Forwarder', href: '/admin/settings/sms-forwarder', icon: Smartphone, exact: false, extraMatches: [] as string[] },
        ],
    },
];

function SidebarContent({ currentUrl, logoUrl, onClose }: { currentUrl: string; logoUrl?: string | null; onClose?: () => void }) {
    return (
        <div className="flex h-full flex-col">
            {/* Logo */}
            <div className="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                <Link href="/admin/dashboard" className="flex items-center gap-2.5" onClick={onClose}>
                    {logoUrl ? (
                        <img src={logoUrl} alt="Logo" className="size-8 rounded-xl object-contain" />
                    ) : (
                        <span className="flex size-8 items-center justify-center rounded-xl bg-[#c8102e]">
                            <AppLogoIcon className="size-4 fill-current text-white" />
                        </span>
                    )}
                    <div className="leading-none">
                        <p className="text-[14px] font-bold text-slate-900">ShoppyDrive</p>
                        <p className="text-[11px] text-slate-400">Admin Panel</p>
                    </div>
                </Link>
                {onClose && (
                    <button type="button" onClick={onClose} className="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
                        <X className="size-4" />
                    </button>
                )}
            </div>

            {/* Nav */}
            <nav className="flex-1 overflow-y-auto px-3 py-4">
                {navSections.map((section) => (
                    <div key={section.label} className="mb-5">
                        <p className="mb-1.5 px-2 text-[10px] font-semibold uppercase tracking-widest text-slate-400">
                            {section.label}
                        </p>
                        {section.items.map((item) => {
                            const isActive = (item.exact ? currentUrl === item.href : currentUrl.startsWith(item.href)) || item.extraMatches.some(m => currentUrl.startsWith(m));
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
                                    {isActive && <span className="ml-auto h-1.5 w-1.5 rounded-full bg-[#c8102e]" />}
                                </Link>
                            );
                        })}
                    </div>
                ))}
            </nav>
        </div>
    );
}

function SmsToastItem({ toast, onDismiss }: { toast: SmsToast; onDismiss: () => void }) {
    useEffect(() => {
        const id = setTimeout(onDismiss, 8000);
        return () => clearTimeout(id);
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []); // run once on mount — dismiss is stable via useCallback in parent

    return (
        <div className="flex w-80 items-start gap-3 rounded-xl bg-slate-900 px-4 py-3 shadow-xl ring-1 ring-white/10 animate-in slide-in-from-right-4 fade-in duration-300">
            <div className="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-pink-600">
                <MessageSquare className="size-3.5 text-white" />
            </div>
            <div className="min-w-0 flex-1">
                <p className="text-xs font-bold text-white">New SMS Received</p>
                <p className="text-xs text-slate-400 truncate">From: {toast.sender}</p>
                {toast.parsed_transaction_id && (
                    <p className="text-xs text-pink-400 font-mono font-semibold mt-0.5">
                        TrxID: {toast.parsed_transaction_id}
                        {toast.parsed_amount !== null && <span className="text-slate-300 font-sans font-normal"> · ৳{toast.parsed_amount.toLocaleString()}</span>}
                    </p>
                )}
                <p className="mt-1 text-[11px] text-slate-400 line-clamp-2 leading-relaxed">{toast.content}</p>
                <Link
                    href="/admin/received-sms"
                    className="mt-1.5 inline-block text-[11px] font-semibold text-pink-400 hover:text-pink-300"
                >
                    View all SMS →
                </Link>
            </div>
            <button type="button" onClick={onDismiss} className="mt-0.5 shrink-0 text-slate-500 hover:text-slate-300 transition-colors">
                <X className="size-3.5" />
            </button>
        </div>
    );
}

export default function AdminLayout({ children }: { children: React.ReactNode }) {
    const page = usePage<SharedPageProps>();
    const admin = page.props.auth?.admin;
    const currentUrl = page.url;
    const logoUrl = (page.props.settings as AppSettings)?.logo ?? null;
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const { toasts, dismiss } = useSmsPolling();
    const handleDismiss = useCallback((key: number) => dismiss(key), [dismiss]);

    const handleLogout = () => router.post(route('admin.logout'));

    return (
        <div className="flex min-h-screen bg-slate-50">
            {/* Desktop sidebar */}
            <aside className="hidden w-56 shrink-0 flex-col border-r border-slate-200 bg-white lg:flex sticky top-0 h-screen">
                <SidebarContent currentUrl={currentUrl} logoUrl={logoUrl} />
            </aside>

            {/* Mobile sidebar overlay */}
            {sidebarOpen && (
                <div className="fixed inset-0 z-50 lg:hidden">
                    <div className="absolute inset-0 bg-black/40" onClick={() => setSidebarOpen(false)} />
                    <aside className="absolute left-0 top-0 h-full w-56 bg-white shadow-xl">
                        <SidebarContent currentUrl={currentUrl} logoUrl={logoUrl} onClose={() => setSidebarOpen(false)} />
                    </aside>
                </div>
            )}

            {/* Main area */}
            <div className="flex min-w-0 flex-1 flex-col">
                {/* Top bar */}
                <header className="sticky top-0 z-40 flex h-14 items-center border-b border-slate-200 bg-white px-4 gap-3">
                    <button
                        type="button"
                        className="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 lg:hidden"
                        onClick={() => setSidebarOpen(true)}
                    >
                        <Menu className="size-5" />
                    </button>

                    <div className="flex-1" />

                    {admin && (
                        <span className="hidden text-sm font-medium text-slate-700 sm:block">{admin.name}</span>
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
                    {children}
                </main>
            </div>

            {/* SMS toast notifications */}
            {toasts.length > 0 && (
                <div className="fixed bottom-5 right-5 z-50 flex flex-col gap-2.5 items-end">
                    {toasts.map((toast) => (
                        <SmsToastItem key={toast.key} toast={toast} onDismiss={() => handleDismiss(toast.key)} />
                    ))}
                </div>
            )}
        </div>
    );
}
