import { useEffect, useState } from 'react';
import { Link } from '@inertiajs/react';
import { Shield, Sparkles } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Toggle } from '@/components/ui/toggle';
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

    return (
        <div className="fixed inset-x-0 bottom-0 z-[65] px-4 pb-4 sm:px-6 lg:px-8">
            <div className="mx-auto max-w-7xl rounded-3xl border border-[var(--color-border)] bg-surface/95 p-5 shadow-[var(--shadow-modal)] backdrop-blur-xl">
                <div className="grid gap-5 lg:grid-cols-[1.2fr_0.8fr]">
                    <div className="space-y-3">
                        <div className="inline-flex items-center gap-2 rounded-full bg-[var(--color-primary-light)] px-3 py-1 text-xs font-semibold uppercase tracking-[0.22em] text-[var(--color-primary)]">
                            <Shield className="size-3.5" />
                            Cookie preferences
                        </div>
                        <div className="space-y-2">
                            <h3 className="text-lg font-semibold text-[var(--color-text-primary)]">
                                We use cookies to improve shopping and personalize offers.
                            </h3>
                            <p className="max-w-2xl text-sm leading-6 text-[var(--color-text-secondary)]">
                                Necessary cookies keep the store working. Analytics and marketing cookies are optional and only load after consent.
                            </p>
                        </div>
                    </div>

                    <div className="space-y-4 rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface-alt)] p-4">
                        <div className="flex items-center justify-between gap-4">
                            <div>
                                <p className="text-sm font-semibold text-[var(--color-text-primary)]">Necessary</p>
                                <p className="text-xs text-[var(--color-text-secondary)]">Always on</p>
                            </div>
                            <Toggle pressed className="pointer-events-none" aria-label="Necessary cookies enabled" />
                        </div>

                        <div className="flex items-center justify-between gap-4">
                            <div>
                                <p className="text-sm font-semibold text-[var(--color-text-primary)]">Analytics</p>
                                <p className="text-xs text-[var(--color-text-secondary)]">Measure visits and performance</p>
                            </div>
                            <Toggle
                                pressed={analyticsEnabled}
                                onPressedChange={setAnalyticsEnabled}
                                aria-label="Toggle analytics cookies"
                            />
                        </div>

                        <div className="flex items-center justify-between gap-4">
                            <div>
                                <p className="text-sm font-semibold text-[var(--color-text-primary)]">Marketing</p>
                                <p className="text-xs text-[var(--color-text-secondary)]">Personalized promos and ads</p>
                            </div>
                            <Toggle
                                pressed={marketingEnabled}
                                onPressedChange={setMarketingEnabled}
                                aria-label="Toggle marketing cookies"
                            />
                        </div>
                    </div>
                </div>

                <div className="mt-5 flex flex-col gap-3 border-t border-[var(--color-border)] pt-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex flex-wrap items-center gap-3 text-sm">
                        <Button
                            onClick={() => persistConsent(true, true)}
                            className="shadow-sm"
                        >
                            Accept all
                        </Button>
                        <Button
                            variant="secondary"
                            onClick={() => persistConsent(analyticsEnabled, marketingEnabled)}
                        >
                            Save preferences
                        </Button>
                        <button
                            type="button"
                            onClick={() => persistConsent(false, false)}
                            className="text-sm font-semibold text-[var(--color-text-secondary)] transition hover:text-[var(--color-primary)]"
                        >
                            Reject all
                        </button>
                    </div>

                    <Link
                        href="/privacy-policy"
                        className="inline-flex items-center gap-2 text-sm font-medium text-[var(--color-text-secondary)] transition hover:text-[var(--color-primary)]"
                    >
                        <Sparkles className="size-4" />
                        View privacy policy
                    </Link>
                </div>
            </div>
        </div>
    );
}

