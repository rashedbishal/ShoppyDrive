import { motion } from 'framer-motion';
import { useState } from 'react';
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

export const DEFAULT_LOADING_SPIN_SECONDS =
    LOADING_SCREEN_FIELDS.loading_spin_seconds.default;

type Props = {
    /** Image shown, still, in the middle; the built-in icon when null. */
    logo: string | null;
    /** Seconds for one full turn of the ring. */
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
 * gradient ring turns around it on the Y axis. Shared by the page-loading
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
    const ringWidth = `max(1px, calc(var(--spinner-size) * ${ringThickness / ringSize}))`;
    const ringMask = `radial-gradient(farthest-side, transparent calc(100% - ${ringWidth}), #000 calc(100% - ${ringWidth} + 1px))`;

    return (
        <span
            className="relative flex items-center justify-center"
            style={
                {
                    '--spinner-size': ringDiameter,
                    width: 'var(--spinner-size)',
                    height: 'var(--spinner-size)',
                    perspective: 900,
                } as CSSProperties
            }
        >
            <motion.span
                key={seconds}
                aria-hidden="true"
                className="absolute inset-0 rounded-full drop-shadow-[0_6px_18px_rgba(238,89,35,0.35)]"
                style={{
                    background:
                        'linear-gradient(135deg, #F58120 0%, #EE5923 100%)',
                    WebkitMask: ringMask,
                    mask: ringMask,
                }}
                animate={{ rotateY: 360 }}
                transition={{
                    duration: seconds,
                    repeat: Infinity,
                    ease: 'linear',
                }}
            />
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
