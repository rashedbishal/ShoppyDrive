import { useEffect, useState } from 'react';
import { Link } from '@inertiajs/react';
import { BarChart3, Megaphone, ShieldCheck, Sparkles } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { STORAGE_KEYS } from '@/lib/constants';
import { useLocalStorage } from '@/lib/hooks/useLocalStorage';
import { cn } from '@/lib/utils';

type CookieConsentState = {
    necessary: true;
    analytics: boolean;
    marketing: boolean;
    timestamp: string;
};

export default function CookieConsent(): React.JSX.Element | null {
    const [consent, setConsent] = useLocalStorage<CookieConsentState | null>(
        STORAGE_KEYS.cookieConsent,
        null,
    );
    const [analyticsEnabled, setAnalyticsEnabled] = useState(false);
    const [marketingEnabled, setMarketingEnabled] = useState(false);

    useEffect(() => {
        if (consent === null) {
            return;
        }

        setAnalyticsEnabled(consent.analytics);
        setMarketingEnabled(consent.marketing);
    }, [consent]);

    if (consent !== null) {
        return null;
    }

    const persistConsent = (nextAnalytics: boolean, nextMarketing: boolean): void => {
        setConsent({
            necessary: true,
            analytics: nextAnalytics,
            marketing: nextMarketing,
            timestamp: new Date().toISOString(),
        });
    };

    const categories = [
        { key: 'necessary', label: 'Necessary', icon: ShieldCheck, checked: true, disabled: true, onToggle: undefined },
        { key: 'analytics', label: 'Analytics', icon: BarChart3, checked: analyticsEnabled, disabled: false, onToggle: () => setAnalyticsEnabled((v) => !v) },
        { key: 'marketing', label: 'Marketing', icon: Megaphone, checked: marketingEnabled, disabled: false, onToggle: () => setMarketingEnabled((v) => !v) },
    ] as const;

    return (
        <div className="fixed bottom-4 left-4 z-[65] w-[320px] max-w-[calc(100vw-2rem)]">
            <div className="rounded-2xl border border-[var(--color-border)] bg-surface/95 p-4 shadow-[var(--shadow-modal)] backdrop-blur-xl">
                <div className="mb-2 inline-flex items-center gap-1.5 rounded-full bg-[var(--color-primary-light)] px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-[0.18em] text-[var(--color-primary)]">
                    <ShieldCheck className="size-3" />
                    Cookies
                </div>
                <p className="mb-3 text-xs leading-5 text-[var(--color-text-secondary)]">
                    We use cookies to improve shopping and personalize offers.
                </p>

                <div className="mb-3 flex flex-wrap gap-1.5">
                    {categories.map(({ key, label, icon: Icon, checked, disabled, onToggle }) => (
                        <button
                            key={key}
                            type="button"
                            disabled={disabled}
                            onClick={onToggle}
                            aria-pressed={checked}
                            className={cn(
                                'inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition',
                                checked
                                    ? 'bg-[var(--color-primary)] text-white'
                                    : 'border border-[var(--color-border)] text-[var(--color-text-secondary)] hover:border-[var(--color-primary)] hover:text-[var(--color-primary)]',
                                disabled && 'cursor-default',
                            )}
                        >
                            <Icon className="size-3.5" />
                            {label}
                        </button>
                    ))}
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <Button
                        size="sm"
                        onClick={() => persistConsent(true, true)}
                        className="shadow-sm"
                    >
                        Accept all
                    </Button>
                    <Button
                        size="sm"
                        variant="secondary"
                        onClick={() => persistConsent(analyticsEnabled, marketingEnabled)}
                    >
                        Save
                    </Button>
                    <button
                        type="button"
                        onClick={() => persistConsent(false, false)}
                        className="text-xs font-semibold text-[var(--color-text-secondary)] transition hover:text-[var(--color-primary)]"
                    >
                        Reject all
                    </button>
                </div>

                <Link
                    href="/privacy-policy"
                    className="mt-2.5 inline-flex items-center gap-1.5 text-[11px] font-medium text-[var(--color-text-secondary)] transition hover:text-[var(--color-primary)]"
                >
                    <Sparkles className="size-3" />
                    Privacy policy
                </Link>
            </div>
        </div>
    );
}
