import type { ReactNode } from 'react';
import StorefrontLayout from '@/components/layout/AppLayout';

export default function AppLayout({ children }: { children: ReactNode }) {
    return <StorefrontLayout>{children}</StorefrontLayout>;
}
