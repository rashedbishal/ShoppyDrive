import { ArrowRight } from 'lucide-react';
import { cn } from '@/lib/utils';

type Props = {
    href: string;
    children: React.ReactNode;
    size?: 'md' | 'lg' | 'xl';
    className?: string;
};

const sizes = {
    md: 'h-11 gap-2 px-6 text-sm',
    lg: 'h-14 gap-2.5 px-8 text-base',
    xl: 'h-16 gap-3 px-10 text-lg sm:h-[4.5rem] sm:px-12 sm:text-xl',
} as const;

/**
 * The landing page's one loud call to action: a large ember-red pill with a
 * pulsing glow and a slow light sweep. Every "Order now" uses it, so the eye
 * always has one obvious place to go. The animations (`lp-glow`, `lp-shine`)
 * are defined by the landing page itself and switch off for visitors who
 * prefer reduced motion.
 */
export default function GlowButton({
    href,
    children,
    size = 'lg',
    className,
}: Props): React.JSX.Element {
    return (
        <a
            href={href}
            className={cn(
                'lp-glow group relative inline-flex items-center justify-center overflow-hidden rounded-full bg-gradient-to-b from-brand-bright to-brand font-extrabold tracking-wide text-brand-foreground ring-1 ring-white/25 transition-transform duration-200 ring-inset hover:scale-[1.04] active:scale-[0.98]',
                sizes[size],
                className,
            )}
        >
            <span
                aria-hidden
                className="lp-shine pointer-events-none absolute inset-y-0 -left-1/3 w-1/3 -skew-x-12 bg-gradient-to-r from-transparent via-white/45 to-transparent"
            />
            <span className="relative">{children}</span>
            <ArrowRight className="relative size-[1.15em] transition-transform duration-200 group-hover:translate-x-1" />
        </a>
    );
}
