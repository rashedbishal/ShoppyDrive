import { router } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import { useEffect, useRef, useState } from 'react';
import LoadingLogoSpinner, {
    LOADING_SCREEN_FIELDS,
} from '@/components/shared/LoadingLogoSpinner';
import type { AppSettings, SharedPageProps } from '@/types';

type LoaderLook = {
    logo: string | null;
    seconds: number;
    ringSize: number;
    logoSize: number;
    ringThickness: number;
};

/** A positive number from the shared settings, else the field's default. */
function numberSetting(
    settings: Partial<AppSettings> | undefined,
    key: keyof typeof LOADING_SCREEN_FIELDS,
): number {
    const value = Number(settings?.[key]);

    return Number.isFinite(value) && value > 0
        ? value
        : LOADING_SCREEN_FIELDS[key].default;
}

function lookFrom(settings: Partial<AppSettings> | undefined): LoaderLook {
    return {
        logo: settings?.loading_logo || settings?.logo || null,
        seconds: numberSetting(settings, 'loading_spin_seconds'),
        ringSize: numberSetting(settings, 'loading_ring_size'),
        logoSize: numberSetting(settings, 'loading_logo_size'),
        ringThickness: numberSetting(settings, 'loading_ring_thickness'),
    };
}

/**
 * The brand the loader draws. `brand` is shared under its own key because
 * several admin pages send a page prop called `settings` that replaces the
 * shared one (and has no logo), which made the loader fall back to the
 * generic mark there; `settings` is only a fallback for older page data.
 */
function brandOf(
    props: Partial<SharedPageProps> | undefined,
): Partial<AppSettings> | undefined {
    return props?.brand ?? props?.settings;
}

function readInitialLook(): LoaderLook {
    try {
        // Inertia v3 puts the first page's data in a <script data-page>
        // element; older versions used the root element's data-page attribute.
        const raw =
            document.querySelector('script[data-page]')?.textContent ||
            document.getElementById('app')?.dataset.page;

        if (raw) {
            return lookFrom(brandOf(JSON.parse(raw)?.props));
        }
    } catch {
        // Missing or malformed page data: fall through to the default below.
    }

    return lookFrom(undefined);
}

/**
 * Replaces Inertia's default top progress bar. Shows the CMS "Loading Logo"
 * (Settings ▸ General, falling back to the brand logo) inside a ring that
 * turns at the CMS speed, at the CMS sizes on desktop and scaled down on
 * smaller screens.
 * Visits under ~150ms never show it at all, so fast/cached navigations
 * don't flash the overlay.
 */
export default function PageLoadingOverlay(): React.JSX.Element {
    const [visible, setVisible] = useState(false);
    const [look, setLook] = useState<LoaderLook>(readInitialLook);
    const showTimeout = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(() => {
        return router.on('navigate', (event) => {
            const brand = brandOf(
                event.detail.page.props as Partial<SharedPageProps>,
            );

            // A page without brand data keeps the logo already known.
            if (
                brand?.logo !== undefined ||
                brand?.loading_logo !== undefined
            ) {
                setLook(lookFrom(brand));
            }
        });
    }, []);

    // Download and decode the logo once, as soon as the page loads, and keep
    // that image alive for the whole visit: the overlay is only on screen
    // during a slow visit — exactly when a first download (or decode) of it
    // would be slowest — so it must already be ready by then. The ref stops
    // the browser from discarding the decoded image between visits.
    const preloadedLogo = useRef<HTMLImageElement | null>(null);

    useEffect(() => {
        if (!look.logo) {
            preloadedLogo.current = null;

            return;
        }

        const image = new Image();
        image.decoding = 'async';
        image.src = look.logo;
        image.decode().catch(() => {
            // A broken or blocked image: the overlay still shows the ring.
        });
        preloadedLogo.current = image;
    }, [look.logo]);

    useEffect(() => {
        const clearPendingShow = () => {
            if (showTimeout.current) {
                clearTimeout(showTimeout.current);
                showTimeout.current = null;
            }
        };

        const removeStart = router.on('start', (event) => {
            // Visits that opt out via `showProgress: false` (e.g. a page's
            // own scoped filter reloads) manage their own loading UI and
            // shouldn't be covered by this full-screen overlay.
            if (event.detail.visit.showProgress === false) {
                return;
            }

            clearPendingShow();
            showTimeout.current = setTimeout(() => setVisible(true), 150);
        });
        const removeFinish = router.on('finish', () => {
            clearPendingShow();
            setVisible(false);
        });

        return () => {
            clearPendingShow();
            removeStart();
            removeFinish();
        };
    }, []);

    return (
        <AnimatePresence>
            {visible && (
                <motion.div
                    key="page-loading-overlay"
                    initial={{ opacity: 0 }}
                    animate={{ opacity: 1 }}
                    exit={{ opacity: 0 }}
                    transition={{ duration: 0.15 }}
                    className="fixed inset-0 z-[9999] flex items-center justify-center bg-white/10 backdrop-blur-xl"
                >
                    <LoadingLogoSpinner
                        logo={look.logo}
                        seconds={look.seconds}
                        ringSize={look.ringSize}
                        logoSize={look.logoSize}
                        ringThickness={look.ringThickness}
                    />
                </motion.div>
            )}
        </AnimatePresence>
    );
}
