import type { SharedPageProps } from '@/types';

declare module '@inertiajs/core' {
    interface InertiaConfig {
        sharedPageProps: SharedPageProps;
    }
}
