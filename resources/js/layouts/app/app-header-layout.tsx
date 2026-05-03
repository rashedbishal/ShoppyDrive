import { AppContent } from '@/components/app-content';
import { AppHeader } from '@/components/app-header';
import { AppShell } from '@/components/app-shell';
import type { AppLayoutProps } from '@/types';

export default function AppHeaderLayout({
    children,
    breadcrumbs,
}: AppLayoutProps) {
    return (
        <AppShell variant="header">
            <a 
                href="#main-content" 
                className="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-[1000] focus:px-6 focus:py-3 focus:bg-[var(--color-primary)] focus:text-white focus:rounded-xl focus:font-bold focus:shadow-2xl transition-all"
            >
                Skip to main content
            </a>
            <AppHeader breadcrumbs={breadcrumbs} />
            <AppContent variant="header">
                <div id="main-content">
                    {children}
                </div>
            </AppContent>
        </AppShell>
    );
}
