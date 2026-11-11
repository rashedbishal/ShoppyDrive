import { usePage } from '@inertiajs/react';
import type { SharedPageProps } from '@/types';

/**
 * The URL of an admin list page as the admin last left it (same page number,
 * search and filters), falling back to the plain list route. Use it for the
 * Back/Cancel links on create/edit/detail pages so returning to the list
 * doesn't reset it to page 1. Filled in by the server's AdminListUrl.
 */
export function useAdminListUrl(routeName: string): string {
    const { admin_list_urls } = usePage<SharedPageProps>().props;

    return admin_list_urls?.[routeName] ?? route(routeName);
}
