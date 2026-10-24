import { usePage } from '@inertiajs/react';
import type { SharedPageProps } from '@/types';

/** The storefront theme resolved server-side for this request ('default' | 'neomorphism' | 'glassmorphism' | 'aurora'). */
export function useTheme(): string {
    return usePage<SharedPageProps>().props.theme;
}
