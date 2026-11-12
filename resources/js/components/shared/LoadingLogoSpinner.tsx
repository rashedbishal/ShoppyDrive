import { motion } from 'framer-motion';
import { useId, useState } from 'react';
import type { CSSProperties } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';

/**
 * Defaults and bounds, matching App\Support\LoadingScreenSettings::FIELDS
 * (the server validates and clamps; these drive the CMS controls).
 */
export const LOADING_SCREEN_FIELDS = {
    loading_spin_seconds: { default: 1.1, min: 0.3, max: 10, step: 0.1 },
    loading_ring_size: { default: 340, min: 80, max: 600, step: 1 },
    loading_logo_size: { default: 224, min: 32, max: 560, step: 1 },
    loading_ring_thickness: { default: 4, min: 1, max: 20, step: 1 },
} as const;

/** Screens at least this wide get the CMS (desktop) sizes as set. */
export const LOADING_DESKTOP_MIN_WIDTH_PX = 1024;

/** The smallest the loader gets on narrow screens, as a share of the desktop size. */
export const LOADING_PHONE_SCALE = 0.6;

/** How long one draw-in/draw-out of the arc takes, per "seconds" of speed. */
const LOADING_DRAW_FACTOR = 1.35;

/** How long one full turn of the ring takes, per "seconds" of speed. */
const LOADING_TURN_FACTOR = 1.8;

export const DEFAULT_LOADING_SPIN_SECONDS =
    LOADING_SCREEN_FIELDS.loading_spin_seconds.default;

type Props = {
    /** Image shown, still, in the middle; the built-in icon when null. */
    logo: string | null;
    /** The loader's pace: smaller is faster (see LOADING_DRAW_FACTOR). */
    seconds: number;
    /** Outer diameter of the ring, in pixels. */
    ringSize?: number;
    /** Width and height of the box the logo is fitted into, in pixels. */
    logoSize?: number;
    /** Thickness of the ring's line, in pixels. */
    ringThickness?: number;
    /**
     * Scale with the screen: the sizes above on screens at least
     * LOADING_DESKTOP_MIN_WIDTH_PX wide, shrinking in step with the screen
     * width down to LOADING_PHONE_SCALE of them on phones. Off for the CMS
     * preview, which shows the desktop proportions.
     */
    responsive?: boolean;
    /**
     * Largest the ring may be drawn, any CSS length (86vw keeps it on a
     * phone screen).
     */
    maxSize?: string;
};

/**
 * The loading mark: the logo sits still in the centre while an orange
 * gradient arc sweeps around it, drawing itself in and back out as it
 * turns, over a faint track. Shared by the page-loading
 * overlay and its live preview in Settings ▸ General. The ring, logo and
 * line thickness scale together, so their proportions hold at every size.
 */
export default function LoadingLogoSpinner({
    logo,
    seconds,
    ringSize = LOADING_SCREEN_FIELDS.loading_ring_size.default,
    logoSize = LOADING_SCREEN_FIELDS.loading_logo_size.default,
    ringThickness = LOADING_SCREEN_FIELDS.loading_ring_thickness.default,
    responsive = true,
    maxSize = '86vw',
}: Props): React.JSX.Element {
    // The ring's drawn diameter; the logo and the line thickness are kept
    // as fixed fractions of it, so everything shrinks together.
    const ringDiameter = responsive
        ? `min(${maxSize}, clamp(${ringSize * LOADING_PHONE_SCALE}px, ${(ringSize * 100) / LOADING_DESKTOP_MIN_WIDTH_PX}vw, ${ringSize}px))`
        : `min(${ringSize}px, ${maxSize})`;
    const logoBox = `calc(var(--spinner-size) * ${logoSize / ringSize})`;
    // The logo is normally preloaded (PageLoadingOverlay), so it's ready
    // at once; if it isn't yet, it fades in rather than painting in pieces.
    const [loadedLogo, setLoadedLogo] = useState<string | null>(null);
    const logoReady = loadedLogo === logo;
    // The ring is drawn in an SVG whose units are the CMS desktop pixels,
    // so the arc, its track and its thickness scale together with it.
    const strokeWidth = ringThickness;
    const radius = (ringSize - strokeWidth) / 2;
    const circumference = 2 * Math.PI * radius;
    const gradientId = `loading-ring-${useId().replace(/[^a-zA-Z0-9_-]/g, '')}`;
    // One "seconds" sets the whole rhythm: the arc draws itself in and
    // back out over a little more than that, while the ring as a whole
    // turns a little slower, so the two never line up into a loop you
    // can spot (the classic indeterminate loader).
    const drawSeconds = seconds * LOADING_DRAW_FACTOR;
    const turnSeconds = seconds * LOADING_TURN_FACTOR;

    return (
        <span
            className="relative flex items-center justify-center"
            style={
                {
                    '--spinner-size': ringDiameter,
                    width: 'var(--spinner-size)',
                    height: 'var(--spinner-size)',
                } as CSSProperties
            }
        >
            <motion.svg
                key={seconds}
                aria-hidden="true"
                viewBox={`0 0 ${ringSize} ${ringSize}`}
                className="absolute inset-0 size-full drop-shadow-[0_6px_18px_rgba(238,89,35,0.35)]"
                animate={{ rotate: 360 }}
                transition={{
                    duration: turnSeconds,
                    repeat: Infinity,
                    ease: 'linear',
                }}
            >
                <defs>
                    <linearGradient
                        id={gradientId}
                        x1="0%"
                        y1="0%"
                        x2="100%"
                        y2="100%"
                    >
                        <stop offset="0%" stopColor="#F58120" />
                        <stop offset="100%" stopColor="#EE5923" />
                    </linearGradient>
                </defs>
                <circle
                    cx={ringSize / 2}
                    cy={ringSize / 2}
                    r={radius}
                    fill="none"
                    stroke="#EE5923"
                    strokeOpacity={0.12}
                    strokeWidth={strokeWidth}
                />
                <motion.circle
                    cx={ringSize / 2}
                    cy={ringSize / 2}
                    r={radius}
                    fill="none"
                    stroke={`url(#${gradientId})`}
                    strokeWidth={strokeWidth}
                    strokeLinecap="round"
                    transform={`rotate(-90 ${ringSize / 2} ${ringSize / 2})`}
                    initial={{
                        strokeDasharray: `${circumference * 0.01} ${circumference}`,
                        strokeDashoffset: 0,
                    }}
                    animate={{
                        strokeDasharray: [
                            `${circumference * 0.01} ${circumference}`,
                            `${circumference * 0.7} ${circumference}`,
                            `${circumference * 0.7} ${circumference}`,
                        ],
                        strokeDashoffset: [
                            0,
                            -circumference * 0.25,
                            -circumference * 0.99,
                        ],
                    }}
                    transition={{
                        duration: drawSeconds,
                        repeat: Infinity,
                        ease: 'easeInOut',
                        times: [0, 0.5, 1],
                    }}
                />
            </motion.svg>
            {logo ? (
                <img
                    src={logo}
                    alt="Loading"
                    decoding="async"
                    ref={(node) => {
                        if (node?.complete && node.naturalWidth > 0) {
                            setLoadedLogo(logo);
                        }
                    }}
                    onLoad={() => setLoadedLogo(logo)}
                    className="relative object-contain transition-opacity duration-300"
                    style={{
                        opacity: logoReady ? 1 : 0,
                        width: logoBox,
                        height: logoBox,
                    }}
                />
            ) : (
                <span
                    className="relative flex items-center justify-center rounded-[22%] bg-[var(--color-secondary)] text-white shadow-[var(--shadow-modal)]"
                    style={{
                        width: `calc(${logoBox} * 0.6)`,
                        height: `calc(${logoBox} * 0.6)`,
                    }}
                >
                    <AppLogoIcon className="size-1/2 fill-current text-white" />
                </span>
            )}
        </span>
    );
}
