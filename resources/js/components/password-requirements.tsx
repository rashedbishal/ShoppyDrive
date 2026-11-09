/**
 * Shown on every page that sets a new password, so the customer knows the
 * real rules upfront instead of learning what's still missing one rule at
 * a time across repeated failed submissions. The list itself comes from
 * the server (App\Support\PasswordPolicy) rather than being hardcoded
 * here, so it can never again drift out of sync with the actual policy.
 */
export default function PasswordRequirements({
    requirements,
}: {
    requirements: string[];
}): React.JSX.Element | null {
    if (requirements.length === 0) {
        return null;
    }

    return (
        <ul className="space-y-0.5 text-xs text-gray-500">
            {requirements.map((requirement) => (
                <li key={requirement} className="flex items-start gap-1.5">
                    <span className="mt-1.5 size-1 shrink-0 rounded-full bg-gray-300" />
                    <span>{requirement}</span>
                </li>
            ))}
        </ul>
    );
}
