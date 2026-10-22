import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

export type PageHeroChip = {
    icon: LucideIcon;
    label: ReactNode;
    /** Icon colour, e.g. `text-[var(--color-success)]`. */
    iconClassName?: string;
};

type PageHeroProps = {
    badge: { icon: LucideIcon; label: string };
    /** Small uppercase line above the title. */
    eyebrow?: ReactNode;
    title: ReactNode;
    description?: ReactNode;
    chips?: PageHeroChip[];
    /** Right-hand column on desktop (stats, search, countdown…); stacks below on mobile. */
    aside?: ReactNode;
    /** Extra content under the chips, e.g. action buttons. */
    children?: ReactNode;
};

/**
 * The compact first section shared by every storefront page except the home
 * page: badge, eyebrow, title, short description and info chips in one card,
 * with an optional right-hand column.
 */
export function PageHero({
    badge,
    eyebrow,
    title,
    description,
    chips = [],
    aside,
    children,
}: PageHeroProps): React.JSX.Element {
    const BadgeIcon = badge.icon;

    return (
        <section className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div
                className={cn(
                    'grid grid-cols-1 gap-4 rounded-[2rem] border border-[var(--color-border)] bg-[var(--color-surface)] p-4 shadow-[var(--shadow-card)] sm:p-5 lg:gap-6 lg:px-6 lg:py-5',
                    aside ? 'lg:grid-cols-[1.1fr_0.9fr]' : '',
                )}
            >
                <div className="space-y-3">
                    <Badge variant="amber" className="w-fit">
                        <BadgeIcon className="size-3.5" />
                        {badge.label}
                    </Badge>
                    <div className="space-y-1.5">
                        {eyebrow ? (
                            <p className="text-xs font-semibold tracking-[0.22em] text-[var(--color-text-muted)] uppercase">
                                {eyebrow}
                            </p>
                        ) : null}
                        <h1 className="font-display text-2xl font-semibold tracking-tight text-[var(--color-text-primary)] sm:text-3xl">
                            {title}
                        </h1>
                        {description ? (
                            <p className="line-clamp-3 max-w-2xl text-sm leading-6 text-[var(--color-text-secondary)] sm:line-clamp-2">
                                {description}
                            </p>
                        ) : null}
                    </div>
                    {chips.length > 0 ? (
                        <div className="flex flex-wrap gap-2">
                            {chips.map(
                                (
                                    { icon: Icon, label, iconClassName },
                                    index,
                                ) => (
                                    <div
                                        key={index}
                                        className="inline-flex items-center gap-2 rounded-full border border-[var(--color-border)] bg-[var(--color-surface-alt)] px-3 py-1.5 text-xs text-[var(--color-text-secondary)] sm:text-sm"
                                    >
                                        <Icon
                                            className={cn(
                                                'size-4',
                                                iconClassName ??
                                                    'text-[var(--color-primary)]',
                                            )}
                                        />
                                        {label}
                                    </div>
                                ),
                            )}
                        </div>
                    ) : null}
                    {children}
                </div>

                {aside ? (
                    <div className="min-w-0 self-center">{aside}</div>
                ) : null}
            </div>
        </section>
    );
}
