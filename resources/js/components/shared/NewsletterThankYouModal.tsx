import * as DialogPrimitive from '@radix-ui/react-dialog';
import { AnimatePresence, motion, useReducedMotion } from 'framer-motion';
import { BellRing, MailCheck, PartyPopper, X } from 'lucide-react';

export type NewsletterStatus =
    | 'subscribed'
    | 'resubscribed'
    | 'already_subscribed';

type Props = {
    /** Which outcome to thank the visitor for; null keeps the modal closed. */
    status: NewsletterStatus | null;
    /** The address that was submitted, echoed back in the message. */
    email: string;
    onClose: () => void;
};

const CONTENT: Record<
    NewsletterStatus,
    {
        icon: typeof MailCheck;
        eyebrow: string;
        title: string;
        body: (email: string) => string;
        celebrate: boolean;
    }
> = {
    subscribed: {
        icon: PartyPopper,
        eyebrow: 'Subscription confirmed',
        title: 'Thank you for subscribing!',
        body: (email) =>
            `You're on the list. Deals, new arrivals and sale alerts will land in ${email} — keep an eye on your inbox.`,
        celebrate: true,
    },
    resubscribed: {
        icon: MailCheck,
        eyebrow: 'Welcome back',
        title: 'Great to have you back!',
        body: (email) =>
            `You're subscribed again. Deals, new arrivals and sale alerts will start arriving at ${email}.`,
        celebrate: true,
    },
    already_subscribed: {
        icon: BellRing,
        eyebrow: 'Already on the list',
        title: "You're already subscribed",
        body: (email) =>
            `${email} is already on our mailing list, so you won't miss a deal. Thanks for staying with us!`,
        celebrate: false,
    },
};

/** Directions for the one-off burst of dots behind the icon. */
const BURST = Array.from({ length: 14 }, (_, index) => {
    const angle = (index / 14) * Math.PI * 2;
    const distance = 70 + (index % 3) * 18;

    return {
        x: Math.cos(angle) * distance,
        y: Math.sin(angle) * distance,
        size: 5 + (index % 3) * 2,
        accent: index % 2 === 0,
    };
});

/**
 * The storefront's newsletter thank-you: a centred card that springs up out
 * of a soft blur, its icon popping in with ripple rings and (for a new or
 * returning subscriber) a small burst of colour. Built on the theme's
 * colour variables so it matches whichever storefront theme is active.
 */
export default function NewsletterThankYouModal({
    status,
    email,
    onClose,
}: Props): React.JSX.Element {
    const reduceMotion = useReducedMotion();
    const content = status ? CONTENT[status] : null;
    const Icon = content?.icon ?? MailCheck;

    const rise = (delay: number) => ({
        initial: reduceMotion ? { opacity: 0 } : { opacity: 0, y: 12 },
        animate: { opacity: 1, y: 0 },
        transition: {
            delay,
            duration: 0.45,
            ease: [0.22, 1, 0.36, 1] as const,
        },
    });

    return (
        <DialogPrimitive.Root
            open={content !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <AnimatePresence>
                {content ? (
                    <DialogPrimitive.Portal forceMount>
                        <DialogPrimitive.Overlay asChild forceMount>
                            <motion.div
                                className="fixed inset-0 z-[70] bg-[var(--color-overlay)] backdrop-blur-md"
                                initial={{ opacity: 0 }}
                                animate={{ opacity: 1 }}
                                exit={{ opacity: 0 }}
                                transition={{ duration: 0.3 }}
                            />
                        </DialogPrimitive.Overlay>
                        <div className="pointer-events-none fixed inset-0 z-[71] flex items-center justify-center p-4">
                            <DialogPrimitive.Content asChild forceMount>
                                <motion.div
                                    className="pointer-events-auto relative w-full max-w-md overflow-hidden rounded-3xl border border-[var(--color-border)] bg-[var(--color-surface)] text-center shadow-[var(--shadow-modal)] focus:outline-none"
                                    initial={
                                        reduceMotion
                                            ? { opacity: 0 }
                                            : {
                                                  opacity: 0,
                                                  y: 40,
                                                  scale: 0.86,
                                                  filter: 'blur(10px)',
                                              }
                                    }
                                    animate={{
                                        opacity: 1,
                                        y: 0,
                                        scale: 1,
                                        filter: 'blur(0px)',
                                    }}
                                    exit={
                                        reduceMotion
                                            ? { opacity: 0 }
                                            : {
                                                  opacity: 0,
                                                  y: 16,
                                                  scale: 0.94,
                                                  filter: 'blur(6px)',
                                              }
                                    }
                                    transition={{
                                        type: 'spring',
                                        stiffness: 260,
                                        damping: 24,
                                        mass: 0.9,
                                    }}
                                >
                                    {/* Soft brand-coloured wash behind the icon. */}
                                    <div
                                        aria-hidden="true"
                                        className="pointer-events-none absolute inset-x-0 top-0 h-44"
                                        style={{
                                            background:
                                                'radial-gradient(120% 90% at 50% 0%, var(--color-primary-light) 0%, transparent 70%)',
                                        }}
                                    />

                                    <DialogPrimitive.Close
                                        className="absolute top-3 right-3 z-10 inline-flex size-9 items-center justify-center rounded-full text-[var(--color-text-muted)] transition hover:bg-[var(--color-surface-alt)] hover:text-[var(--color-text-primary)] focus-visible:ring-2 focus-visible:ring-[var(--color-primary)] focus-visible:outline-none"
                                        aria-label="Close"
                                    >
                                        <X className="size-4.5" />
                                    </DialogPrimitive.Close>

                                    <div className="relative px-6 pt-10 pb-7 sm:px-8">
                                        <div className="relative mx-auto mb-6 flex size-24 items-center justify-center">
                                            {!reduceMotion &&
                                                [0, 0.35].map((delay) => (
                                                    <motion.span
                                                        key={delay}
                                                        aria-hidden="true"
                                                        className="absolute inset-0 rounded-full border-2 border-[var(--color-primary)]"
                                                        initial={{
                                                            scale: 0.7,
                                                            opacity: 0.55,
                                                        }}
                                                        animate={{
                                                            scale: 1.9,
                                                            opacity: 0,
                                                        }}
                                                        transition={{
                                                            delay: 0.25 + delay,
                                                            duration: 1.4,
                                                            repeat: 2,
                                                            repeatDelay: 0.5,
                                                            ease: 'easeOut',
                                                        }}
                                                    />
                                                ))}

                                            {content.celebrate &&
                                                !reduceMotion &&
                                                BURST.map((dot, index) => (
                                                    <motion.span
                                                        key={index}
                                                        aria-hidden="true"
                                                        className="absolute top-1/2 left-1/2 rounded-full"
                                                        style={{
                                                            width: dot.size,
                                                            height: dot.size,
                                                            marginLeft:
                                                                -dot.size / 2,
                                                            marginTop:
                                                                -dot.size / 2,
                                                            background:
                                                                dot.accent
                                                                    ? 'var(--color-accent)'
                                                                    : 'var(--color-primary)',
                                                        }}
                                                        initial={{
                                                            x: 0,
                                                            y: 0,
                                                            scale: 0,
                                                            opacity: 1,
                                                        }}
                                                        animate={{
                                                            x: dot.x,
                                                            y: dot.y,
                                                            scale: [
                                                                0, 1.2, 0.6,
                                                            ],
                                                            opacity: [1, 1, 0],
                                                        }}
                                                        transition={{
                                                            delay: 0.35,
                                                            duration: 1.1,
                                                            ease: [
                                                                0.16, 1, 0.3, 1,
                                                            ],
                                                        }}
                                                    />
                                                ))}

                                            <motion.span
                                                className="relative flex size-20 items-center justify-center rounded-full text-white shadow-[var(--shadow-card-hover)]"
                                                style={{
                                                    background:
                                                        'linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-hover) 100%)',
                                                }}
                                                initial={
                                                    reduceMotion
                                                        ? { opacity: 0 }
                                                        : {
                                                              scale: 0,
                                                              rotate: -25,
                                                          }
                                                }
                                                animate={{
                                                    opacity: 1,
                                                    scale: 1,
                                                    rotate: 0,
                                                }}
                                                transition={{
                                                    type: 'spring',
                                                    stiffness: 320,
                                                    damping: 14,
                                                    delay: 0.15,
                                                }}
                                            >
                                                <Icon
                                                    className="size-9"
                                                    strokeWidth={2}
                                                />
                                            </motion.span>
                                        </div>

                                        <motion.p
                                            {...rise(0.3)}
                                            className="text-[11px] font-bold tracking-[0.22em] text-[var(--color-primary)] uppercase"
                                        >
                                            {content.eyebrow}
                                        </motion.p>
                                        <DialogPrimitive.Title asChild>
                                            <motion.h2
                                                {...rise(0.38)}
                                                className="mt-2 font-display text-2xl font-semibold tracking-tight text-[var(--color-text-primary)] sm:text-[1.75rem]"
                                            >
                                                {content.title}
                                            </motion.h2>
                                        </DialogPrimitive.Title>
                                        <DialogPrimitive.Description asChild>
                                            <motion.p
                                                {...rise(0.46)}
                                                className="mx-auto mt-3 max-w-sm text-sm leading-6 break-words text-[var(--color-text-secondary)]"
                                            >
                                                {content.body(email)}
                                            </motion.p>
                                        </DialogPrimitive.Description>

                                        <motion.div
                                            {...rise(0.56)}
                                            className="mt-7"
                                        >
                                            <DialogPrimitive.Close className="inline-flex h-11 w-full items-center justify-center rounded-xl bg-[var(--color-primary)] px-6 text-sm font-semibold text-white transition hover:bg-[var(--color-primary-hover)] focus-visible:ring-2 focus-visible:ring-[var(--color-primary)] focus-visible:ring-offset-2 focus-visible:outline-none active:scale-[0.98]">
                                                Continue shopping
                                            </DialogPrimitive.Close>
                                            <p className="mt-3 text-xs text-[var(--color-text-muted)]">
                                                You can unsubscribe any time
                                                from the link in our emails.
                                            </p>
                                        </motion.div>
                                    </div>
                                </motion.div>
                            </DialogPrimitive.Content>
                        </div>
                    </DialogPrimitive.Portal>
                ) : null}
            </AnimatePresence>
        </DialogPrimitive.Root>
    );
}
