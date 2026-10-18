import { router } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import { useEffect, useRef, useState } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';

/**
 * Replaces Inertia's default top progress bar. Visits under ~150ms never
 * show it at all, so fast/cached navigations don't flash the overlay.
 */
export default function PageLoadingOverlay(): React.JSX.Element {
    const [visible, setVisible] = useState(false);
    const showTimeout = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(() => {
        const clearPendingShow = () => {
            if (showTimeout.current) {
                clearTimeout(showTimeout.current);
                showTimeout.current = null;
            }
        };

        const removeStart = router.on('start', () => {
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
                    className="fixed inset-0 z-[9999] flex items-center justify-center bg-white/90 backdrop-blur-sm"
                >
                    <motion.span
                        className="flex size-16 items-center justify-center rounded-2xl bg-[var(--color-secondary)] text-white shadow-[var(--shadow-modal)]"
                        style={{ perspective: 600 }}
                        animate={{ rotateY: 360 }}
                        transition={{ duration: 1.1, repeat: Infinity, ease: 'linear' }}
                    >
                        <AppLogoIcon className="size-8 fill-current text-white" />
                    </motion.span>
                </motion.div>
            )}
        </AnimatePresence>
    );
}
