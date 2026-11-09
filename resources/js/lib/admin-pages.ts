import type { ResolvedComponent } from '@inertiajs/react';
import AdminLayout from '@/layouts/admin-layout';

const adminPages = import.meta.glob<{ default: ResolvedComponent }>(
    '../pages/admin/**/*.tsx',
);

/**
 * SEC-016: the admin pages, their layout and every admin URL they import live
 * in this separately loaded file, so the script every visitor downloads
 * doesn't list the admin area. The layout is set on each page directly: it
 * has to be the real component, because a lazy wrapper made React loop
 * endlessly on admin pages (PERF-003).
 */
export async function resolveAdminPage(
    name: string,
): Promise<ResolvedComponent> {
    const page = await adminPages[`../pages/${name}.tsx`]?.();

    if (!page) {
        throw new Error(`Page not found: ${name}`);
    }

    page.default.layout ??= AdminLayout;

    return page.default;
}
