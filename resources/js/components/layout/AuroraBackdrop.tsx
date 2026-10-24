import { useSiteTheme } from '@/hooks/use-theme';

/**
 * A fixed, animated gradient-mesh layer behind all storefront content, shown
 * only for the "aurora" theme. Pure CSS (see `--aurora-gradient-*` and the
 * `aurora-drift` keyframes in app.css) — no JS animation loop.
 */
export default function AuroraBackdrop(): React.JSX.Element | null {
    const { theme } = useSiteTheme();

    if (theme !== 'aurora') {
        return null;
    }

    return (
        <div
            aria-hidden="true"
            className="pointer-events-none fixed inset-0 -z-10 overflow-hidden"
        >
            <div
                className="absolute -inset-[20%] opacity-40 blur-3xl"
                style={{
                    background:
                        'radial-gradient(circle at 20% 30%, var(--aurora-gradient-1) 0%, transparent 45%),' +
                        'radial-gradient(circle at 80% 20%, var(--aurora-gradient-2) 0%, transparent 45%),' +
                        'radial-gradient(circle at 50% 80%, var(--aurora-gradient-3) 0%, transparent 45%)',
                    animation: 'aurora-drift 22s ease-in-out infinite',
                }}
            />
        </div>
    );
}
