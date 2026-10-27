import type { Transition, Variants } from 'framer-motion';

/**
 * framer-motion counterparts of the `.dialog-premium` CSS animation in
 * app.css, for admin modals that aren't built on Radix Dialog (and so can't
 * pick it up via `<DialogContent appearance="premium">`). Keep the two in
 * step so every admin modal opens and closes the same way.
 */
export const dialogOverlayMotion = {
    initial: { opacity: 0 },
    animate: { opacity: 1 },
    exit: { opacity: 0 },
    transition: { duration: 0.2 } satisfies Transition,
};

/** Panel springs up out of a soft blur, then its sections cascade in. */
export const dialogPanel: Variants = {
    hidden: { opacity: 0, y: 24, scale: 0.94, filter: 'blur(8px)' },
    visible: {
        opacity: 1,
        y: 0,
        scale: 1,
        filter: 'blur(0px)',
        transition: {
            type: 'spring',
            stiffness: 420,
            damping: 32,
            mass: 0.8,
            staggerChildren: 0.045,
            delayChildren: 0.06,
        },
    },
    exit: {
        opacity: 0,
        y: 12,
        scale: 0.97,
        filter: 'blur(4px)',
        transition: { duration: 0.16, ease: [0.4, 0, 1, 1] },
    },
};

export const dialogItem: Variants = {
    hidden: { opacity: 0, y: 8 },
    visible: {
        opacity: 1,
        y: 0,
        transition: { type: 'spring', stiffness: 500, damping: 34 },
    },
};
