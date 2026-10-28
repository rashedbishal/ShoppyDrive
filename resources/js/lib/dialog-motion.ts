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
    exit: {
        opacity: 0,
        transition: {
            duration: 0.32,
            delay: 0.04,
            ease: [0.4, 0, 0.2, 1],
        } satisfies Transition,
    },
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
    // A brief lift (anticipation), then the panel sinks away into a blur
    // while its sections fade out bottom-up just ahead of it.
    exit: {
        opacity: [1, 1, 0],
        y: [0, -4, 18],
        scale: [1, 1.015, 0.9],
        filter: ['blur(0px)', 'blur(0px)', 'blur(10px)'],
        transition: {
            duration: 0.3,
            times: [0, 0.28, 1],
            ease: [0.55, 0, 0.75, 0],
            staggerChildren: 0.025,
            staggerDirection: -1,
        },
    },
};

export const dialogItem: Variants = {
    hidden: { opacity: 0, y: 8 },
    visible: {
        opacity: 1,
        y: 0,
        transition: { type: 'spring', stiffness: 500, damping: 34 },
    },
    exit: {
        opacity: 0,
        y: -6,
        transition: { duration: 0.15, ease: [0.4, 0, 1, 1] },
    },
};
