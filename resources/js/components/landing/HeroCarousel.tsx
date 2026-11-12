import { AnimatePresence, motion, useReducedMotion } from 'framer-motion';
import { useEffect, useState } from 'react';

export type CarouselSettings = {
    animation: 'fade' | 'slide' | 'zoom' | 'flip' | 'blur';
    autoplay: boolean;
    interval_seconds: number;
    transition_ms: number;
    show_dots: boolean;
    show_progress: boolean;
};

const variants = {
    fade: {
        initial: { opacity: 0 },
        animate: { opacity: 1 },
        exit: { opacity: 0 },
    },
    slide: {
        initial: { opacity: 0, x: '12%' },
        animate: { opacity: 1, x: 0 },
        exit: { opacity: 0, x: '-12%' },
    },
    zoom: {
        initial: { opacity: 0, scale: 1.18 },
        animate: { opacity: 1, scale: 1 },
        exit: { opacity: 0, scale: 0.92 },
    },
    flip: {
        initial: { opacity: 0, rotateY: 70 },
        animate: { opacity: 1, rotateY: 0 },
        exit: { opacity: 0, rotateY: -70 },
    },
    blur: {
        initial: { opacity: 0, filter: 'blur(18px)', scale: 1.05 },
        animate: { opacity: 1, filter: 'blur(0px)', scale: 1 },
        exit: { opacity: 0, filter: 'blur(18px)', scale: 0.97 },
    },
} as const;

/**
 * Auto-advancing image showcase for the hero. It is display-only by design:
 * no arrows, swipe or click handling, and the dots and progress bar only
 * show where it is. Speed, animation and indicators come from the CMS.
 */
export default function HeroCarousel({
    slides,
    alt,
    settings,
}: {
    slides: string[];
    alt: string;
    settings: CarouselSettings;
}): React.JSX.Element | null {
    const [index, setIndex] = useState(0);
    const reduceMotion = useReducedMotion();
    const count = slides.length;
    const intervalMs = Math.max(2, settings.interval_seconds) * 1000;
    const canAdvance = settings.autoplay && count > 1 && !reduceMotion;

    useEffect(() => {
        if (!canAdvance) {
            return;
        }

        const timer = window.setInterval(
            () => setIndex((current) => (current + 1) % count),
            intervalMs,
        );

        return () => window.clearInterval(timer);
    }, [canAdvance, count, intervalMs]);

    if (count === 0) {
        return null;
    }

    const motionSet = variants[reduceMotion ? 'fade' : settings.animation];
    const current = slides[index % count];

    return (
        <div
            className="pointer-events-none relative aspect-square w-full select-none"
            style={{ perspective: 1200 }}
            aria-label={alt}
        >
            <div className="absolute inset-0 rounded-[2rem] bg-gradient-to-br from-white/15 to-white/5 ring-1 ring-white/15 backdrop-blur-sm" />
            <div className="absolute inset-0 overflow-hidden rounded-[2rem]">
                <AnimatePresence initial={false}>
                    <motion.img
                        key={`${index}-${current}`}
                        src={current}
                        alt={alt}
                        draggable={false}
                        initial={motionSet.initial}
                        animate={motionSet.animate}
                        exit={motionSet.exit}
                        transition={{
                            duration: settings.transition_ms / 1000,
                            ease: [0.22, 1, 0.36, 1],
                        }}
                        className="absolute inset-0 size-full object-contain p-4 drop-shadow-2xl"
                    />
                </AnimatePresence>
            </div>

            {count > 1 && settings.show_dots && (
                <div
                    aria-hidden
                    className="absolute inset-x-0 -bottom-6 flex justify-center gap-2"
                >
                    {slides.map((_, dot) => (
                        <span
                            key={dot}
                            className={`h-1.5 rounded-full transition-all duration-500 ${
                                dot === index % count
                                    ? 'w-7 bg-amber-400'
                                    : 'w-1.5 bg-white/30'
                            }`}
                        />
                    ))}
                </div>
            )}

            {canAdvance && settings.show_progress && (
                <div
                    aria-hidden
                    className="absolute inset-x-8 bottom-3 h-0.5 overflow-hidden rounded-full bg-white/15"
                >
                    <div
                        key={index}
                        className="lp-progress h-full origin-left bg-amber-400"
                        style={{ animationDuration: `${intervalMs}ms` }}
                    />
                </div>
            )}
        </div>
    );
}
