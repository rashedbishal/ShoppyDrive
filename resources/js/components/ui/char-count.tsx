import { cn } from '@/lib/utils';

/**
 * "42/120" length indicator for a limited text field. Quiet while there is
 * plenty of room, amber from 85% of the limit, red at the limit. The field
 * itself enforces the limit with `maxLength`; this only tells the admin where
 * they stand.
 */
export function CharCount({
    value,
    max,
    className,
}: {
    value: string | number | readonly string[] | null | undefined;
    max: number;
    className?: string;
}): React.JSX.Element {
    const length = String(value ?? '').length;

    return (
        <span
            aria-live="off"
            className={cn(
                'shrink-0 text-[11px] tabular-nums',
                length >= max
                    ? 'font-semibold text-destructive'
                    : length >= max * 0.85
                      ? 'text-amber-600'
                      : 'text-text-secondary',
                className,
            )}
        >
            {length}/{max}
        </span>
    );
}
