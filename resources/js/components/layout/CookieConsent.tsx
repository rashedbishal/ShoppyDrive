import { Link } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import { ShieldCheck } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { STORAGE_KEYS } from '@/lib/constants';
import { useLocalStorage } from '@/lib/hooks/useLocalStorage';

type CookieNoticeState = {
    necessary: true;
    analytics: false;
    marketing: false;
    timestamp: string;
};

/**
 * FE-013: a notice, not a choice — the owner opted to fire the Meta Pixel
 * (app.blade.php) for every visitor rather than gate it behind a consent
 * toggle, so there's nothing to opt in or out of here. A visitor who
 * answered the old banner isn't asked again, and the stored shape stays
 * what `lib/analytics.ts` reads.
 */
export default function CookieConsent(): React.JSX.Element {
    const [notice, setNotice] = useLocalStorage<CookieNoticeState | null>(
        STORAGE_KEYS.cookieConsent,
        null,
    );

    const dismiss = (): void => {
        setNotice({
            necessary: true,
            analytics: false,
            marketing: false,
            timestamp: new Date().toISOString(),
        });
    };

    return (
        <AnimatePresence>
            {notice === null && (
                <motion.div
                    key="cookie-consent"
                    className="fixed bottom-4 left-4 z-[65] w-[320px] max-w-[calc(100vw-2rem)]"
                    initial={{ opacity: 0, y: 24, scale: 0.96 }}
                    animate={{ opacity: 1, y: 0, scale: 1 }}
                    exit={{ opacity: 0, y: 16, scale: 0.96 }}
                    transition={{ duration: 0.45, ease: [0.16, 1, 0.3, 1] }}
                >
                    <div className="rounded-2xl border border-[var(--color-border)] bg-surface/95 p-4 shadow-[var(--shadow-modal)] backdrop-blur-xl">
                        <div className="mb-2 inline-flex items-center gap-1.5 text-xs font-semibold text-[var(--color-text-primary)]">
                            <ShieldCheck className="size-3.5" />
                            Cookies
                        </div>
                        <p className="mb-3 text-xs leading-5 text-[var(--color-text-secondary)]">
                            We use cookies to keep your cart and login
                            working, count visits, and note how you found us.
                            We also use the Meta Pixel to measure and improve
                            our ads.
                        </p>
                        <div className="flex items-center gap-3">
                            <Button
                                size="sm"
                                onClick={dismiss}
                                className="shadow-sm"
                            >
                                Got it
                            </Button>
                            <Link
                                href="/privacy-policy"
                                className="text-[11px] font-semibold text-[var(--color-text-secondary)] underline"
                            >
                                Privacy policy
                            </Link>
                        </div>
                    </div>
                </motion.div>
            )}
        </AnimatePresence>
    );
}
