import { useEffect, useState } from 'react';

export function useMediaQuery(query: string): boolean {
    const [matches, setMatches] = useState<boolean>(() => {
        if (typeof window === 'undefined') {
            return false;
        }

        return window.matchMedia(query).matches;
    });

    useEffect(() => {
        const mediaQuery = window.matchMedia(query);

        const onChange = (event: MediaQueryListEvent): void => {
            setMatches(event.matches);
        };

        setMatches(mediaQuery.matches);
        mediaQuery.addEventListener('change', onChange);

        return () => {
            mediaQuery.removeEventListener('change', onChange);
        };
    }, [query]);

    return matches;
}

