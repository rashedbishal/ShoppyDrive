import { useReducedMotion } from 'framer-motion';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { cn, getInitials } from '@/lib/utils';

export type SubcategoryShowcaseItem = {
    id: number | string;
    name: string;
    slug: string;
    image?: string | null;
};

type SubcategoryShowcaseProps = {
    subCategories: SubcategoryShowcaseItem[];
    onNavigate: (slug: string) => void;
    className?: string;
};

/** Constant auto-scroll speed, independent of how much content there is. */
const PIXELS_PER_SECOND = 45;

/** How long an arrow click's eased scroll takes to settle. */
const ARROW_SCROLL_DURATION_MS = 350;

/** Ease-out cubic: fast start, gentle settle — a typical "click to page" feel. */
function easeOutCubic(progress: number): number {
    return 1 - Math.pow(1 - progress, 3);
}

/** A stable accent hue per sub-category, derived from its name — same technique as BrandCard, since sub-categories (unlike their parent Category) have no color/icon field of their own. */
function getSubCategoryHue(name: string): number {
    let hash = 0;

    for (const character of name) {
        hash = (hash * 31 + character.charCodeAt(0)) % 360;
    }

    return hash;
}

function SubcategoryCard({
    subCategory,
    onNavigate,
}: {
    subCategory: SubcategoryShowcaseItem;
    onNavigate: (slug: string) => void;
}): React.JSX.Element {
    const [hasImageError, setHasImageError] = useState(false);
    const showImage = Boolean(subCategory.image) && !hasImageError;

    return (
        <button
            type="button"
            data-subcategory-card
            onClick={() => onNavigate(subCategory.slug)}
            style={
                {
                    '--subcategory-color': `hsl(${getSubCategoryHue(subCategory.name)} 65% 48%)`,
                } as React.CSSProperties
            }
            className="group flex w-20 shrink-0 flex-col items-center gap-2 rounded-2xl p-1 text-center transition-colors focus-visible:ring-2 focus-visible:ring-[var(--color-primary)] focus-visible:ring-offset-2 focus-visible:outline-none sm:w-24"
        >
            <div className="flex size-20 items-center justify-center overflow-hidden rounded-full border border-[var(--color-border)] bg-[var(--color-surface-alt)] shadow-[var(--shadow-card)] transition duration-300 group-hover:border-[var(--color-primary)]/60 group-hover:shadow-[var(--shadow-card-hover)] sm:size-24">
                {showImage ? (
                    <img
                        src={subCategory.image ?? ''}
                        alt=""
                        loading="lazy"
                        decoding="async"
                        onError={() => setHasImageError(true)}
                        className="h-full w-full object-cover transition duration-300 group-hover:scale-110"
                    />
                ) : (
                    <span
                        className="text-xl font-bold text-[var(--subcategory-color)]"
                        aria-hidden="true"
                    >
                        {getInitials(subCategory.name)}
                    </span>
                )}
            </div>
            <p className="line-clamp-2 text-xs leading-tight font-semibold text-[var(--color-text-primary)] transition group-hover:text-[var(--color-primary)] sm:text-sm">
                {subCategory.name}
            </p>
        </button>
    );
}

// Deliberately no `scroll-smooth` (CSS `scroll-behavior: smooth`): per the
// CSSOM View spec, the `scrollLeft` *property setter* also respects that CSS
// property, so the continuous per-frame `scrollLeft =` below would each
// kick off their own smooth-scroll animation, each one immediately
// superseded by the next ~16ms later — the browser ends up chasing a
// perpetually receding target instead of actually moving. The arrow
// buttons get their own easing from `tick` itself instead (see
// `manualScrollRef`), so they don't need this class to animate smoothly.
const ROW_CLASS =
    'flex items-start gap-5 overflow-x-auto pb-1 [-webkit-overflow-scrolling:touch] [scrollbar-width:none] sm:gap-6 [&::-webkit-scrollbar]:hidden';

const ARROW_BUTTON_CLASS =
    'absolute top-1/2 z-10 flex size-8 -translate-y-1/2 items-center justify-center rounded-full border border-[var(--color-border)] bg-[var(--color-surface)]/95 text-[var(--color-text-primary)] shadow-[var(--shadow-card)] backdrop-blur-sm transition hover:border-[var(--color-primary)]/50 hover:text-[var(--color-primary)] lg:size-10';

/**
 * Category-page-only subcategory showcase (see ProductListing.tsx): a
 * horizontal row of image chips that only moves when it actually needs to —
 * driven purely by measured overflow, not a viewport-width breakpoint, so it
 * behaves consistently whatever size the page is actually rendered at.
 * Enough subcategories to overflow the container gets a continuous,
 * seamless auto-scroll (driven by directly incrementing `scrollLeft` on
 * every animation frame, not a CSS `animation` — changing a CSS animation's
 * duration/style restarts it from frame zero, which is what caused an
 * earlier keyframe-based version to visibly jump/restart whenever a
 * ResizeObserver measurement fired). The track renders two identical sets of
 * cards; once the scroll position passes the width of one set, that width is
 * subtracted straight back off, so the wrap is an instant, pixel-identical,
 * invisible jump rather than a visible reset. The same tracked position also
 * drives the `<`/`>` buttons (by moving that position directly, not via a
 * separate `scrollBy` animation, which would otherwise fight the loop's own
 * per-frame write) and native touch/trackpad scrolling, so manual and
 * automatic movement share one source of truth and never fight each other.
 * Hovering the control with a mouse, or actively touching/dragging it,
 * pauses the auto-scroll — it resumes once released.
 * `prefers-reduced-motion` disables the automatic movement
 * entirely, but the arrows (user-initiated, not an autoplaying animation)
 * and the seamless wraparound keep working regardless. Anything that
 * already fits is a plain static row: never duplicated, never animated, no
 * arrows.
 */
export function SubcategoryShowcase({
    subCategories,
    onNavigate,
    className,
}: SubcategoryShowcaseProps): React.JSX.Element | null {
    const prefersReducedMotion = useReducedMotion();
    const scrollRef = useRef<HTMLDivElement>(null);
    const setRef = useRef<HTMLDivElement>(null);
    const isPausedRef = useRef(false);
    // Shared with the arrow buttons below, so a manual click moves the
    // exact same tracked position the auto-scroll loop writes from each
    // frame, rather than fighting it (see the arrow-button handler for why
    // a separate browser-driven `scrollBy({behavior: 'smooth'})` doesn't
    // work here).
    const scrollPositionRef = useRef(0);
    // An in-flight eased arrow-click animation, consumed by the same loop
    // below (see `tick`) instead of a separate browser-driven animation —
    // never a bare instant jump, and never something that can fight the
    // auto-scroll's own per-frame write.
    const manualScrollRef = useRef<{
        from: number;
        to: number;
        startTime: number;
    } | null>(null);
    const [shouldLoop, setShouldLoop] = useState(false);

    // Overflow detection: does one set of cards exceed the visible width?
    // Runs at any size — there's no viewport-width gate — so this behaves
    // the same whether the page is rendered at phone, tablet, or desktop
    // width, or anything in between.
    useEffect(() => {
        const container = scrollRef.current;
        const set = setRef.current;
        const canMeasure = subCategories.length > 0 && container && set;

        const measure = (): void => {
            if (!canMeasure) {
                setShouldLoop(false);

                return;
            }

            // +1px tolerance so a pixel-rounding difference on an exact fit
            // never flips into an unnecessary (if harmless) loop.
            setShouldLoop(set.scrollWidth > container.clientWidth + 1);
        };

        measure();

        if (!canMeasure) {
            return;
        }

        // Catches container resize (viewport/sidebar changes), card-set
        // resize (data changes), and image loads that change the card
        // row's natural size — all through the one observer, no separate
        // load listeners needed.
        const observer = new ResizeObserver(measure);
        observer.observe(container);
        observer.observe(set);

        return () => observer.disconnect();
    }, [subCategories]);

    // The scroll loop: always runs whenever there's overflow (so the
    // seamless wraparound keeps working for manual arrow clicks and native
    // touch/trackpad scrolling even under reduced motion), but only actually
    // advances the position on its own when autoplay is allowed.
    useEffect(() => {
        if (!shouldLoop) {
            return;
        }

        const container = scrollRef.current;
        const set = setRef.current;

        if (!container || !set) {
            return;
        }

        let frameId = 0;
        let lastTime: number | null = null;

        // The true scroll position, tracked as a full-precision float. This
        // is the fix for a real bug: `container.scrollLeft += smallFraction`
        // reads the CURRENT scrollLeft back from the DOM to do the
        // addition, but browsers store/report scrollLeft rounded to a whole
        // pixel. At this speed each frame's delta is under 1px, so reading
        // it back from the (rounded) DOM property every frame discarded the
        // fractional part every time — it could never accumulate, leaving
        // the position permanently stuck at its starting pixel forever.
        // Accumulating here instead, and only ever *writing* to
        // `scrollLeft` (never reading it back into this variable, except to
        // resync after an external change below), keeps the true position
        // continuous regardless of DOM-side rounding.
        scrollPositionRef.current = container.scrollLeft;

        const tick = (time: number): void => {
            if (lastTime === null) {
                lastTime = time;
            }

            const deltaSeconds = (time - lastTime) / 1000;
            lastTime = time;

            const manual = manualScrollRef.current;

            if (manual) {
                // An arrow click is animating: it alone drives the
                // position this frame, start to finish — skip the resync
                // check and the auto-scroll increment below entirely, so
                // nothing else can fight it mid-animation.
                const elapsed = time - manual.startTime;

                if (elapsed >= ARROW_SCROLL_DURATION_MS) {
                    scrollPositionRef.current = manual.to;
                    manualScrollRef.current = null;
                } else {
                    const eased = easeOutCubic(
                        elapsed / ARROW_SCROLL_DURATION_MS,
                    );

                    scrollPositionRef.current =
                        manual.from + (manual.to - manual.from) * eased;
                }
            } else {
                // If something other than our own last write moved the real
                // scrollLeft by more than a touch (native touch/trackpad
                // dragging), resync to it so auto-scroll continues from
                // where the user left it. The small threshold is
                // deliberate: it must be bigger than a single frame's own
                // sub-pixel rounding noise, or every frame would "resync"
                // to the rounded DOM value and reintroduce the exact
                // stuck-accumulator bug this is fixing.
                const liveScrollLeft = container.scrollLeft;

                if (Math.abs(liveScrollLeft - scrollPositionRef.current) > 2) {
                    scrollPositionRef.current = liveScrollLeft;
                }

                if (!isPausedRef.current && !prefersReducedMotion) {
                    scrollPositionRef.current +=
                        PIXELS_PER_SECOND * deltaSeconds;
                }
            }

            // Wraps the scroll position back into [0, singleSetWidth) every
            // frame, regardless of what moved it (auto-scroll, the arrow
            // buttons, or native touch/trackpad scrolling) — since the two
            // sets are identical, landing anywhere in that range looks the
            // same whether it came from set one or set two. A single modulo
            // (rather than a while-loop of +/- singleSetWidth) guarantees
            // this always terminates in one step no matter how far out of
            // range the position ends up.
            const singleSetWidth = set.scrollWidth;

            if (singleSetWidth > 0) {
                scrollPositionRef.current =
                    ((scrollPositionRef.current % singleSetWidth) +
                        singleSetWidth) %
                    singleSetWidth;
            }

            container.scrollLeft = scrollPositionRef.current;

            frameId = requestAnimationFrame(tick);
        };

        frameId = requestAnimationFrame(tick);

        return () => {
            cancelAnimationFrame(frameId);
        };
    }, [shouldLoop, prefersReducedMotion]);

    if (subCategories.length === 0) {
        return null;
    }

    // Deliberately not `container.scrollBy({behavior: 'smooth'})`: the
    // auto-scroll loop above writes `container.scrollLeft` on every single
    // frame, which fights a separate browser-driven smooth-scroll
    // animation and wins — the browser's gradual interpolation gets
    // overwritten back to the loop's own tracked position before it can
    // visibly progress, so the click appeared to do nothing. Instead, this
    // hands the loop a `{from, to, startTime}` target (see `manualScrollRef`
    // and `tick` above) and the loop eases toward it itself, frame by
    // frame — there's only ever one thing driving the position, so nothing
    // fights. `to` is deliberately NOT pre-wrapped into [0, singleSetWidth):
    // the loop's existing modulo step wraps whatever it's given every
    // frame regardless, so letting the raw target exceed that range (or go
    // negative) makes the animation travel the short, correct way through
    // a wrap instead of jumping backwards across almost the full width.
    const scrollByStep = (direction: 'left' | 'right'): void => {
        const container = scrollRef.current;

        if (!container) {
            return;
        }

        const card = container.querySelector<HTMLElement>(
            '[data-subcategory-card]',
        );
        const gap = parseFloat(getComputedStyle(container).columnGap) || 0;
        const step = card ? (card.offsetWidth + gap) * 3 : 300;
        const delta = direction === 'right' ? step : -step;
        const from = scrollPositionRef.current;
        const to = from + delta;

        if (prefersReducedMotion) {
            const set = setRef.current;
            const singleSetWidth = set?.scrollWidth ?? 0;
            const next =
                singleSetWidth > 0
                    ? ((to % singleSetWidth) + singleSetWidth) % singleSetWidth
                    : to;

            manualScrollRef.current = null;
            scrollPositionRef.current = next;
            container.scrollLeft = next;

            return;
        }

        // Continuing from the current (possibly still-animating) position
        // rather than resetting it means rapid clicks chain smoothly
        // instead of snapping back to a stale start each time.
        manualScrollRef.current = { from, to, startTime: performance.now() };
    };

    const pause = (): void => {
        isPausedRef.current = true;
    };
    const resume = (): void => {
        isPausedRef.current = false;
    };

    // Renders the same tree shape at every size and on the server — only
    // `shouldLoop` (always false until the post-mount ResizeObserver
    // measurement runs) ever changes what's inside it, and that starts
    // identically on the server and on the client's first paint. Branching
    // this return on a viewport-width or media-query value instead (unknown
    // during SSR and only resolved once real browser APIs are available)
    // would render a completely different element tree for the very first
    // client paint than what the server sent, which is a hydration
    // mismatch — React then tears down and remounts the whole subtree,
    // leaving the previous render's rAF loop running against now-detached
    // nodes and locking up the tab.
    return (
        <div
            className={cn('relative', className)}
            onMouseEnter={pause}
            onMouseLeave={resume}
            onTouchStart={pause}
            onTouchEnd={resume}
            onTouchCancel={resume}
        >
            <div ref={scrollRef} className={ROW_CLASS}>
                <div
                    ref={setRef}
                    className="flex shrink-0 items-start gap-5 sm:gap-6"
                >
                    {subCategories.map((subCategory) => (
                        <SubcategoryCard
                            key={subCategory.id}
                            subCategory={subCategory}
                            onNavigate={onNavigate}
                        />
                    ))}
                </div>
                {shouldLoop ? (
                    <div
                        className="flex shrink-0 items-start gap-5 sm:gap-6"
                        aria-hidden="true"
                    >
                        {subCategories.map((subCategory) => (
                            <SubcategoryCard
                                key={`duplicate-${subCategory.id}`}
                                subCategory={subCategory}
                                onNavigate={onNavigate}
                            />
                        ))}
                    </div>
                ) : null}
            </div>
            {shouldLoop ? (
                <>
                    <button
                        type="button"
                        onClick={() => scrollByStep('left')}
                        aria-label="Scroll subcategories left"
                        className={cn(ARROW_BUTTON_CLASS, 'left-1 lg:-left-4')}
                    >
                        <ChevronLeft className="size-4 lg:size-5" />
                    </button>
                    <button
                        type="button"
                        onClick={() => scrollByStep('right')}
                        aria-label="Scroll subcategories right"
                        className={cn(
                            ARROW_BUTTON_CLASS,
                            'right-1 lg:-right-4',
                        )}
                    >
                        <ChevronRight className="size-4 lg:size-5" />
                    </button>
                </>
            ) : null}
        </div>
    );
}
