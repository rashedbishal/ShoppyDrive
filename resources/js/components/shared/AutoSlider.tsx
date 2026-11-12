import { useReducedMotion } from 'framer-motion';
import { useCallback, useEffect, useRef, useState } from 'react';

import { cn } from '@/lib/utils';

type AutoSliderProps = {
    children: React.ReactNode;
    /** Milliseconds between automatic steps. */
    interval?: number;
    className?: string;
    label: string;
};

/**
 * A scrollbar-less row that advances one item at a time by itself and wraps
 * back to the start at the end. It is swipeable by touch (native scrolling with
 * snap points) and draggable with a mouse, and it pauses while someone hovers,
 * touches or focuses it. People who prefer reduced motion get no auto-advance.
 * Every item must be a direct child; they should be a fixed width.
 */
export function AutoSlider({
    children,
    interval = 3000,
    className,
    label,
}: AutoSliderProps): React.JSX.Element {
    const trackRef = useRef<HTMLDivElement>(null);
    const [paused, setPaused] = useState(false);
    const reducedMotion = useReducedMotion();
    const drag = useRef<{ x: number; left: number; moved: boolean } | null>(
        null,
    );

    const advance = useCallback(() => {
        const track = trackRef.current;
        const first = track?.firstElementChild as HTMLElement | null;

        if (!track || !first) {
            return;
        }

        const gap = parseFloat(getComputedStyle(track).columnGap) || 0;
        const step = first.offsetWidth + gap;
        const atEnd =
            track.scrollLeft >= track.scrollWidth - track.clientWidth - 4;

        if (track.scrollWidth <= track.clientWidth + 4) {
            return;
        }

        track.scrollTo({
            left: atEnd ? 0 : track.scrollLeft + step,
            behavior: 'smooth',
        });
    }, []);

    useEffect(() => {
        if (paused || reducedMotion) {
            return;
        }

        const timer = window.setInterval(() => {
            if (!document.hidden) {
                advance();
            }
        }, interval);

        return () => window.clearInterval(timer);
    }, [advance, interval, paused, reducedMotion]);

    const onPointerDown = (event: React.PointerEvent<HTMLDivElement>) => {
        const track = trackRef.current;

        if (event.pointerType !== 'mouse' || !track) {
            return;
        }

        drag.current = {
            x: event.clientX,
            left: track.scrollLeft,
            moved: false,
        };
    };

    const onPointerMove = (event: React.PointerEvent<HTMLDivElement>) => {
        const track = trackRef.current;
        const state = drag.current;

        if (!track || !state) {
            return;
        }

        const delta = event.clientX - state.x;

        if (!state.moved && Math.abs(delta) > 5) {
            state.moved = true;
            track.style.scrollSnapType = 'none';
            track.style.scrollBehavior = 'auto';
            track.setPointerCapture(event.pointerId);
        }

        if (state.moved) {
            track.scrollLeft = state.left - delta;
        }
    };

    const endDrag = () => {
        const track = trackRef.current;

        if (track && drag.current?.moved) {
            track.style.scrollSnapType = '';
            track.style.scrollBehavior = '';
        }

        // Keep the flag until the click that follows a drag has been swallowed.
        window.setTimeout(() => {
            drag.current = null;
        }, 0);
    };

    return (
        <div
            role="region"
            aria-roledescription="carousel"
            aria-label={label}
            onMouseEnter={() => setPaused(true)}
            onMouseLeave={() => setPaused(false)}
            onFocus={() => setPaused(true)}
            onBlur={() => setPaused(false)}
            onTouchStart={() => setPaused(true)}
            onTouchEnd={() => setPaused(false)}
        >
            <div
                ref={trackRef}
                onPointerDown={onPointerDown}
                onPointerMove={onPointerMove}
                onPointerUp={endDrag}
                onPointerCancel={endDrag}
                onClickCapture={(event) => {
                    if (drag.current?.moved) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                }}
                onDragStart={(event) => event.preventDefault()}
                className={cn(
                    'flex cursor-grab snap-x snap-mandatory overflow-x-auto scroll-smooth select-none active:cursor-grabbing [&::-webkit-scrollbar]:hidden',
                    className,
                )}
                style={{ scrollbarWidth: 'none' }}
            >
                {children}
            </div>
        </div>
    );
}
