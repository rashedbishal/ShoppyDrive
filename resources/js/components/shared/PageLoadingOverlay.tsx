import { router } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import { useEffect, useRef, useState } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import type { SharedPageProps } from '@/types';

function readInitialLogo(): string | null {
    try {
        const raw = document.getElementById('app')?.dataset.page;

        if (raw) {
            const settings = JSON.parse(raw)?.props?.settings;

            return settings?.loading_logo || settings?.logo || null;
        }
    } catch {
        // Missing or malformed page data: fall through to the default below.
    }

    return null;
}

/**
 * Replaces Inertia's default top progress bar. Spins the CMS "Loading Logo"
 * (Settings ▸ General), falling back to the brand logo when none is set.
 * Visits under ~150ms never show it at all, so fast/cached navigations
 * don't flash the overlay.
 */
export default function PageLoadingOverlay(): React.JSX.Element {
    const [visible, setVisible] = useState(false);
    const [logo, setLogo] = useState<string | null>(readInitialLogo);
    const showTimeout = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(() => {
        return router.on('navigate', (event) => {
            const props = event.detail.page.props as SharedPageProps;
            setLogo(
                props.settings?.loading_logo || props.settings?.logo || null,
            );
        });
    }, []);

    useEffect(() => {
        const clearPendingShow = () => {
            if (showTimeout.current) {
                clearTimeout(showTimeout.current);
                showTimeout.current = null;
            }
        };

        const removeStart = router.on('start', (event) => {
            // Visits that opt out via `showProgress: false` (e.g. a page's
            // own scoped filter reloads) manage their own loading UI and
            // shouldn't be covered by this full-screen overlay.
            if (event.detail.visit.showProgress === false) {
                return;
            }

            clearPendingShow();
            showTimeout.current = setTimeout(() => setVisible(true), 150);
        });
        const removeFinish = router.on('finish', () => {
            clearPendingShow();
            setVisible(false);
        });

        return () => {
            clearPendingShow();
            removeStart();
            removeFinish();
        };
    }, []);

    return (
        <AnimatePresence>
            {visible && (
                <motion.div
                    key="page-loading-overlay"
                    initial={{ opacity: 0 }}
                    animate={{ opacity: 1 }}
                    exit={{ opacity: 0 }}
                    transition={{ duration: 0.15 }}
                    className="fixed inset-0 z-[9999] flex items-center justify-center bg-white/10 backdrop-blur-xl"
                >
                    <motion.span
                        style={{ perspective: 600 }}
                        animate={{ rotateY: 360 }}
                        transition={{
                            duration: 1.1,
                            repeat: Infinity,
                            ease: 'linear',
                        }}
                    >
                        {logo ? (
                            <img
                                src={logo}
                                alt="Loading"
                                className="h-14 w-auto object-contain drop-shadow-[0_8px_24px_rgba(0,0,0,0.25)]"
                            />
                        ) : (
                            <span className="flex size-16 items-center justify-center rounded-2xl bg-[var(--color-secondary)] text-white shadow-[var(--shadow-modal)]">
                                <AppLogoIcon className="size-8 fill-current text-white" />
                            </span>
                        )}
                    </motion.span>
                </motion.div>
            )}
        </AnimatePresence>
    );
}
