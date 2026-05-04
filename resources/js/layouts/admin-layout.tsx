import { Link, router, usePage } from '@inertiajs/react';
import {
    Award,
    Folder,
    FolderTree,
    Image,
    LayoutDashboard,
    LogOut,
    Menu,
    Package,
    Settings,
    ShoppingCart,
    Star,
    Tag,
    Users,
    X,
    Zap,
} from 'lucide-react';
import { useState } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import type { SharedPageProps } from '@/types';

const navSections = [
    {
        label: 'Overview',
        items: [
            { label: 'Dashboard', href: '/admin/dashboard', icon: LayoutDashboard, match: 'admin.dashboard' },
        ],
    },
    {
        label: 'Catalog',
        items: [
            { label: 'Products', href: '/admin/products', icon: Package, match: 'admin.products.' },
            { label: 'Categories', href: '/admin/categories', icon: Folder, match: 'admin.categories.' },
            { label: 'Sub-categories', href: '/admin/sub-categories', icon: FolderTree, match: 'admin.sub-categories.' },
            { label: 'Brands', href: '/admin/brands', icon: Award, match: 'admin.brands.' },
        ],
    },
    {
        label: 'Sales',
        items: [
            { label: 'Orders', href: '/admin/orders', icon: ShoppingCart, match: 'admin.orders.' },
            { label: 'Coupons', href: '/admin/coupons', icon: Tag, match: 'admin.coupons.' },
            { label: 'Flash Sales', href: '/admin/flash-sales', icon: Zap, match: 'admin.flash-sales.' },
        ],
    },
    {
        label: 'Content',
        items: [
            { label: 'Banners', href: '/admin/banners', icon: Image, match: 'admin.banners.' },
        ],
    },
    {
        label: 'Users',
        items: [
            { label: 'Customers', href: '/admin/customers', icon: Users, match: 'admin.customers.' },
            { label: 'Reviews', href: '/admin/reviews', icon: Star, match: 'admin.reviews.' },
        ],
    },
    {
        label: 'System',
        items: [
            { label: 'Settings', href: '/admin/settings', icon: Settings, match: 'admin.settings.' },
        ],
    },
];

function SidebarContent({ currentUrl, onClose }: { currentUrl: string; onClose?: () => void }) {
    return (
        <div className="flex h-full flex-col">
            {/* Logo */}
            <div className="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                <Link href="/admin/dashboard" className="flex items-center gap-2.5" onClick={onClose}>
                    <span className="flex size-8 items-center justify-center rounded-xl bg-[#c8102e]">
                        <AppLogoIcon className="size-4 fill-current text-white" />
                    </span>
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
                            const isActive = currentUrl.startsWith(item.href);
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

export default function AdminLayout({ children }: { children: React.ReactNode }) {
    const page = usePage<SharedPageProps>();
    const admin = page.props.auth?.admin;
    const currentUrl = page.url;
    const [sidebarOpen, setSidebarOpen] = useState(false);

    const handleLogout = () => router.post(route('admin.logout'));

    return (
        <div className="flex min-h-screen bg-slate-50">
            {/* Desktop sidebar */}
            <aside className="hidden w-56 shrink-0 flex-col border-r border-slate-200 bg-white lg:flex">
                <SidebarContent currentUrl={currentUrl} />
            </aside>

            {/* Mobile sidebar overlay */}
            {sidebarOpen && (
                <div className="fixed inset-0 z-50 lg:hidden">
                    <div className="absolute inset-0 bg-black/40" onClick={() => setSidebarOpen(false)} />
                    <aside className="absolute left-0 top-0 h-full w-56 bg-white shadow-xl">
                        <SidebarContent currentUrl={currentUrl} onClose={() => setSidebarOpen(false)} />
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
        </div>
    );
}
