import { AnimatePresence, motion } from 'framer-motion';
import { Cookie, Shield, X } from 'lucide-react';
import React, { useEffect, useState } from 'react';

import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';

export function CookieConsent(): React.JSX.Element {
    const [isVisible, setIsVisible] = useState(false);
    const [showSettings, setShowSettings] = useState(false);
    const [preferences, setPreferences] = useState({
        necessary: true,
        analytics: true,
        marketing: true,
    });

    useEffect(() => {
        const consent = localStorage.getItem('cookie_consent');

        if (!consent) {
            const timer = setTimeout(() => setIsVisible(true), 2000);

            return () => clearTimeout(timer);
        }
    }, []);

    const handleAcceptAll = () => {
        const consent = {
            analytics: true,
            marketing: true,
            timestamp: Date.now(),
        };
        localStorage.setItem('cookie_consent', JSON.stringify(consent));
        setIsVisible(false);
        // Trigger analytics initialization here if needed
    };

    const handleSavePreferences = () => {
        const consent = {
            analytics: preferences.analytics,
            marketing: preferences.marketing,
            timestamp: Date.now(),
        };
        localStorage.setItem('cookie_consent', JSON.stringify(consent));
        setIsVisible(false);
    };

    const handleRejectAll = () => {
        const consent = {
            analytics: false,
            marketing: false,
            timestamp: Date.now(),
        };
        localStorage.setItem('cookie_consent', JSON.stringify(consent));
        setIsVisible(false);
    };

    return (
        <AnimatePresence>
            {isVisible && (
                <motion.div
                    initial={{ y: 100, opacity: 0 }}
                    animate={{ y: 0, opacity: 1 }}
                    exit={{ y: 100, opacity: 0 }}
                    className="fixed inset-x-0 bottom-0 z-[200] p-4 md:p-8"
                >
                    <div className="container mx-auto max-w-4xl">
                        <div className="relative overflow-hidden rounded-[32px] border border-[var(--color-border)] bg-white p-6 shadow-[0_20px_60px_rgba(0,0,0,0.15)] md:p-10">
                            {/* Decorative background icon */}
                            <div className="absolute -top-6 -right-6 text-[var(--color-surface-alt)] opacity-50">
                                <Cookie size={160} strokeWidth={1} />
                            </div>

                            <div className="relative z-10">
                                {!showSettings ? (
                                    <div className="flex flex-col items-center gap-8 md:flex-row">
                                        <div className="flex h-16 w-16 shrink-0 items-center justify-center rounded-3xl bg-[var(--color-primary-light)] text-[var(--color-primary)]">
                                            <Cookie size={32} />
                                        </div>
                                        <div className="flex-1 text-center md:text-left">
                                            <h3 className="mb-2 text-xl font-bold text-[var(--color-text-primary)]">
                                                Cookie Preferences
                                            </h3>
                                            <p className="text-sm leading-relaxed text-[var(--color-text-secondary)]">
                                                We use cookies to enhance your
                                                browsing experience, serve
                                                personalized ads or content, and
                                                analyze our traffic. By clicking
                                                "Accept All", you consent to our
                                                use of cookies.
                                            </p>
                                        </div>
                                        <div className="flex w-full flex-wrap justify-center gap-3 md:w-auto">
                                            <Button
                                                variant="ghost"
                                                onClick={() =>
                                                    setShowSettings(true)
                                                }
                                                className="h-12 rounded-xl px-6 font-bold"
                                            >
                                                Settings
                                            </Button>
                                            <Button
                                                variant="outline"
                                                onClick={handleRejectAll}
                                                className="h-12 rounded-xl border-[var(--color-border)] px-6 font-bold"
                                            >
                                                Reject All
                                            </Button>
                                            <Button
                                                onClick={handleAcceptAll}
                                                className="h-12 rounded-xl px-8 font-bold shadow-[var(--color-primary)]/20 shadow-lg"
                                            >
                                                Accept All
                                            </Button>
                                        </div>
                                    </div>
                                ) : (
                                    <div className="space-y-8">
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-4">
                                                <div className="flex h-10 w-10 items-center justify-center rounded-2xl bg-[var(--color-primary-light)] text-[var(--color-primary)]">
                                                    <Shield size={20} />
                                                </div>
                                                <h3 className="text-xl font-bold text-[var(--color-text-primary)]">
                                                    Privacy Settings
                                                </h3>
                                            </div>
                                            <button
                                                onClick={() =>
                                                    setShowSettings(false)
                                                }
                                                className="rounded-full p-2 transition-colors hover:bg-[var(--color-surface-alt)]"
                                            >
                                                <X size={20} />
                                            </button>
                                        </div>

                                        <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                                            <div className="rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface-alt)]/50 p-5">
                                                <div className="mb-3 flex items-center justify-between">
                                                    <span className="text-sm font-bold">
                                                        Necessary
                                                    </span>
                                                    <Checkbox
                                                        checked
                                                        disabled
                                                    />
                                                </div>
                                                <p className="text-[10px] leading-relaxed text-[var(--color-text-muted)]">
                                                    These cookies are essential
                                                    for the website to function
                                                    and cannot be switched off.
                                                </p>
                                            </div>

                                            <div
                                                className={`cursor-pointer rounded-2xl border p-5 transition-all ${preferences.analytics ? 'border-[var(--color-primary)] bg-[var(--color-primary-light)]' : 'border-[var(--color-border)] bg-white'}`}
                                                onClick={() =>
                                                    setPreferences((prev) => ({
                                                        ...prev,
                                                        analytics:
                                                            !prev.analytics,
                                                    }))
                                                }
                                            >
                                                <div className="mb-3 flex items-center justify-between">
                                                    <span className="text-sm font-bold">
                                                        Analytics
                                                    </span>
                                                    <Checkbox
                                                        checked={
                                                            preferences.analytics
                                                        }
                                                        onCheckedChange={(
                                                            checked,
                                                        ) =>
                                                            setPreferences(
                                                                (prev) => ({
                                                                    ...prev,
                                                                    analytics:
                                                                        !!checked,
                                                                }),
                                                            )
                                                        }
                                                    />
                                                </div>
                                                <p className="text-[10px] leading-relaxed text-[var(--color-text-muted)]">
                                                    Allow us to count visits and
                                                    traffic sources so we can
                                                    measure and improve
                                                    performance.
                                                </p>
                                            </div>

                                            <div
                                                className={`cursor-pointer rounded-2xl border p-5 transition-all ${preferences.marketing ? 'border-[var(--color-primary)] bg-[var(--color-primary-light)]' : 'border-[var(--color-border)] bg-white'}`}
                                                onClick={() =>
                                                    setPreferences((prev) => ({
                                                        ...prev,
                                                        marketing:
                                                            !prev.marketing,
                                                    }))
                                                }
                                            >
                                                <div className="mb-3 flex items-center justify-between">
                                                    <span className="text-sm font-bold">
                                                        Marketing
                                                    </span>
                                                    <Checkbox
                                                        checked={
                                                            preferences.marketing
                                                        }
                                                        onCheckedChange={(
                                                            checked,
                                                        ) =>
                                                            setPreferences(
                                                                (prev) => ({
                                                                    ...prev,
                                                                    marketing:
                                                                        !!checked,
                                                                }),
                                                            )
                                                        }
                                                    />
                                                </div>
                                                <p className="text-[10px] leading-relaxed text-[var(--color-text-muted)]">
                                                    These cookies are used to
                                                    track visitors across
                                                    websites to display relevant
                                                    ads.
                                                </p>
                                            </div>
                                        </div>

                                        <div className="flex items-center justify-between border-t border-[var(--color-border)] pt-6">
                                            <button
                                                className="text-xs font-bold text-[var(--color-primary)] hover:underline"
                                                onClick={handleRejectAll}
                                            >
                                                Disable All
                                            </button>
                                            <div className="flex gap-3">
                                                <Button
                                                    variant="ghost"
                                                    onClick={() =>
                                                        setShowSettings(false)
                                                    }
                                                    className="rounded-xl font-bold"
                                                >
                                                    Back
                                                </Button>
                                                <Button
                                                    onClick={
                                                        handleSavePreferences
                                                    }
                                                    className="h-12 rounded-xl px-8 font-bold"
                                                >
                                                    Save Preferences
                                                </Button>
                                            </div>
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                </motion.div>
            )}
        </AnimatePresence>
    );
}
