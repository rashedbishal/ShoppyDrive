import { useReducedMotion } from 'framer-motion';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import {
    useCallback,
    useEffect,
    useLayoutEffect,
    useRef,
    useState,
} from 'react';

const AUTOPLAY_MS = 3500;
/** Wait for the scroll to settle before silently jumping back to the middle copy. */
const SETTLE_MS = 140;

function slideCenter(track: HTMLDivElement, index: number): number {
    const slide = track.children[index] as HTMLElement;

    return slide.offsetLeft + slide.offsetWidth / 2 - track.clientWidth / 2;
}

function jump(track: HTMLDivElement, left: number): void {
    // `scroll-smooth` would animate this move; it must be invisible.
    const previous = track.style.scrollBehavior;
    track.style.scrollBehavior = 'auto';
    track.scrollLeft = left;
    track.style.scrollBehavior = previous;
}

/**
 * Customer testimonials shown as pictures (phone screenshots, product or
 * customer photos). Every image keeps its own proportions, scaled to one
 * shared height, so a tall chat screenshot is never cropped.
 *
 * It is an auto slider that loops forever: the pictures are laid out three
 * times in a row and the scroll position is quietly moved back to the middle
 * copy whenever it drifts into the first or last one, so there is never an end
 * to reach, in either direction, by autoplay, arrows, dots or swiping. The
 * slider pauses while a visitor hovers, touches or focuses it, and doesn't
 * advance by itself for people who prefer reduced motion.
 */
export function TestimonialCarousel({
    images,
    label,
}: {
    images: string[];
    label: string;
}): React.JSX.Element {
    const count = images.length;
    const trackRef = useRef<HTMLDivElement>(null);
    // How many times the whole list repeats inside one "block", so that a block
    // is always at least as wide as the visible track (needed for a seamless jump).
    const [repeats, setRepeats] = useState(1);
    const [active, setActive] = useState(0);
    const [paused, setPaused] = useState(false);
    const reducedMotion = useReducedMotion();
    const settleTimer = useRef<number | undefined>(undefined);

    const blockSize = count * repeats;
    const looping = count > 1;
    const slides = looping
        ? Array.from({ length: 3 * blockSize }, (_, index) => ({
              src: images[index % count],
              key: `${Math.floor(index / blockSize)}-${index % blockSize}`,
              isOriginal: index >= blockSize && index < blockSize + count,
          }))
        : images.map((src) => ({ src, key: src, isOriginal: true }));

    const nearestIndex = useCallback((track: HTMLDivElement): number => {
        const centre = track.scrollLeft + track.clientWidth / 2;
        let nearest = 0;
        let nearestDistance = Infinity;

        Array.from(track.children).forEach((child, index) => {
            const element = child as HTMLElement;
            const distance = Math.abs(
                element.offsetLeft + element.offsetWidth / 2 - centre,
            );

            if (distance < nearestDistance) {
                nearestDistance = distance;
                nearest = index;
            }
        });

        return nearest;
    }, []);

    /** Keeps the scroll position inside the middle block; the move is exactly one block wide, so it can't be seen. */
    const recentre = useCallback(() => {
        const track = trackRef.current;

        if (!track || !looping || track.children.length < 3 * blockSize) {
            return;
        }

        const period =
            (track.children[blockSize] as HTMLElement).offsetLeft -
            (track.children[0] as HTMLElement).offsetLeft;
        const middle = slideCenter(track, blockSize);

        if (track.scrollLeft < middle - period / 2) {
            jump(track, track.scrollLeft + period);
        } else if (track.scrollLeft >= middle + period / 2) {
            jump(track, track.scrollLeft - period);
        }
    }, [blockSize, looping]);

    // Size the blocks to the track, then start on the middle copy.
    useLayoutEffect(() => {
        const track = trackRef.current;

        if (!track || !looping) {
            return;
        }

        let cancelled = false;

        const arrange = () => {
            if (cancelled) {
                return;
            }

            const setWidth =
                (track.children[count] as HTMLElement).offsetLeft -
                (track.children[0] as HTMLElement).offsetLeft;
            const needed = Math.max(
                1,
                Math.ceil((track.clientWidth + 1) / Math.max(setWidth, 1)),
            );

            if (needed !== repeats) {
                setRepeats(needed);

                return;
            }

            jump(track, slideCenter(track, blockSize));
            setActive(0);
        };

        // Slide widths follow the pictures, so measure only once they have loaded.
        const pending = Array.from(track.querySelectorAll('img')).filter(
            (img) => !img.complete,
        );

        if (pending.length === 0) {
            arrange();
        } else {
            let remaining = pending.length;
            const done = () => {
                remaining -= 1;

                if (remaining === 0) {
                    arrange();
                }
            };

            pending.forEach((img) => {
                img.addEventListener('load', done, { once: true });
                img.addEventListener('error', done, { once: true });
            });
        }

        const onResize = () => arrange();
        window.addEventListener('resize', onResize);

        return () => {
            cancelled = true;
            window.removeEventListener('resize', onResize);
        };
    }, [blockSize, count, looping, repeats]);

    const onScroll = () => {
        const track = trackRef.current;

        if (!track) {
            return;
        }

        setActive(nearestIndex(track) % count);
        window.clearTimeout(settleTimer.current);
        settleTimer.current = window.setTimeout(recentre, SETTLE_MS);
    };

    useEffect(() => () => window.clearTimeout(settleTimer.current), []);

    const goToChild = useCallback((index: number) => {
        const track = trackRef.current;

        if (!track || index < 0 || index >= track.children.length) {
            return;
        }

        track.scrollTo({ left: slideCenter(track, index), behavior: 'smooth' });
    }, []);

    const step = useCallback(
        (direction: 1 | -1) => {
            const track = trackRef.current;

            if (track) {
                goToChild(nearestIndex(track) + direction);
            }
        },
        [goToChild, nearestIndex],
    );

    const goToImage = (target: number) => {
        const track = trackRef.current;

        if (!track) {
            return;
        }

        // The copy of that image, in the middle block, nearest to where we are.
        const current = nearestIndex(track);
        const candidates = [blockSize + target, blockSize + target + count];
        const best = candidates.reduce((a, b) =>
            Math.abs(a - current) <= Math.abs(b - current) ? a : b,
        );

        goToChild(best);
    };

    useEffect(() => {
        if (!looping || paused || reducedMotion) {
            return;
        }

        const timer = window.setInterval(() => {
            if (!document.hidden) {
                step(1);
            }
        }, AUTOPLAY_MS);

        return () => window.clearInterval(timer);
    }, [looping, paused, reducedMotion, step]);

    return (
        <div
            className="relative"
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
                onScroll={looping ? onScroll : undefined}
                className={`flex snap-x snap-mandatory gap-4 overflow-x-auto scroll-smooth px-4 pb-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden ${
                    looping ? '' : 'justify-center'
                }`}
            >
                {slides.map((slide, index) => (
                    <figure
                        key={slide.key}
                        className="shrink-0 snap-center overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
                        aria-hidden={slide.isOriginal ? undefined : true}
                    >
                        <img
                            src={slide.src}
                            alt={
                                slide.isOriginal
                                    ? `${label} ${(index % count) + 1}`
                                    : ''
                            }
                            draggable={false}
                            className="block h-[380px] w-auto max-w-[82vw] object-contain sm:h-[460px] sm:max-w-[28rem]"
                        />
                    </figure>
                ))}
            </div>

            {looping && (
                <>
                    <button
                        type="button"
                        onClick={() => step(-1)}
                        aria-label="Previous"
                        className="absolute top-1/2 left-1 z-10 hidden size-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/95 text-slate-700 shadow-md ring-1 ring-slate-200 transition hover:bg-white sm:flex"
                    >
                        <ChevronLeft className="size-5" />
                    </button>
                    <button
                        type="button"
                        onClick={() => step(1)}
                        aria-label="Next"
                        className="absolute top-1/2 right-1 z-10 hidden size-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/95 text-slate-700 shadow-md ring-1 ring-slate-200 transition hover:bg-white sm:flex"
                    >
                        <ChevronRight className="size-5" />
                    </button>
                    <div className="mt-3 flex items-center justify-center gap-1.5">
                        {images.map((src, index) => (
                            <button
                                key={src}
                                type="button"
                                onClick={() => goToImage(index)}
                                aria-label={`Go to image ${index + 1}`}
                                aria-current={index === active}
                                className={`h-2 rounded-full transition-all ${
                                    index === active
                                        ? 'w-5 bg-slate-800'
                                        : 'w-2 bg-slate-300 hover:bg-slate-400'
                                }`}
                            />
                        ))}
                    </div>
                </>
            )}
        </div>
    );
}
