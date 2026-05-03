import { useEffect, useState, type RefObject } from 'react';

export function useIntersectionObserver<T extends Element>(
    targetRef: RefObject<T>,
    options: IntersectionObserverInit = {},
): boolean {
    const [isIntersecting, setIsIntersecting] = useState(false);

    useEffect(() => {
        const target = targetRef.current;

        if (target === null || typeof IntersectionObserver === 'undefined') {
            return;
        }

        const observer = new IntersectionObserver(([entry]) => {
            setIsIntersecting(entry?.isIntersecting ?? false);
        }, options);

        observer.observe(target);

        return () => {
            observer.disconnect();
        };
    }, [options, targetRef]);

    return isIntersecting;
}

