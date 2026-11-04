import { motion, useReducedMotion } from 'framer-motion';
import type { Variants } from 'framer-motion';
import type { ComponentProps, ReactNode } from 'react';

const EASE_OUT: [number, number, number, number] = [0.22, 1, 0.36, 1];
const EASE_IN: [number, number, number, number] = [0.4, 0, 0.2, 1];

const revealVariants: Variants = {
    hidden: {
        height: 0,
        opacity: 0,
        y: -10,
        scale: 0.98,
        filter: 'blur(4px)',
        overflow: 'hidden',
    },
    visible: {
        height: 'auto',
        opacity: 1,
        y: 0,
        scale: 1,
        filter: 'blur(0px)',
        overflow: 'hidden',
        pointerEvents: 'auto',
        // Nothing may linger once open: clipping would cut off focus rings and
        // popups, and a filter would capture `position: fixed` descendants.
        transitionEnd: { overflow: 'visible', filter: 'none' },
        transition: {
            height: { duration: 0.42, ease: EASE_OUT },
            y: { duration: 0.42, ease: EASE_OUT },
            scale: { duration: 0.42, ease: EASE_OUT },
            opacity: { duration: 0.3, delay: 0.06, ease: 'easeOut' },
            filter: { duration: 0.3, delay: 0.06, ease: 'easeOut' },
        },
    },
    exit: {
        height: 0,
        opacity: 0,
        y: -6,
        scale: 0.98,
        filter: 'blur(4px)',
        overflow: 'hidden',
        pointerEvents: 'none',
        // Handed to the CSS transition on `.admin-reveal`, so the gap left by a
        // parent's `space-y-*` closes along with the height.
        marginTop: 0,
        marginBottom: 0,
        transition: {
            default: { duration: 0.3, ease: EASE_IN },
            opacity: { duration: 0.2, ease: 'easeIn' },
            filter: { duration: 0.2, ease: 'easeIn' },
            marginTop: { duration: 0 },
            marginBottom: { duration: 0 },
        },
    },
};

const fadeVariants: Variants = {
    hidden: { opacity: 0, scale: 0.92, filter: 'blur(3px)' },
    visible: {
        opacity: 1,
        scale: 1,
        filter: 'blur(0px)',
        pointerEvents: 'auto',
        transitionEnd: { filter: 'none' },
        transition: { duration: 0.26, ease: EASE_OUT },
    },
    exit: {
        opacity: 0,
        scale: 0.92,
        filter: 'blur(3px)',
        pointerEvents: 'none',
        transition: { duration: 0.18, ease: 'easeIn' },
    },
};

const instantVariants: Variants = {
    hidden: { opacity: 0 },
    visible: { opacity: 1, transition: { duration: 0 } },
    exit: { opacity: 0, transition: { duration: 0 } },
};

type AdminMotionProps = Omit<
    ComponentProps<typeof motion.div>,
    'children' | 'variants' | 'initial' | 'animate' | 'exit'
> & {
    children: ReactNode;
};

/**
 * Animates a block of admin UI opening and closing: panels revealed by a
 * toggle, or rows in a list. Render it directly inside `<AnimatePresence
 * initial={false}>` with a stable `key`, so it animates only in response to a
 * change and not on page load.
 */
export function AdminReveal({
    children,
    className,
    ...props
}: AdminMotionProps): React.JSX.Element {
    const prefersReducedMotion = useReducedMotion();

    return (
        <motion.div
            {...props}
            className={['admin-reveal', className].filter(Boolean).join(' ')}
            variants={prefersReducedMotion ? instantVariants : revealVariants}
            initial="hidden"
            animate="visible"
            exit="exit"
        >
            {children}
        </motion.div>
    );
}

/**
 * For small inline items (badges, status text, buttons sitting in a row),
 * where changing height would make the row itself jump. Same usage as
 * AdminReveal.
 */
export function AdminFade({
    children,
    ...props
}: AdminMotionProps): React.JSX.Element {
    const prefersReducedMotion = useReducedMotion();

    return (
        <motion.div
            {...props}
            variants={prefersReducedMotion ? instantVariants : fadeVariants}
            initial="hidden"
            animate="visible"
            exit="exit"
        >
            {children}
        </motion.div>
    );
}

const rowVariants: Variants = {
    hidden: { opacity: 0, x: -12 },
    visible: {
        opacity: 1,
        x: 0,
        transition: { duration: 0.3, ease: EASE_OUT },
    },
    exit: {
        opacity: 0,
        x: 12,
        pointerEvents: 'none',
        transition: { duration: 0.2, ease: 'easeIn' },
    },
};

type AdminTableRowProps = Omit<
    ComponentProps<typeof motion.tr>,
    'children' | 'variants' | 'initial' | 'animate' | 'exit'
> & {
    children: ReactNode;
};

/**
 * A table row version of AdminReveal: browsers can't animate a `<tr>`'s height
 * and a `<div>` can't wrap one, so it slides and fades instead.
 */
export function AdminTableRow({
    children,
    ...props
}: AdminTableRowProps): React.JSX.Element {
    const prefersReducedMotion = useReducedMotion();

    return (
        <motion.tr
            {...props}
            variants={prefersReducedMotion ? instantVariants : rowVariants}
            initial="hidden"
            animate="visible"
            exit="exit"
        >
            {children}
        </motion.tr>
    );
}
