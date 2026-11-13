import { Head, Link, useForm } from '@inertiajs/react';
import { motion, useReducedMotion } from 'framer-motion';
import {
    ArrowRight,
    BadgePercent,
    CheckCircle2,
    LinkIcon,
    MailX,
    Sparkles,
    Zap,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';

type Props = {
    status: 'confirm' | 'unsubscribed' | 'invalid';
    email?: string;
    token?: string;
};

/** What a subscriber gives up — shown on the confirm step. */
const PERKS = [
    {
        icon: Sparkles,
        label: 'New arrivals',
        text: 'First look at fresh stock',
    },
    { icon: Zap, label: 'Flash sales', text: 'Alerts before deals run out' },
    {
        icon: BadgePercent,
        label: 'Exclusive offers',
        text: 'Subscriber-only discounts',
    },
];

/**
 * The newsletter unsubscribe flow (FSA-14): the confirm step a mailed link
 * opens, the one-time success state after confirming, and the invalid-link
 * state. A reused link after unsubscribing never reaches this page — the
 * server sends it home instead.
 */
export default function Unsubscribe({
    status,
    email,
    token,
}: Props): React.JSX.Element {
    const title =
        status === 'unsubscribed'
            ? "You've been unsubscribed"
            : status === 'invalid'
              ? 'Link not valid'
              : 'Unsubscribe';

    return (
        <>
            <Head title={`${title} — Newsletter`} />
            <section
                className="relative flex min-h-[70vh] items-center justify-center overflow-hidden px-4 py-12 sm:py-20"
                style={{
                    background:
                        'radial-gradient(90% 60% at 50% 0%, var(--color-primary-light) 0%, transparent 70%)',
                }}
            >
                <div className="w-full max-w-xl">
                    {status === 'confirm' && (
                        <ConfirmStep email={email ?? ''} token={token ?? ''} />
                    )}
                    {status === 'unsubscribed' && (
                        <SuccessStep email={email ?? ''} />
                    )}
                    {status === 'invalid' && <InvalidStep />}
                </div>
            </section>
        </>
    );
}

/** The shared card: rises in softly, then lays out its icon, text and actions. */
function StepCard({
    icon,
    iconTone = 'primary',
    eyebrow,
    title,
    children,
}: {
    icon: ReactNode;
    iconTone?: 'primary' | 'success';
    eyebrow: string;
    title: string;
    children: ReactNode;
}): React.JSX.Element {
    const reduceMotion = useReducedMotion();

    return (
        <motion.div
            initial={reduceMotion ? { opacity: 0 } : { opacity: 0, y: 24 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.5, ease: [0.22, 1, 0.36, 1] as const }}
            className="rounded-3xl border border-[var(--color-border)] bg-[var(--color-surface)] p-6 text-center shadow-[var(--shadow-card)] sm:p-10"
        >
            <motion.div
                initial={
                    reduceMotion ? { opacity: 0 } : { scale: 0, rotate: -20 }
                }
                animate={{ opacity: 1, scale: 1, rotate: 0 }}
                transition={{
                    type: 'spring',
                    stiffness: 300,
                    damping: 16,
                    delay: 0.15,
                }}
                className="mx-auto mb-6 flex size-16 items-center justify-center rounded-2xl text-white shadow-[var(--shadow-card-hover)] sm:size-20"
                style={{
                    background:
                        iconTone === 'success'
                            ? 'linear-gradient(135deg, var(--color-success) 0%, color-mix(in srgb, var(--color-success) 75%, black) 100%)'
                            : 'linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-hover) 100%)',
                }}
            >
                {icon}
            </motion.div>
            <p className="text-[11px] font-bold tracking-[0.22em] text-[var(--color-primary)] uppercase">
                {eyebrow}
            </p>
            <h1 className="mt-2 font-display text-2xl font-semibold tracking-tight text-[var(--color-text-primary)] sm:text-3xl">
                {title}
            </h1>
            {children}
        </motion.div>
    );
}

function EmailPill({ email }: { email: string }): React.JSX.Element {
    return (
        <span className="inline-block max-w-full rounded-full border border-[var(--color-border)] bg-[var(--color-surface-alt)] px-3 py-1 text-sm font-medium break-all text-[var(--color-text-primary)]">
            {email}
        </span>
    );
}

function ConfirmStep({
    email,
    token,
}: {
    email: string;
    token: string;
}): React.JSX.Element {
    const { post, processing } = useForm({ email, token });

    const submit = (event: React.FormEvent): void => {
        event.preventDefault();
        post(route('newsletter.unsubscribe'));
    };

    return (
        <StepCard
            icon={<MailX className="size-8 sm:size-9" strokeWidth={2} />}
            eyebrow="Newsletter"
            title="Sorry to see you go"
        >
            <p className="mx-auto mt-3 max-w-md text-sm leading-6 text-[var(--color-text-secondary)] sm:text-base sm:leading-7">
                Confirm below and this address will stop receiving our
                newsletter:
            </p>
            <div className="mt-3">
                <EmailPill email={email} />
            </div>

            <div className="mt-7 rounded-2xl bg-[var(--color-surface-alt)] p-4 text-left sm:p-5">
                <p className="mb-3 text-xs font-semibold tracking-wide text-[var(--color-text-secondary)] uppercase">
                    You'll miss out on
                </p>
                <ul className="grid gap-3 sm:grid-cols-3">
                    {PERKS.map(({ icon: Icon, label, text }) => (
                        <li
                            key={label}
                            className="flex items-start gap-3 sm:block"
                        >
                            <span className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-[var(--color-primary-light)] text-[var(--color-primary)] sm:mb-2">
                                <Icon className="size-4.5" />
                            </span>
                            <span>
                                <span className="block text-sm font-semibold text-[var(--color-text-primary)]">
                                    {label}
                                </span>
                                <span className="block text-xs leading-5 text-[var(--color-text-secondary)]">
                                    {text}
                                </span>
                            </span>
                        </li>
                    ))}
                </ul>
            </div>

            <form
                onSubmit={submit}
                className="mt-7 flex flex-col-reverse gap-3 sm:flex-row"
            >
                <Button
                    type="submit"
                    variant="outline"
                    size="lg"
                    fullWidth
                    loading={processing}
                    loadingText="Unsubscribing…"
                    className="sm:flex-1"
                >
                    Yes, unsubscribe me
                </Button>
                <Button asChild size="lg" fullWidth className="sm:flex-1">
                    <Link href={route('home')}>Keep me subscribed</Link>
                </Button>
            </form>
            <p className="mt-4 text-xs text-[var(--color-text-secondary)]">
                Order and delivery emails aren't affected — you'll still get
                those.
            </p>
        </StepCard>
    );
}

function SuccessStep({ email }: { email: string }): React.JSX.Element {
    return (
        <StepCard
            icon={<CheckCircle2 className="size-8 sm:size-9" strokeWidth={2} />}
            iconTone="success"
            eyebrow="All done"
            title="You've been unsubscribed"
        >
            <div className="mt-4">
                <EmailPill email={email} />
            </div>
            <p className="mx-auto mt-4 max-w-md text-sm leading-6 text-[var(--color-text-secondary)] sm:text-base sm:leading-7">
                won't receive our newsletter anymore. Your order and delivery
                emails will still arrive as usual.
            </p>

            <div className="mt-8">
                <Button asChild size="lg" fullWidth>
                    <Link href={route('home')}>
                        Continue shopping
                        <ArrowRight className="size-4" />
                    </Link>
                </Button>
            </div>
            <p className="mt-5 text-xs leading-5 text-[var(--color-text-secondary)]">
                Changed your mind? You can subscribe again any time from the
                newsletter box at the bottom of every page.
            </p>
        </StepCard>
    );
}

function InvalidStep(): React.JSX.Element {
    return (
        <StepCard
            icon={<LinkIcon className="size-8 sm:size-9" strokeWidth={2} />}
            eyebrow="Newsletter"
            title="This link isn't valid"
        >
            <p className="mx-auto mt-3 max-w-md text-sm leading-6 text-[var(--color-text-secondary)] sm:text-base sm:leading-7">
                It may be incomplete or out of date. If you still want to stop
                receiving our newsletter, request a fresh link — we'll email it
                to you.
            </p>

            <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                <Button asChild size="lg" fullWidth className="sm:flex-1">
                    <Link href={route('newsletter.manage')}>
                        Get a new link
                    </Link>
                </Button>
                <Button
                    asChild
                    variant="outline"
                    size="lg"
                    fullWidth
                    className="sm:flex-1"
                >
                    <Link href={route('home')}>Go to home page</Link>
                </Button>
            </div>
        </StepCard>
    );
}
