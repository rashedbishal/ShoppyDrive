import { cn } from '@/lib/utils';

/**
 * Shown on every page that sets a new password, so the customer knows the
 * real rules upfront instead of learning what's still missing one rule at
 * a time across repeated failed submissions. The list itself comes from
 * the server (App\Support\PasswordPolicy) rather than being hardcoded
 * here, so it can never again drift out of sync with the actual policy.
 */
export default function PasswordRequirements({
    requirements,
    nowrap = false,
}: {
    requirements: string[];
    /** Keep each hint on one line (the registration page gives it a full-width row). */
    nowrap?: boolean;
}): React.JSX.Element | null {
    if (requirements.length === 0) {
        return null;
    }

    return (
        <ul
            className={cn(
                'space-y-0.5 text-gray-600',
                // On one line the longest hint needs ~310px at 12px, so on
                // narrow phones the text scales down instead of wrapping.
                nowrap
                    ? 'text-[clamp(8.5px,2.8vw,0.75rem)] leading-[1.3333]'
                    : 'text-xs',
            )}
        >
            {requirements.map((requirement) => (
                <li
                    key={requirement}
                    className={cn(
                        'flex gap-1.5',
                        nowrap ? 'items-center' : 'items-start',
                    )}
                >
                    <span
                        className={cn(
                            'size-1 shrink-0 rounded-full bg-gray-300',
                            !nowrap && 'mt-1.5',
                        )}
                    />
                    <span className={nowrap ? 'whitespace-nowrap' : undefined}>
                        {requirement}
                    </span>
                </li>
            ))}
        </ul>
    );
}
