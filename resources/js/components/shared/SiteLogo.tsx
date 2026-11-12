import { usePage } from '@inertiajs/react';

import AppLogoIcon from '@/components/app-logo-icon';
import { cn } from '@/lib/utils';
import type { AppSettings } from '@/types';

type SiteLogoProps = {
    /** Overrides the shared brand logo (pages that receive it as their own prop). */
    src?: string | null;
    /** Overrides the shared site name (alt text, and the fallback wordmark). */
    name?: string;
    /** Height of the logo, e.g. `h-12`. The width follows the artwork. */
    className?: string;
    /**
     * Puts the logo on a small white rounded plate, so its dark lettering stays
     * readable. `dark` does so only in the dark theme; `always` on a coloured or
     * dark surface in both themes.
     */
    plate?: 'dark' | 'always';
    /** Text colour of the fallback wordmark: `onDark` for dark/coloured surfaces. */
    tone?: 'default' | 'onDark';
};

/**
 * The one place the brand logo is drawn. The logo uploaded in Admin ›
 * Settings is the complete lockup (mark, name and motto), so nothing is
 * printed beside it. Without an upload it falls back to the generic mark and
 * the site name as text.
 */
export function SiteLogo({
    src,
    name,
    className = 'h-10',
    plate,
    tone = 'default',
}: SiteLogoProps): React.JSX.Element {
    // `brand` first: admin pages that send their own `settings` prop replace
    // the shared one, which has the logo (see HandleInertiaRequests).
    const props = usePage().props as {
        brand?: Partial<AppSettings>;
        settings?: AppSettings;
    };
    const settings = (props.brand ?? props.settings) as AppSettings | undefined;
    const logo = src === undefined ? settings?.logo : src;
    const siteName = name ?? settings?.site_name ?? 'ShoppyDrive';

    if (logo) {
        return (
            <span
                className={cn(
                    'inline-flex shrink-0 items-center',
                    plate === 'always' &&
                        'rounded-xl bg-white px-2.5 py-1.5 shadow-sm ring-1 ring-black/5',
                    plate === 'dark' &&
                        'dark:rounded-lg dark:bg-white dark:px-2 dark:py-1',
                )}
            >
                <img
                    src={logo}
                    alt={siteName}
                    className={cn(
                        'w-auto max-w-full object-contain',
                        className,
                    )}
                />
            </span>
        );
    }

    return (
        <span className="inline-flex min-w-0 items-center gap-2.5">
            <span
                className={cn(
                    'flex size-9 shrink-0 items-center justify-center rounded-xl',
                    tone === 'onDark'
                        ? 'bg-white/20'
                        : 'bg-[var(--color-primary)]',
                )}
            >
                <AppLogoIcon className="size-5 fill-current text-white" />
            </span>
            <span
                className={cn(
                    'truncate text-lg font-bold tracking-tight',
                    tone === 'onDark'
                        ? 'text-white'
                        : 'text-[var(--color-text-primary)]',
                )}
            >
                {siteName}
            </span>
        </span>
    );
}
