import StorefrontLayout from '@/components/layout/AppLayout';
import type { ReactNode } from 'react';

export default function AppLayout({ children }: { children: ReactNode }) {
    return <StorefrontLayout>{children}</StorefrontLayout>;
}
