import { AnimatePresence, motion } from 'framer-motion';
import { Cookie, Shield, X } from 'lucide-react';
import React, { useEffect, useState } from 'react';

import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

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
                    className="fixed bottom-0 inset-x-0 z-[200] p-4 md:p-8"
                >
                    <div className="container mx-auto max-w-4xl">
                        <div className="bg-white rounded-[32px] p-6 md:p-10 shadow-[0_20px_60px_rgba(0,0,0,0.15)] border border-[var(--color-border)] relative overflow-hidden">
                            {/* Decorative background icon */}
                            <div className="absolute -top-6 -right-6 text-[var(--color-surface-alt)] opacity-50">
                                <Cookie size={160} strokeWidth={1} />
                            </div>

                            <div className="relative z-10">
                                {!showSettings ? (
                                    <div className="flex flex-col md:flex-row items-center gap-8">
                                        <div className="w-16 h-16 rounded-3xl bg-[var(--color-primary-light)] text-[var(--color-primary)] flex items-center justify-center shrink-0">
                                            <Cookie size={32} />
                                        </div>
                                        <div className="flex-1 text-center md:text-left">
                                            <h3 className="text-xl font-bold text-[var(--color-text-primary)] mb-2">Cookie Preferences</h3>
                                            <p className="text-sm text-[var(--color-text-secondary)] leading-relaxed">
                                                We use cookies to enhance your browsing experience, serve personalized ads or content, and analyze our traffic. By clicking "Accept All", you consent to our use of cookies.
                                            </p>
                                        </div>
                                        <div className="flex flex-wrap justify-center gap-3 w-full md:w-auto">
                                            <Button 
                                                variant="ghost" 
                                                onClick={() => setShowSettings(true)}
                                                className="rounded-xl font-bold h-12 px-6"
                                            >
                                                Settings
                                            </Button>
                                            <Button 
                                                variant="outline" 
                                                onClick={handleRejectAll}
                                                className="rounded-xl font-bold h-12 px-6 border-[var(--color-border)]"
                                            >
                                                Reject All
                                            </Button>
                                            <Button 
                                                onClick={handleAcceptAll}
                                                className="rounded-xl font-bold h-12 px-8 shadow-lg shadow-[var(--color-primary)]/20"
                                            >
                                                Accept All
                                            </Button>
                                        </div>
                                    </div>
                                ) : (
                                    <div className="space-y-8">
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-4">
                                                <div className="w-10 h-10 rounded-2xl bg-[var(--color-primary-light)] text-[var(--color-primary)] flex items-center justify-center">
                                                    <Shield size={20} />
                                                </div>
                                                <h3 className="text-xl font-bold text-[var(--color-text-primary)]">Privacy Settings</h3>
                                            </div>
                                            <button 
                                                onClick={() => setShowSettings(false)}
                                                className="p-2 hover:bg-[var(--color-surface-alt)] rounded-full transition-colors"
                                            >
                                                <X size={20} />
                                            </button>
                                        </div>

                                        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                                            <div className="p-5 rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface-alt)]/50">
                                                <div className="flex items-center justify-between mb-3">
                                                    <span className="font-bold text-sm">Necessary</span>
                                                    <Checkbox checked disabled />
                                                </div>
                                                <p className="text-[10px] text-[var(--color-text-muted)] leading-relaxed">
                                                    These cookies are essential for the website to function and cannot be switched off.
                                                </p>
                                            </div>

                                            <div className={`p-5 rounded-2xl border transition-all cursor-pointer ${preferences.analytics ? 'border-[var(--color-primary)] bg-[var(--color-primary-light)]' : 'border-[var(--color-border)] bg-white'}`}
                                                onClick={() => setPreferences(prev => ({ ...prev, analytics: !prev.analytics }))}
                                            >
                                                <div className="flex items-center justify-between mb-3">
                                                    <span className="font-bold text-sm">Analytics</span>
                                                    <Checkbox 
                                                        checked={preferences.analytics} 
                                                        onCheckedChange={(checked) => setPreferences(prev => ({ ...prev, analytics: !!checked }))}
                                                    />
                                                </div>
                                                <p className="text-[10px] text-[var(--color-text-muted)] leading-relaxed">
                                                    Allow us to count visits and traffic sources so we can measure and improve performance.
                                                </p>
                                            </div>

                                            <div className={`p-5 rounded-2xl border transition-all cursor-pointer ${preferences.marketing ? 'border-[var(--color-primary)] bg-[var(--color-primary-light)]' : 'border-[var(--color-border)] bg-white'}`}
                                                onClick={() => setPreferences(prev => ({ ...prev, marketing: !prev.marketing }))}
                                            >
                                                <div className="flex items-center justify-between mb-3">
                                                    <span className="font-bold text-sm">Marketing</span>
                                                    <Checkbox 
                                                        checked={preferences.marketing} 
                                                        onCheckedChange={(checked) => setPreferences(prev => ({ ...prev, marketing: !!checked }))}
                                                    />
                                                </div>
                                                <p className="text-[10px] text-[var(--color-text-muted)] leading-relaxed">
                                                    These cookies are used to track visitors across websites to display relevant ads.
                                                </p>
                                            </div>
                                        </div>

                                        <div className="flex justify-between items-center pt-6 border-t border-[var(--color-border)]">
                                            <button 
                                                className="text-xs font-bold text-[var(--color-primary)] hover:underline"
                                                onClick={handleRejectAll}
                                            >
                                                Disable All
                                            </button>
                                            <div className="flex gap-3">
                                                <Button 
                                                    variant="ghost" 
                                                    onClick={() => setShowSettings(false)}
                                                    className="rounded-xl font-bold"
                                                >
                                                    Back
                                                </Button>
                                                <Button 
                                                    onClick={handleSavePreferences}
                                                    className="rounded-xl font-bold h-12 px-8"
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
