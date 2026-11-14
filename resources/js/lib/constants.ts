export const APP_NAME = import.meta.env.VITE_APP_NAME ?? 'ShoppyDrive';
export const CURRENCY_CODE = 'BDT';
export const CURRENCY_SYMBOL = '৳';
export const CURRENCY_LOCALE = 'en-BD';
export const DEFAULT_COUNTRY = 'BD';
export const DEFAULT_DATE_LOCALE = 'en-GB';

export const STORAGE_KEYS = {
    cart: 'shoppydrive.cart.v1',
    wishlist: 'shoppydrive.wishlist.v1',
    ui: 'shoppydrive.ui.v1',
    cookieConsent: 'cookie_consent',
    recentlyViewed: 'recently_viewed',
} as const;

export const FONT_FAMILIES = {
    display: '"Playfair Display", serif',
    body: '"DM Sans", ui-sans-serif, system-ui, sans-serif',
    mono: '"DM Mono", ui-monospace, SFMono-Regular, monospace',
} as const;

export const BREAKPOINTS = {
    mobile: 375,
    tablet: 768,
    desktop: 1280,
    wide: 1440,
} as const;

export const MOTION = {
    page: {
        duration: 0.3,
        distance: 8,
    },
    cardHover: {
        scale: 1.02,
        duration: 0.2,
    },
    drawer: {
        duration: 0.35,
    },
    modal: {
        duration: 0.25,
    },
    stagger: 0.06,
    /** Product cards fading up as they scroll into view: slow and eased in both directions, never a sudden jump. */
    reveal: {
        duration: 0.9,
        distance: 20,
        scale: 0.98,
        ease: [0.4, 0, 0.2, 1],
        stagger: 0.08,
        maxDelay: 0.4,
    },
} as const;

/**
 * Fixed storefront product card heights per device tier (mobile / tablet / desktop).
 * Cards never grow or shrink with their content; the image area absorbs the slack.
 */
export const PRODUCT_CARD_HEIGHT = 'h-[20rem] sm:h-[22rem] lg:h-[24rem]';

/**
 * Site-wide column system. Every page's content area (max-w-7xl) is split into
 * 2 columns on mobile, 3 on tablet and 4 on desktop, all with the same gaps, so
 * a product card is the same width on every page. Pages with a sidebar give the
 * sidebar exactly one desktop column and put products in the other three.
 */
export const PRODUCT_GRID_CLASS =
    'grid grid-cols-2 gap-3 sm:grid-cols-3 md:gap-4 lg:grid-cols-4';

/** Page layout with a sidebar: sidebar = 1 desktop column, main = `lg:col-span-3`. */
export const SIDEBAR_LAYOUT_CLASS =
    'grid grid-cols-1 gap-6 lg:grid-cols-4 lg:gap-4';

/** Product grid inside the 3-column main area of a SIDEBAR_LAYOUT_CLASS page. */
export const PRODUCT_GRID_BESIDE_SIDEBAR_CLASS =
    'grid grid-cols-2 gap-3 sm:grid-cols-3 md:gap-4';

/**
 * Horizontally scrolling product row: exactly 2 cards per view on mobile and
 * 3 on tablet (so short lists never overflow), becoming a 4-column grid on lg.
 */
export const PRODUCT_RAIL_CLASS =
    'grid grid-flow-col auto-cols-[calc((100%-0.75rem)/2)] gap-3 overflow-x-auto pb-2 sm:auto-cols-[calc((100%-1.5rem)/3)] md:auto-cols-[calc((100%-2rem)/3)] md:gap-4 lg:grid-flow-row lg:grid-cols-4 lg:overflow-visible';

export const SHADOWS = {
    card: '0 1px 3px rgba(0, 0, 0, 0.05), 0 4px 16px rgba(0, 0, 0, 0.06)',
    cardHover: '0 8px 32px rgba(0, 0, 0, 0.12)',
    dropdown: '0 4px 24px rgba(0, 0, 0, 0.12)',
    modal: '0 20px 60px rgba(0, 0, 0, 0.2)',
} as const;

export const DESIGN_TOKENS = {
    colors: {
        // Admin › Settings › Brand Colour (CSS variables; see app.css).
        primary: 'var(--brand)',
        primaryHover: 'var(--brand-hover)',
        primaryLight: 'var(--brand-light)',
        secondary: '#1A1A2E',
        accent: '#F5A623',
        accentLight: '#FFF8EC',
        surface: '#FFFFFF',
        surfaceAlt: '#F8F8FB',
        border: '#E8E8F0',
        textPrimary: '#1A1A2E',
        textSecondary: '#6B7280',
        textMuted: '#9CA3AF',
        success: '#16A34A',
        warning: '#D97706',
        error: '#DC2626',
        overlay: 'rgba(26,26,46,0.5)',
    },
    radius: {
        card: '1rem',
        button: '0.75rem',
        input: '0.5rem',
        pill: '9999px',
    },
} as const;
