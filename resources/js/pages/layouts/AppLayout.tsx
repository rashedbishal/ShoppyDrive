import type { ReactNode } from 'react';
import StorefrontLayout from '@/components/layout/AppLayout';

interface AppLayoutProps {
    children: ReactNode;
}

export default function AppLayout({ children }: AppLayoutProps) {
    return <StorefrontLayout>{children}</StorefrontLayout>;
}
