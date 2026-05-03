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
} as const;

export const SHADOWS = {
    card: '0 1px 3px rgba(0, 0, 0, 0.05), 0 4px 16px rgba(0, 0, 0, 0.06)',
    cardHover: '0 8px 32px rgba(0, 0, 0, 0.12)',
    dropdown: '0 4px 24px rgba(0, 0, 0, 0.12)',
    modal: '0 20px 60px rgba(0, 0, 0, 0.2)',
} as const;

export const DESIGN_TOKENS = {
    colors: {
        primary: '#C8102E',
        primaryHover: '#A50D25',
        primaryLight: '#FFF0F2',
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

