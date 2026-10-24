import { useSyncExternalStore } from 'react';

export type SiteTheme = 'default' | 'neomorphism' | 'glassmorphism' | 'aurora';

export const SITE_THEMES: { value: SiteTheme; label: string }[] = [
    { value: 'default', label: 'Classic' },
    { value: 'neomorphism', label: 'Neomorphism' },
    { value: 'glassmorphism', label: 'Glassmorphism' },
    { value: 'aurora', label: 'Aurora' },
];

const STORAGE_KEY = 'site_theme';

const listeners = new Set<() => void>();
let currentTheme: SiteTheme = 'default';

const isSiteTheme = (value: string | null): value is SiteTheme =>
    value === 'default' || value === 'neomorphism' || value === 'glassmorphism' || value === 'aurora';

const getStoredTheme = (): SiteTheme => {
    if (typeof window === 'undefined') {
        return 'default';
    }

    const stored = localStorage.getItem(STORAGE_KEY);

    return isSiteTheme(stored) ? stored : 'default';
};

const applyTheme = (theme: SiteTheme): void => {
    if (typeof document === 'undefined') {
        return;
    }

    if (theme === 'default') {
        document.documentElement.removeAttribute('data-theme');
    } else {
        document.documentElement.setAttribute('data-theme', theme);
    }
};

const subscribe = (callback: () => void) => {
    listeners.add(callback);

    return () => listeners.delete(callback);
};

const notify = (): void => listeners.forEach((listener) => listener());

/** Sync in-memory state from localStorage on boot. The `data-theme` attribute itself is already set pre-hydration by an inline script in app.blade.php, so this just keeps React state in sync — it doesn't need to touch the DOM. */
export function initializeSiteTheme(): void {
    currentTheme = getStoredTheme();
}

export function useSiteTheme(): { theme: SiteTheme; setTheme: (theme: SiteTheme) => void } {
    const theme: SiteTheme = useSyncExternalStore(
        subscribe,
        () => currentTheme,
        () => 'default',
    );

    const setTheme = (next: SiteTheme): void => {
        currentTheme = next;
        localStorage.setItem(STORAGE_KEY, next);
        applyTheme(next);
        notify();
    };

    return { theme, setTheme };
}
