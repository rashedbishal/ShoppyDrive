import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { Palette, Shield, User } from 'lucide-react';
import AppLayout from '@/pages/layouts/AppLayout';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';

const navItems = [
    { title: 'Profile', href: edit(), icon: User },
    { title: 'Security', href: editSecurity(), icon: Shield },
    { title: 'Appearance', href: editAppearance(), icon: Palette },
];

function SettingsContent({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();

    return (
        <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8 lg:py-12">
            <div className="mb-8">
                <h1 className="text-2xl font-bold text-[var(--color-text-primary)]">Settings</h1>
                <p className="mt-1 text-sm text-[var(--color-text-secondary)]">Manage your profile and account preferences</p>
            </div>

            <div className="flex flex-col gap-8 lg:flex-row lg:items-start">
                <aside className="w-full lg:w-56 lg:shrink-0">
                    <nav className="overflow-hidden rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface-alt)] p-2">
                        {navItems.map(({ title, href, icon: Icon }) => {
                            const isActive = isCurrentOrParentUrl(href);
                            return (
                                <Link
                                    key={toUrl(href)}
                                    href={href}
                                    className={cn(
                                        'flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-sm font-medium transition',
                                        isActive
                                            ? 'bg-[var(--color-primary)] text-white'
                                            : 'text-[var(--color-text-secondary)] hover:bg-[var(--color-surface)] hover:text-[var(--color-text-primary)]',
                                    )}
                                >
                                    <Icon className="size-4 shrink-0" />
                                    {title}
                                </Link>
                            );
                        })}
                    </nav>
                </aside>

                <div className="min-w-0 flex-1">
                    <div className="max-w-2xl space-y-8">
                        {children}
                    </div>
                </div>
            </div>
        </div>
    );
}

export default function SettingsLayout({ children }: PropsWithChildren) {
    return (
        <AppLayout>
            <SettingsContent>{children}</SettingsContent>
        </AppLayout>
    );
}
