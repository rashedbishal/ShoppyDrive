import { router } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import { useEffect, useState } from 'react';

export default function NProgress(): React.JSX.Element | null {
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        let finishTimeout: number | undefined;

        const removeStart = router.on('start', () => {
            if (finishTimeout !== undefined) {
                window.clearTimeout(finishTimeout);
            }

            setVisible(true);
        });

        const removeFinish = router.on('finish', () => {
            finishTimeout = window.setTimeout(() => {
                setVisible(false);
            }, 180);
        });

        return () => {
            if (typeof removeStart === 'function') {
                removeStart();
            }

            if (typeof removeFinish === 'function') {
                removeFinish();
            }

            if (finishTimeout !== undefined) {
                window.clearTimeout(finishTimeout);
            }
        };
    }, []);

    return (
        <AnimatePresence>
            {visible ? (
                <motion.div
                    aria-hidden="true"
                    className="fixed inset-x-0 top-0 z-[70] h-1 overflow-hidden bg-transparent"
                    initial={{ opacity: 0 }}
                    animate={{ opacity: 1 }}
                    exit={{ opacity: 0 }}
                >
                    <motion.div
                        className="h-full origin-left bg-[var(--color-primary)] shadow-[0_0_18px_rgba(200,16,46,0.45)]"
                        initial={{ scaleX: 0.15, x: '-40%' }}
                        animate={{
                            scaleX: [0.2, 0.6, 0.88],
                            x: ['-40%', '-10%', '0%'],
                        }}
                        exit={{ scaleX: 1, opacity: 0 }}
                        transition={{
                            duration: 0.55,
                            repeat: Infinity,
                            repeatType: 'reverse',
                            ease: 'easeInOut',
                        }}
                        style={{ width: '100%' }}
                    />
                </motion.div>
            ) : null}
        </AnimatePresence>
    );
}
